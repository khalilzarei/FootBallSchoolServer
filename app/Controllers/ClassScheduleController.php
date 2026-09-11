<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\ClassScheduleService;

class ClassScheduleController
{
    public function index(Request $request, array $params = []): void
    {
        $classId = (int) ($params['id'] ?? 0);

        try {
            $schedules = ClassScheduleService::listForClass($classId);
            Response::success('برنامه هفتگی کلاس', ['schedules' => $schedules]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $classId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'weekday' => 'required|integer',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'location' => 'string|max:255',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $schedule = ClassScheduleService::create($classId, $data);
            Response::success('برنامه هفتگی ایجاد شد', ['schedule' => $schedule], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $scheduleId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'weekday' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
            'location' => 'string|max:255',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $schedule = ClassScheduleService::update($scheduleId, $data);
            Response::success('برنامه هفتگی به‌روزرسانی شد', ['schedule' => $schedule]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        $scheduleId = (int) ($params['id'] ?? 0);

        try {
            $schedule = ClassScheduleService::activate($scheduleId);
            Response::success('برنامه هفتگی فعال شد', ['schedule' => $schedule]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deactivate(Request $request, array $params = []): void
    {
        $scheduleId = (int) ($params['id'] ?? 0);

        try {
            $schedule = ClassScheduleService::deactivate($scheduleId);
            Response::success('برنامه هفتگی غیرفعال شد', ['schedule' => $schedule]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}