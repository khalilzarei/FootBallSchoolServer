<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AvatarService;

/**
 * داده‌های سمت کلاینت (me/*) — بر پایه‌ی نقش player (لاگین با حساب بازیکن) و coach
 */
class ClientRepository
{
    // ═════════════════════════════════════════════
    // بازیکنِ کاربر جاری
    // ═════════════════════════════════════════════

    /** ردیف بازیکنی که حساب کاربری آن به این user_id وصل است (+ age) */
    public static function playerForUser(int $userId): ?array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.*, TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
            FROM football_players p
            WHERE p.user_id = :user_id AND p.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute(['user_id' => $userId]);

        $player = $stmt->fetch();

        return $player ?: null;
    }

    /** کلاس‌های فعالِ ثبت‌نام‌شده‌ی یک بازیکن */
    public static function playerClassIds(int $playerId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT DISTINCT e.class_id
            FROM football_enrollments e
            INNER JOIN football_classes c ON c.id = e.class_id AND c.deleted_at IS NULL AND c.status = "active"
            WHERE e.player_id = :player_id AND e.status = "active" AND e.ended_at IS NULL
        ');
        $stmt->execute(['player_id' => $playerId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'class_id'));
    }

    // ═════════════════════════════════════════════
    // بافت کاربر (برای اخبار/رسانه مخاطب‌محور)
    // ═════════════════════════════════════════════

    public static function getUserContext(int $userId, string $role): array
    {
        $playerIds = [];
        $classIds = [];
        $ageGroupIds = [];

        if ($role === 'player') {
            $player = self::playerForUser($userId);
            if ($player !== null) {
                $playerIds = [(int) $player['id']];
                $classIds = self::playerClassIds((int) $player['id']);
            }
        }

        if ($role === 'coach') {
            $coachClassIds = self::coachClassIds($userId);
            $classIds = $coachClassIds;
            $playerIds = self::classPlayerIds($coachClassIds);
        }

        if (!empty($classIds)) {
            $ageGroupIds = self::classAgeGroupIds($classIds);
        }

        return [
            'player_ids' => array_values(array_unique(array_map('intval', $playerIds))),
            'class_ids' => array_values(array_unique(array_map('intval', $classIds))),
            'age_group_ids' => array_values(array_unique(array_map('intval', $ageGroupIds))),
        ];
    }

    // ═════════════════════════════════════════════
    // برنامه جلسات
    // ═════════════════════════════════════════════

    public static function scheduleForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();

        // پنجره: ۱۴ روز گذشته تا آینده — تا توضیحات/موضوع جلسات برگزارشده هم دیده شود

        if ($role === 'admin') {
            // ادمینِ هم‌زمان مربی: فقط کلاس‌های خودش (و کلاس‌های هم‌گروه سنی‌اش) — نه همه کلاس‌ها
            $classIds = self::coachScopedClassIds($userId);

            if (empty($classIds)) {
                // ادمین بدون کلاس مربی‌گری → همه جلسات
                $stmt = $pdo->prepare('
                    SELECT s.*, c.title AS class_title
                    FROM football_sessions s
                    INNER JOIN football_classes c ON c.id = s.class_id
                    WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                      AND s.status IN ("scheduled", "makeup", "completed")
                    ORDER BY s.session_date ASC, s.start_time ASC
                    LIMIT 100
                ');
                $stmt->execute();
                return $stmt->fetchAll();
            }
        } elseif ($role === 'coach') {
            // مربی: کلاس‌های خودش + کلاس‌های هم‌گروه سنی‌اش (مربی می‌تواند چند کلاس داشته باشد)
            $classIds = self::coachScopedClassIds($userId);

            if (empty($classIds)) {
                return [];
            }
        } else {
            // بازیکن: کلاس‌های فعالِ ثبت‌نام خودش
            $player = self::playerForUser($userId);
            if ($player === null) return [];

            $classIds = self::playerClassIds((int) $player['id']);
            if (empty($classIds)) return [];
        }

        [$inClause, $params] = self::makeInClause($classIds, 'cid');

        $stmt = $pdo->prepare("
            SELECT s.*, c.title AS class_title
            FROM football_sessions s
            INNER JOIN football_classes c ON c.id = s.class_id
            WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              AND s.status IN (\"scheduled\", \"makeup\", \"completed\")
              AND s.class_id IN ({$inClause})
            ORDER BY s.session_date ASC, s.start_time ASC
            LIMIT 100
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ═════════════════════════════════════════════
    // اخبار / رسانه (مخاطب‌محور)
    // ═════════════════════════════════════════════

    public static function publishedNewsForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();

        if ($role === 'admin') {
            $stmt = $pdo->prepare('
                SELECT n.* FROM football_news n
                WHERE n.status = "published" AND n.deleted_at IS NULL
                ORDER BY n.publish_at DESC, n.id DESC LIMIT 100
            ');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $context = self::getUserContext($userId, $role);

        $params = ['role' => $role];

        $classIn = self::makeInClause($context['class_ids'], 'cid', $params);
        $ageGroupIn = self::makeInClause($context['age_group_ids'], 'aid', $params);
        $playerIn = self::makeInClause($context['player_ids'], 'pid', $params);

        $stmt = $pdo->prepare("
            SELECT DISTINCT n.*
            FROM football_news n
            LEFT JOIN football_news_audiences a ON a.news_id = n.id
            WHERE n.status = \"published\" AND n.deleted_at IS NULL
              AND (
                    a.id IS NULL
                 OR a.audience_type = \"global\"
                 OR (a.audience_type = \"role\" AND a.role = :role)
                 OR (a.audience_type = \"class\" AND a.target_id IN ({$classIn[0]}))
                 OR (a.audience_type = \"age_group\" AND a.target_id IN ({$ageGroupIn[0]}))
                 OR (a.audience_type = \"player\" AND a.target_id IN ({$playerIn[0]}))
              )
            ORDER BY n.publish_at DESC, n.id DESC
            LIMIT 100
        ");

        $stmt->execute(array_merge($params, $classIn[1], $ageGroupIn[1], $playerIn[1]));

        return $stmt->fetchAll();
    }

    public static function visibleMediaForUser(int $userId, string $role): array
    {
        $pdo = Database::connection();

        if ($role === 'admin') {
            $stmt = $pdo->prepare('SELECT m.* FROM football_media m WHERE m.status = "active" ORDER BY m.id DESC LIMIT 100');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $context = self::getUserContext($userId, $role);

        $params = ['user_id' => $userId];

        $classIn = self::makeInClause($context['class_ids'], 'cid', $params);
        $ageGroupIn = self::makeInClause($context['age_group_ids'], 'aid', $params);
        $playerIn = self::makeInClause($context['player_ids'], 'pid', $params);

        $stmt = $pdo->prepare("
            SELECT DISTINCT m.*
            FROM football_media m
            LEFT JOIN football_media_audiences ma ON ma.media_id = m.id
            WHERE m.status = \"active\"
              AND (
                    m.visibility = \"public\"
                 OR (m.visibility = \"private\" AND m.uploader_id = :user_id)
                 OR (
                        m.visibility IN (\"class\", \"age_group\", \"players\")
                        AND (
                              (m.visibility = \"class\" AND ma.audience_type = \"class\" AND ma.target_id IN ({$classIn[0]}))
                           OR (m.visibility = \"age_group\" AND ma.audience_type = \"age_group\" AND ma.target_id IN ({$ageGroupIn[0]}))
                           OR (m.visibility = \"players\" AND ma.audience_type = \"player\" AND ma.target_id IN ({$playerIn[0]}))
                        )
                 )
              )
            ORDER BY m.id DESC
            LIMIT 100
        ");

        $stmt->execute(array_merge($params, $classIn[1], $ageGroupIn[1], $playerIn[1]));

        return $stmt->fetchAll();
    }

    // ═════════════════════════════════════════════
    // صورت حساب بازیکن
    // ═════════════════════════════════════════════

    /**
     * وضعیت مالی یک بازیکن: جمع فاکتور/پرداخت + فاکتورهای فعال + پرداخت‌های در انتظار
     * + جزئیات آیتم‌های هر فاکتور با عنوان کلاس + خلاصه بدهی به تفکیک کلاس
     */
    public static function financeForPlayer(int $playerId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.id AS player_id, p.first_name, p.last_name
            FROM football_players p
            WHERE p.id = :pid AND p.deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute(['pid' => $playerId]);
        $c = $stmt->fetch();
        if (!$c) return [];

        // فاکتورهای فعال (غیر پیش‌نویس و غیر لغو)
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, invoice_type, period_start_date, period_end_date,
                   due_date, status, subtotal, discount_total, paid_total, remaining_total, notes
            FROM football_invoices
            WHERE player_id = :pid AND status NOT IN ('draft', 'cancelled')
            ORDER BY due_date ASC, id ASC
        ");
        $stmt->execute(['pid' => $playerId]);
        $rawInvoices = $stmt->fetchAll();

        $invoices = [];
        $allInvoiceIds = [];
        foreach ($rawInvoices as $i) {
            $allInvoiceIds[] = (int) $i['id'];
        }

        // آیتم‌های فاکتورها با عنوان کلاس
        $itemsByInvoice = [];
        $classDebt = []; // class_id => [title, total, paid, remaining]
        if (!empty($allInvoiceIds)) {
            $ids = implode(',', $allInvoiceIds);
            $stmt = $pdo->prepare("
                SELECT ii.*, c.title AS class_title, ag.title AS age_group_title
                FROM football_invoice_items ii
                LEFT JOIN football_classes c ON c.id = ii.class_id
                LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
                WHERE ii.invoice_id IN ({$ids})
                ORDER BY ii.id ASC
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $it) {
                $invId = (int) $it['invoice_id'];
                $itemsByInvoice[$invId][] = [
                    'id' => (int) $it['id'],
                    'title' => (string) $it['title'],
                    'item_type' => (string) $it['item_type'],
                    'amount' => (int) $it['amount'],
                    'quantity' => (int) $it['quantity'],
                    'total' => (int) $it['total'],
                    'class_id' => isset($it['class_id']) ? (int) $it['class_id'] : null,
                    'class_title' => $it['class_title'] ?? null,
                    'age_group_title' => $it['age_group_title'] ?? null,
                    'description' => $it['description'] ?? null,
                ];

                // برای خلاصه به تفکیک کلاس
                $classId = $it['class_id'] ? (int) $it['class_id'] : 0;
                $classKey = $classId !== 0 ? $classId : -1;
                if (!isset($classDebt[$classKey])) {
                    $classDebt[$classKey] = [
                        'class_id' => $classId !== 0 ? $classId : null,
                        'class_title' => $it['class_title'] ?? ($classId === 0 ? 'سایر هزینه‌ها' : 'کلاس #' . $classId),
                        'age_group_title' => $it['age_group_title'] ?? null,
                        'total' => 0,
                        'paid' => 0,
                        'remaining' => 0,
                        'items_count' => 0,
                    ];
                }
                $classDebt[$classKey]['total'] += (int) $it['total'];
                $classDebt[$classKey]['items_count']++;
            }
        }

        foreach ($rawInvoices as $i) {
            $id = (int) $i['id'];
            $total = (int) $i['paid_total'] + (int) $i['remaining_total'];
            $invoices[] = [
                'id' => $id,
                'invoice_number' => (string) $i['invoice_number'],
                'invoice_type' => (string) $i['invoice_type'],
                'period_start_date' => $i['period_start_date'],
                'period_end_date' => $i['period_end_date'],
                'due_date' => $i['due_date'],
                'status' => (string) $i['status'],
                'subtotal' => (int) ($i['subtotal'] ?? $total),
                'discount_total' => (int) ($i['discount_total'] ?? 0),
                'total_amount' => $total,
                'paid_amount' => (int) $i['paid_total'],
                'remaining_amount' => (int) $i['remaining_total'],
                'notes' => $i['notes'] ?? null,
                'items' => $itemsByInvoice[$id] ?? [],
            ];
        }

        // توزیع paid/remaining به تفکیک کلاس بر اساس نسبت آیتم‌ها در هر فاکتور
        foreach ($invoices as $inv) {
            $invTotal = $inv['total_amount'] > 0 ? $inv['total_amount'] : 1;
            foreach ($inv['items'] as $it) {
                $classId = $it['class_id'] ? (int) $it['class_id'] : 0;
                $classKey = $classId !== 0 ? $classId : -1;
                if (!isset($classDebt[$classKey])) continue;
                $ratio = $it['total'] / $invTotal;
                $classDebt[$classKey]['paid'] += (int) round($inv['paid_amount'] * $ratio);
                $classDebt[$classKey]['remaining'] += (int) round($inv['remaining_amount'] * $ratio);
            }
        }

        // پرداخت‌های در انتظار تأیید
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
            FROM football_payments
            WHERE player_id = :pid AND status = 'pending'
        ");
        $stmt->execute(['pid' => $playerId]);
        $pending = $stmt->fetch() ?: ['cnt' => 0, 'total' => 0];

        $totalInvoiced = array_sum(array_map(fn($i) => $i['total_amount'], $invoices));
        $totalPaid = array_sum(array_map(fn($i) => $i['paid_amount'], $invoices));

        // کلاس‌های فعلی بازیکن برای نمایش شهریه مصوب هر کلاس
        $currentClasses = \App\Repositories\EnrollmentRepository::currentClassByPlayerIds([$playerId]);
        $enrolledClassInfo = $currentClasses[$playerId] ?? null;
        $classFees = [];
        if (!empty($currentClasses)) {
            $classIds = array_keys($currentClasses);
            // شهریه مصوب هر کلاس - با پشتیبانی فصلی
            $ids = implode(',', array_map('intval', $classIds));
            try {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.title, c.monthly_fee, c.seasonal_fee, c.billing_cycle, c.billing_months, c.session_fee, c.registration_fee,
                           ag.title AS age_group_title
                    FROM football_classes c
                    LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
                    WHERE c.id IN ({$ids})
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.title, c.monthly_fee, c.session_fee, c.registration_fee,
                           ag.title AS age_group_title
                    FROM football_classes c
                    LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
                    WHERE c.id IN ({$ids})
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll();
            }
            foreach ($rows as $cc) {
                $cid = (int) $cc['id'];
                $classFees[] = [
                    'class_id' => $cid,
                    'class_title' => (string) $cc['title'],
                    'age_group_title' => $cc['age_group_title'] ?? null,
                    'monthly_fee' => isset($cc['monthly_fee']) ? (int) $cc['monthly_fee'] : null,
                    'seasonal_fee' => isset($cc['seasonal_fee']) ? (int) $cc['seasonal_fee'] : null,
                    'billing_cycle' => $cc['billing_cycle'] ?? 'monthly',
                    'billing_months' => isset($cc['billing_months']) ? (int) $cc['billing_months'] : (($cc['billing_cycle'] ?? 'monthly') === 'seasonal' ? 3 : 1),
                    'session_fee' => isset($cc['session_fee']) ? (int) $cc['session_fee'] : null,
                    'registration_fee' => isset($cc['registration_fee']) ? (int) $cc['registration_fee'] : null,
                    'enrolled' => true,
                    'debt' => $classDebt[$cid]['remaining'] ?? 0,
                    'paid' => $classDebt[$cid]['paid'] ?? 0,
                    'total' => $classDebt[$cid]['total'] ?? 0,
                ];
            }
        }

        return [
            [
                'player_id' => (int) $c['player_id'],
                'player_name' => trim(((string) $c['first_name']) . ' ' . ((string) $c['last_name'])),
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'balance' => $totalInvoiced - $totalPaid,
                'debt' => $totalInvoiced - $totalPaid,
                'pending_payments' => (int) $pending['cnt'],
                'pending_amount' => (int) $pending['total'],
                'invoices' => $invoices,
                'class_debts' => array_values($classDebt),
                'class_fees' => $classFees,
                'is_debtor' => ($totalInvoiced - $totalPaid) > 0,
            ],
        ];
    }

    // ═════════════════════════════════════════════
    // کلاس‌های بازیکن
    // ═════════════════════════════════════════════

    /** کلاس‌های فعال بازیکن: مربی + برنامه هفتگی */
    public static function classesForPlayer(int $playerId): array
    {
        $classIds = self::playerClassIds($playerId);
        if (empty($classIds)) return [];

        $pdo = Database::connection();
        [$classIn, $classParams] = self::makeInClause($classIds, 'cid');

        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.location, c.description, c.capacity, c.status,
                   ag.title AS age_group_title,
                   cu.full_name AS coach_name,
                   acu.full_name AS assistant_coach_name,
                   (SELECT COUNT(*) FROM football_enrollments e2
                    WHERE e2.class_id = c.id AND e2.status = 'active' AND e2.ended_at IS NULL) AS enrolled_count
            FROM football_classes c
            LEFT JOIN football_age_groups ag ON ag.id = c.age_group_id
            LEFT JOIN football_coaches co ON co.id = c.coach_id
            LEFT JOIN football_users cu ON cu.id = co.user_id
            LEFT JOIN football_coaches aco ON aco.id = c.assistant_coach_id
            LEFT JOIN football_users acu ON acu.id = aco.user_id
            WHERE c.id IN ({$classIn})
            ORDER BY c.id ASC
        ");
        $stmt->execute($classParams);
        $classes = $stmt->fetchAll();

        // برنامه هفتگی — قرارداد weekday سرور: ۱=شنبه ... ۷=جمعه
        $weekdayLabels = [1 => 'شنبه', 2 => 'یکشنبه', 3 => 'دوشنبه', 4 => 'سه‌شنبه', 5 => 'چهارشنبه', 6 => 'پنج‌شنبه', 7 => 'جمعه'];
        $stmt = $pdo->prepare("
            SELECT class_id, weekday, start_time, end_time, location
            FROM football_class_schedules
            WHERE class_id IN ({$classIn}) AND status = 'active'
            ORDER BY weekday ASC, start_time ASC
        ");
        $stmt->execute($classParams);
        $schedulesByClass = [];
        foreach ($stmt->fetchAll() as $s) {
            $wd = (int) $s['weekday'];
            $schedulesByClass[(int) $s['class_id']][] = [
                'weekday' => $wd,
                'weekday_label' => $weekdayLabels[$wd] ?? (string) $wd,
                'start_time' => substr((string) $s['start_time'], 0, 5),
                'end_time' => substr((string) $s['end_time'], 0, 5),
                'location' => $s['location'] ?? null,
            ];
        }

        $out = [];
        foreach ($classes as $c) {
            $cid = (int) $c['id'];
            $out[] = [
                'id' => $cid,
                'title' => (string) $c['title'],
                'age_group_title' => $c['age_group_title'] ?? null,
                'coach_name' => $c['coach_name'] ?? null,
                'assistant_coach_name' => $c['assistant_coach_name'] ?? null,
                'location' => $c['location'] ?? null,
                'description' => $c['description'] ?? null,
                'capacity' => isset($c['capacity']) ? (int) $c['capacity'] : null,
                'enrolled_count' => (int) ($c['enrolled_count'] ?? 0),
                'schedules' => $schedulesByClass[$cid] ?? [],
            ];
        }
        return $out;
    }

    // ═════════════════════════════════════════════
    // مسابقات بازیکن
    // ═════════════════════════════════════════════

    /** مسابقات مرتبط با بازیکن: کلاس‌ها/گروه‌های سنی او یا حضور در ترکیب + اطلاعات دعوت بازیکن */
    public static function matchesForPlayer(int $playerId): array
    {
        $classIds = self::playerClassIds($playerId);

        $pdo = Database::connection();
        $ageGroupIds = [];
        if (!empty($classIds)) {
            $ageGroupIds = self::classAgeGroupIds($classIds);
        }

        if (empty($classIds) && empty($ageGroupIds)) {
            $stmt = $pdo->prepare('
                SELECT m.id, m.title, m.match_type, m.class_id, m.age_group_id, m.opponent_team,
                       m.match_date, m.match_time, m.location, m.status, m.home_score, m.away_score, m.notes, m.result,
                       NULL AS class_title, NULL AS age_group_title,
                       mp.invitation_status, mp.attendance_status, mp.jersey_number,
                       mp.position, mp.goals, mp.assists, mp.yellow_cards, mp.red_cards,
                       mp.minutes_played, mp.rating, mp.notes AS player_notes
                FROM football_matches m
                INNER JOIN football_match_players mp ON mp.match_id = m.id AND mp.player_id = :pid
                ORDER BY m.match_date DESC, m.match_time DESC, m.id DESC
                LIMIT 100
            ');
            $stmt->execute(['pid' => $playerId]);
            return $stmt->fetchAll();
        }

        $ors = [];
        $params = [];

        if (!empty($classIds)) {
            [$in, $p] = self::makeInClause($classIds, 'cid');
            $ors[] = "m.class_id IN ({$in})";
            $params = array_merge($params, $p);
        }
        if (!empty($ageGroupIds)) {
            [$in, $p] = self::makeInClause($ageGroupIds, 'aid');
            $ors[] = "m.age_group_id IN ({$in})";
            $params = array_merge($params, $p);
        }
        $ors[] = 'm.id IN (SELECT mp2.match_id FROM football_match_players mp2 WHERE mp2.player_id = :pid2)';
        $params['pid'] = $playerId;
        $params['pid2'] = $playerId;

        $stmt = $pdo->prepare("
            SELECT m.id, m.title, m.match_type, m.class_id, m.age_group_id, m.opponent_team,
                   m.match_date, m.match_time, m.location, m.status, m.home_score, m.away_score, m.notes, m.result,
                   c.title AS class_title, ag.title AS age_group_title,
                   mp.invitation_status, mp.attendance_status, mp.jersey_number,
                   mp.position, mp.goals, mp.assists, mp.yellow_cards, mp.red_cards,
                   mp.minutes_played, mp.rating, mp.notes AS player_notes
            FROM football_matches m
            LEFT JOIN football_classes c ON c.id = m.class_id
            LEFT JOIN football_age_groups ag ON ag.id = m.age_group_id
            LEFT JOIN football_match_players mp ON mp.match_id = m.id AND mp.player_id = :pid
            WHERE (" . implode(' OR ', $ors) . ")
            ORDER BY m.match_date DESC, m.match_time DESC, m.id DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ═════════════════════════════════════════════
    // مخاطبین گفتگو
    // ═════════════════════════════════════════════

    /**
     * مخاطبین قابل گفتگو: ادمین‌های فعال همیشه +
     * بازیکن: مربیان کلاس‌های فعال خودش / مربی: بازیکنانِ دارای حساب در کلاس‌های فعال خودش
     */
    public static function chatContactsForUser(int $userId, string $role): array
    {
        if ($role === 'admin') return [];

        $pdo = Database::connection();
        $out = [];

        // ادمین‌های فعال — همیشه قابل گفتگو
        $stmt = $pdo->prepare('
            SELECT u.id, u.full_name, u.avatar_path
            FROM football_users u
            WHERE u.role = "admin" AND u.status = "active" AND u.deleted_at IS NULL
            ORDER BY u.full_name ASC
        ');
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['id']] = [
                'user_id' => (int) $r['id'],
                'full_name' => (string) $r['full_name'],
                'role' => 'admin',
                'avatar_url' => AvatarService::getAvatarUrl($r['avatar_path'] ?? null, 'user'),
                'class_title' => null,
            ];
        }

        if ($role === 'player') {
            // مربیان اصلی و کمکی کلاس‌های فعال بازیکن
            $stmt = $pdo->prepare('
                SELECT DISTINCT u.id, u.full_name, u.avatar_path, c.title AS class_title
                FROM football_enrollments e
                INNER JOIN football_classes c ON c.id = e.class_id AND c.status = "active" AND c.deleted_at IS NULL
                INNER JOIN football_coaches co ON (co.id = c.coach_id OR co.id = c.assistant_coach_id)
                INNER JOIN football_users u ON u.id = co.user_id AND u.status = "active" AND u.deleted_at IS NULL
                WHERE e.player_id = :pid AND e.status = "active" AND e.ended_at IS NULL
                ORDER BY u.full_name ASC
            ');
            $player = self::playerForUser($userId);
            if ($player === null) return array_values($out);
            $stmt->execute(['pid' => (int) $player['id']]);
            $contactRole = 'coach';
        } else {
            // مربی: بازیکنانِ دارای حساب کاربری در کلاس‌های فعال او
            $stmt = $pdo->prepare('
                SELECT DISTINCT u.id, u.full_name, u.avatar_path, c.title AS class_title
                FROM football_coaches co2
                INNER JOIN football_classes c ON (c.coach_id = co2.id OR c.assistant_coach_id = co2.id)
                    AND c.status = "active" AND c.deleted_at IS NULL
                INNER JOIN football_enrollments e ON e.class_id = c.id AND e.status = "active" AND e.ended_at IS NULL
                INNER JOIN football_players p ON p.id = e.player_id AND p.deleted_at IS NULL AND p.user_id IS NOT NULL
                INNER JOIN football_users u ON u.id = p.user_id AND u.status = "active" AND u.deleted_at IS NULL
                WHERE co2.user_id = :uid
                ORDER BY u.full_name ASC
            ');
            $stmt->execute(['uid' => $userId]);
            $contactRole = 'player';
        }

        foreach ($stmt->fetchAll() as $r) {
            $uid = (int) $r['id'];
            if (isset($out[$uid])) continue;
            $out[$uid] = [
                'user_id' => $uid,
                'full_name' => (string) $r['full_name'],
                'role' => $contactRole,
                'avatar_url' => AvatarService::getAvatarUrl($r['avatar_path'] ?? null, 'user'),
                'class_title' => $r['class_title'] ?? null,
            ];
        }

        return array_values($out);
    }

    // ═════════════════════════════════════════════
    // ارزیابی عملکرد بازیکن (برای نمایش توضیح مربی در هر جلسه)
    // ═════════════════════════════════════════════

    public static function evaluationsForPlayer(int $playerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT ev.*, s.session_date, s.start_time, s.class_id, c.title AS class_title,
                   u.full_name AS coach_name
            FROM football_evaluations ev
            LEFT JOIN football_sessions s ON s.id = ev.session_id
            LEFT JOIN football_classes c ON c.id = s.class_id
            LEFT JOIN football_coaches co ON co.id = ev.coach_id
            LEFT JOIN football_users u ON u.id = co.user_id
            WHERE ev.player_id = :pid AND ev.status = "active"
            ORDER BY s.session_date DESC, ev.id DESC
            LIMIT 200
        ');
        $stmt->execute(['pid' => $playerId]);
        return $stmt->fetchAll();
    }

    // ═════════════════════════════════════════════
    // ابزارهای مشترک
    // ═════════════════════════════════════════════

    private static function coachScopedClassIds(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM football_coaches WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $coach = $stmt->fetch();

        if (!$coach) {
            return [];
        }

        $coachId = (int) $coach['id'];

        // کلاس‌های خود مربی (سرمربی یا کمکی) — فعال و حذف‌نشده
        $stmt = $pdo->prepare('
            SELECT id, age_group_id
            FROM football_classes
            WHERE (coach_id = :coach_id OR assistant_coach_id = :coach_id_2)
              AND status = "active" AND deleted_at IS NULL
        ');
        $stmt->execute(['coach_id' => $coachId, 'coach_id_2' => $coachId]);
        $ownClasses = $stmt->fetchAll();

        if (empty($ownClasses)) {
            return [];
        }

        $classIds = array_map('intval', array_column($ownClasses, 'id'));

        // گروه‌های سنی کلاس‌های خود مربی (null نادیده گرفته می‌شود)
        $ageGroupIds = [];
        foreach ($ownClasses as $c) {
            $ag = $c['age_group_id'] ?? null;
            if ($ag !== null && (int) $ag > 0) {
                $ageGroupIds[(int) $ag] = true;
            }
        }
        $ageGroupIds = array_keys($ageGroupIds);

        if (empty($ageGroupIds)) {
            return $classIds;
        }

        // + کلاس‌های فعالِ هم‌گروه سنی
        [$in, $params] = self::makeInClause($ageGroupIds, 'ag');
        $stmt = $pdo->prepare("
            SELECT id FROM football_classes
            WHERE age_group_id IN ({$in}) AND status = 'active' AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return array_values(array_unique(array_merge($classIds, array_map('intval', array_column($stmt->fetchAll(), 'id')))));
    }

    private static function coachClassIds(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT c.id
            FROM football_classes c
            INNER JOIN football_coaches co ON (co.id = c.coach_id OR co.id = c.assistant_coach_id)
            WHERE co.user_id = :user_id AND c.status = "active" AND c.deleted_at IS NULL
        ');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private static function classPlayerIds(array $classIds): array
    {
        if (empty($classIds)) return [];
        $pdo = Database::connection();
        [$in, $params] = self::makeInClause($classIds, 'cid');

        $stmt = $pdo->prepare("
            SELECT DISTINCT e.player_id
            FROM football_enrollments e
            INNER JOIN football_players p ON p.id = e.player_id AND p.deleted_at IS NULL
            WHERE e.class_id IN ({$in}) AND e.status = 'active' AND e.ended_at IS NULL
        ");
        $stmt->execute($params);

        return array_map('intval', array_column($stmt->fetchAll(), 'player_id'));
    }

    private static function classAgeGroupIds(array $classIds): array
    {
        if (empty($classIds)) return [];
        $pdo = Database::connection();
        [$in, $params] = self::makeInClause($classIds, 'cid');

        $stmt = $pdo->prepare("
            SELECT DISTINCT age_group_id
            FROM football_classes
            WHERE id IN ({$in}) AND age_group_id IS NOT NULL AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return array_map('intval', array_column($stmt->fetchAll(), 'age_group_id'));
    }

    private static function makeInClause(array $ids, string $prefix, array &$params = []): array
    {
        if (empty($ids)) {
            return ['0', []];
        }

        $parts = [];
        $localParams = [];
        $index = 0;

        foreach ($ids as $id) {
            $key = $prefix . $index;
            $parts[] = ':' . $key;
            $localParams[$key] = (int) $id;
            $index++;
        }

        return [implode(', ', $parts), $localParams];
    }
}
