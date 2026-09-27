<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ReportRepository
{
    public static function dashboard(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(role <> "player"), 0) AS total_users,
                   COALESCE(SUM(role = "admin"), 0) AS total_admins,
                   COALESCE(SUM(role = "coach"), 0) AS total_coaches,
                   COALESCE(SUM(role = "player"), 0) AS total_guardians
            FROM football_users WHERE status = "active" AND deleted_at IS NULL
        ');
        $stmt->execute();
        $users = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM football_players WHERE status = "active" AND deleted_at IS NULL');
        $stmt->execute();
        $players = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM football_classes WHERE status = "active" AND deleted_at IS NULL');
        $stmt->execute();
        $classes = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM football_sessions WHERE session_date = CURDATE() AND status IN ("scheduled", "makeup")');
        $stmt->execute();
        $sessionsToday = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM football_payments WHERE status = "pending"');
        $stmt->execute();
        $pendingPayments = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(remaining_total), 0) AS total_debt FROM football_invoices WHERE status IN ("open", "partial")');
        $stmt->execute();
        $debt = $stmt->fetch();

        return [
            'users' => $users,
            'active_players' => (int) $players['total'],
            'active_classes' => (int) $classes['total'],
            'sessions_today' => (int) $sessionsToday['total'],
            'pending_payments' => (int) $pendingPayments['total'],
            'total_debt' => (int) $debt['total_debt'],
        ];
    }

    public static function finance(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT status, COUNT(*) AS count,
                   COALESCE(SUM(subtotal), 0) AS subtotal,
                   COALESCE(SUM(discount_total), 0) AS discount_total,
                   COALESCE(SUM(paid_total), 0) AS paid_total,
                   COALESCE(SUM(remaining_total), 0) AS remaining_total
            FROM football_invoices GROUP BY status ORDER BY status ASC
        ');
        $stmt->execute();
        $invoices = $stmt->fetchAll();

        $stmt = $pdo->prepare('
            SELECT status, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount_total
            FROM football_payments GROUP BY status ORDER BY status ASC
        ');
        $stmt->execute();
        $payments = $stmt->fetchAll();

        return [
            'invoices_by_status' => $invoices,
            'payments_by_status' => $payments,
        ];
    }

    public static function debts(): array
    {
        $pdo = Database::connection();

        // بدهی کلی هر بازیکن
        $stmt = $pdo->prepare('
            SELECT p.id AS player_id, p.first_name, p.last_name,
                   CONCAT(p.first_name, " ", p.last_name) AS player_name,
                   COALESCE(SUM(i.remaining_total), 0) AS debt,
                   COALESCE(SUM(i.paid_total),0) AS paid,
                   COALESCE(SUM(i.subtotal),0) AS total,
                   MIN(i.due_date) AS oldest_due_date
            FROM football_players p
            LEFT JOIN football_invoices i ON i.player_id = p.id AND i.status IN ("open", "partial")
            WHERE p.deleted_at IS NULL
            GROUP BY p.id, p.first_name, p.last_name
            HAVING debt > 0
            ORDER BY debt DESC
            LIMIT 200
        ');
        $stmt->execute();
        $players = $stmt->fetchAll();

        // برای هر بازیکن بدهی به تفکیک کلاس
        $playerIds = array_map(fn($r) => (int)$r['player_id'], $players);
        $classDebtsByPlayer = [];

        if (!empty($playerIds)) {
            $ids = implode(',', $playerIds);
            $stmt = $pdo->prepare("
                SELECT inv.player_id, ii.class_id, c.title AS class_title, ag.title AS age_group_title,
                       SUM(ii.total) AS class_total,
                       SUM(ROUND(inv.paid_total * (ii.total / NULLIF(inv.subtotal,0)))) AS class_paid,
                       SUM(ROUND(inv.remaining_total * (ii.total / NULLIF(inv.subtotal,0)))) AS class_remaining
                FROM football_invoices inv
                INNER JOIN football_invoice_items ii ON ii.invoice_id = inv.id
                LEFT JOIN football_classes c ON c.id = ii.class_id
                LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
                WHERE inv.player_id IN ({$ids}) AND inv.status IN ('open','partial')
                GROUP BY inv.player_id, ii.class_id, c.title, ag.title
                ORDER BY inv.player_id, class_remaining DESC
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $pid = (int)$row['player_id'];
                $classDebtsByPlayer[$pid][] = [
                    'class_id' => $row['class_id'] ? (int)$row['class_id'] : null,
                    'class_title' => $row['class_title'] ?? ($row['class_id'] ? 'کلاس #'.$row['class_id'] : 'سایر هزینه‌ها'),
                    'age_group_title' => $row['age_group_title'] ?? null,
                    'total' => (int)($row['class_total'] ?? 0),
                    'paid' => (int)($row['class_paid'] ?? 0),
                    'remaining' => (int)($row['class_remaining'] ?? 0),
                ];
            }
        }

        foreach ($players as &$p) {
            $pid = (int)$p['player_id'];
            $p['player_name'] = $p['player_name'] ?? trim($p['first_name'].' '.$p['last_name']);
            $p['total_debt'] = (int)($p['debt'] ?? 0);
            $p['total'] = (int)($p['total'] ?? 0);
            $p['paid'] = (int)($p['paid'] ?? 0);
            $p['oldest_invoice_date'] = $p['oldest_due_date'] ?? null;
            $p['overdue_debt'] = $p['debt'];
            $p['class_debts'] = $classDebtsByPlayer[$pid] ?? [];
            $p['is_debtor'] = $p['debt'] > 0;
        }

        return $players;
    }

    public static function attendance(array $filters): array
    {
        $pdo = Database::connection();

        $where = ['a.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['session_id'])) {
            $where[] = 'a.session_id = :session_id';
            $params['session_id'] = $filters['session_id'];
        }

        if (!empty($filters['class_id'])) {
            $where[] = 's.class_id = :class_id';
            $params['class_id'] = $filters['class_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT a.status, COUNT(*) AS count
            FROM football_attendances a
            INNER JOIN football_sessions s ON s.id = a.session_id
            WHERE {$whereSql}
            GROUP BY a.status ORDER BY a.status ASC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function classes(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT c.*, ag.title AS age_group_title,
                   (SELECT COUNT(*) FROM football_enrollments e WHERE e.class_id = c.id AND e.status = "active") AS active_enrollments
            FROM football_classes c
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            WHERE c.deleted_at IS NULL
            ORDER BY c.id DESC
            LIMIT 200
        ');

        $stmt->execute();

        return $stmt->fetchAll();
    }
}