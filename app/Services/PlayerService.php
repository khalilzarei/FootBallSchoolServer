<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\EnrollmentRepository;
use App\Repositories\GuardianPlayerRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\UserRepository;
use App\Services\AvatarService; // ← ایمپورت سرویس آواتار
use DateTime;

class PlayerService
{
    private const STATUSES = ['active', 'inactive', 'archived'];
    private const GENDERS = ['male', 'female'];
    private const RELATIONS = ['father', 'mother', 'grandfather', 'grandmother', 'guardian', 'other'];
    private const GUARDIAN_RELATIONS = ['father', 'mother', 'grandfather', 'grandmother', 'uncle', 'aunt', 'guardian', 'other'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            throw new AppException('وضعیت بازیکن معتبر نیست', 422);
        }

        $result = PlayerRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);

        // افزودن avatar_url، سرپرست‌ها و کلاس فعلی به تمام آیتم‌های لیست
        // (سرپرست‌ها برای دکمه‌های تماس/پیام؛ کلاس فعلی برای نمایش «کلاس: ...»)
        $playerIds = array_map(fn($p) => (int) $p['id'], $result['items']);
        $guardiansByPlayer = self::guardiansByPlayerId($playerIds);
        $currentClassByPlayer = EnrollmentRepository::currentClassByPlayerIds($playerIds);

        $result['items'] = array_map(
            fn($player) => self::addAvatarUrl(
                $player + [
                    'guardians' => $guardiansByPlayer[(int) $player['id']] ?? [],
                    'current_class' => $currentClassByPlayer[(int) $player['id']] ?? null,
                ]
            ),
            $result['items']
        );

        return $result;
    }

    public static function get(int $id): array
    {
        $player = self::addAvatarUrl(self::requirePlayer($id));
        $player['guardians'] = self::guardiansByPlayerId([$id])[(int) $id] ?? [];
        $player['current_class'] = EnrollmentRepository::currentClassByPlayerIds([$id])[(int) $id] ?? null;
        return $player;
    }

    public static function create(array $data, ?array $avatarFile = null): array // ← پارامتر آواتار اضافه شد
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') throw new AppException('نام و نام خانوادگی بازیکن الزامی است', 422);

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        self::validateBirthDate($birthDate);

        $gender = trim((string) ($data['gender'] ?? '')) ?: null;
        if ($gender !== null && !in_array($gender, self::GENDERS, true)) throw new AppException('جنسیت معتبر نیست', 422);

        $nationalCode = trim((string) ($data['national_code'] ?? '')) ?: null;
        if ($nationalCode !== null) {
            if (!preg_match('/^[0-9]{10}$/', $nationalCode)) throw new AppException('کد ملی معتبر نیست', 422);
            if (PlayerRepository::existsByNationalCode($nationalCode)) throw new AppException('این کد ملی قبلاً ثبت شده است', 422);
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت بازیکن معتبر نیست', 422);

        // پردازش آواتار
        $avatarPath = null;
        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $avatarPath = AvatarService::upload($avatarFile, 'players');
        }

        $playerId = PlayerRepository::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'national_code' => $nationalCode,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'avatar_path' => $avatarPath, // ← ذخیره مسیر آواتار
            'medical_notes' => trim((string) ($data['medical_notes'] ?? '')) ?: null,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        // حساب ورود بازیکن (اگر کد ملی دارد) — کاربر با نقش player؛ شناسه ورود = کد ملی
        self::createPlayerLogin($playerId, $firstName . ' ' . $lastName, $nationalCode);

        return self::get($playerId);
    }

    public static function update(int $id, array $data, ?array $avatarFile = null): array // ← پارامتر آواتار اضافه شد
    {
        $player = self::requirePlayer($id);
        $updateData = [];

        if (array_key_exists('first_name', $data)) {
            $v = trim((string) $data['first_name']);
            if ($v === '') throw new AppException('نام بازیکن معتبر نیست', 422);
            $updateData['first_name'] = $v;
        }
        if (array_key_exists('last_name', $data)) {
            $v = trim((string) $data['last_name']);
            if ($v === '') throw new AppException('نام خانوادگی معتبر نیست', 422);
            $updateData['last_name'] = $v;
        }
        if (array_key_exists('birth_date', $data)) {
            $v = trim((string) $data['birth_date']);
            self::validateBirthDate($v);
            $updateData['birth_date'] = $v;
        }
        if (array_key_exists('gender', $data)) {
            $v = trim((string) $data['gender']);
            if ($v === '') $updateData['gender'] = null;
            else {
                if (!in_array($v, self::GENDERS, true)) throw new AppException('جنسیت معتبر نیست', 422);
                $updateData['gender'] = $v;
            }
        }
        if (array_key_exists('national_code', $data)) {
            $updateData['national_code'] = trim((string) $data['national_code']) ?: null;
        }
        if (array_key_exists('medical_notes', $data)) {
            $updateData['medical_notes'] = trim((string) $data['medical_notes']) ?: null;
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = trim((string) $data['notes']) ?: null;
        }
        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        // پردازش آواتار جدید
        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $newAvatarPath = AvatarService::upload($avatarFile, 'players');
            if (!empty($player['avatar_path'])) {
                AvatarService::delete($player['avatar_path']); // حذف عکس قدیمی
            }
            $updateData['avatar_path'] = $newAvatarPath;
        }

        $finalNC = $updateData['national_code'] ?? $player['national_code'];
        if ($finalNC !== null) {
            if (!preg_match('/^[0-9]{10}$/', $finalNC)) throw new AppException('کد ملی معتبر نیست', 422);
            if (PlayerRepository::existsByNationalCode($finalNC, $id)) throw new AppException('کد ملی تکراری است', 422);
        }

        if (!empty($updateData)) {
            PlayerRepository::update($id, $updateData);
        }

        // همگام‌سازی حساب ورود بازیکن با تغییرات (نام/کد ملی) — و ساخت حساب اگر هنوز ندارد
        self::syncPlayerLogin($id, $player, $updateData, $finalNC);

        return self::get($id);
    }

    // متدهای مدیریت آواتار
    public static function updateAvatar(int $id, ?array $avatarFile): array
    {
        $player = self::requirePlayer($id);
        if ($avatarFile === null || ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppException('فایل عکس الزامی است', 422);
        }
        $newPath = AvatarService::upload($avatarFile, 'players');
        if (!empty($player['avatar_path'])) AvatarService::delete($player['avatar_path']);
        PlayerRepository::updateAvatar($id, $newPath);
        return self::get($id);
    }

    public static function deleteAvatar(int $id): array
    {
        $player = self::requirePlayer($id);
        if (!empty($player['avatar_path'])) AvatarService::delete($player['avatar_path']);
        PlayerRepository::updateAvatar($id, null);
        return self::get($id);
    }

    // ═══════════════════════════════════════════
    // سرپرستان بازیکن
    // ═══════════════════════════════════════════

    public static function guardians(int $playerId): array
    {
        self::requirePlayer($playerId);
        return self::hydrateGuardianRows(GuardianPlayerRepository::guardiansForPlayer($playerId));
    }

    public static function attachGuardian(int $playerId, array $data): array
    {
        self::requirePlayer($playerId);

        $guardianId = (int) ($data['guardian_id'] ?? 0);
        $guardian = GuardianRepository::findById($guardianId);
        if (!$guardian) throw new AppException('سرپرست یافت نشد', 404);

        // هر بازیکن فقط یک سرپرست فعال دارد
        self::assertNoGuardian($playerId, $guardianId);

        $relation = (string) ($data['relation'] ?? 'other');
        if (!in_array($relation, self::GUARDIAN_RELATIONS, true)) {
            throw new AppException('نسبت سرپرست با بازیکن معتبر نیست', 422);
        }

        GuardianPlayerRepository::attach($guardianId, $playerId, [
            'relation' => $relation,
            'is_primary' => true,
            'can_view_reports' => !empty($data['can_view_reports']) || !isset($data['can_view_reports']),
            'can_pay' => !empty($data['can_pay']) || !isset($data['can_pay']),
        ]);

        foreach (self::hydrateGuardianRows(GuardianPlayerRepository::guardiansForPlayer($playerId)) as $r) {
            if ((int) $r['guardian_id'] === $guardianId) {
                return ['guardian_player' => $r];
            }
        }

        return ['guardian_player' => null];
    }

    public static function detachGuardian(int $playerId, int $guardianId): array
    {
        self::requirePlayer($playerId);

        $relation = GuardianPlayerRepository::findRelation($guardianId, $playerId);
        if (!$relation || $relation['status'] !== 'active') {
            throw new AppException('ارتباط فعال بین این سرپرست و بازیکن یافت نشد', 404);
        }

        GuardianPlayerRepository::detach($guardianId, $playerId);

        return ['guardian_id' => $guardianId, 'player_id' => $playerId];
    }

    /**
     * ساخت سرپرست جدید (رکورد تماس — بدون حساب کاربری) و اتصال به بازیکن در یک عملیات.
     */
    public static function attachNewGuardian(int $playerId, array $data): array
    {
        self::requirePlayer($playerId);

        // هر بازیکن فقط یک سرپرست فعال دارد
        self::assertNoGuardian($playerId);

        $fullName = trim((string) ($data['full_name'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        if ($fullName === '') throw new AppException('نام و نام خانوادگی سرپرست الزامی است', 422);
        if ($mobile === '') throw new AppException('شماره موبایل سرپرست الزامی است', 422);
        if (!preg_match('/^09[0-9]{9}$/', $mobile)) throw new AppException('شماره موبایل سرپرست معتبر نیست', 422);

        $relation = (string) ($data['relation'] ?? 'other');
        if (!in_array($relation, self::GUARDIAN_RELATIONS, true)) {
            throw new AppException('نسبت سرپرست با بازیکن معتبر نیست', 422);
        }

        $nationalCode = trim((string) ($data['national_code'] ?? '')) ?: null;
        if ($nationalCode !== null && !preg_match('/^[0-9]{10}$/', $nationalCode)) {
            throw new AppException('کد ملی سرپرست معتبر نیست', 422);
        }

        // سرپرست فقط یک رکورد تماس است — حساب کاربری و ورود ندارد
        $guardianId = GuardianRepository::create([
            'full_name' => $fullName,
            'mobile' => $mobile,
            'national_code' => $nationalCode,
            'emergency_phone' => trim((string) ($data['emergency_phone'] ?? '')) ?: null,
        ]);

        GuardianPlayerRepository::attach($guardianId, $playerId, [
            'relation' => $relation,
            'is_primary' => true,
            'can_view_reports' => !empty($data['can_view_reports']) || !isset($data['can_view_reports']),
            'can_pay' => !empty($data['can_pay']) || !isset($data['can_pay']),
        ]);

        $rows = self::hydrateGuardianRows(GuardianPlayerRepository::guardiansForPlayer($playerId));

        return [
            'guardian_player' => $rows[0] ?? null,
        ];
    }

    /**
     * ویرایش سرپرستِ متصل به بازیکن (اطلاعات هویتی + نسبت + دسترسی‌ها)
     */
    public static function updateGuardian(int $playerId, int $guardianId, array $data): array
    {
        self::requirePlayer($playerId);

        $relation = GuardianPlayerRepository::findRelation($guardianId, $playerId);
        if (!$relation || $relation['status'] !== 'active') {
            throw new AppException('ارتباط فعال بین این سرپرست و بازیکن یافت نشد', 404);
        }

        $guardian = GuardianRepository::findById($guardianId);
        if (!$guardian) throw new AppException('سرپرست یافت نشد', 404);

        $newRelation = (string) ($data['relation'] ?? $relation['relation']);
        if (!in_array($newRelation, self::GUARDIAN_RELATIONS, true)) {
            throw new AppException('نسبت سرپرست با بازیکن معتبر نیست', 422);
        }

        // به‌روزرسانی مشخصات سرپرست (نام/موبایل/کد ملی) روی رکورد خودِ سرپرست
        $guardianUpdate = [];
        if (array_key_exists('full_name', $data) && trim((string) $data['full_name']) !== '') {
            $guardianUpdate['full_name'] = trim((string) $data['full_name']);
        }
        if (array_key_exists('mobile', $data)) {
            $guardianUpdate['mobile'] = trim((string) $data['mobile']);
        }
        if (array_key_exists('national_code', $data)) {
            $guardianUpdate['national_code'] = trim((string) $data['national_code']) ?: null;
        }
        if (!empty($guardianUpdate)) {
            GuardianRepository::update($guardianId, $guardianUpdate);
        }

        GuardianPlayerRepository::attach($guardianId, $playerId, [
            'relation' => $newRelation,
            'is_primary' => true,
            'can_view_reports' => !empty($data['can_view_reports']) || !isset($data['can_view_reports']),
            'can_pay' => !empty($data['can_pay']) || !isset($data['can_pay']),
        ]);

        foreach (self::hydrateGuardianRows(GuardianPlayerRepository::guardiansForPlayer($playerId)) as $r) {
            if ((int) $r['guardian_id'] === $guardianId) {
                return ['guardian_player' => $r];
            }
        }

        return ['guardian_player' => null];
    }

    /**
     * ساخت حساب ورود بازیکن (کاربر با نقش player).
     * شناسه ورود و رمز اولیه = کد ملی بازیکن؛ بدون کد ملی حساب ساخته نمی‌شود.
     * must_change_password=1 است تا در اولین ورود رمز عوض شود.
     */
    private static function createPlayerLogin(int $playerId, string $fullName, ?string $nationalCode): void
    {
        if ($nationalCode === null) return;

        // کاربر موجودی با این کد ملی؟ (حساب بازیکنِ دیگری یا کاربر ادمین/مربی → خطا)
        $existing = UserRepository::findByIdentifier($nationalCode);
        if ($existing !== null && ($existing['national_code'] ?? '') === $nationalCode) {
            if (($existing['role'] ?? '') !== 'player') {
                throw new AppException('این کد ملی متعلق به کاربر دیگری است', 422);
            }
            // حساب بازیکن موجود → فقط اتصال به این بازیکن (رمز فعلی همان حساب حفظ می‌شود)
            PlayerRepository::update($playerId, [
                'user_id' => (int) $existing['id'],
                'password_hash' => (string) $existing['password_hash'],
            ]);
            return;
        }

        // رمز اولیه = کد ملی بازیکن (هش‌شده) — در اولین ورود اجباری عوض می‌شود
        $passwordHash = password_hash($nationalCode, PASSWORD_DEFAULT);

        $userId = UserRepository::create([
            'full_name' => $fullName,
            'mobile' => null,
            'national_code' => $nationalCode,
            'avatar_path' => null,
            'password_hash' => $passwordHash,
            'role' => 'player',
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        // رمز روی رکورد خود بازیکن ذخیره می‌شود (منبع اصلی رمز بازیکن)
        PlayerRepository::update($playerId, [
            'user_id' => $userId,
            'password_hash' => $passwordHash,
        ]);
    }

    /**
     * همگام‌سازی حساب ورود بازیکن پس از ویرایش پروفایل:
     * - حساب ندارد و حالا کد ملی گرفته → حساب ساخته می‌شود (رمز اولیه برمی‌گردد)
     * - حساب دارد → نام/کد ملی حساب با بازیکن همگام می‌شود؛ کد ملیِ خالی = قطع ورود
     */
    private static function syncPlayerLogin(int $playerId, array $oldPlayer, array $updateData, ?string $finalNC): void
    {
        $current = PlayerRepository::findById($playerId);
        if (!$current) return;

        $fullName = trim(((string) ($updateData['first_name'] ?? $oldPlayer['first_name']))
            . ' ' . ((string) ($updateData['last_name'] ?? $oldPlayer['last_name'])));

        $userId = $current['user_id'] ?? null;

        // هنوز حساب ندارد → اگر حالا کد ملی دارد، بساز
        if (empty($userId)) {
            if ($finalNC === null) return;
            self::createPlayerLogin($playerId, $fullName, $finalNC);
            return;
        }

        // حساب دارد → همگام‌سازی نام و کد ملی
        $userUpdate = [];
        if (array_key_exists('first_name', $updateData) || array_key_exists('last_name', $updateData)) {
            $userUpdate['full_name'] = $fullName;
        }
        if ($finalNC !== null) {
            $userUpdate['national_code'] = $finalNC;
        } else {
            // کد ملی برداشته شد → شناسه ورود و رمز برداشته می‌شوند (ورود ممکن نمی‌شود)
            $userUpdate['national_code'] = null;
            PlayerRepository::update($playerId, ['password_hash' => null]);
        }
        if (!empty($userUpdate)) {
            UserRepository::update((int) $userId, $userUpdate);
        }
    }

    /** هر بازیکن فقط یک سرپرست فعال دارد */
    private static function assertNoGuardian(int $playerId, ?int $exceptGuardianId = null): void
    {
        foreach (GuardianPlayerRepository::guardiansForPlayer($playerId) as $r) {
            if ($exceptGuardianId === null || (int) $r['guardian_id'] !== $exceptGuardianId) {
                throw new AppException('این بازیکن قبلاً سرپرست دارد. برای تعویض سرپرست، ابتدا سرپرست فعلی را حذف کنید', 422);
            }
        }
    }

    /** تبدیل ردیف‌های guardiansForPlayer به شکل GuardianPlayerDto اپ (با bool واقعی برای Gson) */
    private static function hydrateGuardianRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'guardian_id' => (int) $r['guardian_id'],
                'player_id' => (int) ($r['player_id'] ?? 0),
                'relation' => $r['relation'],
                'is_primary' => (bool) $r['is_primary'],
                'can_view_reports' => (bool) $r['can_view_reports'],
                'can_pay' => (bool) $r['can_pay'],
                'guardian' => [
                    'id' => (int) $r['guardian_id'],
                    'user_id' => 0,
                    'address' => $r['address'] ?? null,
                    'emergency_phone' => $r['emergency_phone'] ?? null,
                    'user' => [
                        'id' => 0,
                        'full_name' => $r['user_full_name'] ?? '',
                        'mobile' => $r['user_mobile'] ?? null,
                        'national_code' => $r['user_national_code'] ?? null,
                        'role' => 'guardian',
                        'status' => 'active',
                    ],
                ],
            ];
        }
        return $out;
    }

    // متدهای کمکی
    /**
     * سرپرستان فعال بازیکنان، گروه‌بندی‌شده بر اساس player_id
     * شکل خروجی منطبق با GuardianPlayerDto اپ است (با bool واقعی برای Gson)
     */
    private static function guardiansByPlayerId(array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        $rows = GuardianPlayerRepository::guardiansForPlayers($playerIds);

        $byPlayer = [];
        foreach ($rows as $r) {
            $byPlayer[(int) $r['player_id']][] = [
                'guardian_id' => (int) $r['guardian_id'],
                'player_id' => (int) $r['player_id'],
                'relation' => $r['relation'],
                'is_primary' => (bool) $r['is_primary'],
                'can_view_reports' => (bool) $r['can_view_reports'],
                'can_pay' => (bool) $r['can_pay'],
                'guardian' => [
                    'id' => (int) $r['guardian_id'],
                    'user_id' => 0,
                    'emergency_phone' => $r['emergency_phone'],
                    'user' => [
                        'id' => 0,
                        'full_name' => $r['user_full_name'],
                        'mobile' => $r['user_mobile'],
                        'national_code' => $r['user_national_code'],
                        'role' => 'guardian',
                        'status' => 'active',
                    ],
                ],
            ];
        }

        return $byPlayer;
    }

    private static function requirePlayer(int $id): array
    {
        $player = PlayerRepository::findById($id);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);
        return $player;
    }

    private static function addAvatarUrl(array $player): array
    {
        // رمز هش‌شده هرگز در خروجی API نمی‌آید
        unset($player['password_hash']);
        $player['avatar_url'] = AvatarService::getAvatarUrl($player['avatar_path'] ?? null, 'player');
        return $player;
    }

    private static function validateBirthDate(string $birthDate): void
    {
        if ($birthDate === '') throw new AppException('تاریخ تولد الزامی است', 422);
        $date = DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            throw new AppException('تاریخ تولد باید با فرمت Y-m-d و معتبر باشد', 422);
        }
        if ($date->getTimestamp() > time()) {
            throw new AppException('تاریخ تولد نمی‌تواند در آینده باشد', 422);
        }
    }
}