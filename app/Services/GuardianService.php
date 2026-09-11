<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\GuardianPlayerRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\PlayerRepository;

class GuardianService
{
    private const RELATIONS = ['father', 'mother', 'grandfather', 'grandmother', 'guardian', 'other'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) {
            throw new AppException('وضعیت معتبر نیست', 422);
        }

        return GuardianRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        return self::requireGuardian($id);
    }

    public static function update(int $id, array $data): array
    {
        self::requireGuardian($id);

        $updateData = [];

        if (array_key_exists('address', $data)) {
            $address = trim((string) $data['address']);
            $updateData['address'] = $address !== '' ? $address : null;
        }

        if (array_key_exists('emergency_phone', $data)) {
            $phone = trim((string) $data['emergency_phone']);
            $updateData['emergency_phone'] = $phone !== '' ? $phone : null;
        }

        if (array_key_exists('notes', $data)) {
            $notes = trim((string) $data['notes']);
            $updateData['notes'] = $notes !== '' ? $notes : null;
        }

        GuardianRepository::update($id, $updateData);

        return self::requireGuardian($id);
    }

    public static function players(int $guardianId): array
    {
        self::requireGuardian($guardianId);
        return GuardianPlayerRepository::playersForGuardian($guardianId);
    }

    public static function attachPlayer(int $guardianId, array $data): array
    {
        self::requireGuardian($guardianId);

        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        $player = PlayerRepository::findById($playerId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        $relation = (string) ($data['relation'] ?? 'other');
        if (!in_array($relation, self::RELATIONS, true)) {
            throw new AppException('نسبت سرپرست با بازیکن معتبر نیست', 422);
        }

        GuardianPlayerRepository::attach($guardianId, $playerId, [
            'relation' => $relation,
            'is_primary' => !empty($data['is_primary']),
            'can_view_reports' => !empty($data['can_view_reports']) || !isset($data['can_view_reports']),
            'can_pay' => !empty($data['can_pay']) || !isset($data['can_pay']),
        ]);

        return [
            'guardian_id' => $guardianId,
            'player_id' => $playerId,
            'relation' => GuardianPlayerRepository::findRelation($guardianId, $playerId),
        ];
    }

    public static function detachPlayer(int $guardianId, int $playerId): array
    {
        self::requireGuardian($guardianId);

        $player = PlayerRepository::findById($playerId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        $relation = GuardianPlayerRepository::findRelation($guardianId, $playerId);
        if (!$relation || $relation['status'] !== 'active') {
            throw new AppException('ارتباط فعال بین این سرپرست و بازیکن یافت نشد', 404);
        }

        GuardianPlayerRepository::detach($guardianId, $playerId);

        return ['guardian_id' => $guardianId, 'player_id' => $playerId];
    }

    private static function requireGuardian(int $id): array
    {
        $guardian = GuardianRepository::findById($id);
        if (!$guardian) throw new AppException('سرپرست یافت نشد', 404);
        return $guardian;
    }
}