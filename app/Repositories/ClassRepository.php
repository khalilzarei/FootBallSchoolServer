<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AvatarService;

class ClassRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['c.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['season_id'])) {
            $where[] = 'c.season_id = :season_id';
            $params['season_id'] = $filters['season_id'];
        }

        if (!empty($filters['age_group_id'])) {
            $where[] = 'c.age_group_id = :age_group_id';
            $params['age_group_id'] = $filters['age_group_id'];
        }

        if (!empty($filters['coach_id'])) {
            $where[] = 'c.coach_id = :coach_id';
            $params['coach_id'] = $filters['coach_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(c.title LIKE :q1 OR c.location LIKE :q2)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_classes c WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            " . self::decoratedSelect() . "
            WHERE {$whereSql}
            ORDER BY c.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $items = array_map(static fn (array $row): array => self::hydrate($row), $stmt->fetchAll());

        // بارگذاری دسته‌ای برنامه‌های هفتگی صفحه جاری (تک کوئری)
        $schedulesByClass = ClassScheduleRepository::forClassIds(array_column($items, 'id'));

        foreach ($items as &$item) {
            $item['schedules'] = $schedulesByClass[(int) $item['id']] ?? [];
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            ' . self::decoratedSelect() . '
            WHERE c.id = :id AND c.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $class = $stmt->fetch();

        if (!$class) {
            return null;
        }

        $class = self::hydrate($class);
        $class['schedules'] = ClassScheduleRepository::forClass($id);

        return $class;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_classes (
                title, season_id, age_group_id, coach_id, assistant_coach_id, capacity, status,
                location, description, pricing_type, monthly_fee, session_fee,
                registration_fee, start_date, end_date, created_by, created_at
            ) VALUES (
                :title, :season_id, :age_group_id, :coach_id, :assistant_coach_id, :capacity, :status,
                :location, :description, :pricing_type, :monthly_fee, :session_fee,
                :registration_fee, :start_date, :end_date, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'title' => $data['title'],
            'season_id' => $data['season_id'] ?? null,
            'age_group_id' => $data['age_group_id'] ?? null,
            'coach_id' => $data['coach_id'] ?? null,
            'assistant_coach_id' => $data['assistant_coach_id'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'status' => $data['status'] ?? 'active',
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'pricing_type' => $data['pricing_type'] ?? 'monthly',
            'monthly_fee' => $data['monthly_fee'] ?? 0,
            'session_fee' => $data['session_fee'] ?? 0,
            'registration_fee' => $data['registration_fee'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = [
            'title', 'season_id', 'age_group_id', 'coach_id', 'assistant_coach_id', 'capacity',
            'status', 'location', 'description', 'pricing_type', 'monthly_fee',
            'session_fee', 'registration_fee', 'start_date', 'end_date',
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

        $stmt = $pdo->prepare("
            UPDATE football_classes SET " . implode(', ', $sets) . "
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_classes SET status = :status, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function softDelete(int $id): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_classes
            SET deleted_at = NOW(), status = :status, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ');
        $stmt->execute(['id' => $id, 'status' => 'archived']);
    }

    // ═════════════════════════════════════════════════════════════
    // دکوراسیون پاسخ: ساختار تخت دیتابیس به ساختار تو در تویی
    // که سمت اپ اندروید (ClassDto) انتظار دارد تبدیل می‌شود:
    // age_group / season / coach / assistant_coach / is_active /
    // enrolled_count / schedules
    // ═════════════════════════════════════════════════════════════

    private static function decoratedSelect(): string
    {
        return '
            SELECT
                c.*,
                (c.status = \'active\') AS is_active,
                (
                    SELECT COUNT(*)
                    FROM football_enrollments e
                    WHERE e.class_id = c.id
                      AND e.status = \'active\'
                      AND e.ended_at IS NULL
                ) AS enrolled_count,

                ag.id               AS ag_id,
                ag.title            AS ag_title,
                ag.birth_date_from  AS ag_birth_from,
                ag.birth_date_to    AS ag_birth_to,
                ag.min_age_at_cutoff AS ag_min_age,
                ag.max_age_at_cutoff AS ag_max_age,
                ag.sort_order       AS ag_sort_order,
                ag.status           AS ag_status,
                (ag.status = \'active\') AS ag_is_active,
                (
                    SELECT COUNT(*)
                    FROM football_players p
                    WHERE p.deleted_at IS NULL
                      AND p.birth_date IS NOT NULL
                      AND p.birth_date >= ag.birth_date_from
                      AND p.birth_date <= ag.birth_date_to
                ) AS ag_players_count,

                s.id               AS s_id,
                s.title            AS s_title,
                s.start_date       AS s_start_date,
                s.end_date         AS s_end_date,
                s.age_cutoff_date  AS s_cutoff_date,
                s.status           AS s_status,
                (s.status = \'active\') AS s_is_active,
                s.notes            AS s_notes,

                co.id            AS co_id,
                co.user_id       AS co_user_id,
                co.specialty     AS co_specialty,
                co.license_level AS co_license_level,
                co.bio           AS co_bio,
                cu.full_name     AS co_user_full_name,
                cu.mobile        AS co_user_mobile,
                cu.national_code AS co_user_national_code,
                cu.role          AS co_user_role,
                cu.status        AS co_user_status,
                cu.avatar_path   AS co_user_avatar,

                ac.id            AS ac_id,
                ac.user_id       AS ac_user_id,
                ac.specialty     AS ac_specialty,
                ac.license_level AS ac_license_level,
                ac.bio           AS ac_bio,
                au.full_name     AS ac_user_full_name,
                au.mobile        AS ac_user_mobile,
                au.national_code AS ac_user_national_code,
                au.role          AS ac_user_role,
                au.status        AS ac_user_status,
                au.avatar_path   AS ac_user_avatar
            FROM football_classes c
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            LEFT JOIN football_seasons s ON s.id = c.season_id
            LEFT JOIN football_coaches co ON co.id = c.coach_id
            LEFT JOIN football_users cu ON cu.id = co.user_id
            LEFT JOIN football_coaches ac ON ac.id = c.assistant_coach_id
            LEFT JOIN football_users au ON au.id = ac.user_id
        ';
    }

    private static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_active'] = ((int) ($row['is_active'] ?? 0)) === 1;
        $row['enrolled_count'] = (int) ($row['enrolled_count'] ?? 0);

        foreach (['season_id', 'age_group_id', 'coach_id', 'assistant_coach_id', 'capacity', 'created_by'] as $field) {
            if (isset($row[$field]) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }

        $row['age_group'] = !empty($row['ag_id']) ? [
            'id' => (int) $row['ag_id'],
            'title' => $row['ag_title'],
            'birth_date_from' => $row['ag_birth_from'],
            'birth_date_to' => $row['ag_birth_to'],
            'min_age_at_cutoff' => $row['ag_min_age'] !== null ? (int) $row['ag_min_age'] : null,
            'max_age_at_cutoff' => $row['ag_max_age'] !== null ? (int) $row['ag_max_age'] : null,
            'sort_order' => (int) $row['ag_sort_order'],
            'status' => $row['ag_status'],
            'is_active' => ((int) ($row['ag_is_active'] ?? 0)) === 1,
            'players_count' => (int) ($row['ag_players_count'] ?? 0),
        ] : null;

        $row['season'] = !empty($row['s_id']) ? [
            'id' => (int) $row['s_id'],
            'title' => $row['s_title'],
            'start_date' => $row['s_start_date'],
            'end_date' => $row['s_end_date'],
            'age_cutoff_date' => $row['s_cutoff_date'],
            'status' => $row['s_status'],
            'is_active' => ((int) ($row['s_is_active'] ?? 0)) === 1,
            'notes' => $row['s_notes'],
        ] : null;

        $row['coach'] = self::hydrateCoach($row, 'co_');
        $row['assistant_coach'] = self::hydrateCoach($row, 'ac_');

        // حذف کلیدهای موقت استفاده‌شده در ساخت اشیای تو در تو
        foreach (array_keys($row) as $key) {
            if (str_starts_with((string) $key, 'ag_')
                || str_starts_with((string) $key, 's_')
                || str_starts_with((string) $key, 'co_')
                || str_starts_with((string) $key, 'ac_')) {
                unset($row[$key]);
            }
        }

        return $row;
    }

    private static function hydrateCoach(array $row, string $prefix): ?array
    {
        if (empty($row[$prefix . 'id'])) {
            return null;
        }

        $coach = [
            'id' => (int) $row[$prefix . 'id'],
            'user_id' => (int) $row[$prefix . 'user_id'],
            'specialty' => $row[$prefix . 'specialty'],
            'license_level' => $row[$prefix . 'license_level'],
            'bio' => $row[$prefix . 'bio'],
        ];

        if (!empty($row[$prefix . 'user_id'])) {
            $coach['user'] = [
                'id' => (int) $row[$prefix . 'user_id'],
                'full_name' => (string) ($row[$prefix . 'user_full_name'] ?? ''),
                'mobile' => $row[$prefix . 'user_mobile'],
                'national_code' => $row[$prefix . 'user_national_code'],
                'role' => (string) ($row[$prefix . 'user_role'] ?? 'coach'),
                'status' => (string) ($row[$prefix . 'user_status'] ?? 'active'),
                'avatar_url' => AvatarService::getAvatarUrl($row[$prefix . 'user_avatar'], 'user'),
            ];
        }

        return $coach;
    }
}
