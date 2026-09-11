<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\DiscountService;

class DiscountController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = DiscountService::list($request->input());
            Response::success('لیست تخفیف‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:150',
            'discount_type' => 'required|string|in:percent,fixed',
            'value' => 'required|integer',
            'applies_to' => 'string|in:any,registration,monthly,session',
            'auto_apply' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'string|in:active,inactive',
            'description' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $discount = DiscountService::create($data);
            Response::success('تخفیف ایجاد شد', ['discount' => $discount], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $discount = DiscountService::get($id);
            Response::success('جزئیات تخفیف', ['discount' => $discount]);
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
            'discount_type' => 'string|in:percent,fixed',
            'value' => 'integer',
            'applies_to' => 'string|in:any,registration,monthly,session',
            'auto_apply' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'string|in:active,inactive',
            'description' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $discount = DiscountService::update($id, $data);
            Response::success('تخفیف به‌روزرسانی شد', ['discount' => $discount]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $discount = DiscountService::activate($id);
            Response::success('تخفیف فعال شد', ['discount' => $discount]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deactivate(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $discount = DiscountService::deactivate($id);
            Response::success('تخفیف غیرفعال شد', ['discount' => $discount]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}