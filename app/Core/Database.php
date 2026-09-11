<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function init(array $config): void
    {
        if (self::$pdo instanceof PDO) {
            return;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'] ?? 'localhost',
            $config['port'] ?? '3306',
            $config['database'] ?? ''
        );

        self::$pdo = new PDO(
            $dsn,
            $config['username'] ?? '',
            $config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public static function connection(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new AppException('اتصال دیتابیس اولیه نشده است', 500);
        }

        return self::$pdo;
    }
}