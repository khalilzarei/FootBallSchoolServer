<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Config;
use App\Repositories\ChatRepository;
use App\Repositories\UserRepository;

class ChatService
{
    private const ROOM_TYPES = ['guardian_admin', 'coach_admin', 'guardian_coach'];
    private const MESSAGE_TYPES = ['text', 'image', 'video', 'file', 'system'];

    public static function rooms(): array
    {
        $rooms = ChatRepository::roomsForUser((int) Auth::id());

        foreach ($rooms as &$room) {
            $room['members'] = ChatRepository::members((int) $room['id']);
        }

        return $rooms;
    }

    public static function createRoom(array $data): array
    {
        $roomType = (string) ($data['room_type'] ?? '');
        if (!in_array($roomType, self::ROOM_TYPES, true)) throw new AppException('نوع اتاق چت معتبر نیست', 422);

        $targetUserId = (int) ($data['target_user_id'] ?? 0);
        if ($targetUserId <= 0) throw new AppException('شناسه کاربر مقابل معتبر نیست', 422);

        $currentUserId = (int) Auth::id();

        if ($targetUserId === $currentUserId) throw new AppException('کاربر نمی‌تواند با خودش اتاق چت بسازد', 422);

        $currentUser = Auth::user();
        $targetUser = UserRepository::findById($targetUserId);

        if (!$targetUser || $targetUser['status'] !== 'active') throw new AppException('کاربر مقابل یافت نشد یا فعال نیست', 404);

        $playerId = self::normalizeOptionalInt($data['player_id'] ?? null);
        $classId = self::normalizeOptionalInt($data['class_id'] ?? null);

        self::assertRoomPermission($roomType, $currentUser, $targetUser);

        $userIds = [$currentUserId, $targetUserId];
        sort($userIds);

        $uniqueSource = $roomType . '-' . implode('-', $userIds);

        if ($roomType === 'guardian_coach' && $playerId !== null) {
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
        $room['members'] = ChatRepository::members($roomId);
        return $room;
    }

    public static function messages(int $roomId, array $query): array
    {
        self::requireRoom($roomId);

        if (!ChatRepository::isMember($roomId, (int) Auth::id())) throw new AppException('شما عضو این اتاق چت نیستید', 403);

        $limit = (int) ($query['limit'] ?? 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 100) $limit = 100;

        return ChatRepository::messages($roomId, $limit);
    }

    public static function sendMessage(int $roomId, array $data): array
    {
        self::requireRoom($roomId);

        if (!ChatRepository::isMember($roomId, (int) Auth::id())) throw new AppException('شما عضو این اتاق چت نیستید', 403);

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

    private static function requireRoom(int $roomId): array
    {
        $room = ChatRepository::findById($roomId);
        if (!$room) throw new AppException('اتاق چت یافت نشد', 404);
        return $room;
    }

    private static function assertRoomPermission(string $roomType, array $currentUser, array $targetUser): void
    {
        $role = (string) $currentUser['role'];
        $targetRole = (string) $targetUser['role'];

        if ($roomType === 'guardian_admin') {
            $allowed = ($role === 'guardian' && $targetRole === 'admin')
                || ($role === 'admin' && $targetRole === 'guardian');
            if (!$allowed) throw new AppException('نقش کاربران برای این چت معتبر نیست', 403);
            return;
        }

        if ($roomType === 'coach_admin') {
            $allowed = ($role === 'coach' && $targetRole === 'admin')
                || ($role === 'admin' && $targetRole === 'coach');
            if (!$allowed) throw new AppException('نقش کاربران برای این چت معتبر نیست', 403);
            return;
        }

        if ($roomType === 'guardian_coach') {
            if ($role === 'guardian' && $targetRole === 'coach') {
                if (!ChatRepository::guardianCanChatCoach((int) $currentUser['id'], (int) $targetUser['id'])) {
                    throw new AppException('این مربی به فرزندان فعال این سرپرست مرتبط نیست', 403);
                }
                return;
            }

            if ($role === 'coach' && $targetRole === 'guardian') {
                if (!ChatRepository::coachCanChatGuardian((int) $currentUser['id'], (int) $targetUser['id'])) {
                    throw new AppException('این سرپرست به کلاس‌های فعال این مربی مرتبط نیست', 403);
                }
                return;
            }

            throw new AppException('نقش کاربران برای این چت معتبر نیست', 403);
        }
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