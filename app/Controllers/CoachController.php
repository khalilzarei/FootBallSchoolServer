<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\CoachService;

class CoachController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = CoachService::list($request->input());
            Response::success('لیست مربیان', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $coach = CoachService::get($id);
            Response::success('جزئیات مربی', ['coach' => $coach]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'specialty' => 'string|max:150',
            'license_level' => 'string|max:100',
            'bio' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $coach = CoachService::update($id, $data);
            Response::success('مربی به‌روزرسانی شد', ['coach' => $coach]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}