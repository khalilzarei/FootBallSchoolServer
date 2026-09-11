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

        if (!empty($filters['season_id'])) {
            $where[] = 'ag.season_id = :season_id';
            $params['season_id'] = $filters['season_id'];
        }

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
            SELECT ag.*, s.title AS season_title
            FROM football_age_groups ag
            INNER JOIN football_seasons s ON s.id = ag.season_id
            WHERE {$whereSql}
            ORDER BY ag.sort_order ASC, ag.id DESC
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
            SELECT ag.*, s.title AS season_title
            FROM football_age_groups ag
            INNER JOIN football_seasons s ON s.id = ag.season_id
            WHERE ag.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $ageGroup = $stmt->fetch();

        return $ageGroup ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_age_groups (
                season_id, title, birth_date_from, birth_date_to,
                min_age_at_cutoff, max_age_at_cutoff, sort_order, status, created_at
            ) VALUES (
                :season_id, :title, :birth_date_from, :birth_date_to,
                :min_age_at_cutoff, :max_age_at_cutoff, :sort_order, :status, NOW()
            )
        ');

        $stmt->execute([
            'season_id' => $data['season_id'],
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
            'season_id', 'title', 'birth_date_from', 'birth_date_to',
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

    public static function hasOverlap(int $seasonId, string $from, string $to, ?int $exceptId = null): bool
    {
        $pdo = Database::connection();

        $sql = '
            SELECT id FROM football_age_groups
            WHERE season_id = :season_id AND status = "active"
              AND birth_date_from <= :birth_date_to AND birth_date_to >= :birth_date_from
        ';

        $params = [
            'season_id' => $seasonId,
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