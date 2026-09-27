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

        // جدا کردن رسانه‌ها تا فایل‌های خبر حذف‌شده در لیست عمومی نمانند
        $pdo->prepare('
            UPDATE football_media
            SET related_type = NULL, related_id = NULL, updated_at = NOW()
            WHERE related_type = "news" AND related_id = :news_id
        ')->execute(['news_id' => $id]);
    }

    /**
     * یک خبر واحد برای کاربر کلاینت با بررسی دسترسی مخاطب
     */
    public static function findPublishedForUser(int $newsId, int $userId, string $role): ?array
    {
        $pdo = Database::connection();

        if ($role === 'admin') {
            $stmt = $pdo->prepare('
                SELECT n.*, u.full_name AS created_by_name
                FROM football_news n
                LEFT JOIN football_users u ON u.id = n.created_by
                WHERE n.id = :id AND n.status = "published" AND n.deleted_at IS NULL
                LIMIT 1
            ');
            $stmt->execute(['id' => $newsId]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        $context = \App\Repositories\ClientRepository::getUserContext($userId, $role);

        $params = ['id' => $newsId, 'role' => $role];

        // ساخت IN clause ها
        $makeIn = function(array $ids, string $prefix) use (&$params): string {
            if (empty($ids)) return '0';
            $parts = [];
            foreach ($ids as $i => $id) {
                $k = $prefix . $i;
                $parts[] = ':' . $k;
                $params[$k] = (int)$id;
            }
            return implode(', ', $parts);
        };

        $classIn = $makeIn($context['class_ids'], 'cid');
        $ageGroupIn = $makeIn($context['age_group_ids'], 'aid');
        $playerIn = $makeIn($context['player_ids'], 'pid');

        $stmt = $pdo->prepare("
            SELECT DISTINCT n.*, u.full_name AS created_by_name
            FROM football_news n
            LEFT JOIN football_users u ON u.id = n.created_by
            LEFT JOIN football_news_audiences a ON a.news_id = n.id
            WHERE n.id = :id AND n.status = \"published\" AND n.deleted_at IS NULL
              AND (
                    a.id IS NULL
                 OR a.audience_type = \"global\"
                 OR (a.audience_type = \"role\" AND a.role = :role)
                 OR (a.audience_type = \"class\" AND a.target_id IN ({$classIn}))
                 OR (a.audience_type = \"age_group\" AND a.target_id IN ({$ageGroupIn}))
                 OR (a.audience_type = \"player\" AND a.target_id IN ({$playerIn}))
              )
            LIMIT 1
        ");

        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function audiences(int $newsId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_news_audiences WHERE news_id = :news_id ORDER BY id ASC');
        $stmt->execute(['news_id' => $newsId]);

        return $stmt->fetchAll();
    }

    /**
     * مخاطبان چند خبر به‌صورت گروهی — برای جلوگیری از N+1 در لیست‌ها
     * @param int[] $newsIds
     * @return array<int, array> کلید = news_id
     */
    public static function audiencesForNewsIds(array $newsIds): array
    {
        $newsIds = array_values(array_unique(array_filter(
            array_map('intval', $newsIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($newsIds)) {
            return [];
        }

        $pdo = Database::connection();

        $params = [];
        $placeholders = [];

        foreach ($newsIds as $index => $newsId) {
            $key = 'nid' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $newsId;
        }

        $inClause = implode(', ', $placeholders);

        $stmt = $pdo->prepare("\n            SELECT * FROM football_news_audiences\n            WHERE news_id IN ({$inClause})\n            ORDER BY id ASC\n        ");

        $stmt->execute($params);

        $grouped = [];

        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['news_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * دریافت عنوان گروه‌های سنی برای enrich کردن مخاطبان
     * @param int[] $ids
     * @return array<int, string> id => title
     */
    public static function getAgeGroupTitles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (empty($ids)) return [];

        $pdo = Database::connection();
        $params = [];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $k = 'ag' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = $id;
        }
        $in = implode(', ', $placeholders);
        $stmt = $pdo->prepare("SELECT id, title FROM football_age_groups WHERE id IN ({$in})");
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int)$row['id']] = (string)$row['title'];
        }
        return $map;
    }

    /**
     * دریافت عنوان کلاس‌ها
     * @param int[] $ids
     * @return array<int, string>
     */
    public static function getClassTitles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (empty($ids)) return [];

        $pdo = Database::connection();
        $params = [];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $k = 'cl' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = $id;
        }
        $in = implode(', ', $placeholders);
        $stmt = $pdo->prepare("SELECT id, title FROM football_classes WHERE id IN ({$in})");
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int)$row['id']] = (string)$row['title'];
        }
        return $map;
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

    // ═════════════════════════════════════════════════════════════
    //  رسانه‌های متصل به خبر
    //
    //  از رابطه‌ی polymorphic موجود در جدول football_media استفاده
    //  می‌کنیم (related_type = "news" و related_id = news.id) و
    //  هیچ تغییر دیتابیسی لازم نیست.
    // ═════════════════════════════════════════════════════════════

    /** رسانه‌های فعال یک خبر */
    public static function mediaForNews(int $newsId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT * FROM football_media
            WHERE related_type = "news"
              AND related_id = :news_id
              AND status = "active"
            ORDER BY id ASC
        ');

        $stmt->execute(['news_id' => $newsId]);

        return $stmt->fetchAll();
    }

    /**
     * رسانه‌های چند خبر به‌صورت یکجا — برای جلوگیری از N+1 در لیست‌ها.
     *
     * @param int[] $newsIds
     * @return array<int, array> کلید = news_id
     */
    public static function mediaForNewsIds(array $newsIds): array
    {
        $newsIds = array_values(array_unique(array_filter(
            array_map('intval', $newsIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($newsIds)) {
            return [];
        }

        $pdo = Database::connection();

        $params = [];
        $placeholders = [];

        foreach ($newsIds as $index => $newsId) {
            $key = 'nid' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $newsId;
        }

        $inClause = implode(', ', $placeholders);

        $stmt = $pdo->prepare("
            SELECT * FROM football_media
            WHERE related_type = \"news\"
              AND related_id IN ({$inClause})
              AND status = \"active\"
            ORDER BY id ASC
        ");

        $stmt->execute($params);

        $grouped = [];

        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['related_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * جایگزینی کامل رسانه‌های یک خبر.
     * رسانه‌هایی که در $mediaIds نباشند، از خبر جدا می‌شوند
     * (فایل حذف نمی‌شود؛ فقط اتصال قطع می‌گردد).
     *
     * @param int[] $mediaIds
     */
    public static function replaceMedia(int $newsId, array $mediaIds): void
    {
        $pdo = Database::connection();

        // قطع اتصال همه‌ی رسانه‌های فعلی این خبر
        $pdo->prepare('
            UPDATE football_media
            SET related_type = NULL, related_id = NULL, updated_at = NOW()
            WHERE related_type = "news" AND related_id = :news_id
        ')->execute(['news_id' => $newsId]);

        $mediaIds = array_values(array_unique(array_filter(
            array_map('intval', $mediaIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($mediaIds)) {
            return;
        }

        $params = ['news_id' => $newsId];
        $placeholders = [];

        foreach ($mediaIds as $index => $mediaId) {
            $key = 'mid' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $mediaId;
        }

        $inClause = implode(', ', $placeholders);

        $pdo->prepare("
            UPDATE football_media
            SET related_type = \"news\", related_id = :news_id, updated_at = NOW()
            WHERE id IN ({$inClause})
        ")->execute($params);
    }
}