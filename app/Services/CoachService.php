<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\CoachRepository;

class CoachService
{
    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) throw new AppException('وضعیت معتبر نیست', 422);

        return CoachRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        return self::requireCoach($id);
    }

    public static function update(int $id, array $data): array
    {
        self::requireCoach($id);

        $updateData = [];

        foreach (['specialty', 'license_level', 'bio'] as $field) {
            if (array_key_exists($field, $data)) {
                $v = trim((string) $data[$field]);
                $updateData[$field] = $v !== '' ? $v : null;
            }
        }

        CoachRepository::update($id, $updateData);

        return self::requireCoach($id);
    }

    private static function requireCoach(int $id): array
    {
        $coach = CoachRepository::findById($id);
        if (!$coach) throw new AppException('مربی یافت نشد', 404);
        return $coach;
    }
}