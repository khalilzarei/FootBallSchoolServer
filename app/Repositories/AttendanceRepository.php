<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class AttendanceRepository
{
    public static function listForSession(int $sessionId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT a.*, p.first_name, p.last_name, p.birth_date,
                   TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_attendances a
            INNER JOIN football_players p ON p.id = a.player_id
            WHERE a.session_id = :session_id AND p.deleted_at IS NULL
            ORDER BY p.first_name ASC
        ');

        $stmt->execute(['session_id' => $sessionId]);

        return $stmt->fetchAll();
    }

    public static function find(int $sessionId, int $playerId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT * FROM football_attendances
            WHERE session_id = :session_id AND player_id = :player_id
            LIMIT 1
        ');

        $stmt->execute(['session_id' => $sessionId, 'player_id' => $playerId]);
        $attendance = $stmt->fetch();

        return $attendance ?: null;
    }

    public static function upsert(int $sessionId, int $playerId, array $data, int $recordedBy): void
    {
        $pdo = Database::connection();

        $existing = self::find($sessionId, $playerId);

        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE football_attendances
                SET status = :status, is_billable = :is_billable, note = :note,
                    recorded_by = :recorded_by, recorded_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ');

            $stmt->execute([
                'status' => $data['status'],
                'is_billable' => $data['is_billable'] ?? 1,
                'note' => $data['note'] ?? null,
                'recorded_by' => $recordedBy,
                'id' => $existing['id'],
            ]);

            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO football_attendances (
                session_id, player_id, status, is_billable, note,
                recorded_by, recorded_at, updated_at
            ) VALUES (
                :session_id, :player_id, :status, :is_billable, :note,
                :recorded_by, NOW(), NOW()
            )
        ');

        $stmt->execute([
            'session_id' => $sessionId,
            'player_id' => $playerId,
            'status' => $data['status'],
            'is_billable' => $data['is_billable'] ?? 1,
            'note' => $data['note'] ?? null,
            'recorded_by' => $recordedBy,
        ]);
    }

    public static function playerAttendances(int $playerId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT a.*, s.session_date, s.start_time, s.end_time,
                   s.status AS session_status, c.title AS class_title
            FROM football_attendances a
            INNER JOIN football_sessions s ON s.id = a.session_id
            INNER JOIN football_classes c ON c.id = s.class_id
            WHERE a.player_id = :player_id
            ORDER BY s.session_date DESC
        ');

        $stmt->execute(['player_id' => $playerId]);

        return $stmt->fetchAll();
    }
}