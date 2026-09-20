<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class GuardianRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(g.full_name LIKE :q1 OR g.mobile LIKE :q2 OR g.national_code LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM football_guardians g
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT g.*
            FROM football_guardians g
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
            SELECT g.*
            FROM football_guardians g
            WHERE g.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $guardian = $stmt->fetch();

        return $guardian ?: null;
    }

    /** ساخت رکورد سرپرست (فقط مشخصات تماس — بدون حساب کاربری) */
    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_guardians (full_name, mobile, national_code, address, emergency_phone, notes, created_at, updated_at)
            VALUES (:full_name, :mobile, :national_code, :address, :emergency_phone, :notes, NOW(), NOW())
        ');
        $stmt->execute([
            'full_name' => (string) $data['full_name'],
            'mobile' => $data['mobile'] ?? null,
            'national_code' => $data['national_code'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = ['full_name', 'mobile', 'national_code', 'address', 'emergency_phone', 'notes'];

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