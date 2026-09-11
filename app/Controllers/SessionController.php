<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\SessionService;

class SessionController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = SessionService::list($request->input());
            Response::success('لیست جلسات', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'class_id' => 'required|integer',
            'session_date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'status' => 'string|in:scheduled,completed,cancelled,makeup',
            'location' => 'string|max:255',
            'topic' => 'string|max:255',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $session = SessionService::create($data);
            Response::success('جلسه ایجاد شد', ['session' => $session], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $session = SessionService::get($id);
            Response::success('جزئیات جلسه', ['session' => $session]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'session_date' => 'date',
            'start_time' => 'string',
            'end_time' => 'string',
            'status' => 'string|in:scheduled,completed,cancelled,makeup',
            'location' => 'string|max:255',
            'topic' => 'string|max:255',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $session = SessionService::update($id, $data);
            Response::success('جلسه به‌روزرسانی شد', ['session' => $session]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function cancel(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $session = SessionService::cancel($id);
            Response::success('جلسه لغو شد', ['session' => $session]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function complete(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $session = SessionService::complete($id);
            Response::success('جلسه تکمیل شد', ['session' => $session]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function generate(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'class_id' => 'required|integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $result = SessionService::generate($data);
            Response::success('جلسات تولید شدند', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}