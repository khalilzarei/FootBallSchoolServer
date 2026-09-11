<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\UserRepository;
use App\Repositories\UserTokenRepository;
use App\Services\AvatarService; // ← ایمپورت سرویس آواتار

class UserService
{
    private const ROLES = ['admin', 'coach', 'guardian'];
    private const STATUSES = ['active', 'inactive'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $role = trim((string) ($query['role'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($role !== '' && !in_array($role, self::ROLES, true)) throw new AppException('نقش کاربر معتبر نیست', 422);
        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت کاربر معتبر نیست', 422);

        $result = UserRepository::paginate([
            'role' => $role !== '' ? $role : null,
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);

        $result['items'] = array_map(fn($user) => self::sanitize($user), $result['items']);
        return $result;
    }

    public static function get(int $id): array
    {
        return self::sanitize(self::requireUser($id));
    }

    public static function create(array $data, int $createdBy, ?array $avatarFile = null): array
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') throw new AppException('نام و نام خانوادگی الزامی است', 422);

        $role = (string) ($data['role'] ?? '');
        if (!in_array($role, self::ROLES, true)) throw new AppException('نقش کاربر معتبر نیست', 422);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت کاربر معتبر نیست', 422);

        $mobile = trim((string) ($data['mobile'] ?? '')) ?: null;
        $nationalCode = trim((string) ($data['national_code'] ?? '')) ?: null;

        self::validateIdentifiers($mobile, $nationalCode);
        self::assertUniqueIdentifiers($mobile, $nationalCode);

        $password = trim((string) ($data['password'] ?? '')) ?: self::generatePassword();
        if (strlen($password) < 8) throw new AppException('رمز عبور باید حداقل ۸ کاراکتر باشد', 422);

        // پردازش آواتار
        $avatarPath = null;
        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $avatarPath = AvatarService::upload($avatarFile, 'users');
        }

        $userId = UserRepository::create([
            'full_name' => $fullName,
            'mobile' => $mobile,
            'national_code' => $nationalCode,
            'avatar_path' => $avatarPath,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
            'created_by' => $createdBy,
        ]);

        // ایجاد رکورد مرتبط در جداول فرعی
        $pdo = \App\Core\Database::connection();
        if ($role === 'coach') {
            $pdo->prepare('INSERT INTO football_coaches (user_id) VALUES (:user_id)')->execute(['user_id' => $userId]);
        } elseif ($role === 'guardian') {
            $pdo->prepare('INSERT INTO football_guardians (user_id) VALUES (:user_id)')->execute(['user_id' => $userId]);
        }

        $result = self::sanitize(UserRepository::findById($userId));
        if (empty($data['password'])) {
            $result['initial_password'] = $password;
        }
        return $result;
    }

    public static function update(int $id, array $data, ?array $avatarFile = null): array
    {
        $user = self::requireUser($id);
        $updateData = [];

        if (array_key_exists('full_name', $data)) {
            $v = trim((string) $data['full_name']);
            if ($v === '') throw new AppException('نام و نام خانوادگی معتبر نیست', 422);
            $updateData['full_name'] = $v;
        }
        if (array_key_exists('mobile', $data)) $updateData['mobile'] = trim((string) $data['mobile']) ?: null;
        if (array_key_exists('national_code', $data)) $updateData['national_code'] = trim((string) $data['national_code']) ?: null;
        
        if (array_key_exists('role', $data)) {
            $v = (string) $data['role'];
            if (!in_array($v, self::ROLES, true)) throw new AppException('نقش کاربر معتبر نیست', 422);
            $updateData['role'] = $v;
        }
        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت کاربر معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        $finalMobile = $updateData['mobile'] ?? $user['mobile'];
        $finalNationalCode = $updateData['national_code'] ?? $user['national_code'];
        self::validateIdentifiers($finalMobile, $finalNationalCode);
        self::assertUniqueIdentifiers($finalMobile, $finalNationalCode, $id);

        // پردازش آواتار جدید
        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $newAvatarPath = AvatarService::upload($avatarFile, 'users');
            if (!empty($user['avatar_path'])) AvatarService::delete($user['avatar_path']);
            $updateData['avatar_path'] = $newAvatarPath;
        }

        if (!empty($updateData)) UserRepository::update($id, $updateData);
        return self::sanitize(UserRepository::findById($id));
    }

    public static function resetPassword(int $id, array $data): array
    {
        if ($id === Auth::id()) throw new AppException('برای تغییر رمز خود از مسیر تغییر رمز استفاده کنید', 403);
        self::requireUser($id);

        $password = trim((string) ($data['password'] ?? '')) ?: self::generatePassword();
        if (strlen($password) < 8) throw new AppException('رمز عبور باید حداقل ۸ کاراکتر باشد', 422);

        UserRepository::resetPassword($id, password_hash($password, PASSWORD_DEFAULT));
        UserTokenRepository::revokeAllForUser($id);

        $result = ['user_id' => $id];
        if (empty($data['password'])) $result['new_password'] = $password;
        return $result;
    }

    public static function activate(int $id): array
    {
        if ($id === Auth::id()) throw new AppException('شما نمی‌توانید وضعیت حساب خودتان را تغییر دهید', 403);
        self::requireUser($id);
        UserRepository::setStatus($id, 'active');
        return self::sanitize(UserRepository::findById($id));
    }

    public static function deactivate(int $id): array
    {
        if ($id === Auth::id()) throw new AppException('شما نمی‌توانید حساب خودتان را غیرفعال کنید', 403);
        self::requireUser($id);
        UserRepository::setStatus($id, 'inactive');
        UserTokenRepository::revokeAllForUser($id);
        return self::sanitize(UserRepository::findById($id));
    }

    // متدهای مدیریت آواتار
    public static function updateOwnAvatar(int $userId, ?array $avatarFile): array
    {
        $user = self::requireUser($userId);
        if ($avatarFile === null || ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('فایل عکس الزامی است', 422);
        }
        $newPath = AvatarService::upload($avatarFile, 'users');
        if (!empty($user['avatar_path'])) AvatarService::delete($user['avatar_path']);
        UserRepository::updateAvatar($userId, $newPath);
        return self::sanitize(UserRepository::findById($userId));
    }

    public static function deleteOwnAvatar(int $userId): array
    {
        $user = self::requireUser($userId);
        if (!empty($user['avatar_path'])) AvatarService::delete($user['avatar_path']);
        UserRepository::updateAvatar($userId, null);
        return self::sanitize(UserRepository::findById($userId));
    }

    public static function updateAvatar(int $id, ?array $avatarFile): array
    {
        $user = self::requireUser($id);
        if ($avatarFile === null || ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('فایل عکس الزامی است', 422);
        }
        $newPath = AvatarService::upload($avatarFile, 'users');
        if (!empty($user['avatar_path'])) AvatarService::delete($user['avatar_path']);
        UserRepository::updateAvatar($id, $newPath);
        return self::sanitize(UserRepository::findById($id));
    }

    public static function deleteAvatar(int $id): array
    {
        $user = self::requireUser($id);
        if (!empty($user['avatar_path'])) AvatarService::delete($user['avatar_path']);
        UserRepository::updateAvatar($id, null);
        return self::sanitize(UserRepository::findById($id));
    }

    // متدهای کمکی
    private static function requireUser(int $id): array
    {
        $user = UserRepository::findById($id);
        if (!$user) throw new AppException('کاربر یافت نشد', 404);
        return $user;
    }

    private static function validateIdentifiers(?string $mobile, ?string $nationalCode): void
    {
        if (!$mobile && !$nationalCode) throw new AppException('حداقل یکی از موبایل یا کد ملی باید وارد شود', 422);
        if ($mobile !== null && !preg_match('/^[0-9]{10,15}$/', $mobile)) throw new AppException('شماره موبایل معتبر نیست', 422);
        if ($nationalCode !== null && !preg_match('/^[0-9]{10}$/', $nationalCode)) throw new AppException('کد ملی معتبر نیست', 422);
    }

    private static function assertUniqueIdentifiers(?string $mobile, ?string $nationalCode, ?int $exceptUserId = null): void
    {
        if ($mobile !== null && UserRepository::existsByMobile($mobile, $exceptUserId)) throw new AppException('این شماره موبایل قبلاً ثبت شده است', 422);
        if ($nationalCode !== null && UserRepository::existsByNationalCode($nationalCode, $exceptUserId)) throw new AppException('این کد ملی قبلاً ثبت شده است', 422);
    }

    private static function generatePassword(int $length = 12): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    private static function sanitize(?array $user): array
    {
        if (!$user) return [];
        unset($user['password_hash']);
        $user['avatar_url'] = AvatarService::getAvatarUrl($user['avatar_path'] ?? null, 'user');
        return $user;
    }
}