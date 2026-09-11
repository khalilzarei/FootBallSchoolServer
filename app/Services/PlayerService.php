<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\GuardianPlayerRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\PlayerRepository;
use App\Services\AvatarService; // ← ایمپورت سرویس آواتار
use DateTime;

class PlayerService
{
    private const STATUSES = ['active', 'inactive', 'archived'];
    private const GENDERS = ['male', 'female'];
    private const RELATIONS = ['father', 'mother', 'grandfather', 'grandmother', 'guardian', 'other'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            throw new AppException('وضعیت بازیکن معتبر نیست', 422);
        }

        $result = PlayerRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);

        // افزودن avatar_url به تمام آیتم‌های لیست
        $result['items'] = array_map(fn($player) => self::addAvatarUrl($player), $result['items']);

        return $result;
    }

    public static function get(int $id): array
    {
        return self::addAvatarUrl(self::requirePlayer($id));
    }

    public static function create(array $data, ?array $avatarFile = null): array // ← پارامتر آواتار اضافه شد
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') throw new AppException('نام و نام خانوادگی بازیکن الزامی است', 422);

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        self::validateBirthDate($birthDate);

        $gender = trim((string) ($data['gender'] ?? '')) ?: null;
        if ($gender !== null && !in_array($gender, self::GENDERS, true)) throw new AppException('جنسیت معتبر نیست', 422);

        $nationalCode = trim((string) ($data['national_code'] ?? '')) ?: null;
        if ($nationalCode !== null) {
            if (!preg_match('/^[0-9]{10}$/', $nationalCode)) throw new AppException('کد ملی معتبر نیست', 422);
            if (PlayerRepository::existsByNationalCode($nationalCode)) throw new AppException('این کد ملی قبلاً ثبت شده است', 422);
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت بازیکن معتبر نیست', 422);

        // پردازش آواتار
        $avatarPath = null;
        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $avatarPath = AvatarService::upload($avatarFile, 'players');
        }

        $playerId = PlayerRepository::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'national_code' => $nationalCode,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'avatar_path' => $avatarPath, // ← ذخیره مسیر آواتار
            'medical_notes' => trim((string) ($data['medical_notes'] ?? '')) ?: null,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return self::get($playerId);
    }

    public static function update(int $id, array $data, ?array $avatarFile = null): array // ← پارامتر آواتار اضافه شد
    {
        $player = self::requirePlayer($id);
        $updateData = [];

        if (array_key_exists('first_name', $data)) {
            $v = trim((string) $data['first_name']);
            if ($v === '') throw new AppException('نام بازیکن معتبر نیست', 422);
            $updateData['first_name'] = $v;
        }
        if (array_key_exists('last_name', $data)) {
            $v = trim((string) $data['last_name']);
            if ($v === '') throw new AppException('نام خانوادگی معتبر نیست', 422);
            $updateData['last_name'] = $v;
        }
        if (array_key_exists('birth_date', $data)) {
            $v = trim((string) $data['birth_date']);
            self::validateBirthDate($v);
            $updateData['birth_date'] = $v;
        }
        if (array_key_exists('gender', $data)) {
            $v = trim((string) $data['gender']);
            if ($v === '') $updateData['gender'] = null;
            else {
                if (!in_array($v, self::GENDERS, true)) throw new AppException('جنسیت معتبر نیست', 422);
                $updateData['gender'] = $v;
            }
        }
        if (array_key_exists('national_code', $data)) {
            $updateData['national_code'] = trim((string) $data['national_code']) ?: null;
        }
        if (array_key_exists('medical_notes', $data)) {
            $updateData['medical_notes'] = trim((string) $data['medical_notes']) ?: null;
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = trim((string) $data['notes']) ?: null;
        }
        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        // پردازش آواتار جدید
        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $newAvatarPath = AvatarService::upload($avatarFile, 'players');
            if (!empty($player['avatar_path'])) {
                AvatarService::delete($player['avatar_path']); // حذف عکس قدیمی
            }
            $updateData['avatar_path'] = $newAvatarPath;
        }

        $finalNC = $updateData['national_code'] ?? $player['national_code'];
        if ($finalNC !== null) {
            if (!preg_match('/^[0-9]{10}$/', $finalNC)) throw new AppException('کد ملی معتبر نیست', 422);
            if (PlayerRepository::existsByNationalCode($finalNC, $id)) throw new AppException('کد ملی تکراری است', 422);
        }

        if (!empty($updateData)) {
            PlayerRepository::update($id, $updateData);
        }

        return self::get($id);
    }

    // متدهای مدیریت آواتار
    public static function updateAvatar(int $id, ?array $avatarFile): array
    {
        $player = self::requirePlayer($id);
        if ($avatarFile === null || ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('فایل عکس الزامی است', 422);
        }
        $newPath = AvatarService::upload($avatarFile, 'players');
        if (!empty($player['avatar_path'])) AvatarService::delete($player['avatar_path']);
        PlayerRepository::updateAvatar($id, $newPath);
        return self::get($id);
    }

    public static function deleteAvatar(int $id): array
    {
        $player = self::requirePlayer($id);
        if (!empty($player['avatar_path'])) AvatarService::delete($player['avatar_path']);
        PlayerRepository::updateAvatar($id, null);
        return self::get($id);
    }

    // متدهای کمکی
    private static function requirePlayer(int $id): array
    {
        $player = PlayerRepository::findById($id);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);
        return $player;
    }

    private static function addAvatarUrl(array $player): array
    {
        $player['avatar_url'] = AvatarService::getAvatarUrl($player['avatar_path'] ?? null, 'player');
        return $player;
    }

    private static function validateBirthDate(string $birthDate): void
    {
        if ($birthDate === '') throw new AppException('تاریخ تولد الزامی است', 422);
        $date = DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            throw new AppException('تاریخ تولد باید با فرمت Y-m-d و معتبر باشد', 422);
        }
        if ($date->getTimestamp() > time()) {
            throw new AppException('تاریخ تولد نمی‌تواند در آینده باشد', 422);
        }
    }
}