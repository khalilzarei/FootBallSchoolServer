<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\MatchService;

class MatchController
{
    public function index(Request $request, array $params = []): void
    {
        try {
            $result = MatchService::list($request->input());
            Response::success('لیست مسابقات', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function store(Request $request, array $params = []): void
    {
        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'match_type' => 'required|string|in:friendly,official,league,cup,festival,internal',
            'class_id' => 'integer',
            'age_group_id' => 'integer',
            'opponent_team' => 'string|max:255',
            'match_date' => 'required|date',
            'match_time' => 'required|string',
            'location' => 'string|max:255',
            'status' => 'string|in:planned,confirmed,cancelled,finished',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'result' => 'string|in:win,loss,draw,unknown',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $match = MatchService::create($data);
            Response::success('مسابقه ایجاد شد', ['match' => $match], 201);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function show(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $match = MatchService::getDetailed($id);
            Response::success('جزئیات مسابقه', ['match' => $match]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function update(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'title' => 'string|min:3|max:255',
            'match_type' => 'string|in:friendly,official,league,cup,festival,internal',
            'class_id' => 'integer',
            'age_group_id' => 'integer',
            'opponent_team' => 'string|max:255',
            'match_date' => 'date',
            'match_time' => 'string',
            'location' => 'string|max:255',
            'status' => 'string|in:planned,confirmed,cancelled,finished',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'result' => 'string|in:win,loss,draw,unknown',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $match = MatchService::update($id, $data);
            Response::success('مسابقه به‌روزرسانی شد', ['match' => $match]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function cancel(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $match = MatchService::cancel($id);
            Response::success('مسابقه لغو شد', ['match' => $match]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function setResult(Request $request, array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'home_score' => 'required|integer',
            'away_score' => 'required|integer',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $match = MatchService::setResult($id, $data);
            Response::success('نتیجه مسابقه ثبت شد', ['match' => $match]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function addPlayer(Request $request, array $params = []): void
    {
        $matchId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'player_id' => 'required|integer',
            'invitation_status' => 'string|in:invited,accepted,declined,pending',
            'attendance_status' => 'string|in:present,absent,late,injured',
            'jersey_number' => 'integer',
            'position' => 'string|max:50',
            'goals' => 'integer',
            'assists' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
            'minutes_played' => 'integer',
            'rating' => 'string',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $result = MatchService::addPlayer($matchId, $data);
            Response::success('بازیکن به مسابقه اضافه شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updatePlayer(Request $request, array $params = []): void
    {
        $matchPlayerId = (int) ($params['id'] ?? 0);

        $data = $request->input();

        $errors = Validator::make($data, [
            'invitation_status' => 'string|in:invited,accepted,declined,pending',
            'attendance_status' => 'string|in:present,absent,late,injured',
            'jersey_number' => 'integer',
            'position' => 'string|max:50',
            'goals' => 'integer',
            'assists' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
            'minutes_played' => 'integer',
            'rating' => 'string',
            'notes' => 'string|max:1000',
        ]);

        if (!empty($errors)) {
            Response::error('اطلاعات ورودی معتبر نیست', 422, $errors);
        }

        try {
            $matchPlayer = MatchService::updatePlayer($matchPlayerId, $data);
            Response::success('اطلاعات بازیکن مسابقه به‌روزرسانی شد', ['match_player' => $matchPlayer]);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function removePlayer(Request $request, array $params = []): void
    {
        $matchPlayerId = (int) ($params['id'] ?? 0);

        try {
            $result = MatchService::removePlayer($matchPlayerId);
            Response::success('بازیکن از مسابقه حذف شد', $result);
        } catch (AppException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}