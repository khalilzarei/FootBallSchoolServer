<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Database;
use App\Repositories\AttendanceRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\SessionRepository;

class AttendanceService
{
    private const STATUSES = ['present', 'absent', 'late', 'excused', 'injured'];

    public static function listForSession(int $sessionId): array
    {
        self::requireSession($sessionId);
        return AttendanceRepository::listForSession($sessionId);
    }

    /**
     * برگه حضور و غیاب: لیست بازیکنان ثبت‌نام‌شده کلاس + وضعیت ذخیره‌شده، در یک پاسخ
     */
    public static function sheet(int $sessionId): array
    {
        $session = self::requireSession($sessionId);
        $classId = (int) $session['class_id'];

        $players = AttendanceRepository::listSheetPlayers($classId);
        $attendances = AttendanceRepository::listForSession($sessionId);

        $byPlayer = [];
        foreach ($attendances as $a) {
            $byPlayer[(int) $a['player_id']] = $a;
        }

        $rows = [];
        foreach ($players as $p) {
            $playerId = (int) $p['player_id'];
            $a = $byPlayer[$playerId] ?? null;

            $rows[] = [
                'player_id' => $playerId,
                'first_name' => $p['first_name'],
                'last_name' => $p['last_name'],
                'full_name' => trim($p['first_name'] . ' ' . $p['last_name']),
                'avatar_path' => $p['avatar_path'],
                'avatar_url' => AvatarService::getAvatarUrl($p['avatar_path'] ?? null, 'player'),
                'status' => $a['status'] ?? null,
                'is_billable' => $a !== null ? (bool) $a['is_billable'] : null,
                'note' => $a['note'] ?? null,
                'recorded_at' => $a['recorded_at'] ?? null,
            ];
        }

        return [
            'session_id' => $sessionId,
            'class_id' => $classId,
            'players' => $rows,
        ];
    }

    public static function saveBulk(int $sessionId, array $items): array
    {
        $session = self::requireSession($sessionId);

        if ($session['status'] === 'cancelled') throw new AppException('برای جلسه لغوشده نمی‌توان حضور و غیاب ثبت کرد', 422);

        if (empty($items)) throw new AppException('لیست حضور و غیاب خالی است', 422);

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $saved = 0;

            foreach ($items as $item) {
                $playerId = (int) ($item['player_id'] ?? 0);
                if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

                $player = PlayerRepository::findById($playerId);
                if (!$player) throw new AppException('بازیکن یافت نشد', 404);

                if (!EnrollmentRepository::hasActiveEnrollment((int) $session['class_id'], $playerId)) {
                    throw new AppException('بازیکن در این کلاس ثبت‌نام فعال ندارد', 422);
                }

                $status = (string) ($item['status'] ?? '');
                if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت حضور معتبر نیست', 422);

                $isBillable = filter_var($item['is_billable'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $note = trim((string) ($item['note'] ?? ''));

                AttendanceRepository::upsert($sessionId, $playerId, [
                    'status' => $status,
                    'is_billable' => $isBillable ? 1 : 0,
                    'note' => $note !== '' ? $note : null,
                ], (int) Auth::id());

                $saved++;
            }

            $pdo->commit();

            return ['session_id' => $sessionId, 'saved_items' => $saved];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function playerAttendances(int $playerId): array
    {
        $player = PlayerRepository::findById($playerId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        return AttendanceRepository::playerAttendances($playerId);
    }

    private static function requireSession(int $sessionId): array
    {
        $session = SessionRepository::findById($sessionId);
        if (!$session) throw new AppException('جلسه یافت نشد', 404);
        return $session;
    }
}