<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\PlayerService;

class PlayerController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            Response::success('لیست بازیکنان', PlayerService::list($request->input()));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();
        $errors = Validator::make($data, [
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'birth_date' => 'required|date',
            'gender' => 'string|in:male,female',
            'national_code' => 'digits:10',
            'status' => 'string|in:active,inactive,archived',
            'medical_notes' => 'string|max:1000',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $avatarFile = $_FILES['avatar'] ?? null; // ← دریافت فایل آواتار
            $player = PlayerService::create($data, $avatarFile);
            Response::success('بازیکن با موفقیت ایجاد شد', ['player' => $player], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        try {
            Response::success('جزئیات بازیکن', ['player' => PlayerService::get((int) ($params['id'] ?? 0))]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $request->input();
        $errors = Validator::make($data, [
            'first_name' => 'string|min:2|max:100',
            'last_name' => 'string|min:2|max:100',
            'birth_date' => 'date',
            'gender' => 'string|in:male,female',
            'national_code' => 'digits:10',
            'status' => 'string|in:active,inactive,archived',
            'medical_notes' => 'string|max:1000',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $avatarFile = $_FILES['avatar'] ?? null; // ← دریافت فایل آواتار
            $player = PlayerService::update($id, $data, $avatarFile);
            Response::success('بازیکن به‌روزرسانی شد', ['player' => $player]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function guardians(Request $request, array $params = []): void
    {
        try {
            Response::success('لیست سرپرست‌های بازیکن', PlayerService::guardians((int) ($params['id'] ?? 0)));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function attachGuardian(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);
        $errors = Validator::make($request->input(), [
            'guardian_id' => 'required|integer',
            'relation' => 'string|in:father,mother,grandfather,grandmother,uncle,aunt,guardian,other',
            'is_primary' => 'boolean',
            'can_view_reports' => 'boolean',
            'can_pay' => 'boolean',
        ]);
        if (!empty($errors)) Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);

        try {
            Response::success('سرپرست به بازیکن متصل شد', PlayerService::attachGuardian($playerId, $request->input()));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function detachGuardian(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);
        $guardianId = (int) ($params['guardian_id'] ?? 0);
        try {
            Response::success('ارتباط بازیکن و سرپرست قطع شد', PlayerService::detachGuardian($playerId, $guardianId));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * ساخت سرپرست جدید (با موبایل) + اتصال به بازیکن در یک ریکوئست
     */
    public function attachNewGuardian(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);
        $errors = Validator::make($request->input(), [
            'full_name' => 'required|string|min:2|max:100',
            'mobile' => 'required|string|min:10|max:15',
            'national_code' => 'digits:10',
            'relation' => 'string|in:father,mother,grandfather,grandmother,uncle,aunt,guardian,other',
            'is_primary' => 'boolean',
            'can_view_reports' => 'boolean',
            'can_pay' => 'boolean',
        ]);
        if (!empty($errors)) Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);

        try {
            Response::success('سرپرست جدید ساخته و به بازیکن متصل شد', PlayerService::attachNewGuardian($playerId, $request->input()));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * ویرایش سرپرستِ متصل به بازیکن (نام/موبایل/کد ملی/نسبت/دسترسی‌ها)
     */
    public function updateGuardian(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);
        $guardianId = (int) ($params['guardian_id'] ?? 0);
        $errors = Validator::make($request->input(), [
            'full_name' => 'string|min:2|max:100',
            'mobile' => 'string|min:10|max:15',
            'national_code' => 'digits:10',
            'relation' => 'string|in:father,mother,grandfather,grandmother,uncle,aunt,guardian,other',
            'can_view_reports' => 'boolean',
            'can_pay' => 'boolean',
        ]);
        if (!empty($errors)) Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);

        try {
            Response::success('اطلاعات سرپرست به‌روزرسانی شد', PlayerService::updateGuardian($playerId, $guardianId, $request->input()));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // --- مدیریت آواتار بازیکن توسط ادمین ---
    public function updateAvatar(Request $request, array $params = []): void
    {
        try {
            $player = PlayerService::updateAvatar((int) ($params['id'] ?? 0), $_FILES['avatar'] ?? null);
            Response::success('آواتار بازیکن به‌روزرسانی شد', ['player' => $player]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deleteAvatar(Request $request, array $params = []): void
    {
        try {
            $player = PlayerService::deleteAvatar((int) ($params['id'] ?? 0));
            Response::success('آواتار بازیکن حذف شد', ['player' => $player]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}