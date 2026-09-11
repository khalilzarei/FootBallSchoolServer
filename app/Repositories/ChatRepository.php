<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ChatRepository
{
    public static function roomsForUser(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT r.*, m.last_read_message_id AS my_last_read_message_id
            FROM football_chat_rooms r
            INNER JOIN football_chat_room_members m ON m.chat_room_id = r.id
            WHERE m.user_id = :user_id AND m.status = "active" AND r.status = "active"
            ORDER BY r.id DESC
        ');

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_chat_rooms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $room = $stmt->fetch();

        return $room ?: null;
    }

    public static function findByUniqueKey(string $uniqueKey): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_chat_rooms WHERE unique_key = :unique_key LIMIT 1');
        $stmt->execute(['unique_key' => $uniqueKey]);
        $room = $stmt->fetch();

        return $room ?: null;
    }

    public static function createRoom(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_chat_rooms (
                room_type, player_id, class_id, subject, unique_key, status, created_by, created_at
            ) VALUES (
                :room_type, :player_id, :class_id, :subject, :unique_key, :status, :created_by, NOW()
            )
        ');

        $stmt->execute([
            'room_type' => $data['room_type'],
            'player_id' => $data['player_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'unique_key' => $data['unique_key'],
            'status' => $data['status'] ?? 'active',
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function isMember(int $roomId, int $userId): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id FROM football_chat_room_members
            WHERE chat_room_id = :chat_room_id AND user_id = :user_id AND status = "active"
            LIMIT 1
        ');

        $stmt->execute(['chat_room_id' => $roomId, 'user_id' => $userId]);

        return (bool) $stmt->fetch();
    }

    public static function memberExists(int $roomId, int $userId): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id FROM football_chat_room_members
            WHERE chat_room_id = :chat_room_id AND user_id = :user_id
            LIMIT 1
        ');

        $stmt->execute(['chat_room_id' => $roomId, 'user_id' => $userId]);

        return (bool) $stmt->fetch();
    }

    public static function addMember(int $roomId, int $userId, string $memberRole = 'member'): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_chat_room_members (
                chat_room_id, user_id, member_role, joined_at, is_muted, status, created_at
            ) VALUES (
                :chat_room_id, :user_id, :member_role, NOW(), 0, "active", NOW()
            )
        ');

        $stmt->execute([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
            'member_role' => $memberRole,
        ]);
    }

    public static function members(int $roomId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT cm.*, u.full_name, u.role AS user_role
            FROM football_chat_room_members cm
            INNER JOIN football_users u ON u.id = cm.user_id
            WHERE cm.chat_room_id = :chat_room_id AND cm.status = "active" AND u.deleted_at IS NULL
            ORDER BY cm.id ASC
        ');

        $stmt->execute(['chat_room_id' => $roomId]);

        return $stmt->fetchAll();
    }

    public static function messages(int $roomId, int $limit = 50): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT msg.*, u.full_name AS sender_name, u.role AS sender_role
            FROM football_chat_messages msg
            INNER JOIN football_users u ON u.id = msg.sender_id
            WHERE msg.chat_room_id = :chat_room_id AND msg.deleted_at IS NULL
            ORDER BY msg.id DESC
            LIMIT :limit
        ');

        $stmt->bindValue(':chat_room_id', $roomId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll());
    }

    public static function createMessage(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_chat_messages (
                chat_room_id, sender_id, message_type, body, media_id, sent_at, created_at
            ) VALUES (
                :chat_room_id, :sender_id, :message_type, :body, :media_id, NOW(), NOW()
            )
        ');

        $stmt->execute([
            'chat_room_id' => $data['chat_room_id'],
            'sender_id' => $data['sender_id'],
            'message_type' => $data['message_type'] ?? 'text',
            'body' => $data['body'] ?? null,
            'media_id' => $data['media_id'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findMessageById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT msg.*, u.full_name AS sender_name, u.role AS sender_role
            FROM football_chat_messages msg
            INNER JOIN football_users u ON u.id = msg.sender_id
            WHERE msg.id = :id AND msg.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $message = $stmt->fetch();

        return $message ?: null;
    }

    public static function updateLastRead(int $roomId, int $userId, int $lastReadMessageId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_chat_room_members
            SET last_read_message_id = :last_read_message_id, updated_at = NOW()
            WHERE chat_room_id = :chat_room_id AND user_id = :user_id AND status = "active"
        ');

        $stmt->execute([
            'last_read_message_id' => $lastReadMessageId,
            'chat_room_id' => $roomId,
            'user_id' => $userId,
        ]);
    }

    public static function guardianCanChatCoach(int $guardianUserId, int $coachUserId): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT coach_user.id
            FROM football_guardians g
            INNER JOIN football_guardians_players gp ON gp.guardian_id = g.id AND gp.status = "active"
            INNER JOIN football_enrollments e ON e.player_id = gp.player_id AND e.status = "active"
            INNER JOIN football_classes c ON c.id = e.class_id AND c.deleted_at IS NULL AND c.status = "active"
            LEFT JOIN football_coaches coach ON coach.id = c.coach_id
            LEFT JOIN football_users coach_user ON coach_user.id = coach.user_id
            LEFT JOIN football_coaches assistant_coach ON assistant_coach.id = c.assistant_coach_id
            LEFT JOIN football_users assistant_coach_user ON assistant_coach_user.id = assistant_coach.user_id
            WHERE g.user_id = :guardian_user_id
              AND (coach_user.id = :coach_user_id OR assistant_coach_user.id = :coach_user_id_2)
            LIMIT 1
        ');

        $stmt->execute([
            'guardian_user_id' => $guardianUserId,
            'coach_user_id' => $coachUserId,
            'coach_user_id_2' => $coachUserId,
        ]);

        return (bool) $stmt->fetch();
    }

    public static function coachCanChatGuardian(int $coachUserId, int $guardianUserId): bool
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT guardian_user.id
            FROM football_coaches coach
            INNER JOIN football_classes c ON (c.coach_id = coach.id OR c.assistant_coach_id = coach.id)
                AND c.deleted_at IS NULL AND c.status = "active"
            INNER JOIN football_enrollments e ON e.class_id = c.id AND e.status = "active"
            INNER JOIN football_guardians_players gp ON gp.player_id = e.player_id AND gp.status = "active"
            INNER JOIN football_guardians g ON g.id = gp.guardian_id
            INNER JOIN football_users guardian_user ON guardian_user.id = g.user_id
            WHERE coach.user_id = :coach_user_id AND guardian_user.id = :guardian_user_id
            LIMIT 1
        ');

        $stmt->execute([
            'coach_user_id' => $coachUserId,
            'guardian_user_id' => $guardianUserId,
        ]);

        return (bool) $stmt->fetch();
    }
}