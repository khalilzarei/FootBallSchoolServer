<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class AgeGroupRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['ag.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'ag.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = 'ag.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_age_groups ag WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT ag.*, (ag.status = 'active') AS is_active,
                (SELECT COUNT(*) FROM football_players p
                 WHERE p.deleted_at IS NULL
                   AND p.birth_date IS NOT NULL
                   AND p.birth_date >= ag.birth_date_from
                   AND p.birth_date <= ag.birth_date_to) AS players_count
            FROM football_age_groups ag
            WHERE {$whereSql}
            ORDER BY ag.sort_order ASC, ag.id DESC
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
            SELECT ag.*, (ag.status = \'active\') AS is_active,
                (SELECT COUNT(*) FROM football_players p
                 WHERE p.deleted_at IS NULL
                   AND p.birth_date IS NOT NULL
                   AND p.birth_date >= ag.birth_date_from
                   AND p.birth_date <= ag.birth_date_to) AS players_count
            FROM football_age_groups ag
            WHERE ag.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $ageGroup = $stmt->fetch();

        return $ageGroup ? self::hydrate($ageGroup) : null;
    }

    private static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
        $row['is_active'] = ((int) ($row['is_active'] ?? 0)) === 1;
        $row['players_count'] = (int) ($row['players_count'] ?? 0);

        return $row;
    }

    /**
     * بازیکنانِ عضو گروه سنی = هر بازیکنی که تاریخ تولدش در بازه گروه باشد
     * (حذف‌نشده؛ وضعیت هر بازیکن در خروجی موجود است)
     */
    public static function playersForAgeGroup(int $ageGroupId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.id, p.first_name, p.last_name, p.national_code, p.birth_date, p.status,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_players p
            INNER JOIN football_age_groups ag ON ag.id = :age_group_id
            WHERE p.deleted_at IS NULL
              AND p.birth_date IS NOT NULL
              AND p.birth_date >= ag.birth_date_from
              AND p.birth_date <= ag.birth_date_to
            ORDER BY p.first_name ASC, p.last_name ASC, p.id ASC
        ');

        $stmt->execute(['age_group_id' => $ageGroupId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'first_name' => (string) $row['first_name'],
            'last_name' => (string) $row['last_name'],
            'full_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'national_code' => $row['national_code'],
            'birth_date' => $row['birth_date'],
            'age' => (int) $row['age'],
            'status' => (string) $row['status'],
            'is_active' => ((string) $row['status']) === 'active',
        ], $stmt->fetchAll());
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_age_groups (
                title, birth_date_from, birth_date_to,
                min_age_at_cutoff, max_age_at_cutoff, sort_order, status, created_at
            ) VALUES (
                :title, :birth_date_from, :birth_date_to,
                :min_age_at_cutoff, :max_age_at_cutoff, :sort_order, :status, NOW()
            )
        ');

        $stmt->execute([
            'title' => $data['title'],
            'birth_date_from' => $data['birth_date_from'],
            'birth_date_to' => $data['birth_date_to'],
            'min_age_at_cutoff' => $data['min_age_at_cutoff'] ?? null,
            'max_age_at_cutoff' => $data['max_age_at_cutoff'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = [
            'title', 'birth_date_from', 'birth_date_to',
            'min_age_at_cutoff', 'max_age_at_cutoff', 'sort_order', 'status',
        ];

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

        $stmt = $pdo->prepare("UPDATE football_age_groups SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_age_groups SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * آیا بازه‌ی تولد با گروه سنی فعال دیگری تداخل دارد؟
     * (بدون فصل — بازه‌ها در کل مدرسه یکتا هستند)
     */
    public static function hasOverlap(string $from, string $to, ?int $exceptId = null): bool
    {
        $pdo = Database::connection();

        $sql = '
            SELECT id FROM football_age_groups
            WHERE status = "active"
              AND birth_date_from <= :birth_date_to AND birth_date_to >= :birth_date_from
        ';

        $params = [
            'birth_date_from' => $from,
            'birth_date_to' => $to,
        ];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }
}