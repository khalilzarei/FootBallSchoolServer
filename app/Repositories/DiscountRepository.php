<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class DiscountRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['d.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = 'd.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_discounts d WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT d.* FROM football_discounts d
            WHERE {$whereSql}
            ORDER BY d.id DESC
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

        $stmt = $pdo->prepare('SELECT * FROM football_discounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $discount = $stmt->fetch();

        return $discount ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_discounts (
                title, discount_type, value, applies_to, auto_apply,
                start_date, end_date, status, description, created_by, created_at
            ) VALUES (
                :title, :discount_type, :value, :applies_to, :auto_apply,
                :start_date, :end_date, :status, :description, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'title' => $data['title'],
            'discount_type' => $data['discount_type'],
            'value' => $data['value'],
            'applies_to' => $data['applies_to'] ?? 'any',
            'auto_apply' => $data['auto_apply'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? 'active',
            'description' => $data['description'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = ['title', 'discount_type', 'value', 'applies_to', 'auto_apply', 'start_date', 'end_date', 'status', 'description'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sets[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare("UPDATE football_discounts SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_discounts SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}