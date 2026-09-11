<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class SettingRepository
{
    public static function all(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_settings ORDER BY setting_key ASC');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function findByKey(string $key): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM football_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $setting = $stmt->fetch();

        return $setting ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO football_settings (setting_key, setting_value, value_type, description, updated_by, created_at)
            VALUES (:setting_key, :setting_value, :value_type, :description, :updated_by, NOW())
        ');

        $stmt->execute([
            'setting_key' => $data['setting_key'],
            'setting_value' => $data['setting_value'] ?? null,
            'value_type' => $data['value_type'] ?? 'string',
            'description' => $data['description'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateByKey(string $key, array $data): void
    {
        $pdo = Database::connection();

        $sets = [];
        $params = ['setting_key' => $key];

        $allowedFields = ['setting_value', 'value_type', 'description', 'updated_by'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sets[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare("UPDATE football_settings SET " . implode(', ', $sets) . " WHERE setting_key = :setting_key");
        $stmt->execute($params);
    }
}