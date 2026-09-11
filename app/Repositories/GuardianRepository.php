<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class GuardianRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['u.deleted_at IS NULL', 'u.role = :role'];
        $params = ['role' => 'guardian'];

        if (!empty($filters['status'])) {
            $where[] = 'u.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(u.full_name LIKE :q1 OR u.mobile LIKE :q2 OR u.national_code LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM football_guardians g
            INNER JOIN football_users u ON u.id = g.user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT g.*, u.full_name AS user_full_name, u.mobile AS user_mobile,
                   u.national_code AS user_national_code, u.status AS user_status
            FROM football_guardians g
            INNER JOIN football_users u ON u.id = g.user_id
            WHERE {$whereSql}
            ORDER BY g.id DESC
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
            SELECT g.*, u.full_name AS user_full_name, u.mobile AS user_mobile,
                   u.national_code AS user_national_code, u.status AS user_status
            FROM football_guardians g
            INNER JOIN football_users u ON u.id = g.user_id
            WHERE g.id = :id AND u.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $guardian = $stmt->fetch();

        return $guardian ?: null;
    }

    public static function findByUserId(int $userId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_guardians WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $guardian = $stmt->fetch();

        return $guardian ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = ['address', 'emergency_phone', 'notes'];

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

        $stmt = $pdo->prepare("UPDATE football_guardians SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }
}