<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\ClientRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\NewsRepository;
use App\Repositories\PaymentRepository;
use App\Services\AvatarService;
use App\Services\MediaService;

/**
 * داده‌های سمت کلاینت (me/*) — کاربر با نقش player (حساب بازیکن) یا coach
 */
class ClientService
{
    /**
     * اطلاعات بازیکنِ کاربر جاری (me/children — همیشه آرایه؛ برای ادمین خالی)
     */
    public static function children(): array
    {
        if (Auth::hasRole('admin')) return [];

        if (!Auth::hasRole('player')) {
            throw new AppException('این بخش فقط برای بازیکن در دسترس است', 403);
        }

        $player = ClientRepository::playerForUser((int) Auth::id());
        if ($player === null) return [];

        $playerId = (int) $player['id'];
        $currentClasses = EnrollmentRepository::currentClassByPlayerIds([$playerId]);

        return [
            [
                'id' => $playerId,
                'first_name' => (string) $player['first_name'],
                'last_name' => (string) $player['last_name'],
                'full_name' => trim(((string) $player['first_name']) . ' ' . ((string) $player['last_name'])),
                'birth_date' => $player['birth_date'] ?? null,
                'age' => isset($player['age']) ? (int) $player['age'] : null,
                'gender' => $player['gender'] ?? null,
                'national_code' => $player['national_code'] ?? null,
                'avatar_url' => AvatarService::getAvatarUrl($player['avatar_path'] ?? null, 'player'),
                'current_class' => $currentClasses[$playerId] ?? null,
                'balance' => PaymentRepository::playerBalance($playerId),
            ],
        ];
    }

    public static function schedule(): array
    {
        return ClientRepository::scheduleForUser((int) Auth::id(), (string) Auth::role());
    }

    /**
     * اخبار مجاز برای این کاربر، همراه با عکس/فیلم هر خبر.
     * این همان داده‌ای است که اپ بازیکن در اسلایدر داشبورد نشان می‌دهد.
     */
    public static function news(): array
    {
        $rows = ClientRepository::publishedNewsForUser((int) Auth::id(), (string) Auth::role());

        return self::attachNewsMedia($rows);
    }

    public static function media(): array
    {
        return MediaService::presentMany(
            ClientRepository::visibleMediaForUser((int) Auth::id(), (string) Auth::role())
        );
    }

    /**
     * چسباندن رسانه‌ها به اخبار با یک کوئری گروهی (بدون N+1).
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private static function attachNewsMedia(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $newsIds = [];

        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $newsIds[] = (int) $row['id'];
            }
        }

        $grouped = NewsRepository::mediaForNewsIds($newsIds);

        foreach ($rows as &$row) {
            $row['media'] = MediaService::presentMany($grouped[(int) $row['id']] ?? []);
        }

        unset($row);

        return $rows;
    }

    public static function finance(): array
    {
        if (!Auth::hasRole('player')) {
            throw new AppException('این بخش فقط برای بازیکن در دسترس است', 403);
        }

        $player = ClientRepository::playerForUser((int) Auth::id());
        if ($player === null) return [];

        return ClientRepository::financeForPlayer((int) $player['id']);
    }

    /** کلاس‌های فعال بازیکن (با مربی و برنامه هفتگی) */
    public static function classes(): array
    {
        if (!Auth::hasRole('player')) return [];

        $player = ClientRepository::playerForUser((int) Auth::id());
        if ($player === null) return [];

        return ClientRepository::classesForPlayer((int) $player['id']);
    }

    /** مسابقات مرتبط با بازیکن */
    public static function matches(): array
    {
        if (!Auth::hasRole('player')) return [];

        $player = ClientRepository::playerForUser((int) Auth::id());
        if ($player === null) return [];

        return ClientRepository::matchesForPlayer((int) $player['id']);
    }

    /** مخاطبین قابل گفتگو برای کاربر جاری (ادمین‌ها + مربیان/بازیکنان مرتبط) */
    public static function chatContacts(): array
    {
        return ClientRepository::chatContactsForUser((int) Auth::id(), (string) Auth::role());
    }
}
