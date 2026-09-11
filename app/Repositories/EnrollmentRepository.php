<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

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
                   p.birth_date, p.status AS player_status,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_enrollments e
            INNER JOIN football_players p ON p.id = e.player_id
            WHERE {$whereSql}
            ORDER BY e.id DESC
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
            SELECT e.*, c.title AS class_title, p.first_name, p.last_name,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_enrollments e
            INNER JOIN football_classes c ON c.id = e.class_id
            INNER JOIN football_players p ON p.id = e.player_id
            WHERE e.id = :id AND c.deleted_at IS NULL AND p.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $enrollment = $stmt->fetch();

        return $enrollment ?: null;
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
}