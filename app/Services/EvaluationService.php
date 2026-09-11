<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\ClassRepository;
use App\Repositories\CoachRepository;
use App\Repositories\EvaluationRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\SessionRepository;

class EvaluationService
{
    private const EVALUATION_TYPES = ['session', 'monthly', 'general'];
    private const STATUSES = ['active', 'inactive'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        return EvaluationRepository::paginate([
            'player_id' => (int) ($query['player_id'] ?? 0) > 0 ? (int) $query['player_id'] : null,
            'session_id' => (int) ($query['session_id'] ?? 0) > 0 ? (int) $query['session_id'] : null,
            'coach_id' => (int) ($query['coach_id'] ?? 0) > 0 ? (int) $query['coach_id'] : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        $evaluation = EvaluationRepository::findById($id);
        if (!$evaluation) throw new AppException('ارزیابی یافت نشد', 404);
        return $evaluation;
    }

    public static function create(array $data): array
    {
        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);

        $sessionId = self::normalizeOptionalInt($data['session_id'] ?? null);
        $session = null;

        if ($sessionId !== null) {
            $session = SessionRepository::findById($sessionId);
            if (!$session) throw new AppException('جلسه یافت نشد', 404);
        }

        $coachId = self::normalizeOptionalInt($data['coach_id'] ?? null);

        if ($coachId === null && $session !== null) {
            $class = ClassRepository::findById((int) $session['class_id']);
            if ($class && !empty($class['coach_id'])) $coachId = (int) $class['coach_id'];
        }

        if ($coachId === null) throw new AppException('مربی برای ثبت ارزیابی مشخص نیست', 422);

        if (!CoachRepository::findById($coachId)) throw new AppException('مربی یافت نشد', 404);

        $evaluationType = (string) ($data['evaluation_type'] ?? 'session');
        if (!in_array($evaluationType, self::EVALUATION_TYPES, true)) throw new AppException('نوع ارزیابی معتبر نیست', 422);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $evaluationId = EvaluationRepository::create([
            'session_id' => $sessionId,
            'player_id' => $playerId,
            'coach_id' => $coachId,
            'evaluation_type' => $evaluationType,
            'technical_score' => self::normalizeOptionalScore($data['technical_score'] ?? null),
            'discipline_score' => self::normalizeOptionalScore($data['discipline_score'] ?? null),
            'physical_score' => self::normalizeOptionalScore($data['physical_score'] ?? null),
            'teamwork_score' => self::normalizeOptionalScore($data['teamwork_score'] ?? null),
            'overall_score' => self::normalizeOptionalScore($data['overall_score'] ?? null),
            'strengths' => self::normalizeOptionalText($data['strengths'] ?? null),
            'weaknesses' => self::normalizeOptionalText($data['weaknesses'] ?? null),
            'notes' => self::normalizeOptionalText($data['notes'] ?? null),
            'status' => $status,
        ]);

        return self::get($evaluationId);
    }

    public static function update(int $id, array $data): array
    {
        self::get($id);

        $updateData = [];

        if (array_key_exists('player_id', $data)) {
            $v = (int) $data['player_id'];
            if ($v <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);
            if (!PlayerRepository::findById($v)) throw new AppException('بازیکن یافت نشد', 404);
            $updateData['player_id'] = $v;
        }

        if (array_key_exists('session_id', $data)) {
            $v = self::normalizeOptionalInt($data['session_id']);
            if ($v !== null && !SessionRepository::findById($v)) throw new AppException('جلسه یافت نشد', 404);
            $updateData['session_id'] = $v;
        }

        if (array_key_exists('coach_id', $data)) {
            $v = self::normalizeOptionalInt($data['coach_id']);
            if ($v === null) throw new AppException('مربی نمی‌تواند خالی باشد', 422);
            if (!CoachRepository::findById($v)) throw new AppException('مربی یافت نشد', 404);
            $updateData['coach_id'] = $v;
        }

        if (array_key_exists('evaluation_type', $data)) {
            $v = (string) $data['evaluation_type'];
            if (!in_array($v, self::EVALUATION_TYPES, true)) throw new AppException('نوع ارزیابی معتبر نیست', 422);
            $updateData['evaluation_type'] = $v;
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        $scoreFields = ['technical_score', 'discipline_score', 'physical_score', 'teamwork_score', 'overall_score'];
        foreach ($scoreFields as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalScore($data[$f]);
        }

        $textFields = ['strengths', 'weaknesses', 'notes'];
        foreach ($textFields as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalText($data[$f]);
        }

        if (!empty($updateData)) EvaluationRepository::update($id, $updateData);

        return self::get($id);
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('شناسه معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }

    private static function normalizeOptionalScore(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('نمره باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0 || $v > 10) throw new AppException('نمره باید بین ۰ تا ۱۰ باشد', 422);
        return $v;
    }

    private static function normalizeOptionalText(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}