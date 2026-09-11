<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

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
            SELECT c.*, ag.title AS age_group_title
            FROM football_classes c
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            WHERE {$whereSql}
            ORDER BY c.id DESC
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
            SELECT c.*, ag.title AS age_group_title
            FROM football_classes c
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            WHERE c.id = :id AND c.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $class = $stmt->fetch();

        return $class ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_classes (
                title, age_group_id, coach_id, assistant_coach_id, capacity, status,
                location, description, pricing_type, monthly_fee, session_fee,
                registration_fee, start_date, end_date, created_by, created_at
            ) VALUES (
                :title, :age_group_id, :coach_id, :assistant_coach_id, :capacity, :status,
                :location, :description, :pricing_type, :monthly_fee, :session_fee,
                :registration_fee, :start_date, :end_date, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'title' => $data['title'],
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
            'title', 'age_group_id', 'coach_id', 'assistant_coach_id', 'capacity',
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
}