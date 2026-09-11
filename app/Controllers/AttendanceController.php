<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AttendanceService;

class AttendanceController
{
    public function index(Request $request, array $params = []): void
    {
        $sessionId = (int) ($params['id'] ?? 0);

        try {
            $attendances = AttendanceService::listForSession($sessionId);
            Response::success('لیست حضور و غیاب جلسه', ['attendances' => $attendances]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function saveBulk(Request $request, array $params = []): void
    {
        $sessionId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $items = $data['items'] ?? [];

        if (!is_array($items)) {
            Response::error('آیتم‌های حضور و غیاب باید آرایه باشند', 422);
        }

        try {
            $result = AttendanceService::saveBulk($sessionId, $items);
            Response::success('حضور و غیاب ثبت شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function playerAttendances(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);

        try {
            $attendances = AttendanceService::playerAttendances($playerId);
            Response::success('لیست حضور و غیاب بازیکن', ['attendances' => $attendances]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}