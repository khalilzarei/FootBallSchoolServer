<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\EvaluationService;

class EvaluationController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = EvaluationService::list($request->input());
            Response::success('لیست ارزیابی‌ها', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'required|integer',
            'session_id' => 'integer',
            'coach_id' => 'integer',
            'evaluation_type' => 'string|in:session,monthly,general',
            'technical_score' => 'integer',
            'discipline_score' => 'integer',
            'physical_score' => 'integer',
            'teamwork_score' => 'integer',
            'overall_score' => 'integer',
            'strengths' => 'string|max:1000',
            'weaknesses' => 'string|max:1000',
            'notes' => 'string|max:1000',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $evaluation = EvaluationService::create($data);
            Response::success('ارزیابی ثبت شد', ['evaluation' => $evaluation], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $evaluation = EvaluationService::get($id);
            Response::success('جزئیات ارزیابی', ['evaluation' => $evaluation]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'integer',
            'session_id' => 'integer',
            'coach_id' => 'integer',
            'evaluation_type' => 'string|in:session,monthly,general',
            'technical_score' => 'integer',
            'discipline_score' => 'integer',
            'physical_score' => 'integer',
            'teamwork_score' => 'integer',
            'overall_score' => 'integer',
            'strengths' => 'string|max:1000',
            'weaknesses' => 'string|max:1000',
            'notes' => 'string|max:1000',
            'status' => 'string|in:active,inactive',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $evaluation = EvaluationService::update($id, $data);
            Response::success('ارزیابی به‌روزرسانی شد', ['evaluation' => $evaluation]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function playerEvaluations(Request $request, array $params = []): void
    {
        $playerId = (int) ($params['id'] ?? 0);

        try {
            $result = EvaluationService::list([
                'player_id' => $playerId,
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 20),
            ]);
            Response::success('لیست ارزیابی‌های بازیکن', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function sessionEvaluations(Request $request, array $params = []): void
    {
        $sessionId = (int) ($params['id'] ?? 0);

        try {
            $result = EvaluationService::list([
                'session_id' => $sessionId,
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 20),
            ]);
            Response::success('لیست ارزیابی‌های جلسه', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}