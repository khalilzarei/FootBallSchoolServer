<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\EnrollmentService;

class EnrollmentController
{
    public function index(Request $request, array $params = []): void
    {
        $classId = (int) ($params['id'] ?? 0);

        try {
            $result = EnrollmentService::listForClass($classId, $request->input());
            Response::success('لیست ثبت‌نام‌های کلاس', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $classId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'required|integer',
            'status' => 'string|in:pending,active,inactive,waitlist,completed',
            'enrolled_at' => 'date',
            'ended_at' => 'date',
            'monthly_fee_override' => 'integer',
            'session_fee_override' => 'integer',
            'registration_fee_override' => 'integer',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $enrollment = EnrollmentService::create($classId, $data);
            Response::success('ثبت‌نام ایجاد شد', ['enrollment' => $enrollment], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * ثبت‌نام گروهی: همه بازیکنان فعالِ گروه سنیِ کلاس
     * POST /api/v1/classes/{id}/enroll-age-group
     */
    public function enrollAgeGroup(Request $request, array $params = []): void
    {
        $classId = (int) ($params['id'] ?? 0);

        try {
            $result = EnrollmentService::enrollAgeGroup($classId);
            Response::success('ثبت‌نام گروهی انجام شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $enrollmentId = (int) ($params['id'] ?? 0);

        try {
            $enrollment = EnrollmentService::get($enrollmentId);
            Response::success('جزئیات ثبت‌نام', ['enrollment' => $enrollment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $enrollmentId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'status' => 'string|in:pending,active,inactive,waitlist,completed',
            'enrolled_at' => 'date',
            'ended_at' => 'date',
            'monthly_fee_override' => 'integer',
            'session_fee_override' => 'integer',
            'registration_fee_override' => 'integer',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $enrollment = EnrollmentService::update($enrollmentId, $data);
            Response::success('ثبت‌نام به‌روزرسانی شد', ['enrollment' => $enrollment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function activate(Request $request, array $params = []): void
    {
        $enrollmentId = (int) ($params['id'] ?? 0);

        try {
            $enrollment = EnrollmentService::activate($enrollmentId);
            Response::success('ثبت‌نام فعال شد', ['enrollment' => $enrollment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
    public function deactivate(Request $request, array $params = []): void
    {
        $enrollmentId = (int) ($params['id'] ?? 0);

        try {
            $enrollment = EnrollmentService::deactivate($enrollmentId);
            Response::success('ثبت‌نام غیرفعال شد', ['enrollment' => $enrollment]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}