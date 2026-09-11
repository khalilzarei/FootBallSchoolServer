<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserTokenRepository;

class Auth
{
    private static ?array $user = null;

    public static function attempt(Request $request): bool
    {
        if (self::$user !== null) {
            return true;
        }

        $token = $request->bearerToken();

        if (!$token) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT
                t.id AS token_id,
                t.expires_at,
                u.*
            FROM football_user_tokens t
            INNER JOIN football_users u ON u.id = t.user_id
            WHERE t.token_hash = :token_hash
              AND t.revoked_at IS NULL
              AND t.expires_at > NOW()
              AND u.status = :status
              AND u.deleted_at IS NULL
            LIMIT 1
        ');

        $stmt->execute([
            'token_hash' => $tokenHash,
            'status' => 'active',
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        UserTokenRepository::touch((int) $row['token_id']);

        self::$user = $row;

        return true;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::$user['id']) ? (int) self::$user['id'] : null;
    }

    public static function role(): ?string
    {
        return self::$user['role'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }
}