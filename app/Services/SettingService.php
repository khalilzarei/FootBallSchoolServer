<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\SettingRepository;

class SettingService
{
    private const VALUE_TYPES = ['string', 'number', 'boolean', 'json', 'date'];

    public static function all(): array
    {
        return SettingRepository::all();
    }

    public static function update(array $data): array
    {
        $settings = $data['settings'] ?? $data;

        if (!is_array($settings)) throw new AppException('ساختار تنظیمات معتبر نیست', 422);

        if (isset($settings[0])) {
            foreach ($settings as $item) {
                if (!is_array($item)) throw new AppException('ساختار آیتم تنظیمات معتبر نیست', 422);

                $key = trim((string) ($item['key'] ?? ''));
                if ($key === '') throw new AppException('کلید تنظیمات الزامی است', 422);

                self::upsert($key, $item['value'] ?? null, $item['value_type'] ?? 'string', $item['description'] ?? null);
            }

            return self::all();
        }

        foreach ($settings as $key => $value) {
            if (!is_string($key) || trim($key) === '') throw new AppException('کلید تنظیمات معتبر نیست', 422);
            self::upsert(trim($key), $value, 'string', null);
        }

        return self::all();
    }

    private static function upsert(string $key, mixed $value, string $valueType, ?string $description): void
    {
        if (!in_array($valueType, self::VALUE_TYPES, true)) throw new AppException('نوع مقدار تنظیمات معتبر نیست', 422);

        $normalizedValue = self::normalizeValue($value, $valueType);

        $existing = SettingRepository::findByKey($key);

        if ($existing) {
            SettingRepository::updateByKey($key, [
                'setting_value' => $normalizedValue,
                'value_type' => $valueType,
                'description' => $description ?? $existing['description'],
                'updated_by' => Auth::id(),
            ]);
            return;
        }

        SettingRepository::create([
            'setting_key' => $key,
            'setting_value' => $normalizedValue,
            'value_type' => $valueType,
            'description' => $description,
            'updated_by' => Auth::id(),
        ]);
    }

    private static function normalizeValue(mixed $value, string $valueType): ?string
    {
        if ($value === null || $value === '') return null;

        if ($valueType === 'json') {
            if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);

            $decoded = json_decode((string) $value, true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new AppException('مقدار JSON معتبر نیست', 422);

            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        if ($valueType === 'boolean') return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';

        if ($valueType === 'number') {
            if (!is_numeric($value)) throw new AppException('مقدار عددی معتبر نیست', 422);
            return (string) $value;
        }

        return (string) $value;
    }
}