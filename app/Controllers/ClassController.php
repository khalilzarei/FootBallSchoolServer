<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\ClassService;

class ClassController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = ClassService::list($request->input());
            Response::success('لیست کلاس‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:150',
            'season_id' => 'integer',
            'age_group_id' => 'integer',
            'coach_id' => 'integer',
            'assistant_coach_id' => 'integer',
            'capacity' => 'integer',
            'status' => 'string|in:active,inactive,archived',
            'location' => 'string|max:255',
            'description' => 'string|max:1000',
            'pricing_type' => 'string|in:monthly,session,both',
            'monthly_fee' => 'integer',
            'session_fee' => 'integer',
            'registration_fee' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $class = ClassService::create($data);
            Response::success('کلاس ایجاد شد', ['class' => $class], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $class = ClassService::get($id);
            Response::success('جزئیات کلاس', ['class' => $class]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'string|min:3|max:150',
            'age_group_id' => 'integer',
            'coach_id' => 'integer',
            'assistant_coach_id' => 'integer',
            'capacity' => 'integer',
            'status' => 'string|in:active,inactive,archived',
            'location' => 'string|max:255',
            'description' => 'string|max:1000',
            'pricing_type' => 'string|in:monthly,session,both',
            'monthly_fee' => 'integer',
            'session_fee' => 'integer',
            'registration_fee' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $class = ClassService::update($id, $data);
            Response::success('کلاس به‌روزرسانی شد', ['class' => $class]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $class = ClassService::activate($id);
            Response::success('کلاس فعال شد', ['class' => $class]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deactivate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $class = ClassService::deactivate($id);
            Response::success('کلاس غیرفعال شد', ['class' => $class]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function destroy(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            ClassService::delete($id);
            Response::success('کلاس حذف شد');
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}