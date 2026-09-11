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

    public static function playerBalance(int $playerId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(remaining_total), 0) AS debt
            FROM football_invoices
            WHERE player_id = :player_id AND status <> "cancelled"
        ');
        $stmt->execute(['player_id' => $playerId]);
        $debt = (int) $stmt->fetch()['debt'];

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) AS total_paid
            FROM football_payments
            WHERE player_id = :player_id AND status = "approved"
        ');
        $stmt->execute(['player_id' => $playerId]);
        $totalPaid = (int) $stmt->fetch()['total_paid'];

        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS pending_count
            FROM football_payments
            WHERE player_id = :player_id AND status = "pending"
        ');
        $stmt->execute(['player_id' => $playerId]);
        $pendingCount = (int) $stmt->fetch()['pending_count'];

        return [
            'player_id' => $playerId,
            'debt' => $debt,
            'total_paid' => $totalPaid,
            'pending_payments_count' => $pendingCount,
        ];
    }
}