<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class SeasonRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['s.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = 's.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_seasons s WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT s.*
            FROM football_seasons s
            WHERE {$whereSql}
            ORDER BY s.id DESC
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

        $stmt = $pdo->prepare('SELECT * FROM football_seasons WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $season = $stmt->fetch();

        return $season ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_seasons (
                title, start_date, end_date, age_cutoff_date, status, notes, created_by, created_at
            ) VALUES (
                :title, :start_date, :end_date, :age_cutoff_date, :status, :notes, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'title' => $data['title'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'age_cutoff_date' => $data['age_cutoff_date'],
            'status' => $data['status'] ?? 'active',
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

        $allowedFields = ['title', 'start_date', 'end_date', 'age_cutoff_date', 'status', 'notes'];

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

        $stmt = $pdo->prepare("UPDATE football_seasons SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_seasons SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }
}