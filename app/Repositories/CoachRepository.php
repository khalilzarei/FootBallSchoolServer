<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AvatarService;

class CoachRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['u.deleted_at IS NULL', 'u.role = :role'];
        $params = ['role' => 'coach'];

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
            FROM football_coaches c
            INNER JOIN football_users u ON u.id = c.user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS user_full_name, u.mobile AS user_mobile,
                   u.national_code AS user_national_code, u.status AS user_status,
                   u.avatar_path AS user_avatar_path
            FROM football_coaches c
            INNER JOIN football_users u ON u.id = c.user_id
            WHERE {$whereSql}
            ORDER BY c.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => array_map(static fn (array $row): array => self::hydrate($row), $stmt->fetchAll()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT c.*, u.full_name AS user_full_name, u.mobile AS user_mobile,
                   u.national_code AS user_national_code, u.status AS user_status,
                   u.avatar_path AS user_avatar_path
            FROM football_coaches c
            INNER JOIN football_users u ON u.id = c.user_id
            WHERE c.id = :id AND u.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $coach = $stmt->fetch();

        return $coach ? self::hydrate($coach) : null;
    }

    /**
     * ساخت شیء تو در توی user که اپ اندروید (CoachDto) انتظار دارد
     */
    private static function hydrate(array $coach): array
    {
        $coach['id'] = (int) $coach['id'];
        $coach['user_id'] = (int) $coach['user_id'];
        $coach['is_active'] = ((string) ($coach['user_status'] ?? '')) === 'active';

        $coach['user'] = [
            'id' => (int) $coach['user_id'],
            'full_name' => (string) ($coach['user_full_name'] ?? ''),
            'mobile' => $coach['user_mobile'] ?? null,
            'national_code' => $coach['user_national_code'] ?? null,
            'role' => 'coach',
            'status' => (string) ($coach['user_status'] ?? 'active'),
            'avatar_url' => AvatarService::getAvatarUrl($coach['user_avatar_path'] ?? null, 'user'),
        ];

        return $coach;
    }

    public static function findByUserId(int $userId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_coaches WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $coach = $stmt->fetch();

        return $coach ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = ['specialty', 'license_level', 'bio'];

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

        $stmt = $pdo->prepare("UPDATE football_coaches SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }
}