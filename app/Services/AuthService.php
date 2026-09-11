<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Config;
use App\Core\Request;
use App\Repositories\UserRepository;
use App\Repositories\UserTokenRepository;

class AuthService
{
    public static function login(array $data, Request $request): array
    {
        $identifier = trim((string) ($data['identifier'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($identifier === '' || $password === '') {
            throw new AppException('اطلاعات ورودی کامل نیست', 422);
        }

        $user = UserRepository::findByIdentifier($identifier);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new AppException('نام کاربری یا رمز عبور اشتباه است', 401);
        }

        if ($user['status'] !== 'active') {
            throw new AppException('حساب کاربری فعال نیست', 403);
        }

        $lifetimeDays = (int) Config::get('app.token_lifetime_days', 30);
        $expiresAt = date('Y-m-d H:i:s', time() + ($lifetimeDays * 86400));

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $platform = $request->header('X-Platform');

        if (!in_array($platform, ['android', 'ios'], true)) {
            $platform = null;
        }

        $deviceName = $request->header('X-Device-Name');

        UserTokenRepository::create(
            (int) $user['id'],
            $tokenHash,
            $expiresAt,
            $platform,
            $deviceName,
            $request->ip()
        );

        UserRepository::updateLastLogin((int) $user['id']);

        unset($user['password_hash']);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => $user,
        ];
    }

    public static function changePassword(int $userId, int $currentTokenId, array $data): void
    {
        $user = UserRepository::findById($userId);

        if (!$user) {
            throw new AppException('کاربر یافت نشد', 404);
        }

        $oldPassword = (string) ($data['old_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');

        if ($oldPassword === '' || $newPassword === '') {
            throw new AppException('رمز عبور فعلی و جدید الزامی است', 422);
        }

        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new AppException('رمز عبور فعلی اشتباه است', 400);
        }

        if (strlen($newPassword) < 8) {
            throw new AppException('رمز عبور جدید باید حداقل ۸ کاراکتر باشد', 422);
        }

        if ($newPassword === $oldPassword) {
            throw new AppException('رمز عبور جدید نباید با رمز عبور فعلی یکسان باشد', 422);
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        UserRepository::updatePassword($userId, $newPasswordHash);
        UserRepository::clearMustChangePassword($userId);

        UserTokenRepository::revokeAllForUserExcept($userId, $currentTokenId);
    }

    public static function logout(int $tokenId): void
    {
        UserTokenRepository::revoke($tokenId);
    }
}