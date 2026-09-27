<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\AgeGroupRepository;
use App\Repositories\ClassRepository;
use App\Repositories\CoachRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\SeasonRepository;
use DateTime;

class ClassService
{
    private const STATUSES = ['active', 'inactive', 'archived'];
    private const PRICING_TYPES = ['monthly', 'session', 'both'];
    private const BILLING_CYCLES = ['monthly', 'seasonal', 'quarterly'];

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

        $pricingType = trim((string) ($data['pricing_type'] ?? '')) ?: 'monthly';
        if (!in_array($pricingType, self::PRICING_TYPES, true)) throw new AppException('نوع شهریه معتبر نیست', 422);

        $billingCycle = trim((string) ($data['billing_cycle'] ?? '')) ?: 'monthly';
        // quarterly معادل seasonal است (۳ ماهه)
        if ($billingCycle === 'quarterly') $billingCycle = 'seasonal';
        if (!in_array($billingCycle, self::BILLING_CYCLES, true) && $billingCycle !== 'seasonal') {
            // برای سازگاری، فقط monthly و seasonal را بپذیر
            if (!in_array($billingCycle, ['monthly','seasonal'], true)) throw new AppException('دوره پرداخت معتبر نیست', 422);
        }
        if ($billingCycle === 'quarterly') $billingCycle = 'seasonal';

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت کلاس معتبر نیست', 422);

        $seasonId = self::normalizeOptionalInt($data['season_id'] ?? null);
        $ageGroupId = self::normalizeOptionalInt($data['age_group_id'] ?? null);
        // چند گروه سنی: age_group_ids — اگر نبود، از age_group_id قدیمی استفاده می‌شود
        $ageGroupIds = self::normalizeAgeGroupIds($data['age_group_ids'] ?? null, $ageGroupId);
        $coachId = self::normalizeOptionalInt($data['coach_id'] ?? null);
        $assistantCoachId = self::normalizeOptionalInt($data['assistant_coach_id'] ?? null);

        self::assertReferences($ageGroupIds[0] ?? null, $coachId, $assistantCoachId, $seasonId);
        foreach ($ageGroupIds as $agId) {
            if (!AgeGroupRepository::findById($agId)) throw new AppException('گروه سنی یافت نشد', 404);
        }

        $capacity = self::normalizeCapacity($data['capacity'] ?? null);
        $monthlyFee = self::normalizeFee($data['monthly_fee'] ?? null);
        $seasonalFee = self::normalizeFee($data['seasonal_fee'] ?? null);
        $sessionFee = self::normalizeFee($data['session_fee'] ?? null);
        $registrationFee = self::normalizeFee($data['registration_fee'] ?? null);

        // اگر فصلی انتخاب شده ولی seasonal_fee خالی است، از monthly_fee*3 استفاده کن
        if ($billingCycle === 'seasonal' && $seasonalFee === 0 && $monthlyFee > 0) {
            $seasonalFee = $monthlyFee * 3;
        }
        // billing_months
        $billingMonths = $billingCycle === 'seasonal' ? 3 : 1;
        if (isset($data['billing_months'])) {
            $bm = (int) $data['billing_months'];
            if ($bm >= 1 && $bm <= 12) $billingMonths = $bm;
        }

        $startDate = self::normalizeOptionalDate($data['start_date'] ?? null);
        $endDate = self::normalizeOptionalDate($data['end_date'] ?? null);
        self::assertDateRange($startDate, $endDate);

        $classId = ClassRepository::create([
            'title' => $title,
            'season_id' => $seasonId,
            'age_group_id' => $ageGroupId,
            'age_group_ids' => $ageGroupIds,
            'coach_id' => $coachId,
            'assistant_coach_id' => $assistantCoachId,
            'capacity' => $capacity,
            'status' => $status,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'pricing_type' => $pricingType,
            'billing_cycle' => $billingCycle,
            'monthly_fee' => $monthlyFee,
            'seasonal_fee' => $seasonalFee,
            'billing_months' => $billingMonths,
            'session_fee' => $sessionFee,
            'registration_fee' => $registrationFee,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => Auth::id(),
        ]);

        /*
         * ساخت خودکار روم چت کلاس.
         * (idempotent و safe — خطای چت ساخت کلاس را خراب نمی‌کند)
         */
        ChatService::ensureClassRoom(
            $classId,
            Auth::id()
        );

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

        if (array_key_exists('billing_cycle', $data)) {
            $v = trim((string) $data['billing_cycle']);
            if ($v === 'quarterly') $v = 'seasonal';
            if (!in_array($v, ['monthly','seasonal'], true)) throw new AppException('دوره پرداخت معتبر نیست', 422);
            $updateData['billing_cycle'] = $v;
            $updateData['billing_months'] = $v === 'seasonal' ? 3 : 1;
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        foreach (['season_id', 'age_group_id', 'coach_id', 'assistant_coach_id'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalInt($data[$f]);
        }

        // چند گروه سنی — اولویت با لیست age_group_ids
        if (array_key_exists('age_group_ids', $data)) {
            $ids = self::normalizeAgeGroupIds($data['age_group_ids'], null);
            foreach ($ids as $agId) {
                if (!AgeGroupRepository::findById($agId)) throw new AppException('گروه سنی یافت نشد', 404);
            }
            $updateData['age_group_ids'] = $ids;
            unset($updateData['age_group_id']);
        }

        if (array_key_exists('capacity', $data)) $updateData['capacity'] = self::normalizeCapacity($data['capacity']);

        foreach (['monthly_fee', 'seasonal_fee', 'session_fee', 'registration_fee', 'billing_months'] as $f) {
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
        $fsn = $updateData['season_id'] ?? ($class['season_id'] !== null ? (int) $class['season_id'] : null);

        self::assertReferences($fag, $fc, $fac, $fsn);

        $fs = $updateData['start_date'] ?? $class['start_date'];
        $fe = $updateData['end_date'] ?? $class['end_date'];
        self::assertDateRange($fs, $fe);

        if (!empty($updateData)) ClassRepository::update($id, $updateData);

        /* همگام‌سازی روم چت کلاس (عنوان/مربی‌ها/اعضا) */
        ChatService::ensureClassRoom($id, Auth::id());

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

    public static function delete(int $id): void
    {
        self::requireClass($id);

        if (EnrollmentRepository::activeCount($id) > 0) {
            throw new AppException('این کلاس دارای ثبت‌نام فعال است؛ ابتدا ثبت‌نام‌ها را پایان دهید', 409);
        }

        ClassRepository::softDelete($id);
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

    /**
     * لیست شناسه‌های گروه سنی (حداکثر یکی: آرایه یا رشته جداشده با کاما).
     * اگر لیست خالی بود، به مقدار تک‌گانه $singleId برمی‌گردد.
     */
    private static function normalizeAgeGroupIds(mixed $value, ?int $singleId = null): array
    {
        $ids = [];

        if (is_string($value) && trim($value) !== '') {
            $value = array_filter(array_map('trim', explode(',', trim($value))));
        }

        if (is_array($value)) {
            foreach ($value as $v) {
                if (filter_var($v, FILTER_VALIDATE_INT) !== false && (int) $v > 0) {
                    $ids[(int) $v] = (int) $v;
                }
            }
        }

        if (empty($ids) && $singleId !== null) {
            $ids[$singleId] = $singleId;
        }

        return array_values($ids);
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

    private static function assertReferences(?int $ageGroupId, ?int $coachId, ?int $assistantCoachId, ?int $seasonId = null): void
    {
        if ($seasonId !== null && !SeasonRepository::findById($seasonId)) throw new AppException('فصل یافت نشد', 404);
        if ($ageGroupId !== null && !AgeGroupRepository::findById($ageGroupId)) throw new AppException('گروه سنی یافت نشد', 404);
        if ($coachId !== null && !CoachRepository::findById($coachId)) throw new AppException('مربی یافت نشد', 404);
        if ($assistantCoachId !== null && !CoachRepository::findById($assistantCoachId)) throw new AppException('مربی دستیار یافت نشد', 404);
        if ($coachId !== null && $assistantCoachId !== null && $coachId === $assistantCoachId) throw new AppException('مربی اصلی و دستیار نمی‌توانند یکسان باشند', 422);
    }
}