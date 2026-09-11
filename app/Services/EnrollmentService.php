<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\PlayerRepository;
use DateTime;

class EnrollmentService
{
    private const STATUSES = ['pending', 'active', 'inactive', 'waitlist', 'completed'];

    public static function listForClass(int $classId, array $query): array
    {
        self::requireClass($classId);

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        return EnrollmentRepository::paginateForClass($classId, [
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);
    }

    public static function get(int $enrollmentId): array
    {
        return self::requireEnrollment($enrollmentId);
    }

    public static function create(int $classId, array $data): array
    {
        $class = self::requireClass($classId);

        if ($class['status'] !== 'active') throw new AppException('ثبت‌نام فقط در کلاس فعال امکان‌پذیر است', 422);

        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        $player = PlayerRepository::findById($playerId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        $existing = EnrollmentRepository::findActiveOrPending($classId, $playerId);
        if ($existing) throw new AppException('این بازیکن قبلاً در این کلاس ثبت‌نام دارد', 422);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $enrolledAt = self::normalizeRequiredDate($data['enrolled_at'] ?? date('Y-m-d'));
        $endedAt = self::normalizeOptionalDate($data['ended_at'] ?? null);
        self::assertDateRange($enrolledAt, $endedAt);

        if ($status === 'active') self::assertCapacity($class, $classId);

        $enrollmentId = EnrollmentRepository::create([
            'class_id' => $classId,
            'player_id' => $playerId,
            'status' => $status,
            'enrolled_at' => $enrolledAt,
            'ended_at' => $endedAt,
            'monthly_fee_override' => self::normalizeOptionalFee($data['monthly_fee_override'] ?? null),
            'session_fee_override' => self::normalizeOptionalFee($data['session_fee_override'] ?? null),
            'registration_fee_override' => self::normalizeOptionalFee($data['registration_fee_override'] ?? null),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return self::requireEnrollment($enrollmentId);
    }

    public static function update(int $enrollmentId, array $data): array
    {
        $enrollment = self::requireEnrollment($enrollmentId);
        $class = ClassRepository::findById((int) $enrollment['class_id']);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);

        $updateData = [];

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        if (array_key_exists('enrolled_at', $data)) $updateData['enrolled_at'] = self::normalizeRequiredDate($data['enrolled_at']);
        if (array_key_exists('ended_at', $data)) $updateData['ended_at'] = self::normalizeOptionalDate($data['ended_at']);

        foreach (['monthly_fee_override', 'session_fee_override', 'registration_fee_override'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalFee($data[$f]);
        }

        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $updateData['notes'] = $v !== '' ? $v : null;
        }

        $finalStatus = $updateData['status'] ?? $enrollment['status'];
        $finalEnrolledAt = $updateData['enrolled_at'] ?? $enrollment['enrolled_at'];
        $finalEndedAt = array_key_exists('ended_at', $updateData) ? $updateData['ended_at'] : $enrollment['ended_at'];

        self::assertDateRange($finalEnrolledAt, $finalEndedAt);

        if ($finalStatus === 'active') {
            if ($class['status'] !== 'active') throw new AppException('ثبت‌نام فعال فقط در کلاس فعال امکان‌پذیر است', 422);
            self::assertCapacity($class, (int) $enrollment['class_id'], $enrollmentId);
        }

        if (!empty($updateData)) EnrollmentRepository::update($enrollmentId, $updateData);

        return self::requireEnrollment($enrollmentId);
    }

    public static function activate(int $enrollmentId): array
    {
        $enrollment = self::requireEnrollment($enrollmentId);
        $class = ClassRepository::findById((int) $enrollment['class_id']);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        if ($class['status'] !== 'active') throw new AppException('کلاس فعال نیست', 422);

        self::assertCapacity($class, (int) $enrollment['class_id'], $enrollmentId);

        EnrollmentRepository::setStatus($enrollmentId, 'active');
        return self::requireEnrollment($enrollmentId);
    }

    public static function deactivate(int $enrollmentId): array
    {
        self::requireEnrollment($enrollmentId);
        EnrollmentRepository::setStatus($enrollmentId, 'inactive');
        return self::requireEnrollment($enrollmentId);
    }

    private static function requireClass(int $classId): array
    {
        $class = ClassRepository::findById($classId);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        return $class;
    }

    private static function requireEnrollment(int $id): array
    {
        $enrollment = EnrollmentRepository::findById($id);
        if (!$enrollment) throw new AppException('ثبت‌نام یافت نشد', 404);
        return $enrollment;
    }

    private static function assertCapacity(array $class, int $classId, ?int $exceptId = null): void
    {
        if ($class['capacity'] === null) return;
        $activeCount = EnrollmentRepository::activeCount($classId, $exceptId);
        if ($activeCount >= (int) $class['capacity']) throw new AppException('ظرفیت کلاس تکمیل است', 422);
    }

    private static function normalizeRequiredDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') $value = date('Y-m-d');
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function normalizeOptionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = (string) $value;
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function normalizeOptionalFee(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('مبلغ باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('مبلغ نمی‌تواند منفی باشد', 422);
        return $v;
    }

    private static function assertDateRange(string $enrolledAt, ?string $endedAt): void
    {
        if ($endedAt === null) return;
        if (new DateTime($endedAt) < new DateTime($enrolledAt)) throw new AppException('تاریخ پایان نمی‌تواند قبل از ثبت‌نام باشد', 422);
    }
}