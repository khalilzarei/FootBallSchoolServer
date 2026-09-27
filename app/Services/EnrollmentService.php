<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\PlayerRepository;
use DateTime;

class EnrollmentService
{
    private const STATUSES = ['pending', 'active', 'inactive', 'waitlist', 'completed'];

    public static function listForClass(int $classId, array $query): array
    {
        $class = self::requireClass($classId);

        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        $q = trim((string) ($query['q'] ?? ''));

        if ($status !== '' && !in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $result = EnrollmentRepository::paginateForClass($classId, [
            'status' => $status !== '' ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], $page, $perPage);

        $classLight = $class;
        unset($classLight['schedules']);

        foreach ($result['items'] as &$item) {
            $item['class'] = $classLight;
        }
        unset($item);

        return $result;
    }

    public static function get(int $enrollmentId): array
    {
        return self::requireEnrollment($enrollmentId);
    }

    /**
     * ثبت‌نام گروهی: همه بازیکنان فعالِ گروه سنیِ خودِ کلاس
     */
    public static function enrollAgeGroup(int $classId): array
    {
        $class = self::requireClass($classId);

        if ($class['status'] !== 'active') throw new AppException('ثبت‌نام فقط در کلاس فعال امکان‌پذیر است', 422);

        $ageGroupId = $class['age_group_id'] !== null ? (int) $class['age_group_id'] : null;

        if ($ageGroupId === null) {
            throw new AppException('این کلاس فوق‌العاده است و گروه سنی ندارد؛ بازیکنان را به‌صورت تکی ثبت‌نام کنید', 422);
        }

        $playerIds = EnrollmentRepository::activePlayerIdsByAgeGroup($ageGroupId);
        $total = count($playerIds);

        $capacity = $class['capacity'] !== null ? (int) $class['capacity'] : null;
        $today = date('Y-m-d');

        $created = 0;
        $skippedExisting = 0;
        $skippedCapacity = 0;
        $invoiced = 0;

        foreach ($playerIds as $playerId) {
            if (EnrollmentRepository::findActiveOrPending($classId, $playerId) !== null) {
                $skippedExisting++;
                continue;
            }

            if ($capacity !== null && EnrollmentRepository::activeCount($classId) >= $capacity) {
                $skippedCapacity++;
                continue;
            }

            EnrollmentRepository::create([
                'class_id' => $classId,
                'player_id' => $playerId,
                'status' => 'active',
                'enrolled_at' => $today,
                'ended_at' => null,
                'monthly_fee_override' => null,
                'session_fee_override' => null,
                'registration_fee_override' => null,
                'notes' => 'ثبت‌نام گروهی بر اساس گروه سنی',
                'created_by' => Auth::id(),
            ]);

            $created++;

            try {
                if (self::createInvoiceForEnrollment($class, $playerId, $today, null, null, null, null)) {
                    $invoiced++;
                }
            } catch (\Throwable $e) {
                error_log('Invoice auto-create failed for player ' . $playerId . ' class ' . $classId . ': ' . $e->getMessage());
            }
        }

        if ($created > 0) {
            ChatService::ensureClassRoom($classId, Auth::id());
        }

        return [
            'age_group_id' => $ageGroupId,
            'total_players' => $total,
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'skipped_capacity' => $skippedCapacity,
            'invoiced' => $invoiced,
        ];
    }

    public static function create(int $classId, array $data): array
    {
        $class = self::requireClass($classId);

        if ($class['status'] !== 'active') throw new AppException('ثبت‌نام فقط در کلاس فعال امکان‌پذیر است', 422);

        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        $player = PlayerRepository::findById($playerId);
        if (!$player) throw new AppException('بازیکن یافت نشد', 404);

        $existing = EnrollmentRepository::findActiveOrPending($classId, $playerId);
        if ($existing) throw new AppException('این بازیکن قبلاً در این کلاس ثبت‌نام دارد', 422);

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);

        $enrolledAt = self::normalizeRequiredDate($data['enrolled_at'] ?? date('Y-m-d'));
        $endedAt = self::normalizeOptionalDate($data['ended_at'] ?? null);
        self::assertDateRange($enrolledAt, $endedAt);

        if ($status === 'active') self::assertCapacity($class, $classId);

        $monthlyOverride = self::normalizeOptionalFee($data['monthly_fee_override'] ?? null);
        $seasonalOverride = self::normalizeOptionalFee($data['seasonal_fee_override'] ?? null);
        $sessionOverride = self::normalizeOptionalFee($data['session_fee_override'] ?? null);
        $registrationOverride = self::normalizeOptionalFee($data['registration_fee_override'] ?? null);

        $enrollmentId = EnrollmentRepository::create([
            'class_id' => $classId,
            'player_id' => $playerId,
            'status' => $status,
            'enrolled_at' => $enrolledAt,
            'ended_at' => $endedAt,
            'monthly_fee_override' => $monthlyOverride,
            'seasonal_fee_override' => $seasonalOverride,
            'session_fee_override' => $sessionOverride,
            'registration_fee_override' => $registrationOverride,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        if ($status === 'active') {
            ChatService::ensureClassRoom($classId, Auth::id());
            try {
                self::createInvoiceForEnrollment($class, $playerId, $enrolledAt, $monthlyOverride, $seasonalOverride, $sessionOverride, $registrationOverride);
            } catch (\Throwable $e) {
                error_log('Invoice auto-create failed for player ' . $playerId . ' class ' . $classId . ': ' . $e->getMessage());
            }
        }

        return self::requireEnrollment($enrollmentId);
    }

    public static function update(int $enrollmentId, array $data): array
    {
        $enrollment = self::requireEnrollment($enrollmentId);
        $class = ClassRepository::findById((int) $enrollment['class_id']);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);

        $updateData = [];

        if (array_key_exists('status', $data)) {
            $v = (string) $data['status'];
            if (!in_array($v, self::STATUSES, true)) throw new AppException('وضعیت معتبر نیست', 422);
            $updateData['status'] = $v;
        }

        if (array_key_exists('enrolled_at', $data)) $updateData['enrolled_at'] = self::normalizeRequiredDate($data['enrolled_at']);
        if (array_key_exists('ended_at', $data)) $updateData['ended_at'] = self::normalizeOptionalDate($data['ended_at']);

        foreach (['monthly_fee_override', 'seasonal_fee_override', 'session_fee_override', 'registration_fee_override'] as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = self::normalizeOptionalFee($data[$f]);
        }

        if (array_key_exists('notes', $data)) {
            $v = trim((string) $data['notes']);
            $updateData['notes'] = $v !== '' ? $v : null;
        }

        $finalStatus = $updateData['status'] ?? $enrollment['status'];
        $finalEnrolledAt = $updateData['enrolled_at'] ?? $enrollment['enrolled_at'];
        $finalEndedAt = array_key_exists('ended_at', $updateData) ? $updateData['ended_at'] : $enrollment['ended_at'];

        self::assertDateRange($finalEnrolledAt, $finalEndedAt);

        if ($finalStatus === 'active') {
            if ($class['status'] !== 'active') throw new AppException('ثبت‌نام فعال فقط در کلاس فعال امکان‌پذیر است', 422);
            self::assertCapacity($class, (int) $enrollment['class_id'], $enrollmentId);
        }

        if (!empty($updateData)) EnrollmentRepository::update($enrollmentId, $updateData);

        return self::requireEnrollment($enrollmentId);
    }

    public static function activate(int $enrollmentId): array
    {
        $enrollment = self::requireEnrollment($enrollmentId);
        $class = ClassRepository::findById((int) $enrollment['class_id']);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        if ($class['status'] !== 'active') throw new AppException('کلاس فعال نیست', 422);

        self::assertCapacity($class, (int) $enrollment['class_id'], $enrollmentId);

        EnrollmentRepository::setStatus($enrollmentId, 'active');

        try {
            $playerId = (int) $enrollment['player_id'];
            $enrolledAt = $enrollment['enrolled_at'] ?? date('Y-m-d');
            $monthlyOverride = isset($enrollment['monthly_fee_override']) ? (int) $enrollment['monthly_fee_override'] : null;
            $seasonalOverride = isset($enrollment['seasonal_fee_override']) ? (int) $enrollment['seasonal_fee_override'] : null;
            $sessionOverride = isset($enrollment['session_fee_override']) ? (int) $enrollment['session_fee_override'] : null;
            $registrationOverride = isset($enrollment['registration_fee_override']) ? (int) $enrollment['registration_fee_override'] : null;
            self::createInvoiceForEnrollment($class, $playerId, $enrolledAt, $monthlyOverride, $seasonalOverride, $sessionOverride, $registrationOverride);
        } catch (\Throwable $e) {
            error_log('Invoice auto-create on activate failed: ' . $e->getMessage());
        }

        return self::requireEnrollment($enrollmentId);
    }

    public static function deactivate(int $enrollmentId): array
    {
        self::requireEnrollment($enrollmentId);
        EnrollmentRepository::setStatus($enrollmentId, 'inactive');
        return self::requireEnrollment($enrollmentId);
    }

    /**
     * Backfill: ساخت فاکتور برای تمام ثبت‌نام‌های فعال که فاکتور ندارند
     * @param bool $fixZero اگر true باشد، فاکتورهای با مبلغ صفر حذف و دوباره ساخته می‌شوند
     */
    public static function backfillMissingInvoices(bool $fixZero = false): array
    {
        $pdo = \App\Core\Database::connection();

        $deletedZero = 0;
        if ($fixZero) {
            // حذف فاکتورهای صفر که قبلاً با باگ ساخته شده‌اند
            $stmt = $pdo->prepare("
                DELETE i FROM football_invoices i
                WHERE i.subtotal = 0 AND i.remaining_total = 0 AND i.paid_total = 0
                  AND i.status IN ('open','paid','draft')
            ");
            $stmt->execute();
            $deletedZero = $stmt->rowCount();

            // حذف آیتم‌های یتیم (اگر باقی مانده باشند)
            $pdo->exec("DELETE ii FROM football_invoice_items ii LEFT JOIN football_invoices i ON i.id = ii.invoice_id WHERE i.id IS NULL");
        }

        // تلاش با ستون جدید seasonal_fee_override، در صورت نبود fallback
        try {
            $stmt = $pdo->prepare("
                SELECT e.id, e.class_id, e.player_id, e.enrolled_at, e.monthly_fee_override, e.seasonal_fee_override, e.registration_fee_override, e.session_fee_override
                FROM football_enrollments e
                INNER JOIN football_classes c ON c.id = e.class_id AND c.deleted_at IS NULL
                INNER JOIN football_players p ON p.id = e.player_id AND p.deleted_at IS NULL
                WHERE e.status = 'active' AND e.ended_at IS NULL
                ORDER BY e.id ASC
            ");
            $stmt->execute();
            $enrollments = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $stmt = $pdo->prepare("
                SELECT e.id, e.class_id, e.player_id, e.enrolled_at, e.monthly_fee_override, e.registration_fee_override, e.session_fee_override
                FROM football_enrollments e
                INNER JOIN football_classes c ON c.id = e.class_id AND c.deleted_at IS NULL
                INNER JOIN football_players p ON p.id = e.player_id AND p.deleted_at IS NULL
                WHERE e.status = 'active' AND e.ended_at IS NULL
                ORDER BY e.id ASC
            ");
            $stmt->execute();
            $enrollments = $stmt->fetchAll();
            foreach ($enrollments as &$en) $en['seasonal_fee_override'] = null;
            unset($en);
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($enrollments as $en) {
            $classId = (int) $en['class_id'];
            $playerId = (int) $en['player_id'];

            $class = ClassRepository::findById($classId);
            if (!$class) {
                $skipped++;
                continue;
            }

            try {
                $enrolledAt = $en['enrolled_at'] ?: date('Y-m-d');
                $monthlyOverride = $en['monthly_fee_override'] !== null ? (int) $en['monthly_fee_override'] : null;
                $seasonalOverride = $en['seasonal_fee_override'] !== null ? (int) $en['seasonal_fee_override'] : null;
                $sessionOverride = $en['session_fee_override'] !== null ? (int) $en['session_fee_override'] : null;
                $registrationOverride = $en['registration_fee_override'] !== null ? (int) $en['registration_fee_override'] : null;

                // دیباگ: اگر کلاس هزینه صفر دارد، لاگ کن
                $classMonthly = (int) ($class['monthly_fee'] ?? 0);
                $classSeasonal = (int) ($class['seasonal_fee'] ?? 0);
                $classReg = (int) ($class['registration_fee'] ?? 0);
                $classSession = (int) ($class['session_fee'] ?? 0);
                $billingCycle = $class['billing_cycle'] ?? 'monthly';
                if ($classMonthly == 0 && $classSeasonal == 0 && $classReg == 0 && $classSession == 0) {
                    $details[] = "Skip player $playerId class $classId ({$class['title']}) - class fees are ZERO (monthly=$classMonthly seasonal=$classSeasonal reg=$classReg session=$classSession)";
                    $skipped++;
                    continue;
                }

                $result = self::createInvoiceForEnrollment($class, $playerId, $enrolledAt, $monthlyOverride, $seasonalOverride, $sessionOverride, $registrationOverride);
                if ($result) {
                    $created++;
                    $details[] = "Created invoice for player $playerId class $classId ({$class['title']}) cycle=$billingCycle monthly=$classMonthly seasonal=$classSeasonal reg=$classReg";
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $details[] = "Failed player $playerId class $classId: " . $e->getMessage();
                error_log('Backfill invoice failed: ' . $e->getMessage());
            }
        }

        return [
            'total_enrollments' => count($enrollments),
            'created' => $created,
            'skipped_existing_or_no_fee' => $skipped,
            'failed' => $failed,
            'deleted_zero' => $deletedZero,
            'details' => $details,
        ];
    }

    /**
     * ایجاد فاکتور خودکار برای ثبت‌نام بر اساس هزینه‌های کلاس
     * ساپورت ماهیانه + فصلی (۳ ماهه)
     */
    private static function createInvoiceForEnrollment(array $class, int $playerId, string $enrolledAt, ?int $monthlyOverride, ?int $seasonalOverride, ?int $sessionOverride, ?int $registrationOverride): bool
    {
        // جلوگیری از فاکتور تکراری برای همین کلاس و بازیکن
        try {
            $pdo = \App\Core\Database::connection();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM football_invoice_items ii
                INNER JOIN football_invoices i ON i.id = ii.invoice_id AND i.status != 'cancelled'
                WHERE i.player_id = :player_id AND ii.class_id = :class_id
            ");
            $stmt->execute(['player_id' => $playerId, 'class_id' => (int) $class['id']]);
            if ((int) $stmt->fetch()['cnt'] > 0) {
                return false;
            }
        } catch (\Throwable $e) {
            // اگر چک شکست خورد، ادامه بده
        }

        $billingCycle = $class['billing_cycle'] ?? 'monthly';
        if ($billingCycle === 'quarterly') $billingCycle = 'seasonal';

        // override صفر یا منفی یعنی استفاده از شهریه کلاس (نه رایگان)
        $monthlyFee = ($monthlyOverride !== null && $monthlyOverride > 0) ? $monthlyOverride : ($class['monthly_fee'] !== null ? (int) $class['monthly_fee'] : 0);
        $seasonalFee = ($seasonalOverride !== null && $seasonalOverride > 0) ? $seasonalOverride : ($class['seasonal_fee'] !== null ? (int) $class['seasonal_fee'] : 0);
        $registrationFee = ($registrationOverride !== null && $registrationOverride > 0) ? $registrationOverride : ($class['registration_fee'] !== null ? (int) $class['registration_fee'] : 0);
        $sessionFee = ($sessionOverride !== null && $sessionOverride > 0) ? $sessionOverride : ($class['session_fee'] !== null ? (int) $class['session_fee'] : 0);

        // اگر فصلی است ولی seasonal_fee صفر است، از monthly*3 استفاده کن
        if ($billingCycle === 'seasonal' && $seasonalFee <= 0 && $monthlyFee > 0) {
            $seasonalFee = $monthlyFee * 3;
        }

        // تعیین مبلغ اصلی بر اساس دوره
        $mainFee = 0;
        $mainTitle = '';
        $mainType = 'monthly';
        if ($billingCycle === 'seasonal') {
            $mainFee = $seasonalFee;
            $mainTitle = 'شهریه فصلی (۳ ماهه) کلاس ';
            $mainType = 'seasonal';
        } else {
            $mainFee = $monthlyFee;
            $mainTitle = 'شهریه ماهانه کلاس ';
            $mainType = 'monthly';
        }

        if ($mainFee <= 0 && $registrationFee <= 0 && $sessionFee <= 0) {
            // اگر فصلی بود و مبلغ اصلی صفر بود ولی ماهیانه دارد، fallback به ماهیانه
            if ($billingCycle === 'seasonal' && $monthlyFee > 0) {
                $mainFee = $monthlyFee;
                $mainTitle = 'شهریه ماهانه کلاس ';
                $mainType = 'monthly';
            } else {
                return false;
            }
        }

        $items = [];
        $classId = (int) $class['id'];
        $classTitle = $class['title'] ?? 'کلاس';

        if ($registrationFee > 0) {
            $items[] = [
                'title' => 'حق ثبت‌نام کلاس ' . $classTitle,
                'item_type' => 'registration',
                'amount' => $registrationFee,
                'quantity' => 1,
                'class_id' => $classId,
                'description' => 'ثبت‌نام در کلاس ' . $classTitle,
            ];
        }

        if ($mainFee > 0) {
            $items[] = [
                'title' => $mainTitle . $classTitle,
                'item_type' => $mainType,
                'amount' => $mainFee,
                'quantity' => 1,
                'class_id' => $classId,
                'description' => $mainTitle . ' - ' . $classTitle . ($billingCycle === 'seasonal' ? ' (۳ ماهه)' : ''),
            ];
        } elseif ($sessionFee > 0) {
            $items[] = [
                'title' => 'شهریه جلسه‌ای کلاس ' . $classTitle,
                'item_type' => 'session',
                'amount' => $sessionFee,
                'quantity' => 1,
                'class_id' => $classId,
                'description' => 'شهریه جلسه‌ای - ' . $classTitle,
            ];
        }

        if (empty($items)) return false;

        $dueDate = date('Y-m-d', strtotime($enrolledAt . ' +7 days'));
        $invoiceType = $registrationFee > 0 ? 'registration' : $mainType;
        $billingMonths = $billingCycle === 'seasonal' ? 3 : 1;

        InvoiceService::create([
            'player_id' => $playerId,
            'invoice_type' => $invoiceType,
            'period_start_date' => $enrolledAt,
            'due_date' => $dueDate,
            'notes' => 'فاکتور خودکار ثبت‌نام در کلاس ' . $classTitle . ' - دوره: ' . ($billingCycle === 'seasonal' ? "فصلی ({$billingMonths} ماهه)" : 'ماهیانه'),
            'items' => $items,
        ]);

        return true;
    }

    private static function requireClass(int $classId): array
    {
        $class = ClassRepository::findById($classId);
        if (!$class) throw new AppException('کلاس یافت نشد', 404);
        return $class;
    }

    private static function requireEnrollment(int $id): array
    {
        $enrollment = EnrollmentRepository::findById($id);
        if (!$enrollment) throw new AppException('ثبت‌نام یافت نشد', 404);
        return $enrollment;
    }

    private static function assertCapacity(array $class, int $classId, ?int $exceptId = null): void
    {
        if ($class['capacity'] === null) return;
        $activeCount = EnrollmentRepository::activeCount($classId, $exceptId);
        if ($activeCount >= (int) $class['capacity']) throw new AppException('ظرفیت کلاس تکمیل است', 422);
    }

    private static function normalizeRequiredDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') $value = date('Y-m-d');
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function normalizeOptionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = (string) $value;
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function normalizeOptionalFee(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('مبلغ باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('مبلغ نمی‌تواند منفی باشد', 422);
        return $v;
    }

    private static function assertDateRange(string $enrolledAt, ?string $endedAt): void
    {
        if ($endedAt === null) return;
        if (new DateTime($endedAt) < new DateTime($enrolledAt)) throw new AppException('تاریخ پایان نمی‌تواند قبل از ثبت‌نام باشد', 422);
    }
}
