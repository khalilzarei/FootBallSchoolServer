<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class NotificationRepository
{
    public static function paginateForUser(int $userId, array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['n.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if (!empty($filters['unread_only'])) {
            $where[] = 'n.is_read = 0';
        }

        if (!empty($filters['type'])) {
            $where[] = 'n.type = :type';
            $params['type'] = $filters['type'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_notifications n WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT n.* FROM football_notifications n
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

    public static function unreadCount(int $userId): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM football_notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetch()['total'];
    }

    public static function findByIdForUser(int $id, int $userId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_notifications WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $notification = $stmt->fetch();

        return $notification ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_notifications (user_id, title, body, type, data, is_read, created_at)
            VALUES (:user_id, :title, :body, :type, :data, 0, NOW())
        ');

        $stmt->execute([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'type' => $data['type'] ?? 'info',
            'data' => $data['data'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function markRead(int $id, int $userId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_notifications SET is_read = 1, read_at = NOW()
            WHERE id = :id AND user_id = :user_id AND is_read = 0
        ');

        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_notifications SET is_read = 1, read_at = NOW()
            WHERE user_id = :user_id AND is_read = 0
        ');

        $stmt->execute(['user_id' => $userId]);
    }

    public static function activeUserIdsByRole(string $role): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id FROM football_users
            WHERE role = :role AND status = "active" AND deleted_at IS NULL
        ');

        $stmt->execute(['role' => $role]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }
}