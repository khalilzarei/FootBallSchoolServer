<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class GuardianPlayerRepository
{
    public static function findRelation(int $guardianId, int $playerId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT * FROM football_guardians_players
            WHERE guardian_id = :guardian_id AND player_id = :player_id
            LIMIT 1
        ');

        $stmt->execute(['guardian_id' => $guardianId, 'player_id' => $playerId]);
        $relation = $stmt->fetch();

        return $relation ?: null;
    }

    public static function attach(int $guardianId, int $playerId, array $data): void
    {
        $pdo = Database::connection();

        $existing = self::findRelation($guardianId, $playerId);

        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE football_guardians_players
                SET relation = :relation, is_primary = :is_primary,
                    can_view_reports = :can_view_reports, can_pay = :can_pay,
                    status = :status, updated_at = NOW()
                WHERE id = :id
            ');

            $stmt->execute([
                'relation' => $data['relation'] ?? 'other',
                'is_primary' => !empty($data['is_primary']) ? 1 : 0,
                'can_view_reports' => !empty($data['can_view_reports']) ? 1 : 0,
                'can_pay' => !empty($data['can_pay']) ? 1 : 0,
                'status' => 'active',
                'id' => $existing['id'],
            ]);

            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO football_guardians_players (
                guardian_id, player_id, relation, is_primary,
                can_view_reports, can_pay, status, created_at, updated_at
            ) VALUES (
                :guardian_id, :player_id, :relation, :is_primary,
                :can_view_reports, :can_pay, :status, NOW(), NOW()
            )
        ');

        $stmt->execute([
            'guardian_id' => $guardianId,
            'player_id' => $playerId,
            'relation' => $data['relation'] ?? 'other',
            'is_primary' => !empty($data['is_primary']) ? 1 : 0,
            'can_view_reports' => !empty($data['can_view_reports']) ? 1 : 0,
            'can_pay' => !empty($data['can_pay']) ? 1 : 0,
            'status' => 'active',
        ]);
    }

    public static function detach(int $guardianId, int $playerId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_guardians_players
            SET status = "inactive", updated_at = NOW()
            WHERE guardian_id = :guardian_id AND player_id = :player_id AND status = "active"
        ');

        $stmt->execute(['guardian_id' => $guardianId, 'player_id' => $playerId]);
    }

    public static function playersForGuardian(int $guardianId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.*, gp.relation, gp.is_primary, gp.can_view_reports, gp.can_pay,
                   gp.status AS relation_status,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_guardians_players gp
            INNER JOIN football_players p ON p.id = gp.player_id
            WHERE gp.guardian_id = :guardian_id AND gp.status = "active" AND p.deleted_at IS NULL
            ORDER BY gp.id DESC
        ');

        $stmt->execute(['guardian_id' => $guardianId]);

        return $stmt->fetchAll();
    }

    public static function guardiansForPlayer(int $playerId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT gp.player_id, g.id AS guardian_id, g.emergency_phone,
                   gp.relation, gp.is_primary, gp.can_view_reports, gp.can_pay,
                   gp.status AS relation_status,
                   g.full_name AS user_full_name, g.mobile AS user_mobile,
                   g.national_code AS user_national_code
            FROM football_guardians_players gp
            INNER JOIN football_guardians g ON g.id = gp.guardian_id
            WHERE gp.player_id = :player_id AND gp.status = "active"
            ORDER BY gp.id DESC
        ');

        $stmt->execute(['player_id' => $playerId]);

        return $stmt->fetchAll();
    }

    /**
     * سرپرستان فعال چند بازیکن — برای دکمه‌های تماس/پیام در لیست بازیکنان
     * (سرپرست اصلی اول برمی‌گردد)
     */
    public static function guardiansForPlayers(array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        $pdo = Database::connection();

        $ids = implode(',', array_map('intval', $playerIds));

        $stmt = $pdo->prepare("
            SELECT gp.player_id, gp.relation, gp.is_primary, gp.can_view_reports, gp.can_pay,
                   g.id AS guardian_id, g.emergency_phone,
                   g.full_name AS user_full_name, g.mobile AS user_mobile,
                   g.national_code AS user_national_code
            FROM football_guardians_players gp
            INNER JOIN football_guardians g ON g.id = gp.guardian_id
            WHERE gp.player_id IN ({$ids}) AND gp.status = \"active\"
            ORDER BY gp.is_primary DESC, gp.id ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll();
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
}