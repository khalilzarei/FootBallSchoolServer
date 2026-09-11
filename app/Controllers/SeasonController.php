<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\SeasonService;

class SeasonController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = SeasonService::list($request->input());
            Response::success('لیست فصل‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'age_cutoff_date' => 'required|date',
            'status' => 'string|in:active,inactive',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $season = SeasonService::create($data);
            Response::success('فصل ایجاد شد', ['season' => $season], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $season = SeasonService::get($id);
            Response::success('جزئیات فصل', ['season' => $season]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'string|min:3|max:100',
            'start_date' => 'date',
            'end_date' => 'date',
            'age_cutoff_date' => 'date',
            'status' => 'string|in:active,inactive',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $season = SeasonService::update($id, $data);
            Response::success('فصل به‌روزرسانی شد', ['season' => $season]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $season = SeasonService::activate($id);
            Response::success('فصل فعال شد', ['season' => $season]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deactivate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $season = SeasonService::deactivate($id);
            Response::success('فصل غیرفعال شد', ['season' => $season]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}