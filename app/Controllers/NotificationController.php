<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\NotificationService;

class NotificationController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = NotificationService::listForCurrentUser($request->input());
            Response::success('لیست اعلان‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function unreadCount(Request $request, array $params = []): void
    {
        try {
            $result = NotificationService::unreadCount();
            Response::success('تعداد اعلان‌های خوانده‌نشده', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function read(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $result = NotificationService::read($id);
            Response::success('اعلان خوانده شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function readAll(Request $request, array $params = []): void
    {
        try {
            $result = NotificationService::readAll();
            Response::success('همه اعلان‌ها خوانده شدند', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function send(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'body' => 'string|max:1000',
            'type' => 'string|in:info,news,payment,attendance,chat,match,system',
            'user_id' => 'integer',
            'role' => 'string|in:admin,coach,guardian',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $result = NotificationService::send($data);
            Response::success('اعلان ارسال شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}