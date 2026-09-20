<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\AgeGroupRepository;
use DateTime;

class AgeGroupService
{
    private const STATUSES = ['active', 'inactive'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        return AgeGroupRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        return self::requireAgeGroup($id);
    }

    /**
     * بازیکنان عضو گروه سنی (بر اساس بازه تاریخ تولد)
     */
    public static function players(int $id): array
    {
        self::requireAgeGroup($id);

        return AgeGroupRepository::playersForAgeGroup($id);
    }

    public static function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان گروه سنی الزامی است', 422);

        $from = trim((string) ($data['birth_date_from'] ?? ''));
        $to = trim((string) ($data['birth_date_to'] ?? ''));

        self::validateDate($from);
        self::validateDate($to);
        self::validateBirthRange($from, $to);

        if (AgeGroupRepository::hasOverlap($from, $to)) {
            throw new AppException('بازه تاریخی با گروه سنی فعال دیگر تداخل دارد', 422);
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $ageGroupId = AgeGroupRepository::create([
            'title' => $title,
            'birth_date_from' => $from,
            'birth_date_to' => $to,
            'min_age_at_cutoff' => self::normalizeOptionalInt($data['min_age_at_cutoff'] ?? null),
            'max_age_at_cutoff' => self::normalizeOptionalInt($data['max_age_at_cutoff'] ?? null),
            'sort_order' => self::normalizeSortOrder($data['sort_order'] ?? 0),
            'status' => $status,
        ]);

        return self::requireAgeGroup($ageGroupId);
    }

    public static function update(int $id, array $data): array
    {
        $ageGroup = self::requireAgeGroup($id);
        $updateData = [];

        if (array_key_exists('title', $data)) {
            $v = trim((string) $data['title']);
            if ($v === '') throw new AppException('عنوان معتبر نیست', 422);
            $updateData['title'] = $v;
        }

        if (array_key_exists('birth_date_from', $data)) {
            $v = trim((string) $data['birth_date_from']);
            self::validateDate($v);
            $updateData['birth_date_from'] = $v;
        }

        if (array_key_exists('birth_date_to', $data)) {
            $v = trim((string) $data['birth_date_to']);
            self::validateDate($v);
            $updateData['birth_date_to'] = $v;
        }

        foreach (['min_age_at_cutoff', 'max_age_at_cutoff'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalInt($data[$f]);
        }

        if (array_key_exists('sort_order', $data)) $updateData['sort_order'] = self::normalizeSortOrder($data['sort_order']);

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        $ff = $updateData['birth_date_from'] ?? $ageGroup['birth_date_from'];
        $ft = $updateData['birth_date_to'] ?? $ageGroup['birth_date_to'];

        self::validateBirthRange($ff, $ft);

        if (AgeGroupRepository::hasOverlap($ff, $ft, $id)) {
            throw new AppException('بازه تاریخی تداخل دارد', 422);
        }

        if (!empty($updateData)) AgeGroupRepository::update($id, $updateData);

        return self::requireAgeGroup($id);
    }

    public static function activate(int $id): array
    {
        self::requireAgeGroup($id);
        AgeGroupRepository::setStatus($id, 'active');
        return self::requireAgeGroup($id);
    }

    public static function deactivate(int $id): array
    {
        self::requireAgeGroup($id);
        AgeGroupRepository::setStatus($id, 'inactive');
        return self::requireAgeGroup($id);
    }

    private static function requireAgeGroup(int $id): array
    {
        $ag = AgeGroupRepository::findById($id);
        if (!$ag) throw new AppException('گروه سنی یافت نشد', 404);
        return $ag;
    }

    private static function validateDate(string $date): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new AppException('تاریخ معتبر نیست', 422);
    }

    private static function validateBirthRange(string $from, string $to): void
    {
        if (new DateTime($to) < new DateTime($from)) throw new AppException('بازه تاریخی معتبر نیست', 422);
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('مقدار سن باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0 || $v > 100) throw new AppException('مقدار سن معتبر نیست', 422);
        return $v;
    }

    private static function normalizeSortOrder(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('ترتیب باید عدد صحیح باشد', 422);
        return max(0, (int) $value);
    }
}