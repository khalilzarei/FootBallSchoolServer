<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;

class ReportController
{
    public function dashboard(Request $request, array $params = []): void
    {
        try {
            $result = ReportService::dashboard();
            Response::success('گزارش داشبورد', ['report' => $result]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function finance(Request $request, array $params = []): void
    {
        try {
            $result = ReportService::finance();
            Response::success('گزارش مالی', ['report' => $result]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function debts(Request $request, array $params = []): void
    {
        try {
            $result = ReportService::debts();
            Response::success('لیست بدهکاران', ['debtors' => $result]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function attendance(Request $request, array $params = []): void
    {
        try {
            $result = ReportService::attendance($request->input());
            Response::success('گزارش حضور و غیاب', ['report' => $result]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function classes(Request $request, array $params = []): void
    {
        try {
            $result = ReportService::classes();
            Response::success('گزارش کلاس‌ها', ['classes' => $result]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}