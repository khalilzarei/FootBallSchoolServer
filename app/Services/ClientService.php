<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\ClientRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\GuardianPlayerRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\NewsRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\UserRepository;
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
        $role = (string) Auth::role();
        if ($role === 'admin' && UserRepository::isCoach((int) Auth::id())) $role = 'coach';
        return ClientRepository::scheduleForUser((int) Auth::id(), $role);
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

    /**
     * جزئیات یک خبر با بررسی دسترسی + رسانه‌ها
     */
    public static function newsDetail(int $newsId): array
    {
        $userId = (int) Auth::id();
        $role = (string) Auth::role();

        $news = NewsRepository::findPublishedForUser($newsId, $userId, $role);

        if (!$news) {
            throw new AppException('خبر یافت نشد یا دسترسی ندارید', 404);
        }

        $media = NewsRepository::mediaForNews($newsId);
        $news['media'] = MediaService::presentMany($media);
        $news['audiences'] = NewsRepository::audiences($newsId);

        return $news;
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
        if (Auth::hasRole('coach') || UserRepository::isCoach((int) Auth::id())) {
            return ClientRepository::classesForCoach((int) Auth::id());
        }

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

    /** پروفایل کاربر جاری */
    public static function profile(): array
    {
        $userId = (int) Auth::id();
        $user = UserRepository::findById($userId);
        if (!$user) throw new AppException('کاربر یافت نشد', 404);

        $player = ClientRepository::playerForUser($userId);
        $guardians = [];
        if ($player) {
            $guardians = GuardianPlayerRepository::guardiansForPlayer((int) $player['id']);
        }

        return [
            'user' => $user + ['avatar_url' => AvatarService::getAvatarUrl($user['avatar_path'] ?? null, 'user')],
            'player' => $player ? [
                'id' => (int) $player['id'],
                'first_name' => (string) $player['first_name'],
                'last_name' => (string) $player['last_name'],
                'full_name' => trim(((string) $player['first_name']) . ' ' . ((string) $player['last_name'])),
                'birth_date' => $player['birth_date'] ?? null,
                'gender' => $player['gender'] ?? null,
                'national_code' => $player['national_code'] ?? null,
                'avatar_url' => AvatarService::getAvatarUrl($player['avatar_path'] ?? null, 'player'),
            ] : null,
            'guardians' => $guardians,
        ];
    }

    /** آپدیت پروفایل خود کاربر - بدون کد ملی و نقش */
    public static function updateOwnProfile(array $data): array
    {
        $userId = (int) Auth::id();
        $updateData = [];

        if (array_key_exists('full_name', $data)) {
            $v = trim((string) $data['full_name']);
            if ($v === '' || mb_strlen($v) < 3) throw new AppException('نام کامل معتبر نیست', 422);
            $updateData['full_name'] = $v;
        }
        if (array_key_exists('mobile', $data)) {
            $v = trim((string) $data['mobile']);
            if ($v !== '' && !preg_match('/^09[0-9]{9}$/', $v)) throw new AppException('شماره موبایل معتبر نیست', 422);
            $updateData['mobile'] = $v !== '' ? $v : null;
        }
        if (array_key_exists('email', $data)) {
            $v = trim((string) $data['email']);
            $updateData['email'] = $v !== '' ? $v : null;
        }

        if (!empty($updateData)) {
            UserRepository::update($userId, $updateData);
        }

        return self::profile();
    }

    /** آپدیت اطلاعات فرزند خود - بدون کد ملی */
    public static function updateOwnChild(int $childId, array $data): array
    {
        $userId = (int) Auth::id();
        $player = ClientRepository::playerForUser($userId);
        if (!$player || (int) $player['id'] !== $childId) {
            throw new AppException('دسترسی به این بازیکن ندارید', 403);
        }

        $updateData = [];
        if (array_key_exists('first_name', $data)) {
            $v = trim((string) $data['first_name']);
            if ($v === '') throw new AppException('نام معتبر نیست', 422);
            $updateData['first_name'] = $v;
        }
        if (array_key_exists('last_name', $data)) {
            $v = trim((string) $data['last_name']);
            if ($v === '') throw new AppException('نام خانوادگی معتبر نیست', 422);
            $updateData['last_name'] = $v;
        }
        if (array_key_exists('birth_date', $data)) {
            $v = trim((string) $data['birth_date']);
            if ($v !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) throw new AppException('تاریخ تولد معتبر نیست', 422);
                $updateData['birth_date'] = $v;
            }
        }
        if (array_key_exists('gender', $data)) {
            $v = trim((string) $data['gender']);
            if ($v !== '' && !in_array($v, ['male', 'female'], true)) throw new AppException('جنسیت معتبر نیست', 422);
            $updateData['gender'] = $v !== '' ? $v : null;
        }

        if (!empty($updateData)) {
            PlayerRepository::update($childId, $updateData);
        }

        return self::profile();
    }

    /** آپدیت سرپرست خود - بدون کد ملی */
    public static function updateOwnGuardian(int $guardianId, array $data): array
    {
        $userId = (int) Auth::id();
        $player = ClientRepository::playerForUser($userId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        $playerId = (int) $player['id'];
        $relation = GuardianPlayerRepository::findRelation($guardianId, $playerId);
        if (!$relation) throw new AppException('این سرپرست متعلق به شما نیست', 403);

        $updateData = [];
        if (array_key_exists('full_name', $data)) {
            $v = trim((string) $data['full_name']);
            if ($v === '') throw new AppException('نام سرپرست معتبر نیست', 422);
            $updateData['full_name'] = $v;
        }
        if (array_key_exists('mobile', $data)) {
            $v = trim((string) $data['mobile']);
            if ($v !== '' && !preg_match('/^09[0-9]{9}$/', $v)) throw new AppException('موبایل سرپرست معتبر نیست', 422);
            $updateData['mobile'] = $v !== '' ? $v : null;
        }
        if (array_key_exists('emergency_phone', $data)) {
            $v = trim((string) $data['emergency_phone']);
            $updateData['emergency_phone'] = $v !== '' ? $v : null;
        }
        if (array_key_exists('relation', $data)) {
            $v = trim((string) $data['relation']);
            $allowed = ['father', 'mother', 'grandfather', 'grandmother', 'uncle', 'aunt', 'guardian', 'other'];
            if ($v !== '' && !in_array($v, $allowed, true)) throw new AppException('نسبت معتبر نیست', 422);
            if ($v !== '') {
                GuardianPlayerRepository::updateRelation($guardianId, $playerId, ['relation' => $v]);
            }
        }

        if (!empty($updateData)) {
            GuardianRepository::update($guardianId, $updateData);
        }

        return self::profile();
    }

    public static function guardians(): array
    {
        $userId = (int) Auth::id();
        $player = ClientRepository::playerForUser($userId);
        if (!$player) return [];
        return GuardianPlayerRepository::guardiansForPlayer((int) $player['id']);
    }

    /** ارزیابی‌های عملکرد بازیکن جاری */
    public static function evaluations(): array
    {
        if (!Auth::hasRole('player')) return [];
        $player = ClientRepository::playerForUser((int) Auth::id());
        if (!$player) return [];
        return ClientRepository::evaluationsForPlayer((int) $player['id']);
    }
}
