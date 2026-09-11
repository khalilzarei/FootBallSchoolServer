<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\AgeGroupRepository;
use App\Repositories\ClassRepository;
use App\Repositories\CoachRepository;
use DateTime;

class ClassService
{
    private const STATUSES = ['active', 'inactive', 'archived'];
    private const PRICING_TYPES = ['monthly', 'session', 'both'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));
        $ageGroupId = (int) ($query['age_group_id'] ?? 0);
        $coachId = (int) ($query['coach_id'] ?? 0);

        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت کلاس معتبر نیست', 422);

        return ClassRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
            'age_group_id' => $ageGroupId > 0 ? $ageGroupId : null,
            'coach_id' => $coachId > 0 ? $coachId : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        return self::requireClass($id);
    }

    public static function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان کلاس الزامی است', 422);

        $pricingType = (string) ($data['pricing_type'] ?? 'monthly');
        if (!in_array($pricingType, self::PRICING_TYPES, true)) throw new AppException('نوع شهریه معتبر نیست', 422);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت کلاس معتبر نیست', 422);

        $ageGroupId = self::normalizeOptionalInt($data['age_group_id'] ?? null);
        $coachId = self::normalizeOptionalInt($data['coach_id'] ?? null);
        $assistantCoachId = self::normalizeOptionalInt($data['assistant_coach_id'] ?? null);

        self::assertReferences($ageGroupId, $coachId, $assistantCoachId);

        $capacity = self::normalizeCapacity($data['capacity'] ?? null);
        $monthlyFee = self::normalizeFee($data['monthly_fee'] ?? null);
        $sessionFee = self::normalizeFee($data['session_fee'] ?? null);
        $registrationFee = self::normalizeFee($data['registration_fee'] ?? null);

        $startDate = self::normalizeOptionalDate($data['start_date'] ?? null);
        $endDate = self::normalizeOptionalDate($data['end_date'] ?? null);
        self::assertDateRange($startDate, $endDate);

        $classId = ClassRepository::create([
            'title' => $title,
            'age_group_id' => $ageGroupId,
            'coach_id' => $coachId,
            'assistant_coach_id' => $assistantCoachId,
            'capacity' => $capacity,
            'status' => $status,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'pricing_type' => $pricingType,
            'monthly_fee' => $monthlyFee,
            'session_fee' => $sessionFee,
            'registration_fee' => $registrationFee,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => Auth::id(),
        ]);

        return self::requireClass($classId);
    }

    public static function update(int $id, array $data): array
    {
        $class = self::requireClass($id);
        $updateData = [];

        if (array_key_exists('title', $data)) {
            $v = trim((string) $data['title']);
            if ($v === '') throw new AppException('عنوان کلاس معتبر نیست', 422);
            $updateData['title'] = $v;
        }

        if (array_key_exists('pricing_type', $data)) {
            $v = (string) $data['pricing_type'];
            if (!in_array($v, self::PRICING_TYPES, true)) throw new AppException('نوع شهریه معتبر نیست', 422);
            $updateData['pricing_type'] = $v;
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        foreach (['age_group_id', 'coach_id', 'assistant_coach_id'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalInt($data[$f]);
        }

        if (array_key_exists('capacity', $data)) $updateData['capacity'] = self::normalizeCapacity($data['capacity']);

        foreach (['monthly_fee', 'session_fee', 'registration_fee'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeFee($data[$f]);
        }

        foreach (['start_date', 'end_date'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalDate($data[$f]);
        }

        if (array_key_exists('location', $data)) {
            $v = trim((string) $data['location']);
            $updateData['location'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('description', $data)) {
            $v = trim((string) $data['description']);
            $updateData['description'] = $v !== '' ? $v : null;
        }

        $fag = $updateData['age_group_id'] ?? ($class['age_group_id'] !== null ? (int) $class['age_group_id'] : null);
        $fc = $updateData['coach_id'] ?? ($class['coach_id'] !== null ? (int) $class['coach_id'] : null);
        $fac = $updateData['assistant_coach_id'] ?? ($class['assistant_coach_id'] !== null ? (int) $class['assistant_coach_id'] : null);

        self::assertReferences($fag, $fc, $fac);

        $fs = $updateData['start_date'] ?? $class['start_date'];
        $fe = $updateData['end_date'] ?? $class['end_date'];
        self::assertDateRange($fs, $fe);

        if (!empty($updateData)) ClassRepository::update($id, $updateData);

        return self::requireClass($id);
    }

    public static function activate(int $id): array
    {
        self::requireClass($id);
        ClassRepository::setStatus($id, 'active');
        return self::requireClass($id);
    }

    public static function deactivate(int $id): array
    {
        self::requireClass($id);
        ClassRepository::setStatus($id, 'inactive');
        return self::requireClass($id);
    }

    private static function requireClass(int $id): array
    {
        $class = ClassRepository::findById($id);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        return $class;
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('شناسه معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }

    private static function normalizeCapacity(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('ظرفیت باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 1) throw new AppException('ظرفیت باید حداقل ۱ باشد', 422);
        return $v;
    }

    private static function normalizeFee(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('مبلغ باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('مبلغ نمی‌تواند منفی باشد', 422);
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

    private static function assertReferences(?int $ageGroupId, ?int $coachId, ?int $assistantCoachId): void
    {
        if ($ageGroupId !== null && !AgeGroupRepository::findById($ageGroupId)) throw new AppException('گروه سنی یافت نشد', 404);
        if ($coachId !== null && !CoachRepository::findById($coachId)) throw new AppException('مربی یافت نشد', 404);
        if ($assistantCoachId !== null && !CoachRepository::findById($assistantCoachId)) throw new AppException('مربی دستیار یافت نشد', 404);
        if ($coachId !== null && $assistantCoachId !== null && $coachId === $assistantCoachId) throw new AppException('مربی اصلی و دستیار نمی‌توانند یکسان باشند', 422);
    }
}