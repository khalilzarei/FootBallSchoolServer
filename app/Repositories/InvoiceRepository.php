<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class InvoiceRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['i.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['player_id'])) {
            $where[] = 'i.player_id = :player_id';
            $params['player_id'] = $filters['player_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['invoice_type'])) {
            $where[] = 'i.invoice_type = :invoice_type';
            $params['invoice_type'] = $filters['invoice_type'];
        }

        if (!empty($filters['q'])) {
            $where[] = 'i.invoice_number LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_invoices i WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT i.*, p.first_name, p.last_name
            FROM football_invoices i
            INNER JOIN football_players p ON p.id = i.player_id
            WHERE {$whereSql}
            ORDER BY i.id DESC
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
            SELECT i.*, p.first_name, p.last_name
            FROM football_invoices i
            INNER JOIN football_players p ON p.id = i.player_id
            WHERE i.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $invoice = $stmt->fetch();

        return $invoice ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_invoices (
                invoice_number, player_id, invoice_type, period_start_date,
                period_end_date, status, due_date, subtotal, discount_total,
                paid_total, remaining_total, notes, created_by, created_at
            ) VALUES (
                :invoice_number, :player_id, :invoice_type, :period_start_date,
                :period_end_date, :status, :due_date, :subtotal, :discount_total,
                :paid_total, :remaining_total, :notes, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'invoice_number' => $data['invoice_number'],
            'player_id' => $data['player_id'],
            'invoice_type' => $data['invoice_type'],
            'period_start_date' => $data['period_start_date'] ?? null,
            'period_end_date' => $data['period_end_date'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => $data['subtotal'] ?? 0,
            'discount_total' => $data['discount_total'] ?? 0,
            'paid_total' => $data['paid_total'] ?? 0,
            'remaining_total' => $data['remaining_total'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateTotals(int $id, int $subtotal, int $discountTotal, int $paidTotal, int $remainingTotal, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_invoices
            SET subtotal = :subtotal, discount_total = :discount_total,
                paid_total = :paid_total, remaining_total = :remaining_total,
                status = :status, updated_at = NOW()
            WHERE id = :id
        ');

        $stmt->execute([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'paid_total' => $paidTotal,
            'remaining_total' => $remainingTotal,
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_invoices SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function items(int $invoiceId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    public static function findItemById(int $itemId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_invoice_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $item = $stmt->fetch();

        return $item ?: null;
    }

    public static function createItem(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_invoice_items (
                invoice_id, title, item_type, amount, quantity, total,
                class_id, session_id, attendance_id, description, created_at
            ) VALUES (
                :invoice_id, :title, :item_type, :amount, :quantity, :total,
                :class_id, :session_id, :attendance_id, :description, NOW()
            )
        ');

        $stmt->execute([
            'invoice_id' => $data['invoice_id'],
            'title' => $data['title'],
            'item_type' => $data['item_type'],
            'amount' => $data['amount'],
            'quantity' => $data['quantity'],
            'total' => $data['total'],
            'class_id' => $data['class_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'attendance_id' => $data['attendance_id'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateItem(int $itemId, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $itemId];

        $allowedFields = ['title', 'item_type', 'amount', 'quantity', 'total', 'class_id', 'session_id', 'attendance_id', 'description'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $stmt = $pdo->prepare("UPDATE football_invoice_items SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function deleteItem(int $itemId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('DELETE FROM football_invoice_items WHERE id = :id');
        $stmt->execute(['id' => $itemId]);
    }

    public static function discounts(int $invoiceId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_invoice_discounts WHERE invoice_id = :invoice_id ORDER BY id ASC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    public static function createDiscount(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_invoice_discounts (
                invoice_id, discount_id, title, discount_type, value, amount, description, created_at
            ) VALUES (
                :invoice_id, :discount_id, :title, :discount_type, :value, :amount, :description, NOW()
            )
        ');

        $stmt->execute([
            'invoice_id' => $data['invoice_id'],
            'discount_id' => $data['discount_id'] ?? null,
            'title' => $data['title'],
            'discount_type' => $data['discount_type'],
            'value' => $data['value'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function installments(int $invoiceId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_installments WHERE invoice_id = :invoice_id ORDER BY installment_number ASC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    public static function createInstallment(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_installments (
                invoice_id, installment_number, amount, paid_amount, due_date, status, notes, created_at
            ) VALUES (
                :invoice_id, :installment_number, :amount, :paid_amount, :due_date, :status, :notes, NOW()
            )
        ');

        $stmt->execute([
            'invoice_id' => $data['invoice_id'],
            'installment_number' => $data['installment_number'],
            'amount' => $data['amount'],
            'paid_amount' => $data['paid_amount'] ?? 0,
            'due_date' => $data['due_date'],
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function installmentsTotal(int $invoiceId): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM football_installments
            WHERE invoice_id = :invoice_id AND status <> "cancelled"
        ');

        $stmt->execute(['invoice_id' => $invoiceId]);

        return (int) $stmt->fetch()['total'];
    }

    public static function maxInstallmentNumber(int $invoiceId): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT COALESCE(MAX(installment_number), 0) AS max_number
            FROM football_installments WHERE invoice_id = :invoice_id
        ');

        $stmt->execute(['invoice_id' => $invoiceId]);

        return (int) $stmt->fetch()['max_number'];
    }
}