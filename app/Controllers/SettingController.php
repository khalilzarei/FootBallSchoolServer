<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingService;

class SettingController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $settings = SettingService::all();
            Response::success('لیست تنظیمات', ['settings' => $settings]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        try {
            $settings = SettingService::update($request->input());
            Response::success('تنظیمات به‌روزرسانی شد', ['settings' => $settings]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}