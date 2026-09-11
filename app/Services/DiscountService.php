<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\DiscountRepository;
use DateTime;

class DiscountService
{
    private const DISCOUNT_TYPES = ['percent', 'fixed'];
    private const APPLIES_TO = ['any', 'registration', 'monthly', 'session'];
    private const STATUSES = ['active', 'inactive'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        return DiscountRepository::paginate([
            'status' => trim((string) ($query['status'] ?? '')) ?: null,
            'q' => trim((string) ($query['q'] ?? '')) ?: null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        $discount = DiscountRepository::findById($id);
        if (!$discount) throw new AppException('تخفیف یافت نشد', 404);
        return $discount;
    }

    public static function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان تخفیف الزامی است', 422);

        $discountType = (string) ($data['discount_type'] ?? '');
        if (!in_array($discountType, self::DISCOUNT_TYPES, true)) throw new AppException('نوع تخفیف معتبر نیست', 422);

        $value = self::normalizeValue($data['value'] ?? null, $discountType);

        $appliesTo = (string) ($data['applies_to'] ?? 'any');
        if (!in_array($appliesTo, self::APPLIES_TO, true)) throw new AppException('محل اعمال تخفیف معتبر نیست', 422);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $startDate = self::normalizeOptionalDate($data['start_date'] ?? null);
        $endDate = self::normalizeOptionalDate($data['end_date'] ?? null);
        self::assertDateRange($startDate, $endDate);

        $discountId = DiscountRepository::create([
            'title' => $title,
            'discount_type' => $discountType,
            'value' => $value,
            'applies_to' => $appliesTo,
            'auto_apply' => !empty($data['auto_apply']) ? 1 : 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return self::get($discountId);
    }

    public static function update(int $id, array $data): array
    {
        $discount = self::get($id);
        $updateData = [];

        if (array_key_exists('title', $data)) {
            $v = trim((string) $data['title']);
            if ($v === '') throw new AppException('عنوان معتبر نیست', 422);
            $updateData['title'] = $v;
        }

        if (array_key_exists('discount_type', $data)) {
            $v = (string) $data['discount_type'];
            if (!in_array($v, self::DISCOUNT_TYPES, true)) throw new AppException('نوع تخفیف معتبر نیست', 422);
            $updateData['discount_type'] = $v;
        }

        if (array_key_exists('value', $data)) {
            $finalType = $updateData['discount_type'] ?? $discount['discount_type'];
            $updateData['value'] = self::normalizeValue($data['value'], $finalType);
        }

        if (array_key_exists('applies_to', $data)) {
            $v = (string) $data['applies_to'];
            if (!in_array($v, self::APPLIES_TO, true)) throw new AppException('محل اعمال معتبر نیست', 422);
            $updateData['applies_to'] = $v;
        }

        if (array_key_exists('auto_apply', $data)) $updateData['auto_apply'] = !empty($data['auto_apply']) ? 1 : 0;

        foreach (['start_date', 'end_date'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalDate($data[$f]);
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        if (array_key_exists('description', $data)) {
            $v = trim((string) $data['description']);
            $updateData['description'] = $v !== '' ? $v : null;
        }

        $fs = $updateData['start_date'] ?? $discount['start_date'];
        $fe = $updateData['end_date'] ?? $discount['end_date'];
        self::assertDateRange($fs, $fe);

        if (!empty($updateData)) DiscountRepository::update($id, $updateData);

        return self::get($id);
    }

    public static function activate(int $id): array
    {
        self::get($id);
        DiscountRepository::setStatus($id, 'active');
        return self::get($id);
    }

    public static function deactivate(int $id): array
    {
        self::get($id);
        DiscountRepository::setStatus($id, 'inactive');
        return self::get($id);
    }

    private static function normalizeValue(mixed $value, string $discountType): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('مقدار تخفیف باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('مقدار تخفیف نمی‌تواند منفی باشد', 422);
        if ($discountType === 'percent' && $v > 100) throw new AppException('درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد', 422);
        return $v;
    }

    private static function normalizeOptionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = (string) $value;
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function assertDateRange(?string $start, ?string $end): void
    {
        if ($start === null || $end === null) return;
        if (new DateTime($end) < new DateTime($start)) throw new AppException('تاریخ پایان نمی‌تواند قبل از شروع باشد', 422);
    }
}