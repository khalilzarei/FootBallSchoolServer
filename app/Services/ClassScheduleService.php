<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\ClassRepository;
use App\Repositories\ClassScheduleRepository;

class ClassScheduleService
{
    private const STATUSES = ['active', 'inactive'];

    public static function listForClass(int $classId): array
    {
        self::requireClass($classId);
        return ClassScheduleRepository::forClass($classId);
    }

    public static function create(int $classId, array $data): array
    {
        self::requireClass($classId);

        $weekday = self::normalizeWeekday($data['weekday'] ?? null);
        $startTime = self::normalizeTime($data['start_time'] ?? null, 'ساعت شروع');
        $endTime = self::normalizeTime($data['end_time'] ?? null, 'ساعت پایان');

        self::validateTimeRange($startTime, $endTime);

        if (ClassScheduleRepository::existsDuplicate($classId, $weekday, $startTime)) {
            throw new AppException('این روز و ساعت قبلاً ثبت شده است', 422);
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $scheduleId = ClassScheduleRepository::create([
            'class_id' => $classId,
            'weekday' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'status' => $status,
        ]);

        return ClassScheduleRepository::findById($scheduleId);
    }

    public static function update(int $scheduleId, array $data): array
    {
        $schedule = self::requireSchedule($scheduleId);
        $updateData = [];

        if (array_key_exists('weekday', $data)) $updateData['weekday'] = self::normalizeWeekday($data['weekday']);
        if (array_key_exists('start_time', $data)) $updateData['start_time'] = self::normalizeTime($data['start_time'], 'ساعت شروع');
        if (array_key_exists('end_time', $data)) $updateData['end_time'] = self::normalizeTime($data['end_time'], 'ساعت پایان');

        if (array_key_exists('location', $data)) {
            $v = trim((string) $data['location']);
            $updateData['location'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        $fw = $updateData['weekday'] ?? (int) $schedule['weekday'];
        $fst = $updateData['start_time'] ?? $schedule['start_time'];
        $fet = $updateData['end_time'] ?? $schedule['end_time'];

        self::validateTimeRange($fst, $fet);

        if (ClassScheduleRepository::existsDuplicate((int) $schedule['class_id'], $fw, $fst, $scheduleId)) {
            throw new AppException('این روز و ساعت قبلاً ثبت شده است', 422);
        }

        if (!empty($updateData)) ClassScheduleRepository::update($scheduleId, $updateData);

        return ClassScheduleRepository::findById($scheduleId);
    }

    public static function activate(int $scheduleId): array
    {
        self::requireSchedule($scheduleId);
        ClassScheduleRepository::setStatus($scheduleId, 'active');
        return ClassScheduleRepository::findById($scheduleId);
    }

    public static function deactivate(int $scheduleId): array
    {
        self::requireSchedule($scheduleId);
        ClassScheduleRepository::setStatus($scheduleId, 'inactive');
        return ClassScheduleRepository::findById($scheduleId);
    }

    private static function requireClass(int $classId): array
    {
        $class = ClassRepository::findById($classId);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        return $class;
    }

    private static function requireSchedule(int $id): array
    {
        $schedule = ClassScheduleRepository::findById($id);
        if (!$schedule) throw new AppException('برنامه هفتگی یافت نشد', 404);
        return $schedule;
    }

    private static function normalizeWeekday(mixed $weekday): int
    {
        if (filter_var($weekday, FILTER_VALIDATE_INT) === false) throw new AppException('روز هفته معتبر نیست', 422);
        $v = (int) $weekday;
        if ($v < 1 || $v > 7) throw new AppException('روز هفته باید بین ۱ تا ۷ باشد', 422);
        return $v;
    }

    private static function normalizeTime(mixed $time, string $fieldName): string
    {
        $time = trim((string) $time);
        if ($time === '') throw new AppException("{$fieldName} الزامی است", 422);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) throw new AppException("{$fieldName} باید با فرمت HH:MM باشد", 422);
        return $time;
    }

    private static function validateTimeRange(string $start, string $end): void
    {
        if ($end <= $start) throw new AppException('ساعت پایان باید بعد از ساعت شروع باشد', 422);
    }
}