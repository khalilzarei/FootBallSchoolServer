<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\SeasonRepository;
use DateTime;

class SeasonService
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

        return SeasonRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        return self::requireSeason($id);
    }

    public static function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان فصل الزامی است', 422);

        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate = trim((string) ($data['end_date'] ?? ''));
        $ageCutoffDate = trim((string) ($data['age_cutoff_date'] ?? ''));

        self::validateDate($startDate);
        self::validateDate($endDate);
        self::validateDate($ageCutoffDate);
        self::validateDateRange($startDate, $endDate);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $seasonId = SeasonRepository::create([
            'title' => $title,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'age_cutoff_date' => $ageCutoffDate,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return self::requireSeason($seasonId);
    }

    public static function update(int $id, array $data): array
    {
        $season = self::requireSeason($id);
        $updateData = [];

        if (array_key_exists('title', $data)) {
            $v = trim((string) $data['title']);
            if ($v === '') throw new AppException('عنوان معتبر نیست', 422);
            $updateData['title'] = $v;
        }

        foreach (['start_date', 'end_date', 'age_cutoff_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $v = trim((string) $data[$field]);
                self::validateDate($v);
                $updateData[$field] = $v;
            }
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $updateData['notes'] = $v !== '' ? $v : null;
        }

        $fs = $updateData['start_date'] ?? $season['start_date'];
        $fe = $updateData['end_date'] ?? $season['end_date'];
        self::validateDateRange($fs, $fe);

        if (!empty($updateData)) SeasonRepository::update($id, $updateData);

        return self::requireSeason($id);
    }

    public static function activate(int $id): array
    {
        self::requireSeason($id);
        SeasonRepository::setStatus($id, 'active');
        return self::requireSeason($id);
    }

    public static function deactivate(int $id): array
    {
        self::requireSeason($id);
        SeasonRepository::setStatus($id, 'inactive');
        return self::requireSeason($id);
    }

    private static function requireSeason(int $id): array
    {
        $season = SeasonRepository::findById($id);
        if (!$season) throw new AppException('فصل یافت نشد', 404);
        return $season;
    }

    private static function validateDate(string $date): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new AppException('تاریخ باید معتبر و با فرمت Y-m-d باشد', 422);
    }

    private static function validateDateRange(string $start, string $end): void
    {
        if (new DateTime($end) < new DateTime($start)) throw new AppException('تاریخ پایان نمی‌تواند قبل از شروع باشد', 422);
    }
}