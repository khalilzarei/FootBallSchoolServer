<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class SessionRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['s.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['class_id'])) {
            $where[] = 's.class_id = :class_id';
            $params['class_id'] = $filters['class_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_sessions s WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT s.*, c.title AS class_title, c.status AS class_status
            FROM football_sessions s
            INNER JOIN football_classes c ON c.id = s.class_id
            WHERE {$whereSql}
            ORDER BY s.session_date DESC, s.start_time DESC
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
            SELECT s.*, c.title AS class_title, c.status AS class_status
            FROM football_sessions s
            INNER JOIN football_classes c ON c.id = s.class_id
            WHERE s.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        return $session ? self::hydrate($session) : null;
    }

    /**
     * افزودن شیء کلاس سبک + is_active به ردیف جلسه
     * (SessionDto اپ اندروید فیلد class و is_active را انتظار دارد)
     */
    private static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['class_id'] = (int) $row['class_id'];

        $row['class'] = [
            'id' => (int) $row['class_id'],
            'title' => (string) ($row['class_title'] ?? ''),
            'status' => (string) ($row['class_status'] ?? 'active'),
            'is_active' => ((string) ($row['class_status'] ?? '')) === 'active',
        ];

        return $row;
    }

    public static function exists(int $classId, string $date, string $startTime, ?int $exceptId = null): bool
    {
        $pdo = Database::connection();

        $sql = 'SELECT id FROM football_sessions WHERE class_id = :class_id AND session_date = :session_date AND start_time = :start_time';
        $params = ['class_id' => $classId, 'session_date' => $date, 'start_time' => $startTime];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_sessions (
                class_id, session_date, start_time, end_time, location,
                status, topic, notes, created_by, created_at
            ) VALUES (
                :class_id, :session_date, :start_time, :end_time, :location,
                :status, :topic, :notes, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'class_id' => $data['class_id'],
            'session_date' => $data['session_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'location' => $data['location'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'topic' => $data['topic'] ?? null,
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

        $allowedFields = ['session_date', 'start_time', 'end_time', 'location', 'status', 'topic', 'notes'];

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

        $stmt = $pdo->prepare("UPDATE football_sessions SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_sessions SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}