<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Config;
use App\Repositories\AgeGroupRepository;
use App\Repositories\ChatRepository;
use App\Repositories\ClassRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\UserRepository;

class ChatService
{
    private const MESSAGE_TYPES = ['text', 'image', 'video', 'file', 'system'];

    private const DEFAULT_GROUP_IMAGE = '/uploads/chat/default-group.webp';

    public static function rooms(): array
    {
        $currentUserId = (int) Auth::id();

        $rooms = ChatRepository::roomsForUser($currentUserId);
        $memberRoomIds = array_map(
            fn($room) => (int) $room['id'],
            $rooms
        );

        /*
         * عضویت خودکار در روم‌های گروهی (گروه سنی و کلاس)
         * برای کاربران واجد شرایط.
         */
        $currentUser = Auth::user();

        foreach (ChatRepository::groupRooms() as $groupRoom) {
            $roomId = (int) $groupRoom['id'];

            if (in_array($roomId, $memberRoomIds, true)) {
                continue;
            }

            $ageGroupId = (int) ($groupRoom['age_group_id'] ?? 0);
            $classId = (int) ($groupRoom['class_id'] ?? 0);

            $eligible = false;

            if ($ageGroupId > 0) {
                $eligible = ChatRepository::userEligibleForAgeGroup(
                    $currentUser,
                    $ageGroupId
                );
            } elseif ($classId > 0) {
                $eligible = ChatRepository::userEligibleForClass(
                    $currentUser,
                    $classId
                );
            }

            if (!$eligible) {
                continue;
            }

            ChatRepository::addMember(
                $roomId,
                $currentUserId,
                'member'
            );

            $rooms[] = $groupRoom;
            $memberRoomIds[] = $roomId;
        }

        if (empty($rooms)) {
            return [];
        }

        $roomIds = array_map(
            fn($room) => (int) $room['id'],
            $rooms
        );

        $lastMessages = ChatRepository::lastMessagesForRooms($roomIds);

        $unreadCounts = ChatRepository::unreadCountsByRoom(
            $currentUserId,
            $roomIds
        );

        $result = [];

        foreach ($rooms as $room) {
            $roomId = (int) $room['id'];

            $result[] = self::hydrateRoom(
                $room,
                ChatRepository::members($roomId),
                $lastMessages[$roomId] ?? null,
                $unreadCounts[$roomId] ?? 0
            );
        }

        return $result;
    }

    /**
     * ساخت یا دریافت:
     *
     * Private:
     * {
     *   "target_user_id": 25
     * }
     *
     * Group:
     * {
     *   "is_group": true,
     *   "title": "نونهالان",
     *   "user_ids": [10, 25, 31]
     * }
     */
    public static function createRoom(array $data): array
    {
        $currentUserId = (int) Auth::id();
        $currentUser = Auth::user();

        /*
         * سازگاری با درخواست قدیمی age_group
         */
        $isLegacyAgeGroup =
            (string) ($data['room_type'] ?? '') === 'age_group';

        $isGroup =
            filter_var(
                $data['is_group'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ) || $isLegacyAgeGroup;

        if ($isGroup) {
            return self::createGroupRoom(
                $data,
                $currentUserId,
                $currentUser
            );
        }

        return self::createPrivateRoom(
            $data,
            $currentUserId,
            $currentUser
        );
    }

    /**
     * ساخت یا دریافت اتاق خصوصی دو نفره
     */
    private static function createPrivateRoom(
        array $data,
        int $currentUserId,
        array $currentUser
    ): array {
        $targetUserId = self::normalizeOptionalInt(
            $data['target_user_id'] ?? null
        );

        if ($targetUserId === null) {
            throw new AppException(
                'شناسه کاربر مقابل الزامی است',
                422
            );
        }

        if ($targetUserId === $currentUserId) {
            throw new AppException(
                'کاربر نمی‌تواند با خودش اتاق چت بسازد',
                422
            );
        }

        $targetUser = UserRepository::findById($targetUserId);

        if (!$targetUser || (string) ($targetUser['status'] ?? '') !== 'active') {
            throw new AppException(
                'کاربر مقابل یافت نشد یا فعال نیست',
                404
            );
        }

        /*
         * بررسی ارتباط مجاز بین نقش‌ها.
         *
         * دیگر room_type از سمت کلاینت لازم نیست.
         * نوع ارتباط فقط برای authorization استفاده می‌شود.
         */
        self::assertPrivateRoomPermission(
            $currentUser,
            $targetUser
        );

        $playerId = self::normalizeOptionalInt(
            $data['player_id'] ?? null
        );

        $classId = self::normalizeOptionalInt(
            $data['class_id'] ?? null
        );

        if (
            $playerId !== null &&
            !PlayerRepository::findById($playerId)
        ) {
            throw new AppException(
                'بازیکن یافت نشد',
                404
            );
        }

        /*
         * پیدا کردن اتاق خصوصی مستقل از ترتیب user1/user2
         */
        $existingRoom = ChatRepository::findPrivateRoom(
            $currentUserId,
            $targetUserId
        );

        if ($existingRoom) {
            $existingRoomId = (int) $existingRoom['id'];

            if (
                !ChatRepository::memberExists(
                    $existingRoomId,
                    $currentUserId
                )
            ) {
                ChatRepository::addMember(
                    $existingRoomId,
                    $currentUserId,
                    'owner'
                );
            }

            if (
                !ChatRepository::memberExists(
                    $existingRoomId,
                    $targetUserId
                )
            ) {
                ChatRepository::addMember(
                    $existingRoomId,
                    $targetUserId,
                    'member'
                );
            }

            /*
             * اگر اتاق از پروفایل بازیکن ایجاد شده باشد،
             * player_id در صورت نیاز تکمیل می‌شود.
             */
            if (
                $playerId !== null &&
                isset($existingRoom['player_id']) &&
                $existingRoom['player_id'] === null
            ) {
                ChatRepository::updateRoomPlayer(
                    $existingRoomId,
                    $playerId
                );
            }

            return self::roomDetails($existingRoomId);
        }

        /*
         * user1/user2 فقط برای identity داخلی دیتابیس هستند.
         * API از users[] استفاده می‌کند.
         */
        $userIds = [$currentUserId, $targetUserId];
        sort($userIds);

        $uniqueKey = hash(
            'sha256',
            'private-' . implode('-', $userIds)
        );

        /*
         * برای سازگاری با دیتابیس‌های قدیمی،
         * room_type همچنان مقدار private می‌گیرد.
         */
        $roomId = ChatRepository::createRoom([
            'room_type' => 'private',
            'is_group' => false,

            'user1_id' => $userIds[0],
            'user2_id' => $userIds[1],

            'player_id' => $playerId,
            'class_id' => $classId,

            'title' => null,
            'image' => null,

            'subject' => trim(
                (string) ($data['subject'] ?? '')
            ) ?: null,

            'unique_key' => $uniqueKey,
            'status' => 'active',
            'created_by' => $currentUserId,
        ]);

        ChatRepository::addMember(
            $roomId,
            $currentUserId,
            'owner'
        );

        ChatRepository::addMember(
            $roomId,
            $targetUserId,
            'member'
        );

        return self::roomDetails($roomId);
    }

    /**
     * ساخت گروه
     */
    private static function createGroupRoom(
        array $data,
        int $currentUserId,
        array $currentUser
    ): array {
        if ((string) ($currentUser['role'] ?? '') !== 'admin') {
            throw new AppException(
                'فقط مدیر می‌تواند گروه گفتگو ایجاد کند',
                403
            );
        }

        /*
         * پشتیبانی از گروه‌های سنی قدیمی
         */
        $ageGroupId = self::normalizeOptionalInt(
            $data['age_group_id'] ?? null
        );

        $isAgeGroup =
            $ageGroupId !== null ||
            (string) ($data['room_type'] ?? '') === 'age_group';

        if ($isAgeGroup) {
            if ($ageGroupId === null) {
                throw new AppException(
                    'شناسه گروه سنی الزامی است',
                    422
                );
            }

            $ageGroup = AgeGroupRepository::findById($ageGroupId);

            if (!$ageGroup) {
                throw new AppException(
                    'گروه سنی یافت نشد',
                    404
                );
            }
        }

        /*
         * عنوان گروه
         */
        $title = trim(
            (string) ($data['title'] ?? '')
        );

        if ($title === '' && $isAgeGroup) {
            $title = trim(
                (string) ($ageGroup['title'] ?? '')
            );
        }

        if ($title === '') {
            throw new AppException(
                'عنوان گروه الزامی است',
                422
            );
        }

        /*
         * تصویر گروه
         */
        $image = trim(
            (string) ($data['image'] ?? '')
        );

        if ($image === '') {
            $image = self::DEFAULT_GROUP_IMAGE;
        }

        /*
         * اعضای گروه
         */
        $requestedUserIds = $data['user_ids'] ?? [];

        if (!is_array($requestedUserIds)) {
            throw new AppException(
                'user_ids باید آرایه باشد',
                422
            );
        }

        $userIds = [];

        foreach ($requestedUserIds as $userId) {
            $normalizedUserId = self::normalizeOptionalInt($userId);

            if ($normalizedUserId === null) {
                continue;
            }

            $userIds[] = $normalizedUserId;
        }

        /*
         * مدیر سازنده همیشه عضو گروه است.
         */
        $userIds[] = $currentUserId;

        $userIds = array_values(
            array_unique($userIds)
        );

        /*
         * بررسی وجود و فعال بودن تمام کاربران
         */
        foreach ($userIds as $userId) {
            $user = UserRepository::findById($userId);

            if (!$user || (string) ($user['status'] ?? '') !== 'active') {
                throw new AppException(
                    'یکی از کاربران انتخاب‌شده یافت نشد یا فعال نیست',
                    404
                );
            }
        }

        /*
         * برای گروه سنی، فقط یک گروه برای هر age_group_id وجود دارد.
         *
         * برای گروه معمولی، title + اعضا identity گروه را می‌سازد.
         */
        if ($isAgeGroup) {
            $uniqueKey = hash(
                'sha256',
                'age_group-' . $ageGroupId
            );
        } else {
            $uniqueIds = $userIds;
            sort($uniqueIds);

            $uniqueKey = hash(
                'sha256',
                'group-' .
                md5(implode(',', $uniqueIds)) .
                '-' .
                md5(mb_strtolower($title))
            );
        }

        $existingRoom = ChatRepository::findByUniqueKey(
            $uniqueKey
        );

        if ($existingRoom) {
            $roomId = (int) $existingRoom['id'];

            /*
             * اعضای جدید را به گروه اضافه می‌کنیم.
             */
            foreach ($userIds as $userId) {
                if (!ChatRepository::memberExists($roomId, $userId)) {
                    ChatRepository::addMember(
                        $roomId,
                        $userId,
                        $userId === $currentUserId
                            ? 'owner'
                            : 'member'
                    );
                }
            }

            /*
             * اگر گروه سنی قبلاً ساخته شده باشد،
             * همان اتاق استفاده می‌شود.
             */
            return self::roomDetails($roomId);
        }

        /*
         * room_type برای compatibility داخلی نگه داشته می‌شود.
         * API دیگر به room_type وابسته نیست.
         */
        $roomType = $isAgeGroup
            ? 'age_group'
            : 'group';

        $roomId = ChatRepository::createRoom([
            'room_type' => $roomType,
            'is_group' => true,

            'user1_id' => null,
            'user2_id' => null,

            'title' => $title,
            'image' => $image,

            'player_id' => null,
            'class_id' => self::normalizeOptionalInt(
                $data['class_id'] ?? null
            ),
            'age_group_id' => $ageGroupId,

            'subject' => trim(
                (string) ($data['subject'] ?? '')
            ) ?: null,

            'unique_key' => $uniqueKey,
            'status' => 'active',
            'created_by' => $currentUserId,
        ]);

        foreach ($userIds as $userId) {
            ChatRepository::addMember(
                $roomId,
                $userId,
                $userId === $currentUserId
                    ? 'owner'
                    : 'member'
            );
        }

        return self::roomDetails($roomId);
    }

    public static function roomDetails(int $roomId): array
    {
        $room = self::requireRoom($roomId);

        $currentUserId = (int) Auth::id();

        /*
         * گروه سنی:
         * اگر کاربر واجد شرایط باشد ولی هنوز عضو نشده باشد،
         * به صورت خودکار عضو می‌شود.
         */
        $isGroup = self::isGroupRoom($room);

        $ageGroupId = self::normalizeOptionalInt(
            $room['age_group_id'] ?? null
        );

        $classId = self::normalizeOptionalInt(
            $room['class_id'] ?? null
        );

        if (
            $isGroup &&
            !ChatRepository::isMember($roomId, $currentUserId)
        ) {
            $eligible = false;

            if ($ageGroupId !== null) {
                $eligible = ChatRepository::userEligibleForAgeGroup(
                    Auth::user(),
                    $ageGroupId
                );
            } elseif ($classId !== null) {
                $eligible = ChatRepository::userEligibleForClass(
                    Auth::user(),
                    $classId
                );
            }

            if ($eligible) {
                ChatRepository::addMember(
                    $roomId,
                    $currentUserId,
                    'member'
                );
            }
        }

        /*
         * فقط اعضای گروه می‌توانند جزئیات و پیام‌ها را ببینند.
         *
         * استثنا: گروه سنی ممکن است به صورت lazy عضو کاربر واجد
         * شرایط شود. بعد از آن بررسی می‌کنیم.
         */
        if (!ChatRepository::isMember($roomId, $currentUserId)) {
            throw new AppException(
                'شما عضو این اتاق چت نیستید',
                403
            );
        }

        $lastMessages = ChatRepository::lastMessagesForRooms([
            $roomId
        ]);

        $unreadCounts = ChatRepository::unreadCountsByRoom(
            $currentUserId,
            [$roomId]
        );

        return self::hydrateRoom(
            $room,
            ChatRepository::members($roomId),
            $lastMessages[$roomId] ?? null,
            $unreadCounts[$roomId] ?? 0
        );
    }

    public static function messages(
        int $roomId,
        array $query
    ): array {
        self::requireRoom($roomId);

        $userId = (int) Auth::id();

        if (!ChatRepository::isMember($roomId, $userId)) {
            throw new AppException(
                'شما عضو این اتاق چت نیستید',
                403
            );
        }

        $limit = (int) ($query['limit'] ?? 50);

        if ($limit < 1) {
            $limit = 50;
        }

        if ($limit > 100) {
            $limit = 100;
        }

        $before = null;

        if (
            isset($query['before']) &&
            $query['before'] !== '' &&
            filter_var(
                $query['before'],
                FILTER_VALIDATE_INT
            ) !== false
        ) {
            $before = (int) $query['before'];

            if ($before <= 0) {
                $before = null;
            }
        }

        $lastReadMessageId =
            ChatRepository::getLastReadMessageId(
                $roomId,
                $userId
            );

        return ChatRepository::messages(
            roomId: $roomId,
            limit: $limit,
            lastReadMessageId: $lastReadMessageId,
            before: $before
        );
    }

    public static function sendMessage(
        int $roomId,
        array $data
    ): array {
        $room = self::requireRoom($roomId);

        $currentUserId = (int) Auth::id();

        if (!ChatRepository::isMember($roomId, $currentUserId)) {
            throw new AppException(
                'شما عضو این اتاق چت نیستید',
                403
            );
        }

        /*
         * اگر گفتگو قفل باشد، فقط ادمین می‌تواند پیام بفرستد.
         */
        if (
            (int) ($room['is_locked'] ?? 0) === 1 &&
            (string) Auth::role() !== 'admin'
        ) {
            throw new AppException(
                'این گفتگو توسط مدیر قفل شده است',
                403
            );
        }

        $messageType = (string) (
            $data['message_type'] ?? 'text'
        );

        if (!in_array(
            $messageType,
            self::MESSAGE_TYPES,
            true
        )) {
            throw new AppException(
                'نوع پیام معتبر نیست',
                422
            );
        }

        $body = trim(
            (string) ($data['body'] ?? '')
        );

        if (
            $messageType === 'text' &&
            $body === ''
        ) {
            throw new AppException(
                'متن پیام نمی‌تواند خالی باشد',
                422
            );
        }

        $mediaId = self::normalizeOptionalInt(
            $data['media_id'] ?? null
        );

        $messageId = ChatRepository::createMessage([
            'chat_room_id' => $roomId,
            'sender_id' => $currentUserId,
            'message_type' => $messageType,
            'body' => $body !== '' ? $body : null,
            'media_id' => $mediaId,
        ]);

        /*
         * ثبت آخرین فعالیت اتاق تا در لیست گفتگوها
         * بر اساس آخرین پیام مرتب شود.
         */
        ChatRepository::touchRoom($roomId);

        $message = ChatRepository::findMessageById(
            $messageId
        );

        self::broadcastMessage(
            $roomId,
            $message
        );

        return $message;
    }

    public static function read(
        int $roomId,
        array $data
    ): array {
        self::requireRoom($roomId);

        $userId = (int) Auth::id();

        if (!ChatRepository::isMember($roomId, $userId)) {
            throw new AppException(
                'شما عضو این اتاق چت نیستید',
                403
            );
        }

        $lastReadMessageId = (int) (
            $data['last_read_message_id'] ?? 0
        );

        if ($lastReadMessageId <= 0) {
            throw new AppException(
                'شناسه آخرین پیام معتبر نیست',
                422
            );
        }

        if (
            !ChatRepository::messageBelongsToRoom(
                $lastReadMessageId,
                $roomId
            )
        ) {
            throw new AppException(
                'پیام متعلق به این گفتگو نیست',
                422
            );
        }

        ChatRepository::updateLastRead(
            $roomId,
            $userId,
            $lastReadMessageId
        );

        return [
            'room_id' => $roomId,
            'last_read_message_id' => $lastReadMessageId,
        ];
    }

    /* ═══════════════════════════════════════════════════════════
     * ساخت خودکار روم گروه سنی / کلاس
     * (idempotent — هر بار صدا زده شود، روم و اعضا به‌روز می‌شوند)
     * ═══════════════════════════════════════════════════════════ */

    /**
     * ساخت یا به‌روزرسانی روم گروه سنی.
     *
     * با ساختن یا ویرایش گروه سنی صدا زده می‌شود.
     * خرابی چت نباید فرآیند اصلی را خراب کند.
     */
    public static function ensureAgeGroupRoom(
        int $ageGroupId,
        ?int $createdBy = null
    ): void {
        try {
            $ageGroup = AgeGroupRepository::findById($ageGroupId);

            if (!$ageGroup) {
                return;
            }

            self::ensureGroupRoom([
                'unique_key' => hash(
                    'sha256',
                    'age_group-' . $ageGroupId
                ),

                'room_type' => 'age_group',

                'age_group_id' => $ageGroupId,
                'class_id' => null,

                'title' => (string) ($ageGroup['title'] ?? ''),

                'eligible_user_ids' => ChatRepository::eligibleUserIdsForAgeGroup(
                    $ageGroupId
                ),

                'created_by' => $createdBy,
            ]);
        } catch (\Throwable $e) {
            error_log(
                '[ChatService] ensureAgeGroupRoom failed for age_group '
                . $ageGroupId . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * ساخت یا به‌روزرسانی روم کلاس.
     *
     * با ساختن/ویرایش کلاس و ثبت‌نام بازیکن‌ها صدا زده می‌شود.
     */
    public static function ensureClassRoom(
        int $classId,
        ?int $createdBy = null
    ): void {
        try {
            $class = ClassRepository::findById($classId);

            if (!$class) {
                return;
            }

            self::ensureGroupRoom([
                'unique_key' => hash(
                    'sha256',
                    'class-' . $classId
                ),

                'room_type' => 'group',

                'age_group_id' => null,
                'class_id' => $classId,

                'title' => (string) ($class['title'] ?? ''),

                'eligible_user_ids' => ChatRepository::eligibleUserIdsForClass(
                    $classId
                ),

                'created_by' => $createdBy,
            ]);
        } catch (\Throwable $e) {
            error_log(
                '[ChatService] ensureClassRoom failed for class '
                . $classId . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * هسته‌ی مشترک: ساخت idempotent روم گروهی + همگام‌سازی اعضا.
     */
    private static function ensureGroupRoom(array $spec): void
    {
        $uniqueKey = (string) $spec['unique_key'];
        $title = trim((string) ($spec['title'] ?? ''));

        if ($title === '') {
            return;
        }

        $eligibleUserIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    $spec['eligible_user_ids'] ?? []
                )
            )
        );

        $createdBy = $spec['created_by'] ?? null;

        if ($createdBy === null || $createdBy <= 0) {
            $createdBy = (int) (
                Auth::id()
                ?? ChatRepository::firstActiveAdminId()
                ?? 0
            );
        }

        /*
         * created_by به football_users فوروکین دارد؛
         * بدون ادمین فعال، ساخت روم را رها می‌کنیم.
         */
        if ($createdBy <= 0) {
            error_log(
                '[ChatService] ensureGroupRoom: no active admin for created_by'
            );

            return;
        }

        $existingRoom = ChatRepository::findByUniqueKey($uniqueKey);

        if ($existingRoom) {
            $roomId = (int) $existingRoom['id'];

            /*
             * فقط اعضا همگام می‌شوند.
             * عنوان/پروفایل روم متعلق به ادمین است و با
             * ویرایش کلاس/گروه سنی بازنویسی نمی‌شود.
             */
            self::syncGroupRoomMembers(
                $roomId,
                $eligibleUserIds,
                $createdBy
            );

            return;
        }

        $roomId = ChatRepository::createRoom([
            'room_type' => $spec['room_type'],
            'is_group' => true,

            'user1_id' => null,
            'user2_id' => null,

            'title' => $title,
            'image' => self::DEFAULT_GROUP_IMAGE,

            'player_id' => null,
            'class_id' => $spec['class_id'],
            'age_group_id' => $spec['age_group_id'],

            'subject' => null,

            'unique_key' => $uniqueKey,
            'status' => 'active',
            'created_by' => $createdBy,
        ]);

        self::syncGroupRoomMembers(
            $roomId,
            $eligibleUserIds,
            $createdBy
        );
    }

    /**
     * اعضای واجد شرایطِ هنوز عضو نشده را به روم اضافه می‌کند.
     * (عضوهای قبلی حذف نمی‌شوند — حذف فقط توسط ادمین است)
     */
    private static function syncGroupRoomMembers(
        int $roomId,
        array $eligibleUserIds,
        int $createdBy
    ): void {
        foreach ($eligibleUserIds as $userId) {
            if (ChatRepository::memberExists($roomId, $userId)) {
                continue;
            }

            ChatRepository::addMember(
                $roomId,
                $userId,
                $userId === $createdBy ? 'owner' : 'member'
            );
        }
    }

    /* ═══════════════════════════════════════════════════════════
     * مدیریت روم — فقط ادمین
     * ═══════════════════════════════════════════════════════════ */

    /**
     * تغییر پروفایل روم (عنوان/تصویر/موضوع) — فقط ادمین
     */
    public static function updateRoom(
        int $roomId,
        array $data
    ): array {
        self::requireRoom($roomId);
        self::requireAdmin();

        $fields = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);

            if ($title === '') {
                throw new AppException(
                    'عنوان گفتگو نمی‌تواند خالی باشد',
                    422
                );
            }

            $fields['title'] = $title;
        }

        if (array_key_exists('image', $data)) {
            $fields['image'] = trim((string) $data['image']) ?: null;
        }

        if (array_key_exists('subject', $data)) {
            $fields['subject'] = trim((string) $data['subject']) ?: null;
        }

        if (!empty($fields)) {
            ChatRepository::updateRoom($roomId, $fields);
        }

        return self::roomDetails($roomId);
    }

    /**
     * افزودن عضو به روم — فقط ادمین
     */
    public static function addMembers(
        int $roomId,
        array $data
    ): array {
        self::requireRoom($roomId);
        self::requireAdmin();

        $requestedUserIds = $data['user_ids'] ?? null;

        if ($requestedUserIds === null) {
            $requestedUserIds = [
                self::normalizeOptionalInt($data['user_id'] ?? null),
            ];
        }

        if (!is_array($requestedUserIds)) {
            throw new AppException(
                'user_ids باید آرایه باشد',
                422
            );
        }

        $added = 0;

        foreach ($requestedUserIds as $userId) {
            $normalized = self::normalizeOptionalInt($userId);

            if ($normalized === null) {
                continue;
            }

            $user = UserRepository::findById($normalized);

            if (
                !$user
                || (string) ($user['status'] ?? '') !== 'active'
            ) {
                throw new AppException(
                    'کاربر پیدا نشد یا فعال نیست',
                    404
                );
            }

            if (
                !ChatRepository::memberExists($roomId, $normalized)
            ) {
                ChatRepository::addMember(
                    $roomId,
                    $normalized,
                    'member'
                );

                $added++;
            }
        }

        if ($added === 0) {
            throw new AppException(
                'هیچ عضوی اضافه نشد',
                422
            );
        }

        return self::roomDetails($roomId);
    }

    /**
     * حذف عضو از روم — فقط ادمین
     */
    public static function removeMember(
        int $roomId,
        int $userId
    ): array {
        self::requireRoom($roomId);
        self::requireAdmin();

        if ($userId <= 0) {
            throw new AppException(
                'شناسه کاربر معتبر نیست',
                422
            );
        }

        if (!ChatRepository::isMember($roomId, $userId)) {
            throw new AppException(
                'این کاربر عضو گفتگو نیست',
                404
            );
        }

        if (ChatRepository::countActiveMembers($roomId) <= 1) {
            throw new AppException(
                'حداقل یک عضو برای گفتگو لازم است',
                422
            );
        }

        ChatRepository::setMemberStatus(
            $roomId,
            $userId,
            'removed'
        );

        return self::roomDetails($roomId);
    }

    /**
     * حذف (غیرفعال‌سازی) روم — فقط ادمین
     */
    public static function deleteRoom(int $roomId): array
    {
        self::requireRoom($roomId);
        self::requireAdmin();

        ChatRepository::setRoomStatus($roomId, 'inactive');

        return [
            'id' => $roomId,
            'status' => 'inactive',
        ];
    }

    /**
     * حذف پیام:
     * - هر عضو می‌تواند پیام خودش را حذف کند
     * - ادمین هر پیامی را می‌تواند حذف کند
     */
    public static function deleteMessage(
        int $roomId,
        int $messageId
    ): array {
        self::requireRoom($roomId);

        $userId = (int) Auth::id();

        if (!ChatRepository::isMember($roomId, $userId)) {
            throw new AppException(
                'شما عضو این اتاق چت نیستید',
                403
            );
        }

        $message = ChatRepository::findMessageWithRoom($messageId);

        if (!$message || (int) $message['chat_room_id'] !== $roomId) {
            throw new AppException(
                'پیام یافت نشد',
                404
            );
        }

        $isOwnMessage = (int) $message['sender_id'] === $userId;
        $isAdmin = (string) Auth::role() === 'admin';

        if (!$isOwnMessage && !$isAdmin) {
            throw new AppException(
                'فقط خودتان یا مدیر می‌توانید این پیام را حذف کنید',
                403
            );
        }

        ChatRepository::softDeleteMessage($messageId);

        return [
            'id' => $messageId,
            'deleted' => true,
        ];
    }

    /**
     * مخاطبین قابل گفتگو برای ادمین
     * (بازیکنان + مربیان فعال)
     */
    public static function adminContacts(): array
    {
        self::requireAdmin();

        return ChatRepository::adminContacts();
    }

    /**
     * بررسی نقش ادمین
     */
    private static function requireAdmin(): void
    {
        if ((string) Auth::role() !== 'admin') {
            throw new AppException(
                'فقط مدیر می‌تواند این کار را انجام دهد',
                403
            );
        }
    }

    /**
     * قفل/باز کردن گفتگو توسط ادمین
     */
    public static function setRoomLocked(
        int $roomId,
        bool $locked
    ): array {
        self::requireRoom($roomId);

        if ((string) Auth::role() !== 'admin') {
            throw new AppException(
                'فقط مدیر می‌تواند وضعیت قفل گفتگو را تغییر دهد',
                403
            );
        }

        ChatRepository::setRoomLocked(
            $roomId,
            $locked
        );

        return self::roomDetails($roomId);
    }

    /**
     * بررسی وجود اتاق
     */
    private static function requireRoom(
        int $roomId
    ): array {
        $room = ChatRepository::findById($roomId);

        if (!$room) {
            throw new AppException(
                'اتاق چت یافت نشد',
                404
            );
        }

        return $room;
    }

    /**
     * تعیین گروهی/خصوصی بودن اتاق.
     *
     * is_group فیلد اصلی است.
     * برای دیتابیس‌های قدیمی age_group نیز پشتیبانی می‌شود.
     */
    private static function isGroupRoom(
        array $room
    ): bool {
        if (array_key_exists('is_group', $room)) {
            return (int) $room['is_group'] === 1;
        }

        return (string) ($room['room_type'] ?? '') === 'age_group';
    }

    /**
     * تبدیل اطلاعات دیتابیس به API جدید:
     *
     * {
     *   "id": 15,
     *   "is_group": false,
     *   "title": "علی رضایی",
     *   "image": "/uploads/users/25.jpg",
     *   "users": [...],
     *   "last_message": null,
     *   "unread_count": 0
     * }
     */
    private static function hydrateRoom(
        array $room,
        array $members,
        ?array $lastMessage,
        int $unreadCount
    ): array {
        $currentUserId = (int) Auth::id();

        $isGroup = self::isGroupRoom($room);

        $shapedUsers = [];
        $targetUser = null;

        foreach ($members as $member) {
            $userId = (int) ($member['user_id'] ?? 0);

            $avatar = trim(
                (string) (
                    $member['avatar_path']
                    ?? $member['avatar']
                    ?? ''
                )
            );

            $role = (string) (
                $member['user_role']
                ?? $member['role']
                ?? ''
            );

            $userData = [
                'id' => $userId,

                'full_name' => (string) (
                    $member['full_name'] ?? ''
                ),

                /*
                 * آواتار عضو (URL کامل).
                 * اگر آواتار تنظیم نشده باشد، آواتار
                 * پیش‌فرض مربوط به نقش عضو می‌آید.
                 */
                'avatar' => AvatarService::getAvatarUrl(
                    $avatar !== '' ? $avatar : null,
                    AvatarService::defaultTypeForRole($role)
                ),

                'role' => $role,

                'member_role' => (string) (
                    $member['member_role']
                    ?? 'member'
                ),
            ];

            $shapedUsers[] = $userData;

            if (
                !$isGroup &&
                $userId !== $currentUserId
            ) {
                $targetUser = $member;
            }
        }

        /*
         * برای چت خصوصی:
         * title و image از کاربر مقابل می‌آید.
         */
        if (!$isGroup) {
            $title = $targetUser
                ? (string) (
                    $targetUser['full_name'] ?? ''
                )
                : '';

            $targetAvatar = $targetUser
                ? trim(
                    (string) (
                        $targetUser['avatar_path']
                        ?? $targetUser['avatar']
                        ?? ''
                    )
                )
                : '';

            /*
             * عکس گفتگوی خصوصی = آواتار کاربر مقابل (URL کامل).
             */
            $image = AvatarService::getAvatarUrl(
                $targetAvatar !== '' ? $targetAvatar : null,
                AvatarService::defaultTypeForRole(
                    (string) (
                        $targetUser['user_role']
                        ?? $targetUser['role']
                        ?? ''
                    )
                )
            );
        } else {
            /*
             * برای گروه:
             * title و image متعلق به خود room است.
             */
            $title = trim(
                (string) ($room['title'] ?? '')
            );

            if ($title === '') {
                $title = 'گروه گفتگو';
            }

            $image = trim(
                (string) ($room['image'] ?? '')
            );

            if ($image === '') {
                $image = self::DEFAULT_GROUP_IMAGE;
            }
        }

        return [
            'id' => (int) $room['id'],

            'is_group' => $isGroup,

            'title' => $title,

            'image' => $image,

            'users' => $shapedUsers,

            'last_message' => $lastMessage,

            'unread_count' => $unreadCount,

            /* وضعیت قفل (برای نمایش در کلاینت‌ها) */
            'is_locked' => isset($room['is_locked'])
                ? (int) $room['is_locked'] === 1
                : false,

            /* وضعیت روم برای سازگاری با DTO کلاینت‌ها */
            'status' => (string) ($room['status'] ?? 'active'),
        ];
    }

    /**
     * بررسی دسترسی ساخت چت خصوصی.
     *
     * room_type دیگر بخشی از API نیست،
     * ولی محدودیت‌های قبلی نقش‌ها حفظ می‌شوند.
     */
    private static function assertPrivateRoomPermission(
        array $currentUser,
        array $targetUser
    ): void {
        $role = (string) ($currentUser['role'] ?? '');
        $targetRole = (string) ($targetUser['role'] ?? '');

        /*
         * admin <-> player
         */
        if (
            ($role === 'admin' && $targetRole === 'player') ||
            ($role === 'player' && $targetRole === 'admin')
        ) {
            return;
        }

        /*
         * admin <-> coach
         */
        if (
            ($role === 'admin' && $targetRole === 'coach') ||
            ($role === 'coach' && $targetRole === 'admin')
        ) {
            return;
        }

        /*
         * player <-> coach
         */
        if (
            $role === 'player' &&
            $targetRole === 'coach'
        ) {
            if (
                !ChatRepository::playerCanChatCoach(
                    (int) $currentUser['id'],
                    (int) $targetUser['id']
                )
            ) {
                throw new AppException(
                    'این مربی به کلاس‌های فعال این بازیکن مرتبط نیست',
                    403
                );
            }

            return;
        }

        if (
            $role === 'coach' &&
            $targetRole === 'player'
        ) {
            if (
                !ChatRepository::coachCanChatPlayer(
                    (int) $currentUser['id'],
                    (int) $targetUser['id']
                )
            ) {
                throw new AppException(
                    'این بازیکن به کلاس‌های فعال این مربی مرتبط نیست',
                    403
                );
            }

            return;
        }

        throw new AppException(
            'نقش کاربران برای این چت معتبر نیست',
            403
        );
    }

    private static function broadcastMessage(
        int $roomId,
        array $message
    ): void {
        $url = Config::get(
            'chat.node_internal_url'
        );

        if (!$url) {
            return;
        }

        $secret = Config::get(
            'chat.internal_secret'
        );

        $payload = json_encode(
            [
                'room_id' => $roomId,
                'message' => $message,
            ],
            JSON_UNESCAPED_UNICODE
        );

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt(
                $ch,
                CURLOPT_POST,
                true
            );

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $payload
            );

            curl_setopt(
                $ch,
                CURLOPT_RETURNTRANSFER,
                true
            );

            curl_setopt(
                $ch,
                CURLOPT_TIMEOUT,
                2
            );

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                [
                    'Content-Type: application/json',
                    'X-Internal-Secret: ' . $secret,
                ]
            );

            curl_exec($ch);

            if (curl_errno($ch)) {
                error_log(
                    'Chat broadcast error: ' .
                    curl_error($ch)
                );
            }

            curl_close($ch);
        }
    }

    private static function normalizeOptionalInt(
        mixed $value
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new AppException(
                'شناسه معتبر نیست',
                422
            );
        }

        $value = (int) $value;

        return $value > 0
            ? $value
            : null;
    }
}