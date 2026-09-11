<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\AgeGroupRepository;
use App\Repositories\ClassRepository;
use App\Repositories\MatchRepository;
use App\Repositories\PlayerRepository;
use DateTime;

class MatchService
{
    private const MATCH_TYPES = ['friendly', 'official', 'league', 'cup', 'festival', 'internal'];
    private const STATUSES = ['planned', 'confirmed', 'cancelled', 'finished'];
    private const RESULTS = ['win', 'loss', 'draw', 'unknown'];
    private const INVITATION_STATUSES = ['invited', 'accepted', 'declined', 'pending'];
    private const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'injured'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $dateTo = trim((string) ($query['date_to'] ?? ''));
        if ($dateFrom !== '') self::validateDate($dateFrom);
        if ($dateTo !== '') self::validateDate($dateTo);

        return MatchRepository::paginate([
            'status' => $status !== '' ? $status : null,
            'q' => trim((string) ($query['q'] ?? '')) ?: null,
            'class_id' => (int) ($query['class_id'] ?? 0) > 0 ? (int) $query['class_id'] : null,
            'age_group_id' => (int) ($query['age_group_id'] ?? 0) > 0 ? (int) $query['age_group_id'] : null,
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
        ], $page, $perPage);
    }

    public static function getDetailed(int $id): array
    {
        $match = self::requireMatch($id);
        $match['players'] = MatchRepository::players($id);
        return $match;
    }

    public static function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان مسابقه الزامی است', 422);

        $matchType = (string) ($data['match_type'] ?? '');
        if (!in_array($matchType, self::MATCH_TYPES, true)) throw new AppException('نوع مسابقه معتبر نیست', 422);

        $classId = self::normalizeOptionalInt($data['class_id'] ?? null);
        $ageGroupId = self::normalizeOptionalInt($data['age_group_id'] ?? null);

        if ($classId === null && $ageGroupId === null) throw new AppException('مسابقه باید حداقل به یک کلاس یا گروه سنی مرتبط باشد', 422);

        if ($classId !== null && !ClassRepository::findById($classId)) throw new AppException('کلاس یافت نشد', 404);
        if ($ageGroupId !== null && !AgeGroupRepository::findById($ageGroupId)) throw new AppException('گروه سنی یافت نشد', 404);

        $matchDate = trim((string) ($data['match_date'] ?? ''));
        $matchTime = self::normalizeTime($data['match_time'] ?? null);
        self::validateDate($matchDate);

        $status = (string) ($data['status'] ?? 'planned');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $result = $data['result'] ?? null;
        if ($result !== null && $result !== '' && !in_array($result, self::RESULTS, true)) throw new AppException('نتیجه معتبر نیست', 422);

        $matchId = MatchRepository::create([
            'title' => $title,
            'match_type' => $matchType,
            'class_id' => $classId,
            'age_group_id' => $ageGroupId,
            'opponent_team' => trim((string) ($data['opponent_team'] ?? '')) ?: null,
            'match_date' => $matchDate,
            'match_time' => $matchTime,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'status' => $status,
            'home_score' => self::normalizeOptionalScore($data['home_score'] ?? null),
            'away_score' => self::normalizeOptionalScore($data['away_score'] ?? null),
            'result' => $result !== '' ? $result : null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return self::getDetailed($matchId);
    }

    public static function update(int $id, array $data): array
    {
        $match = self::requireMatch($id);
        $updateData = [];

        if (array_key_exists('title', $data)) {
            $v = trim((string) $data['title']);
            if ($v === '') throw new AppException('عنوان معتبر نیست', 422);
            $updateData['title'] = $v;
        }

        if (array_key_exists('match_type', $data)) {
            $v = (string) $data['match_type'];
            if (!in_array($v, self::MATCH_TYPES, true)) throw new AppException('نوع مسابقه معتبر نیست', 422);
            $updateData['match_type'] = $v;
        }

        foreach (['class_id', 'age_group_id'] as $f) {
            if (array_key_exists($f, $data)) {
                $v = self::normalizeOptionalInt($data[$f]);
                if ($f === 'class_id' && $v !== null && !ClassRepository::findById($v)) throw new AppException('کلاس یافت نشد', 404);
                if ($f === 'age_group_id' && $v !== null && !AgeGroupRepository::findById($v)) throw new AppException('گروه سنی یافت نشد', 404);
                $updateData[$f] = $v;
            }
        }

        if (array_key_exists('opponent_team', $data)) {
            $v = trim((string) $data['opponent_team']);
            $updateData['opponent_team'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('match_date', $data)) {
            $v = trim((string) $data['match_date']);
            self::validateDate($v);
            $updateData['match_date'] = $v;
        }

        if (array_key_exists('match_time', $data)) $updateData['match_time'] = self::normalizeTime($data['match_time']);

        if (array_key_exists('location', $data)) {
            $v = trim((string) $data['location']);
            $updateData['location'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        foreach (['home_score', 'away_score'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalScore($data[$f]);
        }

        if (array_key_exists('result', $data)) {
            $v = $data['result'];
            if ($v !== null && $v !== '' && !in_array($v, self::RESULTS, true)) throw new AppException('نتیجه معتبر نیست', 422);
            $updateData['result'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $updateData['notes'] = $v !== '' ? $v : null;
        }

        if (!empty($updateData)) MatchRepository::update($id, $updateData);

        return self::getDetailed($id);
    }

    public static function cancel(int $id): array
    {
        self::requireMatch($id);
        MatchRepository::update($id, ['status' => 'cancelled']);
        return self::getDetailed($id);
    }

    public static function setResult(int $id, array $data): array
    {
        self::requireMatch($id);

        $homeScore = self::normalizeOptionalScore($data['home_score'] ?? null);
        $awayScore = self::normalizeOptionalScore($data['away_score'] ?? null);

        if ($homeScore === null || $awayScore === null) throw new AppException('امتیاز هر دو تیم الزامی است', 422);

        $result = 'unknown';
        if ($homeScore > $awayScore) $result = 'win';
        elseif ($homeScore < $awayScore) $result = 'loss';
        elseif ($homeScore === $awayScore) $result = 'draw';

        MatchRepository::update($id, [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'result' => $result,
            'status' => 'finished',
        ]);

        return self::getDetailed($id);
    }

    public static function addPlayer(int $matchId, array $data): array
    {
        $match = self::requireMatch($matchId);

        if ($match['status'] === 'cancelled') throw new AppException('مسابقه لغو شده است', 422);

        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);

        if (MatchRepository::findMatchPlayer($matchId, $playerId)) throw new AppException('بازیکن قبلاً اضافه شده است', 422);

        $invitationStatus = (string) ($data['invitation_status'] ?? 'invited');
        if (!in_array($invitationStatus, self::INVITATION_STATUSES, true)) throw new AppException('وضعیت دعوت معتبر نیست', 422);

        $attendanceStatus = $data['attendance_status'] ?? null;
        if ($attendanceStatus !== null && $attendanceStatus !== '' && !in_array($attendanceStatus, self::ATTENDANCE_STATUSES, true)) throw new AppException('وضعیت حضور معتبر نیست', 422);

        $matchPlayerId = MatchRepository::createMatchPlayer([
            'match_id' => $matchId,
            'player_id' => $playerId,
            'invitation_status' => $invitationStatus,
            'attendance_status' => $attendanceStatus !== '' ? $attendanceStatus : null,
            'jersey_number' => self::normalizeOptionalInt($data['jersey_number'] ?? null),
            'position' => trim((string) ($data['position'] ?? '')) ?: null,
            'goals' => self::normalizeStat($data['goals'] ?? 0),
            'assists' => self::normalizeStat($data['assists'] ?? 0),
            'yellow_cards' => self::normalizeStat($data['yellow_cards'] ?? 0),
            'red_cards' => self::normalizeStat($data['red_cards'] ?? 0),
            'minutes_played' => self::normalizeOptionalInt($data['minutes_played'] ?? null),
            'rating' => self::normalizeRating($data['rating'] ?? null),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return [
            'match_player_id' => $matchPlayerId,
            'match' => self::getDetailed($matchId),
        ];
    }

    public static function updatePlayer(int $matchPlayerId, array $data): array
    {
        $matchPlayer = self::requireMatchPlayer($matchPlayerId);
        $updateData = [];

        if (array_key_exists('invitation_status', $data)) {
            $v = (string) $data['invitation_status'];
            if (!in_array($v, self::INVITATION_STATUSES, true)) throw new AppException('وضعیت دعوت معتبر نیست', 422);
            $updateData['invitation_status'] = $v;
        }

        if (array_key_exists('attendance_status', $data)) {
            $v = $data['attendance_status'];
            if ($v !== null && $v !== '' && !in_array($v, self::ATTENDANCE_STATUSES, true)) throw new AppException('وضعیت حضور معتبر نیست', 422);
            $updateData['attendance_status'] = $v !== '' ? $v : null;
        }

        if (array_key_exists('jersey_number', $data)) $updateData['jersey_number'] = self::normalizeOptionalInt($data['jersey_number']);

        if (array_key_exists('position', $data)) {
            $v = trim((string) $data['position']);
            $updateData['position'] = $v !== '' ? $v : null;
        }

        foreach (['goals', 'assists', 'yellow_cards', 'red_cards'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeStat($data[$f]);
        }

        if (array_key_exists('minutes_played', $data)) $updateData['minutes_played'] = self::normalizeOptionalInt($data['minutes_played']);
        if (array_key_exists('rating', $data)) $updateData['rating'] = self::normalizeRating($data['rating']);

        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $updateData['notes'] = $v !== '' ? $v : null;
        }

        if (!empty($updateData)) MatchRepository::updateMatchPlayer($matchPlayerId, $updateData);

        return MatchRepository::findMatchPlayerById($matchPlayerId);
    }

    public static function removePlayer(int $matchPlayerId): array
    {
        $matchPlayer = self::requireMatchPlayer($matchPlayerId);
        MatchRepository::deleteMatchPlayer($matchPlayerId);
        return ['match_player_id' => $matchPlayerId, 'match_id' => (int) $matchPlayer['match_id']];
    }

    private static function requireMatch(int $id): array
    {
        $match = MatchRepository::findById($id);
        if (!$match) throw new AppException('مسابقه یافت نشد', 404);
        return $match;
    }

    private static function requireMatchPlayer(int $id): array
    {
        $mp = MatchRepository::findMatchPlayerById($id);
        if (!$mp) throw new AppException('بازیکن مسابقه یافت نشد', 404);
        return $mp;
    }

    private static function validateDate(string $date): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new AppException('تاریخ معتبر نیست', 422);
    }

    private static function normalizeTime(mixed $time): string
    {
        $time = trim((string) $time);
        if ($time === '') throw new AppException('ساعت مسابقه الزامی است', 422);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) throw new AppException('ساعت باید با فرمت HH:MM باشد', 422);
        return $time;
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('عدد معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }

    private static function normalizeOptionalScore(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('امتیاز باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('امتیاز نمی‌تواند منفی باشد', 422);
        return $v;
    }

    private static function normalizeStat(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('آمار باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('آمار نمی‌تواند منفی باشد', 422);
        return $v;
    }

    private static function normalizeRating(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) throw new AppException('امتیاز عملکرد باید عدد باشد', 422);
        $v = (float) $value;
        if ($v < 0 || $v > 10) throw new AppException('امتیاز عملکرد باید بین ۰ تا ۱۰ باشد', 422);
        return $v;
    }
}