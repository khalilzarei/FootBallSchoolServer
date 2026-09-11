<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ClientRepository
{
    public static function childrenForGuardianUserId(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.*, gp.relation, gp.is_primary, gp.can_view_reports, gp.can_pay,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_guardians g
            INNER JOIN football_guardians_players gp ON gp.guardian_id = g.id AND gp.status = "active"
            INNER JOIN football_players p ON p.id = gp.player_id AND p.deleted_at IS NULL
            WHERE g.user_id = :user_id
            ORDER BY p.id ASC
        ');

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function getUserContext(int $userId, string $role): array
    {
        $playerIds = [];
        $classIds = [];
        $ageGroupIds = [];

        if ($role === 'guardian') {
            $playerIds = self::guardianPlayerIds($userId);
            $classIds = self::guardianClassIds($userId);
        }

        if ($role === 'coach') {
            $coachClassIds = self::coachClassIds($userId);
            $classIds = $coachClassIds;
            $playerIds = self::classPlayerIds($coachClassIds);
        }

        if (!empty($classIds)) {
            $ageGroupIds = self::classAgeGroupIds($classIds);
        }

        return [
            'player_ids' => array_values(array_unique(array_map('intval', $playerIds))),
            'class_ids' => array_values(array_unique(array_map('intval', $classIds))),
            'age_group_ids' => array_values(array_unique(array_map('intval', $ageGroupIds))),
        ];
    }

    public static function scheduleForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();

        if ($role === 'admin') {
            $stmt = $pdo->prepare('
                SELECT s.*, c.title AS class_title
                FROM football_sessions s
                INNER JOIN football_classes c ON c.id = s.class_id
                WHERE s.session_date >= CURDATE() AND s.status IN ("scheduled", "makeup")
                ORDER BY s.session_date ASC, s.start_time ASC
                LIMIT 100
            ');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $context = self::getUserContext($userId, $role);

        if (empty($context['class_ids'])) {
            return [];
        }

        [$inClause, $params] = self::makeInClause($context['class_ids'], 'cid');

        $stmt = $pdo->prepare("
            SELECT s.*, c.title AS class_title
            FROM football_sessions s
            INNER JOIN football_classes c ON c.id = s.class_id
            WHERE s.session_date >= CURDATE() AND s.status IN (\"scheduled\", \"makeup\")
              AND s.class_id IN ({$inClause})
            ORDER BY s.session_date ASC, s.start_time ASC
            LIMIT 100
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function publishedNewsForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();

        if ($role === 'admin') {
            $stmt = $pdo->prepare('
                SELECT n.* FROM football_news n
                WHERE n.status = "published" AND n.deleted_at IS NULL
                ORDER BY n.publish_at DESC, n.id DESC LIMIT 100
            ');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $context = self::getUserContext($userId, $role);

        $params = ['role' => $role];

        $classIn = self::makeInClause($context['class_ids'], 'cid', $params);
        $ageGroupIn = self::makeInClause($context['age_group_ids'], 'aid', $params);
        $playerIn = self::makeInClause($context['player_ids'], 'pid', $params);

        $stmt = $pdo->prepare("
            SELECT DISTINCT n.*
            FROM football_news n
            LEFT JOIN football_news_audiences a ON a.news_id = n.id
            WHERE n.status = \"published\" AND n.deleted_at IS NULL
              AND (
                    a.id IS NULL
                 OR a.audience_type = \"global\"
                 OR (a.audience_type = \"role\" AND a.role = :role)
                 OR (a.audience_type = \"class\" AND a.target_id IN ({$classIn[0]}))
                 OR (a.audience_type = \"age_group\" AND a.target_id IN ({$ageGroupIn[0]}))
                 OR (a.audience_type = \"player\" AND a.target_id IN ({$playerIn[0]}))
              )
            ORDER BY n.publish_at DESC, n.id DESC
            LIMIT 100
        ");

        $stmt->execute(array_merge($params, $classIn[1], $ageGroupIn[1], $playerIn[1]));

        return $stmt->fetchAll();
    }

    public static function visibleMediaForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();

        if ($role === 'admin') {
            $stmt = $pdo->prepare('SELECT m.* FROM football_media m WHERE m.status = "active" ORDER BY m.id DESC LIMIT 100');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $context = self::getUserContext($userId, $role);

        $params = ['user_id' => $userId];

        $classIn = self::makeInClause($context['class_ids'], 'cid', $params);
        $ageGroupIn = self::makeInClause($context['age_group_ids'], 'aid', $params);
        $playerIn = self::makeInClause($context['player_ids'], 'pid', $params);

        $stmt = $pdo->prepare("
            SELECT DISTINCT m.*
            FROM football_media m
            LEFT JOIN football_media_audiences ma ON ma.media_id = m.id
            WHERE m.status = \"active\"
              AND (
                    m.visibility = \"public\"
                 OR (m.visibility = \"private\" AND m.uploader_id = :user_id)
                 OR (
                        m.visibility IN (\"class\", \"age_group\", \"players\")
                        AND (
                              (m.visibility = \"class\" AND ma.audience_type = \"class\" AND ma.target_id IN ({$classIn[0]}))
                           OR (m.visibility = \"age_group\" AND ma.audience_type = \"age_group\" AND ma.target_id IN ({$ageGroupIn[0]}))
                           OR (m.visibility = \"players\" AND ma.audience_type = \"player\" AND ma.target_id IN ({$playerIn[0]}))
                        )
                 )
              )
            ORDER BY m.id DESC
            LIMIT 100
        ");

        $stmt->execute(array_merge($params, $classIn[1], $ageGroupIn[1], $playerIn[1]));

        return $stmt->fetchAll();
    }

    public static function financeForGuardianUserId(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.id AS player_id, p.first_name, p.last_name,
                   COALESCE(SUM(CASE WHEN i.status IN ("open", "partial") THEN i.remaining_total ELSE 0 END), 0) AS debt,
                   COALESCE(SUM(CASE WHEN i.status <> "cancelled" THEN i.paid_total ELSE 0 END), 0) AS paid_total
            FROM football_guardians g
            INNER JOIN football_guardians_players gp ON gp.guardian_id = g.id AND gp.status = "active"
            INNER JOIN football_players p ON p.id = gp.player_id AND p.deleted_at IS NULL
            LEFT JOIN football_invoices i ON i.player_id = p.id
            WHERE g.user_id = :user_id
            GROUP BY p.id, p.first_name, p.last_name
            ORDER BY p.id ASC
        ');

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    private static function guardianPlayerIds(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT gp.player_id
            FROM football_guardians g
            INNER JOIN football_guardians_players gp ON gp.guardian_id = g.id AND gp.status = "active"
            WHERE g.user_id = :user_id
        ');

        $stmt->execute(['user_id' => $userId]);

        return array_column($stmt->fetchAll(), 'player_id');
    }

    private static function guardianClassIds(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT DISTINCT e.class_id
            FROM football_guardians g
            INNER JOIN football_guardians_players gp ON gp.guardian_id = g.id AND gp.status = "active"
            INNER JOIN football_enrollments e ON e.player_id = gp.player_id AND e.status = "active"
            WHERE g.user_id = :user_id
        ');

        $stmt->execute(['user_id' => $userId]);

        return array_column($stmt->fetchAll(), 'class_id');
    }

    private static function coachClassIds(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM football_coaches WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $coach = $stmt->fetch();

        if (!$coach) {
            return [];
        }

        $stmt = $pdo->prepare('
            SELECT id FROM football_classes
            WHERE (coach_id = :coach_id OR assistant_coach_id = :coach_id_2)
              AND status = "active" AND deleted_at IS NULL
        ');

        $stmt->execute(['coach_id' => $coach['id'], 'coach_id_2' => $coach['id']]);

        return array_column($stmt->fetchAll(), 'id');
    }

    private static function classPlayerIds(array $classIds): array
    {
        if (empty($classIds)) {
            return [];
        }

        $pdo = Database::connection();

        [$inClause, $params] = self::makeInClause($classIds, 'cid');

        $stmt = $pdo->prepare("
            SELECT DISTINCT player_id FROM football_enrollments
            WHERE class_id IN ({$inClause}) AND status = \"active\"
        ");

        $stmt->execute($params);

        return array_column($stmt->fetchAll(), 'player_id');
    }

    private static function classAgeGroupIds(array $classIds): array
    {
        if (empty($classIds)) {
            return [];
        }

        $pdo = Database::connection();

        [$inClause, $params] = self::makeInClause($classIds, 'cid');

        $stmt = $pdo->prepare("
            SELECT DISTINCT age_group_id FROM football_classes
            WHERE id IN ({$inClause}) AND age_group_id IS NOT NULL AND deleted_at IS NULL
        ");

        $stmt->execute($params);

        return array_column($stmt->fetchAll(), 'age_group_id');
    }

    private static function makeInClause(array $ids, string $prefix, array &$params = []): array
    {
        if (empty($ids)) {
            return ['0', []];
        }

        $parts = [];
        $localParams = [];
        $index = 0;

        foreach ($ids as $id) {
            $key = $prefix . $index;
            $parts[] = ':' . $key;
            $localParams[$key] = (int) $id;
            $index++;
        }

        $clause = implode(',', $parts);

        foreach ($localParams as $key => $value) {
            $params[$key] = $value;
        }

        return [$clause, $localParams];
    }
}