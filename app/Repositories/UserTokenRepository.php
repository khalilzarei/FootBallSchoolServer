<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class UserTokenRepository
{
    public static function create(
        int $userId,
        string $tokenHash,
        string $expiresAt,
        ?string $platform = null,
        ?string $deviceName = null,
        ?string $ipAddress = null
    ): int {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_user_tokens (
                user_id, token_hash, device_name, platform, ip_address, expires_at, created_at
            ) VALUES (
                :user_id, :token_hash, :device_name, :platform, :ip_address, :expires_at, NOW()
            )
        ');

        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'device_name' => $deviceName,
            'platform' => $platform,
            'ip_address' => $ipAddress,
            'expires_at' => $expiresAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function touch(int $tokenId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('UPDATE football_user_tokens SET last_used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $tokenId]);
    }

    public static function revoke(int $tokenId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_user_tokens SET revoked_at = NOW()
            WHERE id = :id AND revoked_at IS NULL
        ');

        $stmt->execute(['id' => $tokenId]);
    }

    public static function revokeAllForUser(int $userId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_user_tokens SET revoked_at = NOW()
            WHERE user_id = :user_id AND revoked_at IS NULL
        ');

        $stmt->execute(['user_id' => $userId]);
    }

    public static function revokeAllForUserExcept(int $userId, int $exceptTokenId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            UPDATE football_user_tokens SET revoked_at = NOW()
            WHERE user_id = :user_id AND id <> :except_token_id AND revoked_at IS NULL
        ');

        $stmt->execute(['user_id' => $userId, 'except_token_id' => $exceptTokenId]);
    }
}