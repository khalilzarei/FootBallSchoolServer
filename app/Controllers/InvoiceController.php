<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\InvoiceService;

class InvoiceController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = InvoiceService::list($request->input());
            Response::success('لیست فاکتورها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'required|integer',
            'invoice_type' => 'required|string|in:monthly,session,registration,manual,match',
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'due_date' => 'date',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $invoice = InvoiceService::create($data);
            Response::success('فاکتور ایجاد شد', ['invoice' => $invoice], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $invoice = InvoiceService::getDetailed($id);
            Response::success('جزئیات فاکتور', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function cancel(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $invoice = InvoiceService::cancel($id);
            Response::success('فاکتور لغو شد', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function addItem(Request $request, array $params = []): void
    {
        $invoiceId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'item_type' => 'required|string|in:registration,monthly,session,manual,match',
            'amount' => 'required|integer',
            'quantity' => 'integer',
            'class_id' => 'integer',
            'session_id' => 'integer',
            'attendance_id' => 'integer',
            'description' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $invoice = InvoiceService::addItem($invoiceId, $data);
            Response::success('آیتم فاکتور اضافه شد', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updateItem(Request $request, array $params = []): void
    {
        $itemId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'string|min:3|max:255',
            'item_type' => 'string|in:registration,monthly,session,manual,match',
            'amount' => 'integer',
            'quantity' => 'integer',
            'class_id' => 'integer',
            'session_id' => 'integer',
            'attendance_id' => 'integer',
            'description' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $invoice = InvoiceService::updateItem($itemId, $data);
            Response::success('آیتم فاکتور به‌روزرسانی شد', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deleteItem(Request $request, array $params = []): void
    {
        $itemId = (int) ($params['id'] ?? 0);

        try {
            $invoice = InvoiceService::deleteItem($itemId);
            Response::success('آیتم فاکتور حذف شد', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function applyDiscount(Request $request, array $params = []): void
    {
        $invoiceId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'discount_id' => 'integer',
            'title' => 'string|max:150',
            'discount_type' => 'string|in:percent,fixed',
            'value' => 'integer',
            'description' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $invoice = InvoiceService::applyDiscount($invoiceId, $data);
            Response::success('تخفیف اعمال شد', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function addInstallment(Request $request, array $params = []): void
    {
        $invoiceId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'amount' => 'required|integer',
            'due_date' => 'required|date',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $invoice = InvoiceService::addInstallment($invoiceId, $data);
            Response::success('قسط اضافه شد', ['invoice' => $invoice]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function listInstallments(Request $request, array $params = []): void
    {
        $invoiceId = (int) ($params['id'] ?? 0);

        try {
            $invoice = InvoiceService::getDetailed($invoiceId);
            Response::success('لیست اقساط فاکتور', ['installments' => $invoice['installments']]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function playerInvoices(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);

        try {
            $result = InvoiceService::playerInvoices($playerId, $request->input());
            Response::success('لیست فاکتورهای بازیکن', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}