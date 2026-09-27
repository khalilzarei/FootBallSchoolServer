<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class PaymentRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['p.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['player_id'])) {
            $where[] = 'p.player_id = :player_id';
            $params['player_id'] = $filters['player_id'];
        }

        if (!empty($filters['invoice_id'])) {
            $where[] = 'p.invoice_id = :invoice_id';
            $params['invoice_id'] = $filters['invoice_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_payments p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT p.*, pl.first_name, pl.last_name, i.invoice_number
            FROM football_payments p
            INNER JOIN football_players pl ON pl.id = p.player_id
            LEFT JOIN football_invoices i ON i.id = p.invoice_id
            WHERE {$whereSql}
            ORDER BY p.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.*, pl.first_name, pl.last_name, i.invoice_number
            FROM football_payments p
            INNER JOIN football_players pl ON pl.id = p.player_id
            LEFT JOIN football_invoices i ON i.id = p.invoice_id
            WHERE p.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    public static function findInstallmentById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_installments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $installment = $stmt->fetch();

        return $installment ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_payments (
                payment_number, player_id, invoice_id, installment_id, amount,
                payment_method, status, paid_at, receipt_media_id,
                gateway_name, gateway_reference, gateway_status,
                confirmed_by, confirmed_at, notes, created_by, created_at
            ) VALUES (
                :payment_number, :player_id, :invoice_id, :installment_id, :amount,
                :payment_method, :status, :paid_at, :receipt_media_id,
                :gateway_name, :gateway_reference, :gateway_status,
                :confirmed_by, :confirmed_at, :notes, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'payment_number' => $data['payment_number'],
            'player_id' => $data['player_id'],
            'invoice_id' => $data['invoice_id'] ?? null,
            'installment_id' => $data['installment_id'] ?? null,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'status' => $data['status'] ?? 'pending',
            'paid_at' => $data['paid_at'] ?? null,
            'receipt_media_id' => $data['receipt_media_id'] ?? null,
            'gateway_name' => $data['gateway_name'] ?? null,
            'gateway_reference' => $data['gateway_reference'] ?? null,
            'gateway_status' => $data['gateway_status'] ?? null,
            'confirmed_by' => $data['confirmed_by'] ?? null,
            'confirmed_at' => $data['confirmed_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function markApproved(int $id, int $confirmedBy): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_payments
            SET status = "approved", confirmed_by = :confirmed_by, confirmed_at = NOW(),
                paid_at = COALESCE(paid_at, NOW()), updated_at = NOW()
            WHERE id = :id AND status = "pending"
        ');

        $stmt->execute(['id' => $id, 'confirmed_by' => $confirmedBy]);
    }

    public static function markRejected(int $id, int $confirmedBy): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_payments
            SET status = "rejected", confirmed_by = :confirmed_by, confirmed_at = NOW(), updated_at = NOW()
            WHERE id = :id AND status = "pending"
        ');

        $stmt->execute(['id' => $id, 'confirmed_by' => $confirmedBy]);
    }

    public static function approvedPaymentsTotalForInvoice(int $invoiceId): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM football_payments
            WHERE invoice_id = :invoice_id AND status = "approved"
        ');

        $stmt->execute(['invoice_id' => $invoiceId]);

        return (int) $stmt->fetch()['total'];
    }

    public static function updateInstallmentPaid(int $installmentId, int $amount): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_installments
            SET paid_amount = paid_amount + :amount,
                status = CASE WHEN paid_amount + :amount2 >= amount THEN "paid" ELSE "partial" END,
                updated_at = NOW()
            WHERE id = :id
        ');

        $stmt->execute(['amount' => $amount, 'amount2' => $amount, 'id' => $installmentId]);
    }

    public static function balancesForPlayerIds(array $playerIds): array
    {
        if (empty($playerIds)) return [];
        $pdo = Database::connection();
        $playerIds = array_map('intval', $playerIds);
        $in = implode(',', $playerIds);

        $stmt = $pdo->prepare("
            SELECT player_id,
                   COALESCE(SUM(CASE WHEN status NOT IN ('draft','cancelled') THEN remaining_total ELSE 0 END),0) AS debt,
                   COALESCE(SUM(CASE WHEN status NOT IN ('draft','cancelled') THEN subtotal ELSE 0 END),0) AS total_invoiced,
                   COALESCE(SUM(CASE WHEN status NOT IN ('draft','cancelled') THEN paid_total ELSE 0 END),0) AS total_paid,
                   COUNT(CASE WHEN status IN ('open','partial','overdue') THEN 1 END) AS open_invoices
            FROM football_invoices
            WHERE player_id IN ($in)
            GROUP BY player_id
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $pid = (int) $r['player_id'];
            $debt = (int) $r['debt'];
            $map[$pid] = [
                'player_id' => $pid,
                'debt' => $debt,
                'total_invoiced' => (int) $r['total_invoiced'],
                'total_paid' => (int) $r['total_paid'],
                'balance' => $debt,
                'is_debtor' => $debt > 0,
                'open_invoices' => (int) $r['open_invoices'],
            ];
        }

        // برای بازیکنان بدون فاکتور، مقدار صفر برگردان
        foreach ($playerIds as $pid) {
            if (!isset($map[$pid])) {
                $map[$pid] = [
                    'player_id' => $pid,
                    'debt' => 0,
                    'total_invoiced' => 0,
                    'total_paid' => 0,
                    'balance' => 0,
                    'is_debtor' => false,
                    'open_invoices' => 0,
                ];
            }
        }

        return $map;
    }

    public static function playerBalance(int $playerId): array
    {
        $pdo = Database::connection();

        // خلاصه کلی بدهی
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(remaining_total), 0) AS debt,
                   COALESCE(SUM(subtotal),0) AS total_invoiced,
                   COALESCE(SUM(paid_total),0) AS total_paid_calc
            FROM football_invoices
            WHERE player_id = :player_id AND status NOT IN ("draft","cancelled")
        ');
        $stmt->execute(['player_id' => $playerId]);
        $row = $stmt->fetch();
        $debt = (int) ($row['debt'] ?? 0);
        $totalInvoiced = (int) ($row['total_invoiced'] ?? 0);

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) AS total_paid
            FROM football_payments
            WHERE player_id = :player_id AND status = "approved"
        ');
        $stmt->execute(['player_id' => $playerId]);
        $totalPaid = (int) $stmt->fetch()['total_paid'];

        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS pending_count, COALESCE(SUM(amount),0) AS pending_amount
            FROM football_payments
            WHERE player_id = :player_id AND status = "pending"
        ');
        $stmt->execute(['player_id' => $playerId]);
        $pendingRow = $stmt->fetch();
        $pendingCount = (int) ($pendingRow['pending_count'] ?? 0);
        $pendingAmount = (int) ($pendingRow['pending_amount'] ?? 0);

        // بدهی به تفکیک کلاس (از آیتم‌های فاکتور)
        $stmt = $pdo->prepare('
            SELECT ii.class_id, c.title AS class_title, ag.title AS age_group_title,
                   SUM(ii.total) AS class_total
            FROM football_invoice_items ii
            INNER JOIN football_invoices inv ON inv.id = ii.invoice_id AND inv.status NOT IN ("draft","cancelled")
            LEFT JOIN football_classes c ON c.id = ii.class_id
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            WHERE inv.player_id = :player_id
            GROUP BY ii.class_id, c.title, ag.title
            ORDER BY c.title ASC
        ');
        $stmt->execute(['player_id' => $playerId]);
        $classTotals = $stmt->fetchAll();

        // برای هر کلاس، سهم پرداخت شده و باقی‌مانده را بر اساس نسبت آیتم‌ها حساب می‌کنیم
        $classDebts = [];
        // ابتدا فاکتورها و آیتم‌هایشان را می‌گیریم تا توزیع دقیق انجام شود
        $stmt = $pdo->prepare('
            SELECT inv.id, inv.paid_total, inv.remaining_total,
                   ii.class_id, ii.total, c.title AS class_title, ag.title AS age_group_title
            FROM football_invoices inv
            INNER JOIN football_invoice_items ii ON ii.invoice_id = inv.id
            LEFT JOIN football_classes c ON c.id = ii.class_id
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            WHERE inv.player_id = :player_id AND inv.status NOT IN ("draft","cancelled")
        ');
        $stmt->execute(['player_id' => $playerId]);
        $rows = $stmt->fetchAll();

        $map = []; // class_id => [title, total, paid, remaining]
        foreach ($rows as $r) {
            $cid = $r['class_id'] ? (int)$r['class_id'] : 0;
            $key = $cid !== 0 ? $cid : -1;
            $invTotal = (int)$r['paid_total'] + (int)$r['remaining_total'];
            if ($invTotal <= 0) $invTotal = 1;
            $ratio = (int)$r['total'] / $invTotal;

            if (!isset($map[$key])) {
                $map[$key] = [
                    'class_id' => $cid !== 0 ? $cid : null,
                    'class_title' => $r['class_title'] ?? ($cid === 0 ? 'سایر هزینه‌ها' : 'کلاس #'.$cid),
                    'age_group_title' => $r['age_group_title'] ?? null,
                    'total' => 0,
                    'paid' => 0,
                    'remaining' => 0,
                ];
            }
            $map[$key]['total'] += (int)$r['total'];
            $map[$key]['paid'] += (int) round((int)$r['paid_total'] * $ratio);
            $map[$key]['remaining'] += (int) round((int)$r['remaining_total'] * $ratio);
        }
        $classDebts = array_values($map);

        // کلاس‌های فعلی بازیکن با شهریه مصوب - با پشتیبانی فصلی
        $currentClasses = \App\Repositories\EnrollmentRepository::currentClassByPlayerIds([$playerId]);
        $classFees = [];
        if (!empty($currentClasses)) {
            $classIds = array_keys($currentClasses);
            $ids = implode(',', array_map('intval', $classIds));
            try {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.title, c.monthly_fee, c.seasonal_fee, c.billing_cycle, c.billing_months, c.session_fee, c.registration_fee,
                           ag.title AS age_group_title
                    FROM football_classes c
                    LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
                    WHERE c.id IN ({$ids})
                ");
                $stmt->execute();
                $classRows = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.title, c.monthly_fee, c.session_fee, c.registration_fee,
                           ag.title AS age_group_title
                    FROM football_classes c
                    LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
                    WHERE c.id IN ({$ids})
                ");
                $stmt->execute();
                $classRows = $stmt->fetchAll();
            }
            foreach ($classRows as $cc) {
                $cid = (int)$cc['id'];
                $debtForClass = 0;
                foreach ($classDebts as $cd) {
                    if (($cd['class_id'] ?? null) === $cid) { $debtForClass = $cd['remaining']; break; }
                }
                $classFees[] = [
                    'class_id' => $cid,
                    'class_title' => (string)$cc['title'],
                    'age_group_title' => $cc['age_group_title'] ?? null,
                    'monthly_fee' => isset($cc['monthly_fee']) ? (int)$cc['monthly_fee'] : null,
                    'seasonal_fee' => isset($cc['seasonal_fee']) ? (int)$cc['seasonal_fee'] : null,
                    'billing_cycle' => $cc['billing_cycle'] ?? 'monthly',
                    'billing_months' => isset($cc['billing_months']) ? (int)$cc['billing_months'] : (($cc['billing_cycle'] ?? 'monthly') === 'seasonal' ? 3 : 1),
                    'session_fee' => isset($cc['session_fee']) ? (int)$cc['session_fee'] : null,
                    'registration_fee' => isset($cc['registration_fee']) ? (int)$cc['registration_fee'] : null,
                    'debt' => $debtForClass,
                ];
            }
        }

        return [
            'player_id' => $playerId,
            'debt' => $debt,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'pending_payments_count' => $pendingCount,
            'pending_amount' => $pendingAmount,
            'balance' => $debt,
            'is_debtor' => $debt > 0,
            'class_debts' => $classDebts,
            'class_fees' => $classFees,
        ];
    }
}