<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Config;
use App\Core\Request;
use App\Repositories\PlayerRepository;
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

        // رمز ورود: برای بازیکن روی رکورد خود بازیکن (football_players.password_hash)،
        // برای سایر نقش‌ها روی حساب کاربری (football_users.password_hash)
        $passwordHash = $user ? self::loginPasswordHash($user) : null;

        if ($passwordHash === null || !password_verify($password, $passwordHash)) {
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

    /**
     * هش رمز ورود کاربر:
     * بازیکن → ستون password_hash رکورد خود بازیکن (بازیکن حذف‌شده یا بدون رمز → null = ورود ناموفق)
     * سایر نقش‌ها → football_users.password_hash
     */
    private static function loginPasswordHash(array $user): ?string
    {
        if (($user['role'] ?? '') !== 'player') {
            return $user['password_hash'] ?? null;
        }

        $player = PlayerRepository::findByUserId((int) $user['id']);

        return $player['password_hash'] ?? null;
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

        $currentHash = self::loginPasswordHash($user);

        if ($currentHash === null || !password_verify($oldPassword, $currentHash)) {
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

        // بازیکن: رمز روی رکورد خود بازیکن (منبع اصلی رمز بازیکن) هم همگام می‌شود
        if (($user['role'] ?? '') === 'player') {
            PlayerRepository::updatePasswordByUserId($userId, $newPasswordHash);
        }

        UserTokenRepository::revokeAllForUserExcept($userId, $currentTokenId);
    }

    public static function logout(int $tokenId): void
    {
        UserTokenRepository::revoke($tokenId);
    }
}