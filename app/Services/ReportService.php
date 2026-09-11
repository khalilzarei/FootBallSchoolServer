<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\ReportRepository;
use DateTime;

class ReportService
{
    public static function dashboard(): array
    {
        return ReportRepository::dashboard();
    }

    public static function finance(): array
    {
        return ReportRepository::finance();
    }

    public static function debts(): array
    {
        return ReportRepository::debts();
    }

    public static function attendance(array $query): array
    {
        $sessionId = (int) ($query['session_id'] ?? 0);
        $classId = (int) ($query['class_id'] ?? 0);
        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $dateTo = trim((string) ($query['date_to'] ?? ''));

        if ($dateFrom !== '') self::validateDate($dateFrom);
        if ($dateTo !== '') self::validateDate($dateTo);

        return ReportRepository::attendance([
            'session_id' => $sessionId > 0 ? $sessionId : null,
            'class_id' => $classId > 0 ? $classId : null,
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
        ]);
    }

    public static function classes(): array
    {
        return ReportRepository::classes();
    }

    private static function validateDate(string $date): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new AppException('تاریخ معتبر نیست', 422);
    }
}