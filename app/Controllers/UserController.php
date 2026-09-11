<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\UserService;

class UserController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            Response::success('لیست کاربران', UserService::list($request->input()));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();
        $errors = Validator::make($data, [
            'full_name' => 'required|string|min:3|max:150',
            'role' => 'required|string|in:admin,coach,guardian',
            'status' => 'string|in:active,inactive',
            'password' => 'string|min:8|max:100',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $avatarFile = $_FILES['avatar'] ?? null; // دریافت فایل آواتار
            $user = UserService::create($data, (int) Auth::id(), $avatarFile);
            Response::success('کاربر با موفقیت ایجاد شد', $user, 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        try {
            Response::success('جزئیات کاربر', ['user' => UserService::get((int) ($params['id'] ?? 0))]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $request->input();
        $errors = Validator::make($data, [
            'full_name' => 'string|min:3|max:150',
            'role' => 'string|in:admin,coach,guardian',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $avatarFile = $_FILES['avatar'] ?? null; // دریافت فایل آواتار
            $user = UserService::update($id, $data, $avatarFile);
            Response::success('کاربر به‌روزرسانی شد', ['user' => $user]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function resetPassword(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $errors = Validator::make($request->input(), ['password' => 'string|min:8|max:100']);
        if (!empty($errors)) Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);

        try {
            Response::success('رمز عبور ریست شد', UserService::resetPassword($id, $request->input()));
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        try {
            Response::success('کاربر فعال شد', ['user' => UserService::activate((int) ($params['id'] ?? 0))]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deactivate(Request $request, array $params = []): void
    {
        try {
            Response::success('کاربر غیرفعال شد', ['user' => UserService::deactivate((int) ($params['id'] ?? 0))]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // --- مدیریت آواتار توسط ادمین ---
    public function updateAvatar(Request $request, array $params = []): void
    {
        try {
            $user = UserService::updateAvatar((int) ($params['id'] ?? 0), $_FILES['avatar'] ?? null);
            Response::success('آواتار کاربر به‌روزرسانی شد', ['user' => $user]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deleteAvatar(Request $request, array $params = []): void
    {
        try {
            $user = UserService::deleteAvatar((int) ($params['id'] ?? 0));
            Response::success('آواتار کاربر حذف شد', ['user' => $user]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // --- مدیریت آواتار توسط خود کاربر (کلاینت) ---
    public function updateOwnAvatar(Request $request, array $params = []): void
    {
        try {
            $user = UserService::updateOwnAvatar((int) Auth::id(), $_FILES['avatar'] ?? null);
            Response::success('آواتار شما به‌روزرسانی شد', ['user' => $user]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deleteOwnAvatar(Request $request, array $params = []): void
    {
        try {
            $user = UserService::deleteOwnAvatar((int) Auth::id());
            Response::success('آواتار شما حذف شد', ['user' => $user]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}