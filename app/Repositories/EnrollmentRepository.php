<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AvatarService;

class EnrollmentRepository
{
    public static function paginateForClass(int $classId, array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['e.class_id = :class_id', 'p.deleted_at IS NULL'];
        $params = ['class_id' => $classId];

        if (!empty($filters['status'])) {
            $where[] = 'e.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.first_name LIKE :q1 OR p.last_name LIKE :q2 OR p.national_code LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM football_enrollments e
            INNER JOIN football_players p ON p.id = e.player_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT e.*, p.first_name, p.last_name, p.national_code AS player_national_code,
                   p.avatar_path, p.birth_date AS player_birth_date, p.status AS player_status,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_enrollments e
            INNER JOIN football_players p ON p.id = e.player_id
            WHERE {$whereSql}
            ORDER BY e.id DESC
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
            SELECT e.*, c.title AS class_title, c.status AS class_status,
                   p.first_name, p.last_name, p.avatar_path,
                   p.national_code AS player_national_code, p.birth_date AS player_birth_date,
                   p.status AS player_status,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_enrollments e
            INNER JOIN football_classes c ON c.id = e.class_id
            INNER JOIN football_players p ON p.id = e.player_id
            WHERE e.id = :id AND c.deleted_at IS NULL AND p.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $enrollment = $stmt->fetch();

        return $enrollment ? self::hydrate($enrollment) : null;
    }

    /**
     * تبدیل ردیف خام به ساختاری که EnrollmentDto اپ اندروید انتظار دارد:
     * is_active + شیء تو در توی player (نام کامل، سن و ...)
     */
    private static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['class_id'] = (int) $row['class_id'];
        $row['player_id'] = (int) $row['player_id'];
        $row['is_active'] = ($row['status'] ?? '') === 'active';

        $row['player'] = [
            'id' => (int) $row['player_id'],
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'full_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'national_code' => $row['player_national_code'] ?? null,
            'birth_date' => $row['player_birth_date'] ?? null,
            'age' => $row['age'] !== null ? (int) $row['age'] : null,
            'status' => (string) ($row['player_status'] ?? 'active'),
            'is_active' => ((string) ($row['player_status'] ?? '')) === 'active',
            'avatar_path' => $row['avatar_path'] ?? null,
            'avatar_url' => AvatarService::getAvatarUrl($row['avatar_path'] ?? null, 'player'),
        ];

        // کلاس سبک — فقط در findById که به جدول کلاس JOIN شده است
        if (array_key_exists('class_title', $row)) {
            $row['class'] = [
                'id' => (int) $row['class_id'],
                'title' => (string) ($row['class_title'] ?? ''),
                'status' => (string) ($row['class_status'] ?? 'active'),
                'is_active' => ((string) ($row['class_status'] ?? '')) === 'active',
            ];
        }

        return $row;
    }

    public static function findActiveOrPending(int $classId, int $playerId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT * FROM football_enrollments
            WHERE class_id = :class_id AND player_id = :player_id
              AND status IN ("active", "pending", "waitlist")
            ORDER BY id DESC LIMIT 1
        ');

        $stmt->execute(['class_id' => $classId, 'player_id' => $playerId]);
        $enrollment = $stmt->fetch();

        return $enrollment ?: null;
    }

    public static function activeCount(int $classId, ?int $exceptEnrollmentId = null): int
    {
        $pdo = Database::connection();

        $sql = 'SELECT COUNT(*) AS total FROM football_enrollments WHERE class_id = :class_id AND status = "active"';
        $params = ['class_id' => $classId];

        if ($exceptEnrollmentId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptEnrollmentId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetch()['total'];
    }

    public static function hasActiveEnrollment(int $classId, int $playerId): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id FROM football_enrollments
            WHERE class_id = :class_id AND player_id = :player_id AND status = "active"
            LIMIT 1
        ');

        $stmt->execute(['class_id' => $classId, 'player_id' => $playerId]);

        return (bool) $stmt->fetch();
    }

    /**
     * شناسه بازیکنان فعالِ یک گروه سنی
     * عضویت بر اساس بازه تاریخ تولد است: هر بازیکنی که تاریخ تولدش
     * بین birth_date_from و birth_date_to گروه باشد، عضو این گروه است
     */
    public static function activePlayerIdsByAgeGroup(int $ageGroupId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.id
            FROM football_players p
            INNER JOIN football_age_groups ag ON ag.id = :age_group_id
            WHERE p.deleted_at IS NULL
              AND p.status = "active"
              AND p.birth_date IS NOT NULL
              AND p.birth_date >= ag.birth_date_from
              AND p.birth_date <= ag.birth_date_to
            ORDER BY p.id ASC
        ');

        $stmt->execute(['age_group_id' => $ageGroupId]);

        return array_map(static fn (array $r): int => (int) $r['id'], $stmt->fetchAll());
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_enrollments (
                class_id, player_id, status, enrolled_at, ended_at,
                monthly_fee_override, session_fee_override, registration_fee_override,
                notes, created_by, created_at
            ) VALUES (
                :class_id, :player_id, :status, :enrolled_at, :ended_at,
                :monthly_fee_override, :session_fee_override, :registration_fee_override,
                :notes, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'class_id' => $data['class_id'],
            'player_id' => $data['player_id'],
            'status' => $data['status'] ?? 'active',
            'enrolled_at' => $data['enrolled_at'],
            'ended_at' => $data['ended_at'] ?? null,
            'monthly_fee_override' => $data['monthly_fee_override'] ?? null,
            'session_fee_override' => $data['session_fee_override'] ?? null,
            'registration_fee_override' => $data['registration_fee_override'] ?? null,
            'notes' => $data['notes'] ?? null,
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
            'status', 'enrolled_at', 'ended_at', 'monthly_fee_override',
            'session_fee_override', 'registration_fee_override', 'notes',
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

        $stmt = $pdo->prepare("UPDATE football_enrollments SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_enrollments SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * کلاسِ فعلی (تازه‌ترین ثبت‌نام فعال) هر بازیکن — برای نمایش در لیست/جزئیات بازیکن.
     * اگر بازیکن در چند کلاس فعال ثبت‌نام شده باشد، تازه‌ترین برمی‌گردد.
     * خروجی: [player_id => آرایه‌ی کلاس به شکل ClassDto اپ]
     */
    public static function currentClassByPlayerIds(array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        $pdo = Database::connection();
        $ids = implode(',', array_map('intval', $playerIds));

        // تازه‌ترین ثبت‌نام فعال هر بازیکن
        $stmt = $pdo->prepare("
            SELECT player_id, class_id
            FROM football_enrollments
            WHERE player_id IN ({$ids}) AND status = 'active' AND ended_at IS NULL
            ORDER BY enrolled_at DESC, id DESC
        ");
        $stmt->execute();

        $classIdByPlayer = [];
        foreach ($stmt->fetchAll() as $r) {
            $pid = (int) $r['player_id'];
            if (!isset($classIdByPlayer[$pid])) {
                $classIdByPlayer[$pid] = (int) $r['class_id'];
            }
        }

        if (empty($classIdByPlayer)) {
            return [];
        }

        $classIds = implode(',', array_map('intval', array_unique($classIdByPlayer)));

        $stmt = $pdo->prepare("
            SELECT c.*,
                (
                    SELECT COUNT(*) FROM football_enrollments e
                    WHERE e.class_id = c.id AND e.status = 'active' AND e.ended_at IS NULL
                ) AS enrolled_count
            FROM football_classes c
            WHERE c.id IN ({$classIds})
        ");
        $stmt->execute();
        $classes = [];
        foreach ($stmt->fetchAll() as $c) {
            $classes[(int) $c['id']] = $c;
        }

        $out = [];
        foreach ($classIdByPlayer as $pid => $cid) {
            if (!isset($classes[$cid])) {
                continue;
            }
            $c = $classes[$cid];
            $out[$pid] = [
                'id' => (int) $c['id'],
                'title' => (string) $c['title'],
                'season_id' => isset($c['season_id']) ? (int) $c['season_id'] : null,
                'age_group_id' => isset($c['age_group_id']) ? (int) $c['age_group_id'] : null,
                'coach_id' => isset($c['coach_id']) ? (int) $c['coach_id'] : null,
                'assistant_coach_id' => isset($c['assistant_coach_id']) ? (int) $c['assistant_coach_id'] : null,
                'capacity' => isset($c['capacity']) ? (int) $c['capacity'] : null,
                'status' => $c['status'] ?? 'active',
                'is_active' => ($c['status'] ?? '') === 'active',
                'location' => $c['location'] ?? null,
                'description' => $c['description'] ?? null,
                'pricing_type' => $c['pricing_type'] ?? null,
                'monthly_fee' => isset($c['monthly_fee']) ? (int) $c['monthly_fee'] : null,
                'session_fee' => isset($c['session_fee']) ? (int) $c['session_fee'] : null,
                'registration_fee' => isset($c['registration_fee']) ? (int) $c['registration_fee'] : null,
                'start_date' => $c['start_date'] ?? null,
                'end_date' => $c['end_date'] ?? null,
                'enrolled_count' => (int) ($c['enrolled_count'] ?? 0),
            ];
        }

        return $out;
    }
}