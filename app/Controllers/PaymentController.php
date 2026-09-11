<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\PaymentService;

class PaymentController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = PaymentService::list($request->input());
            Response::success('لیست پرداخت‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'required|integer',
            'invoice_id' => 'integer',
            'installment_id' => 'integer',
            'amount' => 'required|integer',
            'payment_method' => 'required|string|in:cash,card_transfer,pos,cheque,online,other',
            'status' => 'string|in:pending,approved',
            'receipt_media_id' => 'integer',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $payment = PaymentService::create($data);
            Response::success('پرداخت ثبت شد', ['payment' => $payment], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $payment = PaymentService::get($id);
            Response::success('جزئیات پرداخت', ['payment' => $payment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function approve(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $payment = PaymentService::approve($id);
            Response::success('پرداخت تأیید شد', ['payment' => $payment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function reject(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $payment = PaymentService::reject($id);
            Response::success('پرداخت رد شد', ['payment' => $payment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function playerBalance(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);

        try {
            $balance = PaymentService::playerBalance($playerId);
            Response::success('وضعیت مالی بازیکن', ['balance' => $balance]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function playerPayments(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);

        try {
            $result = PaymentService::playerPayments($playerId, $request->input());
            Response::success('لیست پرداخت‌های بازیکن', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}