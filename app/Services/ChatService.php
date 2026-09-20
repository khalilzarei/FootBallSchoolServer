<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Config;
use App\Repositories\AgeGroupRepository;
use App\Repositories\ChatRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\UserRepository;

class ChatService
{
    private const ROOM_TYPES = ['player_admin', 'coach_admin', 'player_coach', 'age_group'];
    private const MESSAGE_TYPES = ['text', 'image', 'video', 'file', 'system'];

    public static function rooms(): array
    {
        $currentUserId = (int) Auth::id();
        $rooms = ChatRepository::roomsForUser($currentUserId);
        $memberRoomIds = array_map(fn($r) => (int) $r['id'], $rooms);

        // ─── عضویت خودکار در گفتگوی گروهی گروه‌های سنی (برای کاربران واجد شرایط) ───
        $currentUser = Auth::user();
        foreach (ChatRepository::ageGroupRooms() as $agRoom) {
            $agRoomId = (int) $agRoom['id'];
            if (in_array($agRoomId, $memberRoomIds, true)) continue;
            if (!ChatRepository::userEligibleForAgeGroup($currentUser, (int) ($agRoom['age_group_id'] ?? 0))) continue;
            ChatRepository::addMember($agRoomId, $currentUserId, 'member');
            $rooms[] = $agRoom;
            $memberRoomIds[] = $agRoomId;
        }

        if (empty($rooms)) return [];

        $roomIds = array_map(fn($r) => (int) $r['id'], $rooms);
        $lastMessages = ChatRepository::lastMessagesForRooms($roomIds);
        $unreadCounts = ChatRepository::unreadCountsByRoom((int) Auth::id(), $roomIds);

        $out = [];
        foreach ($rooms as $room) {
            $roomId = (int) $room['id'];
            $out[] = self::hydrateRoom(
                $room,
                ChatRepository::members($roomId),
                $lastMessages[$roomId] ?? null,
                $unreadCounts[$roomId] ?? 0
            );
        }
        return $out;
    }

    public static function createRoom(array $data): array
    {
        $currentUserId = (int) Auth::id();
        $currentUser = Auth::user();

        // ─── گفتگوی گروهی گروه سنی (فقط ادمین می‌تواند بسازد؛ idempotent با unique_key) ───
        if ((string) ($data['room_type'] ?? '') === 'age_group') {
            $ageGroupId = self::normalizeOptionalInt($data['age_group_id'] ?? null);
            if ($ageGroupId === null) throw new AppException('شناسه گروه سنی الزامی است', 422);
            if ((string) $currentUser['role'] !== 'admin') throw new AppException('فقط مدیر می‌تواند گفتگوی گروه سنی بسازد', 403);
            if (!AgeGroupRepository::findById($ageGroupId)) throw new AppException('گروه سنی یافت نشد', 404);

            $uniqueKey = hash('sha256', 'age_group-' . $ageGroupId);

            $existingRoom = ChatRepository::findByUniqueKey($uniqueKey);
            if ($existingRoom) {
                if (!ChatRepository::memberExists((int) $existingRoom['id'], $currentUserId)) {
                    ChatRepository::addMember((int) $existingRoom['id'], $currentUserId, 'owner');
                }
                return self::roomDetails((int) $existingRoom['id']);
            }

            $roomId = ChatRepository::createRoom([
                'room_type' => 'age_group',
                'player_id' => null,
                'class_id' => null,
                'age_group_id' => $ageGroupId,
                'subject' => null,
                'unique_key' => $uniqueKey,
                'status' => 'active',
                'created_by' => $currentUserId,
            ]);

            ChatRepository::addMember($roomId, $currentUserId, 'owner');

            return self::roomDetails($roomId);
        }

        // ─── اتاق خصوصی دو نفره ───
        $targetUserId = (int) ($data['target_user_id'] ?? 0);
        if ($targetUserId <= 0) throw new AppException('شناسه کاربر مقابل معتبر نیست', 422);

        if ($targetUserId === $currentUserId) throw new AppException('کاربر نمی‌تواند با خودش اتاق چت بسازد', 422);

        $targetUser = UserRepository::findById($targetUserId);

        if (!$targetUser || $targetUser['status'] !== 'active') throw new AppException('کاربر مقابل یافت نشد یا فعال نیست', 404);

        $playerId = self::normalizeOptionalInt($data['player_id'] ?? null);
        $classId = self::normalizeOptionalInt($data['class_id'] ?? null);

        // بازیکنِ مرتبط الزامی نیست، ولی اگر ارسال شود باید واقعی باشد
        if ($playerId !== null && !PlayerRepository::findById($playerId)) {
            throw new AppException('بازیکن یافت نشد', 404);
        }

        // نوع اتاق: اگر اپ نفرستاده باشد (سرپرست/مربی به پروفایل کاربران دسترسی ندارد)،
        // سرور خودش از نقش دو کاربر استنتاج می‌کند
        $roomType = (string) ($data['room_type'] ?? '');
        if ($roomType === '') {
            $roomType = self::inferRoomType($currentUser, $targetUser) ?? '';
        }
        if ($roomType === '' || !in_array($roomType, self::ROOM_TYPES, true)) {
            throw new AppException('نوع اتاق چت معتبر نیست', 422);
        }

        self::assertRoomPermission($roomType, $currentUser, $targetUser);

        $userIds = [$currentUserId, $targetUserId];
        sort($userIds);

        $uniqueSource = $roomType . '-' . implode('-', $userIds);

        if ($roomType === 'player_coach' && $playerId !== null) {
            $uniqueSource .= '-player-' . $playerId;
        }

        $uniqueKey = hash('sha256', $uniqueSource);

        $existingRoom = ChatRepository::findByUniqueKey($uniqueKey);

        if ($existingRoom) {
            if (!ChatRepository::memberExists((int) $existingRoom['id'], $currentUserId)) {
                ChatRepository::addMember((int) $existingRoom['id'], $currentUserId, 'member');
            }
            if (!ChatRepository::memberExists((int) $existingRoom['id'], $targetUserId)) {
                ChatRepository::addMember((int) $existingRoom['id'], $targetUserId, 'member');
            }
            // اگر اتاق قبلاً بدون player_id ساخته شده بود و این بار از پروفایل بازیکن باز شده، تکمیل می‌شود
            if ($playerId !== null && $existingRoom['player_id'] === null) {
                ChatRepository::updateRoomPlayer((int) $existingRoom['id'], $playerId);
            }
            return self::roomDetails((int) $existingRoom['id']);
        }

        $roomId = ChatRepository::createRoom([
            'room_type' => $roomType,
            'player_id' => $playerId,
            'class_id' => $classId,
            'subject' => trim((string) ($data['subject'] ?? '')) ?: null,
            'unique_key' => $uniqueKey,
            'status' => 'active',
            'created_by' => $currentUserId,
        ]);

        ChatRepository::addMember($roomId, $currentUserId, 'owner');
        ChatRepository::addMember($roomId, $targetUserId, 'member');

        return self::roomDetails($roomId);
    }

    public static function roomDetails(int $roomId): array
    {
        $room = self::requireRoom($roomId);

        // عضویت خودکار در گفتگوی گروهی گروه سنی (کاربر واجد شرایطی که هنوز عضو نشده است)
        if (($room['room_type'] ?? '') === 'age_group') {
            $currentUserId = (int) Auth::id();
            if (!ChatRepository::isMember($roomId, $currentUserId)
                && ChatRepository::userEligibleForAgeGroup(Auth::user(), (int) ($room['age_group_id'] ?? 0))) {
                ChatRepository::addMember($roomId, $currentUserId, 'member');
            }
        }

        $lastMessages = ChatRepository::lastMessagesForRooms([$roomId]);
        $unreadCounts = ChatRepository::unreadCountsByRoom((int) Auth::id(), [$roomId]);

        return self::hydrateRoom(
            $room,
            ChatRepository::members($roomId),
            $lastMessages[$roomId] ?? null,
            $unreadCounts[$roomId] ?? 0
        );
    }

    public static function messages(int $roomId, array $query): array
    {
        self::requireRoom($roomId);

        if (!ChatRepository::isMember($roomId, (int) Auth::id())) throw new AppException('شما عضو این اتاق چت نیستید', 403);

        $limit = (int) ($query['limit'] ?? 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 100) $limit = 100;

        $lastReadMessageId = ChatRepository::getLastReadMessageId($roomId, (int) Auth::id());

        return ChatRepository::messages($roomId, $limit, $lastReadMessageId);
    }

    public static function sendMessage(int $roomId, array $data): array
    {
        $room = self::requireRoom($roomId);

        if (!ChatRepository::isMember($roomId, (int) Auth::id())) throw new AppException('شما عضو این اتاق چت نیستید', 403);

        // اگر مدیر گفتگو را قفل کرده باشد، فقط خودش می‌تواند پیام بفرستد
        if ((int) ($room['is_locked'] ?? 0) === 1 && (string) Auth::role() !== 'admin') {
            throw new AppException('این گفتگو توسط مدیر قفل شده است', 403);
        }

        $messageType = (string) ($data['message_type'] ?? 'text');
        if (!in_array($messageType, self::MESSAGE_TYPES, true)) throw new AppException('نوع پیام معتبر نیست', 422);

        $body = trim((string) ($data['body'] ?? ''));
        if ($messageType === 'text' && $body === '') throw new AppException('متن پیام نمی‌تواند خالی باشد', 422);

        $mediaId = self::normalizeOptionalInt($data['media_id'] ?? null);

        $messageId = ChatRepository::createMessage([
            'chat_room_id' => $roomId,
            'sender_id' => Auth::id(),
            'message_type' => $messageType,
            'body' => $body !== '' ? $body : null,
            'media_id' => $mediaId,
        ]);

        $message = ChatRepository::findMessageById($messageId);

        self::broadcastMessage($roomId, $message);

        return $message;
    }

    public static function read(int $roomId, array $data): array
    {
        self::requireRoom($roomId);

        if (!ChatRepository::isMember($roomId, (int) Auth::id())) throw new AppException('شما عضو این اتاق چت نیستید', 403);

        $lastReadMessageId = (int) ($data['last_read_message_id'] ?? 0);
        if ($lastReadMessageId <= 0) throw new AppException('شناسه آخرین پیام معتبر نیست', 422);

        ChatRepository::updateLastRead($roomId, (int) Auth::id(), $lastReadMessageId);

        return ['room_id' => $roomId, 'last_read_message_id' => $lastReadMessageId];
    }

    /** قفل/باز کردن گفتگو توسط ادمین — وقتی قفل باشد کاربران دیگر نمی‌توانند پیام بفرستند */
    public static function setRoomLocked(int $roomId, bool $locked): array
    {
        self::requireRoom($roomId);

        ChatRepository::setRoomLocked($roomId, $locked);

        return self::roomDetails($roomId);
    }

    private static function requireRoom(int $roomId): array
    {
        $room = ChatRepository::findById($roomId);
        if (!$room) throw new AppException('اتاق چت یافت نشد', 404);
        return $room;
    }

    /**
     * شکل نهایی اتاق چت برای اپ:
     * شناسه/نام کاربر مقابل + آخرین پیام + شمار پیام خوانده‌نشده + اعضای شکل‌یافته
     */
    private static function hydrateRoom(array $room, array $members, ?array $lastMessage, int $unreadCount): array
    {
        $currentUserId = (int) Auth::id();
        $target = null;
        $shapedMembers = [];

        // گفتگوی گروهی گروه سنی؟ (عنوان اتاق = عنوان گروه سنی)
        $isAgeGroup = ((string) ($room['room_type'] ?? '')) === 'age_group';
        $ageGroup = null;
        if ($isAgeGroup && !empty($room['age_group_id'])) {
            $ageGroup = AgeGroupRepository::findById((int) $room['age_group_id']);
        }

        foreach ($members as $m) {
            $shapedMembers[] = [
                'user_id' => (int) $m['user_id'],
                'full_name' => (string) ($m['full_name'] ?? ''),
                'role' => (string) ($m['user_role'] ?? ''),
                'member_role' => (string) ($m['member_role'] ?? 'member'),
                'last_read_message_id' => (int) ($m['last_read_message_id'] ?? 0),
            ];
            if ((int) $m['user_id'] !== $currentUserId) {
                $target = $m;
            }
        }

        return [
            'id' => (int) $room['id'],
            'room_type' => (string) $room['room_type'],
            'player_id' => isset($room['player_id']) ? (int) $room['player_id'] : null,
            'class_id' => isset($room['class_id']) ? (int) $room['class_id'] : null,
            'subject' => $room['subject'] ?? null,
            'status' => $room['status'] ?? 'active',
            'created_at' => $room['created_at'] ?? null,
            'updated_at' => $room['updated_at'] ?? null,
            'target_user_id' => $isAgeGroup ? null : ($target ? (int) $target['user_id'] : null),
            'target_user_name' => $isAgeGroup
                ? ($ageGroup['title'] ?? 'گروه سنی')
                : ($target ? (string) ($target['full_name'] ?? '') : null),
            'target_user_role' => $isAgeGroup ? null : ($target ? (string) ($target['user_role'] ?? '') : null),
            'age_group_id' => $isAgeGroup && !empty($room['age_group_id']) ? (int) $room['age_group_id'] : null,
            'age_group_title' => $ageGroup ? (string) $ageGroup['title'] : null,
            'is_locked' => (int) ($room['is_locked'] ?? 0) === 1,
            'member_count' => count($members),
            'members' => $shapedMembers,
            'last_message' => $lastMessage,
            'unread_count' => $unreadCount,
        ];
    }

    private static function assertRoomPermission(string $roomType, array $currentUser, array $targetUser): void
    {
        $role = (string) $currentUser['role'];
        $targetRole = (string) $targetUser['role'];

        if ($roomType === 'player_admin') {
            $allowed = ($role === 'player' && $targetRole === 'admin')
                || ($role === 'admin' && $targetRole === 'player');
            if (!$allowed) throw new AppException('نقش کاربران برای این چت معتبر نیست', 403);
            return;
        }

        if ($roomType === 'coach_admin') {
            $allowed = ($role === 'coach' && $targetRole === 'admin')
                || ($role === 'admin' && $targetRole === 'coach');
            if (!$allowed) throw new AppException('نقش کاربران برای این چت معتبر نیست', 403);
            return;
        }

        if ($roomType === 'player_coach') {
            if ($role === 'player' && $targetRole === 'coach') {
                if (!ChatRepository::playerCanChatCoach((int) $currentUser['id'], (int) $targetUser['id'])) {
                    throw new AppException('این مربی به کلاس‌های فعال این بازیکن مرتبط نیست', 403);
                }
                return;
            }

            if ($role === 'coach' && $targetRole === 'player') {
                if (!ChatRepository::coachCanChatPlayer((int) $currentUser['id'], (int) $targetUser['id'])) {
                    throw new AppException('این بازیکن به کلاس‌های فعال این مربی مرتبط نیست', 403);
                }
                return;
            }

            throw new AppException('نقش کاربران برای این چت معتبر نیست', 403);
        }
    }

    /** استنتاج نوع اتاق از نقش دو کاربر (وقتی اپ room_type نفرستاده) */
    private static function inferRoomType(array $currentUser, array $targetUser): ?string
    {
        $pair = [(string) $currentUser['role'], (string) $targetUser['role']];
        sort($pair);
        $key = implode('-', $pair);
        if ($key === 'admin-player') return 'player_admin';
        if ($key === 'admin-coach') return 'coach_admin';
        if ($key === 'coach-player') return 'player_coach';
        return null;
    }

    private static function broadcastMessage(int $roomId, array $message): void
    {
        $url = Config::get('chat.node_internal_url');
        if (!$url) return;

        $secret = Config::get('chat.internal_secret');

        $payload = json_encode([
            'room_id' => $roomId,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Internal-Secret: ' . $secret,
            ]);

            curl_exec($ch);

            if (curl_errno($ch)) {
                error_log('Chat broadcast error: ' . curl_error($ch));
            }

            curl_close($ch);
        }
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('شناسه معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }
}