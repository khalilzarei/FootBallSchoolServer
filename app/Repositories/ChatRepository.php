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
            SELECT
                r.*,
                m.last_read_message_id AS my_last_read_message_id
            FROM football_chat_rooms r
            INNER JOIN football_chat_room_members m
                ON m.chat_room_id = r.id
            WHERE m.user_id = :user_id
              AND m.status = "active"
              AND r.status = "active"
            ORDER BY r.updated_at DESC, r.id DESC
        ');

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT *
            FROM football_chat_rooms
            WHERE id = :id
            LIMIT 1
        ');

        $stmt->execute([
            'id' => $id,
        ]);

        $room = $stmt->fetch();

        return $room ?: null;
    }

    public static function findByUniqueKey(
        string $uniqueKey
    ): ?array {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT *
            FROM football_chat_rooms
            WHERE unique_key = :unique_key
            LIMIT 1
        ');

        $stmt->execute([
            'unique_key' => $uniqueKey,
        ]);

        $room = $stmt->fetch();

        return $room ?: null;
    }

    /**
     * پیدا کردن اتاق خصوصی بین دو کاربر.
     *
     * ترتیب user1/user2 مهم نیست.
     */
   public static function findPrivateRoom( int $user1Id, int $user2Id ): ?array { $pdo = Database::connection(); /* * برای اتاق خصوصی جدید، این ستون‌ها باید وجود داشته باشند. * اگر migration هنوز اجرا نشده باشد، null برمی‌گردانیم. */ if ( !self::hasColumn( 'football_chat_rooms', 'is_group' ) || !self::hasColumn( 'football_chat_rooms', 'user1_id' ) || !self::hasColumn( 'football_chat_rooms', 'user2_id' ) ) { return null; } /* * هر placeholder فقط یک بار استفاده شده است. * * این کار برای جلوگیری از: * SQLSTATE[HY093]: Invalid parameter number * * انجام شده است. */ $stmt = $pdo->prepare(' SELECT * FROM football_chat_rooms WHERE is_group = 0 AND status = "active" AND ( ( user1_id = :user1_a AND user2_id = :user2_a ) OR ( user1_id = :user2_b AND user2_id = :user1_b ) ) LIMIT 1 '); $stmt->execute([ 'user1_a' => $user1Id, 'user2_a' => $user2Id, 'user2_b' => $user2Id, 'user1_b' => $user1Id, ]); $room = $stmt->fetch(); return $room ?: null; }

    /**
     * ساخت اتاق چت.
     *
     * ساختار جدید:
     *
     * Private:
     *   is_group = 0
     *   user1_id
     *   user2_id
     *
     * Group:
     *   is_group = 1
     *   title
     *   image
     *
     * ستون‌های legacy مثل:
     *   room_type
     *   player_id
     *   class_id
     *   age_group_id
     *   subject
     *
     * فعلاً نگه داشته می‌شوند.
     */
    public static function createRoom(
        array $data
    ): int {
        $pdo = Database::connection();

        $hasAgeGroup = self::hasColumn(
            'football_chat_rooms',
            'age_group_id'
        );

        $hasIsGroup = self::hasColumn(
            'football_chat_rooms',
            'is_group'
        );

        $hasUser1 = self::hasColumn(
            'football_chat_rooms',
            'user1_id'
        );

        $hasUser2 = self::hasColumn(
            'football_chat_rooms',
            'user2_id'
        );

        $hasTitle = self::hasColumn(
            'football_chat_rooms',
            'title'
        );

        $hasImage = self::hasColumn(
            'football_chat_rooms',
            'image'
        );

        $columns = [
            'room_type',
            'player_id',
            'class_id',
            'subject',
            'unique_key',
            'status',
            'created_by',
            'created_at',
        ];

        $values = [
            ':room_type',
            ':player_id',
            ':class_id',
            ':subject',
            ':unique_key',
            ':status',
            ':created_by',
            'NOW()',
        ];

        $params = [
            'room_type' => $data['room_type'] ?? null,
            'player_id' => $data['player_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'unique_key' => $data['unique_key'],
            'status' => $data['status'] ?? 'active',
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($hasAgeGroup) {
            $columns[] = 'age_group_id';
            $values[] = ':age_group_id';

            $params['age_group_id'] =
                $data['age_group_id'] ?? null;
        }

        if ($hasIsGroup) {
            $columns[] = 'is_group';
            $values[] = ':is_group';

            $params['is_group'] =
                !empty($data['is_group']) ? 1 : 0;
        }

        if ($hasUser1) {
            $columns[] = 'user1_id';
            $values[] = ':user1_id';

            $params['user1_id'] =
                $data['user1_id'] ?? null;
        }

        if ($hasUser2) {
            $columns[] = 'user2_id';
            $values[] = ':user2_id';

            $params['user2_id'] =
                $data['user2_id'] ?? null;
        }

        if ($hasTitle) {
            $columns[] = 'title';
            $values[] = ':title';

            $params['title'] =
                $data['title'] ?? null;
        }

        if ($hasImage) {
            $columns[] = 'image';
            $values[] = ':image';

            $params['image'] =
                $data['image'] ?? null;
        }

        $sql = sprintf(
            '
            INSERT INTO football_chat_rooms (
                %s
            ) VALUES (
                %s
            )
            ',
            implode(', ', $columns),
            implode(', ', $values)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $pdo->lastInsertId();
    }

    public static function isMember(
        int $roomId,
        int $userId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id
            FROM football_chat_room_members
            WHERE chat_room_id = :chat_room_id
              AND user_id = :user_id
              AND status = "active"
            LIMIT 1
        ');

        $stmt->execute([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetch();
    }

    public static function memberExists(
        int $roomId,
        int $userId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id
            FROM football_chat_room_members
            WHERE chat_room_id = :chat_room_id
              AND user_id = :user_id
            LIMIT 1
        ');

        $stmt->execute([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * اضافه کردن عضو به اتاق.
     *
     * اگر عضو قبلاً وجود داشته باشد:
     * - رکورد جدید ایجاد نمی‌شود
     * - عضو دوباره active می‌شود
     * - member_role به‌روزرسانی می‌شود
     */
    public static function addMember(
        int $roomId,
        int $userId,
        string $memberRole = 'member'
    ): void {
        $pdo = Database::connection();

        $existingStmt = $pdo->prepare('
            SELECT id, status
            FROM football_chat_room_members
            WHERE chat_room_id = :chat_room_id
              AND user_id = :user_id
            LIMIT 1
        ');

        $existingStmt->execute([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
        ]);

        $existing = $existingStmt->fetch();

        if ($existing) {
            $update = $pdo->prepare('
                UPDATE football_chat_room_members
                SET
                    member_role = :member_role,
                    status = "active",
                    updated_at = NOW()
                WHERE id = :id
            ');

            $update->execute([
                'member_role' => $memberRole,
                'id' => (int) $existing['id'],
            ]);

            return;
        }

        $stmt = $pdo->prepare('
            INSERT INTO football_chat_room_members (
                chat_room_id,
                user_id,
                member_role,
                joined_at,
                is_muted,
                status,
                created_at
            ) VALUES (
                :chat_room_id,
                :user_id,
                :member_role,
                NOW(),
                :is_muted,
                :status,
                NOW()
            )
        ');

        $stmt->execute([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
            'member_role' => $memberRole,
            'is_muted' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * اعضای اتاق.
     *
     * خروجی شامل:
     * - user_id
     * - full_name
     * - avatar_path
     * - user_role
     * - member_role
     */
    public static function members(
        int $roomId
    ): array {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT
                cm.*,
                u.full_name,
                u.avatar_path,
                u.role AS user_role
            FROM football_chat_room_members cm
            INNER JOIN football_users u
                ON u.id = cm.user_id
            WHERE cm.chat_room_id = :chat_room_id
              AND cm.status = "active"
              AND u.deleted_at IS NULL
            ORDER BY cm.id ASC
        ');

        $stmt->execute([
            'chat_room_id' => $roomId,
        ]);

        return $stmt->fetchAll();
    }

    public static function messages(
        int $roomId,
        int $limit = 50,
        int $lastReadMessageId = 0,
        ?int $before = null
    ): array {
        $pdo = Database::connection();

        $limit = max(
            1,
            min($limit, 100)
        );

        $sql = '
            SELECT
                msg.*,
                u.full_name AS sender_name,
                u.role AS sender_role
            FROM football_chat_messages msg
            INNER JOIN football_users u
                ON u.id = msg.sender_id
            WHERE msg.chat_room_id = :chat_room_id
              AND msg.deleted_at IS NULL
        ';

        $params = [
            'chat_room_id' => $roomId,
        ];

        if (
            $before !== null &&
            $before > 0
        ) {
            $sql .= '
                AND msg.id < :before
            ';

            $params['before'] = $before;
        }

        $sql .= '
            ORDER BY msg.id DESC
            LIMIT :limit
        ';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(
                ':' . $key,
                $value,
                \PDO::PARAM_INT
            );
        }

        $stmt->bindValue(
            ':limit',
            $limit,
            \PDO::PARAM_INT
        );

        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map(
            static fn(array $row): array =>
                self::hydrateMessage(
                    $row,
                    $lastReadMessageId
                ),
            array_reverse($rows)
        );
    }

    public static function messageBelongsToRoom(
        int $messageId,
        int $roomId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT id
            FROM football_chat_messages
            WHERE id = :message_id
              AND chat_room_id = :room_id
              AND deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute([
            'message_id' => $messageId,
            'room_id' => $roomId,
        ]);

        return (bool) $stmt->fetch();
    }

    public static function createMessage(
        array $data
    ): int {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_chat_messages (
                chat_room_id,
                sender_id,
                message_type,
                body,
                media_id,
                sent_at,
                created_at
            ) VALUES (
                :chat_room_id,
                :sender_id,
                :message_type,
                :body,
                :media_id,
                NOW(),
                NOW()
            )
        ');

        $stmt->execute([
            'chat_room_id' => $data['chat_room_id'],
            'sender_id' => $data['sender_id'],
            'message_type' =>
                $data['message_type'] ?? 'text',
            'body' => $data['body'] ?? null,
            'media_id' => $data['media_id'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findMessageById(
        int $id
    ): ?array {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT
                msg.*,
                u.full_name AS sender_name,
                u.role AS sender_role
            FROM football_chat_messages msg
            INNER JOIN football_users u
                ON u.id = msg.sender_id
            WHERE msg.id = :id
              AND msg.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute([
            'id' => $id,
        ]);

        $message = $stmt->fetch();

        return $message
            ? self::hydrateMessage($message, 0)
            : null;
    }

    /**
     * آخرین پیام هر اتاق.
     *
     * خروجی:
     * [
     *     room_id => message
     * ]
     */
    public static function lastMessagesForRooms(
        array $roomIds
    ): array {
        if (empty($roomIds)) {
            return [];
        }

        $pdo = Database::connection();

        $ids = implode(
            ',',
            array_map('intval', $roomIds)
        );

        $stmt = $pdo->prepare("
            SELECT
                msg.*,
                u.full_name AS sender_name,
                u.role AS sender_role
            FROM football_chat_messages msg
            INNER JOIN (
                SELECT
                    chat_room_id,
                    MAX(id) AS max_id
                FROM football_chat_messages
                WHERE chat_room_id IN ({$ids})
                  AND deleted_at IS NULL
                GROUP BY chat_room_id
            ) latest
                ON latest.max_id = msg.id
            INNER JOIN football_users u
                ON u.id = msg.sender_id
        ");

        $stmt->execute();

        $out = [];

        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['chat_room_id']] =
                self::hydrateMessage($row, 0);
        }

        return $out;
    }

    /**
     * تعداد پیام‌های خوانده نشده هر اتاق.
     */
    public static function unreadCountsByRoom(
        int $userId,
        array $roomIds
    ): array {
        if (empty($roomIds)) {
            return [];
        }

        $pdo = Database::connection();

        $ids = implode(
            ',',
            array_map('intval', $roomIds)
        );

        $stmt = $pdo->prepare("
            SELECT
                m.chat_room_id,
                COUNT(*) AS cnt
            FROM football_chat_messages m
            INNER JOIN football_chat_room_members mem
                ON mem.chat_room_id = m.chat_room_id
                AND mem.user_id = :user_id
                AND mem.status = 'active'
            WHERE m.chat_room_id IN ({$ids})
              AND m.deleted_at IS NULL
              AND m.sender_id <> :other_user_id
              AND m.id > mem.last_read_message_id
            GROUP BY m.chat_room_id
        ");

        $stmt->execute([
            'user_id' => $userId,
            'other_user_id' => $userId,
        ]);

        $out = [];

        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['chat_room_id']] =
                (int) $row['cnt'];
        }

        return $out;
    }

    public static function getLastReadMessageId(
        int $roomId,
        int $userId
    ): int {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT last_read_message_id
            FROM football_chat_room_members
            WHERE chat_room_id = :chat_room_id
              AND user_id = :user_id
              AND status = "active"
            LIMIT 1
        ');

        $stmt->execute([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();

        return $row
            ? (int) $row['last_read_message_id']
            : 0;
    }

    /**
     * تبدیل پیام دیتابیس به ساختار API.
     */
    private static function hydrateMessage(
        array $row,
        int $lastReadMessageId
    ): array {
        $id = (int) $row['id'];

        return [
            'id' => $id,

            'room_id' => (int) $row['chat_room_id'],

            'sender_id' => (int) $row['sender_id'],

            'sender' => [
                'id' => (int) $row['sender_id'],

                'full_name' => (string) (
                    $row['sender_name'] ?? ''
                ),

                'role' => (string) (
                    $row['sender_role'] ?? ''
                ),

                'status' => 'active',
            ],

            'message_type' => (string) (
                $row['message_type'] ?? 'text'
            ),

            'body' => $row['body'] ?? null,

            'media_id' =>
                $row['media_id'] !== null
                    ? (int) $row['media_id']
                    : null,

            'media' => null,

            'is_read' =>
                $lastReadMessageId > 0 &&
                $id <= $lastReadMessageId,

            'read_at' => null,

            'sent_at' => $row['sent_at'] ?? null,

            'created_at' => $row['created_at'] ?? null,
        ];
    }

    public static function updateLastRead(
        int $roomId,
        int $userId,
        int $lastReadMessageId
    ): void {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_chat_room_members
            SET
                last_read_message_id = GREATEST(
                    COALESCE(last_read_message_id, 0),
                    :last_read_message_id
                ),
                updated_at = NOW()
            WHERE chat_room_id = :chat_room_id
              AND user_id = :user_id
              AND status = "active"
        ');

        $stmt->execute([
            'last_read_message_id' =>
                $lastReadMessageId,

            'chat_room_id' =>
                $roomId,

            'user_id' =>
                $userId,
        ]);
    }

    /**
     * تکمیل player_id اتاقی که قبلاً بدون بازیکن ساخته شده.
     */
    public static function updateRoomPlayer(
        int $roomId,
        int $playerId
    ): void {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_chat_rooms
            SET
                player_id = :player_id,
                updated_at = NOW()
            WHERE id = :id
              AND player_id IS NULL
        ');

        $stmt->execute([
            'player_id' => $playerId,
            'id' => $roomId,
        ]);
    }

    /**
     * قفل / باز کردن گفتگو.
     */
    public static function setRoomLocked(
        int $roomId,
        bool $locked
    ): void {
        $pdo = Database::connection();

        if (
            !self::hasColumn(
                'football_chat_rooms',
                'is_locked'
            )
        ) {
            error_log(
                '[ChatRepository] setRoomLocked called but '
                . 'is_locked column is missing.'
            );

            return;
        }

        $stmt = $pdo->prepare('
            UPDATE football_chat_rooms
            SET
                is_locked = :is_locked,
                updated_at = NOW()
            WHERE id = :id
        ');

        $stmt->execute([
            'is_locked' => $locked ? 1 : 0,
            'id' => $roomId,
        ]);
    }

    /**
     * همه اتاق‌های گروهی گروه‌های سنی.
     *
     * برای compatibility داده‌های قدیمی نگه داشته شده.
     */
    public static function ageGroupRooms(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT *
            FROM football_chat_rooms
            WHERE room_type = "age_group"
              AND status = "active"
        ');

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * بررسی وجود یک ستون در جدول.
     */
    private static array $columnCache = [];

    public static function hasColumn(
        string $table,
        string $column
    ): bool {
        $key = $table . '.' . $column;

        if (array_key_exists(
            $key,
            self::$columnCache
        )) {
            return self::$columnCache[$key];
        }

        try {
            $pdo = Database::connection();

            $stmt = $pdo->prepare('
                SELECT COUNT(*) AS cnt
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
            ');

            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);

            $row = $stmt->fetch();

            self::$columnCache[$key] =
                ((int) ($row['cnt'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            self::$columnCache[$key] = false;
        }

        return self::$columnCache[$key];
    }

    /**
     * آیا کاربر به چت گروه سنی دسترسی دارد؟
     */
    public static function userEligibleForAgeGroup(
        array $user,
        int $ageGroupId
    ): bool {
        if ($ageGroupId <= 0) {
            return false;
        }

        $role = (string) (
            $user['role'] ?? ''
        );

        $userId = (int) (
            $user['id'] ?? 0
        );

        if ($userId <= 0) {
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'coach') {
            return self::coachInAgeGroup(
                $userId,
                $ageGroupId
            );
        }

        if ($role === 'player') {
            return self::playerInAgeGroup(
                $userId,
                $ageGroupId
            );
        }

        return false;
    }

    /**
     * مربی اصلی یا کمکی کلاس فعال گروه سنی.
     */
    private static function coachInAgeGroup(
        int $coachUserId,
        int $ageGroupId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT c.id
            FROM football_classes c

            LEFT JOIN football_coaches coach
                ON coach.id = c.coach_id

            LEFT JOIN football_users coach_user
                ON coach_user.id = coach.user_id

            LEFT JOIN football_coaches assistant
                ON assistant.id = c.assistant_coach_id

            LEFT JOIN football_users assistant_user
                ON assistant_user.id = assistant.user_id

            WHERE c.age_group_id = :age_group_id
              AND c.deleted_at IS NULL
              AND c.status = "active"
              AND (
                    coach_user.id = :uid
                    OR assistant_user.id = :uid2
              )
            LIMIT 1
        ');

        $stmt->execute([
            'age_group_id' => $ageGroupId,
            'uid' => $coachUserId,
            'uid2' => $coachUserId,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * بازیکن ثبت‌نام‌شده در کلاس فعال گروه سنی.
     */
    private static function playerInAgeGroup(
        int $playerUserId,
        int $ageGroupId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT c.id
            FROM football_players p

            INNER JOIN football_enrollments e
                ON e.player_id = p.id
                AND e.status = "active"
                AND e.ended_at IS NULL

            INNER JOIN football_classes c
                ON c.id = e.class_id
                AND c.deleted_at IS NULL
                AND c.status = "active"

            WHERE p.user_id = :player_user_id
              AND p.deleted_at IS NULL
              AND c.age_group_id = :age_group_id
            LIMIT 1
        ');

        $stmt->execute([
            'player_user_id' => $playerUserId,
            'age_group_id' => $ageGroupId,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * آیا بازیکن می‌تواند با مربی خودش گفتگو کند؟
     */
    public static function playerCanChatCoach(
        int $playerUserId,
        int $coachUserId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT coach_user.id
            FROM football_players p

            INNER JOIN football_enrollments e
                ON e.player_id = p.id
                AND e.status = "active"
                AND e.ended_at IS NULL

            INNER JOIN football_classes c
                ON c.id = e.class_id
                AND c.deleted_at IS NULL
                AND c.status = "active"

            LEFT JOIN football_coaches coach
                ON coach.id = c.coach_id

            LEFT JOIN football_users coach_user
                ON coach_user.id = coach.user_id

            LEFT JOIN football_coaches assistant_coach
                ON assistant_coach.id =
                    c.assistant_coach_id

            LEFT JOIN football_users assistant_coach_user
                ON assistant_coach_user.id =
                    assistant_coach.user_id

            WHERE p.user_id = :player_user_id
              AND p.deleted_at IS NULL
              AND (
                    coach_user.id = :coach_user_id
                    OR assistant_coach_user.id =
                        :coach_user_id_2
              )
            LIMIT 1
        ');

        $stmt->execute([
            'player_user_id' => $playerUserId,
            'coach_user_id' => $coachUserId,
            'coach_user_id_2' => $coachUserId,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * آیا مربی می‌تواند با بازیکن خودش گفتگو کند؟
     */
    public static function coachCanChatPlayer(
        int $coachUserId,
        int $playerUserId
    ): bool {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT player_user.id
            FROM football_coaches coach

            INNER JOIN football_classes c
                ON (
                    c.coach_id = coach.id
                    OR c.assistant_coach_id = coach.id
                )
                AND c.deleted_at IS NULL
                AND c.status = "active"

            INNER JOIN football_enrollments e
                ON e.class_id = c.id
                AND e.status = "active"
                AND e.ended_at IS NULL

            INNER JOIN football_players p
                ON p.id = e.player_id
                AND p.deleted_at IS NULL
                AND p.user_id IS NOT NULL

            INNER JOIN football_users player_user
                ON player_user.id = p.user_id

            WHERE coach.user_id = :coach_user_id
              AND player_user.id = :player_user_id
            LIMIT 1
        ');

        $stmt->execute([
            'coach_user_id' => $coachUserId,
            'player_user_id' => $playerUserId,
        ]);

        return (bool) $stmt->fetch();
    }
}