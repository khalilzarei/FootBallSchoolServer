<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AgeGroupService;

class AgeGroupController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = AgeGroupService::list($request->input());
            Response::success('لیست گروه‌های سنی', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'season_id' => 'required|integer',
            'title' => 'required|string|min:3|max:100',
            'birth_date_from' => 'required|date',
            'birth_date_to' => 'required|date',
            'min_age_at_cutoff' => 'integer',
            'max_age_at_cutoff' => 'integer',
            'sort_order' => 'integer',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $ageGroup = AgeGroupService::create($data);
            Response::success('گروه سنی ایجاد شد', ['age_group' => $ageGroup], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $ageGroup = AgeGroupService::get($id);
            Response::success('جزئیات گروه سنی', ['age_group' => $ageGroup]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'season_id' => 'integer',
            'title' => 'string|min:3|max:100',
            'birth_date_from' => 'date',
            'birth_date_to' => 'date',
            'min_age_at_cutoff' => 'integer',
            'max_age_at_cutoff' => 'integer',
            'sort_order' => 'integer',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $ageGroup = AgeGroupService::update($id, $data);
            Response::success('گروه سنی به‌روزرسانی شد', ['age_group' => $ageGroup]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $ageGroup = AgeGroupService::activate($id);
            Response::success('گروه سنی فعال شد', ['age_group' => $ageGroup]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deactivate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $ageGroup = AgeGroupService::deactivate($id);
            Response::success('گروه سنی غیرفعال شد', ['age_group' => $ageGroup]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}