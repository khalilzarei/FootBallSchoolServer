<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\GuardianPlayerRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\PlayerRepository;

class GuardianService
{
    private const RELATIONS = ['father', 'mother', 'grandfather', 'grandmother', 'uncle', 'aunt', 'guardian', 'other'];

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

        $result = GuardianRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);

        $result['items'] = array_map(fn($g) => self::hydrateGuardianRow($g), $result['items']);
        return $result;
    }

    public static function get(int $id): array
    {
        return self::hydrateGuardianRow(self::requireGuardian($id));
    }

    public static function update(int $id, array $data): array
    {
        self::requireGuardian($id);

        $updateData = [];

        if (array_key_exists('full_name', $data)) {
            $v = trim((string) $data['full_name']);
            if ($v === '') throw new AppException('نام و نام خانوادگی سرپرست الزامی است', 422);
            $updateData['full_name'] = $v;
        }
        if (array_key_exists('mobile', $data)) {
            $v = trim((string) $data['mobile']);
            $updateData['mobile'] = $v !== '' ? $v : null;
        }
        if (array_key_exists('national_code', $data)) {
            $v = trim((string) $data['national_code']);
            $updateData['national_code'] = $v !== '' ? $v : null;
        }

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

        return self::hydrateGuardianRow(self::requireGuardian($id));
    }

    public static function players(int $guardianId): array
    {
        self::requireGuardian($guardianId);

        $out = [];
        foreach (GuardianPlayerRepository::playersForGuardian($guardianId) as $r) {
            $out[] = [
                'guardian_id' => (int) $guardianId,
                'player_id' => (int) $r['id'],
                'relation' => (string) $r['relation'],
                'is_primary' => (bool) $r['is_primary'],
                'can_view_reports' => (bool) $r['can_view_reports'],
                'can_pay' => (bool) $r['can_pay'],
                'player' => self::playerSummary($r),
            ];
        }
        return $out;
    }

    public static function attachPlayer(int $guardianId, array $data): array
    {
        self::requireGuardian($guardianId);

        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        $player = PlayerRepository::findById($playerId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        // هر بازیکن فقط یک سرپرست فعال دارد
        foreach (GuardianPlayerRepository::guardiansForPlayer($playerId) as $r) {
            if ((int) $r['guardian_id'] !== $guardianId) {
                throw new AppException('این بازیکن قبلاً سرپرست دارد. برای تعویض سرپرست، ابتدا سرپرست فعلی را حذف کنید', 422);
            }
        }

        $relation = (string) ($data['relation'] ?? 'other');
        if (!in_array($relation, self::RELATIONS, true)) {
            throw new AppException('نسبت سرپرست با بازیکن معتبر نیست', 422);
        }

        GuardianPlayerRepository::attach($guardianId, $playerId, [
            'relation' => $relation,
            'is_primary' => true,
            'can_view_reports' => !empty($data['can_view_reports']) || !isset($data['can_view_reports']),
            'can_pay' => !empty($data['can_pay']) || !isset($data['can_pay']),
        ]);

        $rel = GuardianPlayerRepository::findRelation($guardianId, $playerId);

        return [
            'guardian_player' => [
                'guardian_id' => $guardianId,
                'player_id' => $playerId,
                'relation' => (string) ($rel['relation'] ?? $relation),
                'is_primary' => (bool) ($rel['is_primary'] ?? false),
                'can_view_reports' => (bool) ($rel['can_view_reports'] ?? false),
                'can_pay' => (bool) ($rel['can_pay'] ?? false),
                'player' => self::playerSummary($player),
            ],
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

    /**
     * تبدیل ردیف تخت guardian (با فیلدهای user_*) به شکل GuardianDto اپ
     * — آبجکت user تو در تو با bool واقعی و avatar_url
     */
    private static function hydrateGuardianRow(array $g): array
    {
        return [
            'id' => (int) $g['id'],
            'full_name' => (string) ($g['full_name'] ?? ''),
            'mobile' => $g['mobile'] ?? null,
            'national_code' => $g['national_code'] ?? null,
            'address' => $g['address'] ?? null,
            'emergency_phone' => $g['emergency_phone'] ?? null,
            'notes' => $g['notes'] ?? null,
            'created_at' => $g['created_at'] ?? null,
            'updated_at' => $g['updated_at'] ?? null,
            // سرپرست حساب کاربری ندارد — شیء user صرفاً برای سازگاری شکل خروجی با اپ ساخته می‌شود
            'user_id' => 0,
            'user' => [
                'id' => 0,
                'full_name' => (string) ($g['full_name'] ?? ''),
                'mobile' => $g['mobile'] ?? null,
                'national_code' => $g['national_code'] ?? null,
                'role' => 'guardian',
                'status' => 'active',
                'avatar_url' => AvatarService::getAvatarUrl(null, 'user'),
            ],
        ];
    }

    /**
     * خلاصه‌ی بازیکن برای پاسخ‌های سمت سرپرست — همساخت PlayerDto اپ
     */
    private static function playerSummary(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'first_name' => (string) ($p['first_name'] ?? ''),
            'last_name' => (string) ($p['last_name'] ?? ''),
            'full_name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
            'national_code' => $p['national_code'] ?? null,
            'birth_date' => $p['birth_date'] ?? null,
            'age' => isset($p['age']) ? (int) $p['age'] : null,
            'gender' => $p['gender'] ?? null,
            'status' => $p['status'] ?? 'active',
            'avatar_path' => $p['avatar_path'] ?? null,
            'avatar_url' => AvatarService::getAvatarUrl($p['avatar_path'] ?? null, 'player'),
        ];
    }
}