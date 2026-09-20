<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\ClassRepository;
use App\Repositories\ClassScheduleRepository;
use App\Repositories\SessionRepository;
use DateTime;

class SessionService
{
    private const STATUSES = ['scheduled', 'completed', 'cancelled', 'makeup'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $classId = (int) ($query['class_id'] ?? 0);
        $status = trim((string) ($query['status'] ?? ''));
        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $dateTo = trim((string) ($query['date_to'] ?? ''));

        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت جلسه معتبر نیست', 422);
        if ($dateFrom !== '') self::validateDate($dateFrom);
        if ($dateTo !== '') self::validateDate($dateTo);

        return SessionRepository::paginate([
            'class_id' => $classId > 0 ? $classId : null,
            'status' => $status !== '' ? $status : null,
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        return self::requireSession($id);
    }

    public static function create(array $data): array
    {
        $classId = (int) ($data['class_id'] ?? 0);
        if ($classId <= 0) throw new AppException('شناسه کلاس معتبر نیست', 422);

        $class = ClassRepository::findById($classId);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);

        $sessionDate = trim((string) ($data['session_date'] ?? ''));
        $startTime = self::normalizeTime($data['start_time'] ?? null, 'ساعت شروع');
        $endTime = self::normalizeTime($data['end_time'] ?? null, 'ساعت پایان');

        self::validateDate($sessionDate);
        self::validateTimeRange($startTime, $endTime);

        if (SessionRepository::exists($classId, $sessionDate, $startTime)) {
            throw new AppException('این جلسه قبلاً ثبت شده است', 422);
        }

        $status = (string) ($data['status'] ?? 'scheduled');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $sessionId = SessionRepository::create([
            'class_id' => $classId,
            'session_date' => $sessionDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => trim((string) ($data['location'] ?? '')) ?: ($class['location'] ?? null),
            'status' => $status,
            'topic' => trim((string) ($data['topic'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return self::requireSession($sessionId);
    }

    public static function update(int $id, array $data): array
    {
        $session = self::requireSession($id);
        $updateData = [];

        if (array_key_exists('session_date', $data)) {
            $v = trim((string) $data['session_date']);
            self::validateDate($v);
            $updateData['session_date'] = $v;
        }

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

        if (array_key_exists('topic', $data)) {
            $v = trim((string) $data['topic']);
            $updateData['topic'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $updateData['notes'] = $v !== '' ? $v : null;
        }

        $fsd = $updateData['session_date'] ?? $session['session_date'];
        $fst = $updateData['start_time'] ?? $session['start_time'];
        $fet = $updateData['end_time'] ?? $session['end_time'];

        self::validateTimeRange($fst, $fet);

        if (SessionRepository::exists((int) $session['class_id'], $fsd, $fst, $id)) {
            throw new AppException('این جلسه قبلاً ثبت شده است', 422);
        }

        if (!empty($updateData)) SessionRepository::update($id, $updateData);

        return self::requireSession($id);
    }

    public static function cancel(int $id): array
    {
        self::requireSession($id);
        SessionRepository::setStatus($id, 'cancelled');
        return self::requireSession($id);
    }

    public static function complete(int $id): array
    {
        self::requireSession($id);
        SessionRepository::setStatus($id, 'completed');
        return self::requireSession($id);
    }

    public static function generate(array $data): array
    {
        $classId = (int) ($data['class_id'] ?? 0);
        if ($classId <= 0) throw new AppException('شناسه کلاس معتبر نیست', 422);

        $class = ClassRepository::findById($classId);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        if ($class['status'] !== 'active') throw new AppException('جلسات فقط برای کلاس فعال قابل تولید هستند', 422);

        // ─── بازه تولید: اختیاری ───
        // پیش‌فرض «از» = تاریخ شروع کلاس اگر در آینده باشد، وگرنه امروز
        // پیش‌فرض «تا» = تاریخ پایان کلاس
        $from = trim((string) ($data['from_date'] ?? ''));
        $to = trim((string) ($data['to_date'] ?? ''));

        if ($from === '') {
            $today = date('Y-m-d');
            $classStart = (string) ($class['start_date'] ?? '');
            $from = ($classStart !== '' && $classStart > $today) ? $classStart : $today;
        }

        if ($to === '') {
            $to = (string) ($class['end_date'] ?? '');
            if ($to === '') {
                throw new AppException('تاریخ پایان کلاس مشخص نیست؛ ابتدا در ویرایش کلاس تاریخ پایان را وارد کنید یا بازه را دستی انتخاب کنید', 422);
            }
        }

        self::validateDate($from);
        self::validateDate($to);

        $startDate = new DateTime($from);
        $endDate = new DateTime($to);

        if ($endDate < $startDate) throw new AppException('تاریخ پایان نمی‌تواند قبل از شروع باشد', 422);

        $schedules = ClassScheduleRepository::forClass($classId);

        $activeSchedules = array_filter($schedules, function ($s) {
            return $s['status'] === 'active';
        });

        if (empty($activeSchedules)) throw new AppException('برنامه هفتگی فعالی برای این کلاس وجود ندارد', 422);

        $weekdayMap = [6 => 1, 7 => 2, 1 => 3, 2 => 4, 3 => 5, 4 => 6, 5 => 7];

        $created = 0;
        $current = clone $startDate;

        while ($current <= $endDate) {
            $ourWeekday = $weekdayMap[(int) $current->format('N')];

            foreach ($activeSchedules as $schedule) {
                if ((int) $schedule['weekday'] !== $ourWeekday) continue;

                $sessionDate = $current->format('Y-m-d');

                if (SessionRepository::exists($classId, $sessionDate, $schedule['start_time'])) continue;

                SessionRepository::create([
                    'class_id' => $classId,
                    'session_date' => $sessionDate,
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'location' => $schedule['location'] ?? $class['location'],
                    'status' => 'scheduled',
                    'topic' => null,
                    'notes' => null,
                    'created_by' => Auth::id(),
                ]);

                $created++;
            }

            $current->modify('+1 day');
        }

        return [
            'created_sessions' => $created,
            'from_date' => $from,
            'to_date' => $to,
            'class_id' => $classId,
        ];
    }

    private static function requireSession(int $id): array
    {
        $session = SessionRepository::findById($id);
        if (!$session) throw new AppException('جلسه یافت نشد', 404);
        return $session;
    }

    private static function validateDate(string $date): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new AppException('تاریخ معتبر نیست', 422);
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
        if ($end <= $start) throw new AppException('ساعت پایان باید بعد از شروع باشد', 422);
    }
}