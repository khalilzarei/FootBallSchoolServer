<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\GuardianService;

class GuardianController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = GuardianService::list($request->input());
            Response::success('لیست سرپرست‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $guardian = GuardianService::get($id);
            Response::success('جزئیات سرپرست', ['guardian' => $guardian]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'address' => 'string|max:500',
            'emergency_phone' => 'string|max:20',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $guardian = GuardianService::update($id, $data);
            Response::success('سرپرست به‌روزرسانی شد', ['guardian' => $guardian]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function players(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $players = GuardianService::players($id);
            Response::success('لیست بازیکنان سرپرست', ['players' => $players]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function attachPlayer(Request $request, array $params = []): void
    {
        $guardianId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'required|integer',
            'relation' => 'string|in:father,mother,grandfather,grandmother,guardian,other',
            'is_primary' => 'boolean',
            'can_view_reports' => 'boolean',
            'can_pay' => 'boolean',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $result = GuardianService::attachPlayer($guardianId, $data);
            Response::success('بازیکن به سرپرست متصل شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function detachPlayer(Request $request, array $params = []): void
    {
        $guardianId = (int) ($params['id'] ?? 0);
        $playerId = (int) ($params['player_id'] ?? 0);

        try {
            $result = GuardianService::detachPlayer($guardianId, $playerId);
            Response::success('ارتباط سرپرست و بازیکن قطع شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}