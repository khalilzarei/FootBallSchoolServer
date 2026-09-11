<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class MediaRepository
{
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::connection();

        $where = ['m.id IS NOT NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'm.status = :status';
            $params['status'] = $filters['status'];
        } else {
            $where[] = 'm.status <> "deleted"';
        }

        if (!empty($filters['visibility'])) {
            $where[] = 'm.visibility = :visibility';
            $params['visibility'] = $filters['visibility'];
        }

        if (!empty($filters['file_type'])) {
            $where[] = 'm.file_type = :file_type';
            $params['file_type'] = $filters['file_type'];
        }

        if (!empty($filters['related_type'])) {
            $where[] = 'm.related_type = :related_type';
            $params['related_type'] = $filters['related_type'];
        }

        if (!empty($filters['related_id'])) {
            $where[] = 'm.related_id = :related_id';
            $params['related_id'] = $filters['related_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(m.original_name LIKE :q1 OR m.description LIKE :q2)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM football_media m WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name AS uploader_name
            FROM football_media m
            INNER JOIN football_users u ON u.id = m.uploader_id
            WHERE {$whereSql}
            ORDER BY m.id DESC
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
            SELECT m.*, u.full_name AS uploader_name
            FROM football_media m
            INNER JOIN football_users u ON u.id = m.uploader_id
            WHERE m.id = :id
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $media = $stmt->fetch();

        return $media ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_media (
                uploader_id, file_type, mime_type, original_name, stored_name,
                file_path, thumbnail_path, size_bytes, duration_seconds,
                visibility, related_type, related_id, description, status, created_at
            ) VALUES (
                :uploader_id, :file_type, :mime_type, :original_name, :stored_name,
                :file_path, :thumbnail_path, :size_bytes, :duration_seconds,
                :visibility, :related_type, :related_id, :description, :status, NOW()
            )
        ');

        $stmt->execute([
            'uploader_id' => $data['uploader_id'],
            'file_type' => $data['file_type'],
            'mime_type' => $data['mime_type'],
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'file_path' => $data['file_path'],
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'size_bytes' => $data['size_bytes'],
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'visibility' => $data['visibility'],
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_media SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function audiences(int $mediaId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_media_audiences WHERE media_id = :media_id ORDER BY id ASC');
        $stmt->execute(['media_id' => $mediaId]);

        return $stmt->fetchAll();
    }

    public static function replaceAudiences(int $mediaId, array $audiences): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('DELETE FROM football_media_audiences WHERE media_id = :media_id');
        $stmt->execute(['media_id' => $mediaId]);

        if (empty($audiences)) {
            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO football_media_audiences (media_id, audience_type, target_id, created_at)
            VALUES (:media_id, :audience_type, :target_id, NOW())
        ');

        foreach ($audiences as $audience) {
            $stmt->execute([
                'media_id' => $mediaId,
                'audience_type' => $audience['audience_type'],
                'target_id' => $audience['target_id'],
            ]);
        }
    }
}