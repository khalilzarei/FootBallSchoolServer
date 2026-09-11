<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class NewsRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['n.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'n.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(n.title LIKE :q1 OR n.body LIKE :q2)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_news n WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT n.*, u.full_name AS created_by_name
            FROM football_news n
            LEFT JOIN football_users u ON u.id = n.created_by
            WHERE {$whereSql}
            ORDER BY n.id DESC
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
            SELECT n.*, u.full_name AS created_by_name
            FROM football_news n
            LEFT JOIN football_users u ON u.id = n.created_by
            WHERE n.id = :id AND n.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $news = $stmt->fetch();

        return $news ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_news (title, body, status, publish_at, created_by, created_at)
            VALUES (:title, :body, :status, :publish_at, :created_by, NOW())
        ');

        $stmt->execute([
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $data['status'] ?? 'draft',
            'publish_at' => $data['publish_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['id' => $id];

        $allowedFields = ['title', 'body', 'status', 'publish_at'];

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

        $stmt = $pdo->prepare("UPDATE football_news SET " . implode(', ', $sets) . " WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute($params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_news SET status = :status, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function softDelete(int $id): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_news SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public static function audiences(int $newsId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_news_audiences WHERE news_id = :news_id ORDER BY id ASC');
        $stmt->execute(['news_id' => $newsId]);

        return $stmt->fetchAll();
    }

    public static function replaceAudiences(int $newsId, array $audiences): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('DELETE FROM football_news_audiences WHERE news_id = :news_id');
        $stmt->execute(['news_id' => $newsId]);

        if (empty($audiences)) {
            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO football_news_audiences (news_id, audience_type, role, target_id, created_at)
            VALUES (:news_id, :audience_type, :role, :target_id, NOW())
        ');

        foreach ($audiences as $audience) {
            $stmt->execute([
                'news_id' => $newsId,
                'audience_type' => $audience['audience_type'],
                'role' => $audience['role'] ?? null,
                'target_id' => $audience['target_id'] ?? null,
            ]);
        }
    }
}