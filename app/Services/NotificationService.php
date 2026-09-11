<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\NotificationRepository;

class NotificationService
{
    private const TYPES = ['info', 'news', 'payment', 'attendance', 'chat', 'match', 'system'];
    private const ROLES = ['admin', 'coach', 'guardian'];

    public static function listForCurrentUser(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $unreadOnly = filter_var($query['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $type = trim((string) ($query['type'] ?? ''));

        if ($type !== '' && !in_array($type, self::TYPES, true)) throw new AppException('نوع اعلان معتبر نیست', 422);

        return NotificationRepository::paginateForUser((int) Auth::id(), [
            'unread_only' => $unreadOnly,
            'type' => $type !== '' ? $type : null,
        ], $page, $perPage);
    }

    public static function unreadCount(): array
    {
        return ['unread_count' => NotificationRepository::unreadCount((int) Auth::id())];
    }

    public static function read(int $notificationId): array
    {
        $notification = NotificationRepository::findByIdForUser($notificationId, (int) Auth::id());
        if (!$notification) throw new AppException('اعلان یافت نشد', 404);

        NotificationRepository::markRead($notificationId, (int) Auth::id());

        return ['notification_id' => $notificationId];
    }

    public static function readAll(): array
    {
        NotificationRepository::markAllRead((int) Auth::id());
        return ['unread_count' => 0];
    }

    public static function send(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان اعلان الزامی است', 422);

        $body = trim((string) ($data['body'] ?? ''));

        $type = (string) ($data['type'] ?? 'info');
        if (!in_array($type, self::TYPES, true)) throw new AppException('نوع اعلان معتبر نیست', 422);

        $userId = (int) ($data['user_id'] ?? 0);
        $role = trim((string) ($data['role'] ?? ''));

        if ($userId <= 0 && $role === '') throw new AppException('برای ارسال اعلان باید کاربر یا نقش مشخص شود', 422);

        $payload = null;

        if (!empty($data['data']) && is_array($data['data'])) {
            $payload = json_encode($data['data'], JSON_UNESCAPED_UNICODE);
        }

        $userIds = [];

        if ($userId > 0) $userIds[] = $userId;

        if ($role !== '') {
            if (!in_array($role, self::ROLES, true)) throw new AppException('نقش دریافت‌کننده معتبر نیست', 422);
            $userIds = array_merge($userIds, NotificationRepository::activeUserIdsByRole($role));
        }

        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) throw new AppException('کاربری برای دریافت اعلان یافت نشد', 422);

        $sent = 0;

        foreach ($userIds as $targetUserId) {
            NotificationRepository::create([
                'user_id' => $targetUserId,
                'title' => $title,
                'body' => $body !== '' ? $body : null,
                'type' => $type,
                'data' => $payload,
            ]);
            $sent++;
        }

        return ['sent_count' => $sent];
    }
}