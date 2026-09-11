<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuthService;

class AuthController
{
    public function login(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'identifier' => 'required|string|min:3|max:20',
            'password' => 'required|string|min:6|max:100',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $result = AuthService::login($data, $request);
            Response::success('ورود موفق', $result, 200);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function me(Request $request, array $params = []): void
    {
        Response::success('اطلاعات کاربر فعلی', [
            'user' => $this->sanitizeUser(Auth::user()),
        ]);
    }

    public function changePassword(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'old_password' => 'required|string|min:6|max:100',
            'new_password' => 'required|string|min:8|max:100',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        if (($data['new_password'] ?? '') !== ($data['new_password_confirmation'] ?? '')) {
            Response::error('تکرار رمز عبور جدید مطابقت ندارد', 422, [
                'new_password_confirmation' => ['تکرار رمز عبور جدید مطابقت ندارد'],
            ]);
        }

        try {
            AuthService::changePassword(
                (int) Auth::id(),
                (int) Auth::user()['token_id'],
                $data
            );
            Response::success('رمز عبور با موفقیت تغییر کرد');
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function logout(Request $request, array $params = []): void
    {
        AuthService::logout((int) Auth::user()['token_id']);
        Response::success('خروج موفق');
    }

    private function sanitizeUser(?array $user): array
    {
        if (!$user) return [];
        unset($user['password_hash']);
        return $user;
    }
}