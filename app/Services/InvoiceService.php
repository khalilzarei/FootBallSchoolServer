<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Database;
use App\Repositories\DiscountRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PlayerRepository;
use DateTime;

class InvoiceService
{
    private const INVOICE_TYPES = ['monthly', 'session', 'registration', 'manual', 'match'];
    private const ITEM_TYPES = ['registration', 'monthly', 'session', 'manual', 'match'];
    private const DISCOUNT_TYPES = ['percent', 'fixed'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        return InvoiceRepository::paginate([
            'player_id' => (int) ($query['player_id'] ?? 0) > 0 ? (int) $query['player_id'] : null,
            'status' => trim((string) ($query['status'] ?? '')) ?: null,
            'invoice_type' => trim((string) ($query['invoice_type'] ?? '')) ?: null,
            'q' => trim((string) ($query['q'] ?? '')) ?: null,
        ], $page, $perPage);
    }

    public static function getDetailed(int $id): array
    {
        $invoice = self::requireInvoice($id);
        $invoice['items'] = InvoiceRepository::items($id);
        $invoice['discounts'] = InvoiceRepository::discounts($id);
        $invoice['installments'] = InvoiceRepository::installments($id);
        return $invoice;
    }

    public static function create(array $data): array
    {
        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);

        $invoiceType = (string) ($data['invoice_type'] ?? '');
        if (!in_array($invoiceType, self::INVOICE_TYPES, true)) throw new AppException('نوع فاکتور معتبر نیست', 422);

        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) throw new AppException('فاکتور باید حداقل یک آیتم داشته باشد', 422);

        $periodStartDate = self::normalizeOptionalDate($data['period_start_date'] ?? null);
        $periodEndDate = self::normalizeOptionalDate($data['period_end_date'] ?? null);
        $dueDate = self::normalizeOptionalDate($data['due_date'] ?? null);

        self::assertDateRange($periodStartDate, $periodEndDate);

        $invoiceNumber = 'INV-' . strtoupper(bin2hex(random_bytes(6)));

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $invoiceId = InvoiceRepository::create([
                'invoice_number' => $invoiceNumber,
                'player_id' => $playerId,
                'invoice_type' => $invoiceType,
                'period_start_date' => $periodStartDate,
                'period_end_date' => $periodEndDate,
                'status' => 'draft',
                'due_date' => $dueDate,
                'subtotal' => 0,
                'discount_total' => 0,
                'paid_total' => 0,
                'remaining_total' => 0,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => Auth::id(),
            ]);

            $subtotal = 0;

            foreach ($items as $item) {
                $subtotal += self::createItemInternal($invoiceId, $item);
            }

            $discountTotal = 0;
            $discounts = $data['discounts'] ?? [];

            if (is_array($discounts)) {
                foreach ($discounts as $discount) {
                    $discountTotal += self::applyDiscountInternal($invoiceId, $discount, $subtotal, $discountTotal);
                }
            }

            if ($discountTotal > $subtotal) $discountTotal = $subtotal;

            $remaining = $subtotal - $discountTotal;
            $status = $remaining <= 0 ? 'paid' : 'open';

            InvoiceRepository::updateTotals($invoiceId, $subtotal, $discountTotal, 0, $remaining, $status);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return self::getDetailed($invoiceId);
    }

    public static function cancel(int $id): array
    {
        $invoice = self::requireInvoice($id);
        if ((int) $invoice['paid_total'] > 0) throw new AppException('فاکتوری که پرداخت تأییدشده دارد قابل لغو نیست', 422);
        InvoiceRepository::setStatus($id, 'cancelled');
        return self::getDetailed($id);
    }

    public static function addItem(int $invoiceId, array $data): array
    {
        $invoice = self::requireInvoice($invoiceId);
        self::assertModifiable($invoice);

        self::createItemInternal($invoiceId, $data);
        self::recalculate($invoiceId);

        return self::getDetailed($invoiceId);
    }

    public static function updateItem(int $itemId, array $data): array
    {
        $item = InvoiceRepository::findItemById($itemId);
        if (!$item) throw new AppException('آیتم یافت نشد', 404);

        $invoice = self::requireInvoice((int) $item['invoice_id']);
        self::assertModifiable($invoice);

        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $item['title'];
        if ($title === '') throw new AppException('عنوان آیتم معتبر نیست', 422);

        $itemType = array_key_exists('item_type', $data) ? (string) $data['item_type'] : $item['item_type'];
        if (!in_array($itemType, self::ITEM_TYPES, true)) throw new AppException('نوع آیتم معتبر نیست', 422);

        $amount = array_key_exists('amount', $data) ? self::normalizeAmount($data['amount']) : (int) $item['amount'];
        $quantity = array_key_exists('quantity', $data) ? self::normalizeQuantity($data['quantity']) : (int) $item['quantity'];

        InvoiceRepository::updateItem($itemId, [
            'title' => $title,
            'item_type' => $itemType,
            'amount' => $amount,
            'quantity' => $quantity,
            'total' => $amount * $quantity,
            'class_id' => array_key_exists('class_id', $data) ? self::normalizeOptionalInt($data['class_id']) : $item['class_id'],
            'session_id' => array_key_exists('session_id', $data) ? self::normalizeOptionalInt($data['session_id']) : $item['session_id'],
            'attendance_id' => array_key_exists('attendance_id', $data) ? self::normalizeOptionalInt($data['attendance_id']) : $item['attendance_id'],
            'description' => array_key_exists('description', $data) ? trim((string) $data['description']) ?: null : $item['description'],
        ]);

        self::recalculate((int) $invoice['id']);

        return self::getDetailed((int) $invoice['id']);
    }

    public static function deleteItem(int $itemId): array
    {
        $item = InvoiceRepository::findItemById($itemId);
        if (!$item) throw new AppException('آیتم یافت نشد', 404);

        $invoice = self::requireInvoice((int) $item['invoice_id']);
        self::assertModifiable($invoice);

        InvoiceRepository::deleteItem($itemId);
        self::recalculate((int) $invoice['id']);

        return self::getDetailed((int) $invoice['id']);
    }

    public static function applyDiscount(int $invoiceId, array $data): array
    {
        $invoice = self::requireInvoice($invoiceId);
        self::assertModifiable($invoice);

        $items = InvoiceRepository::items($invoiceId);
        $subtotal = 0;
        foreach ($items as $item) $subtotal += (int) $item['total'];

        $discounts = InvoiceRepository::discounts($invoiceId);
        $currentDiscountTotal = 0;
        foreach ($discounts as $d) $currentDiscountTotal += (int) $d['amount'];

        self::applyDiscountInternal($invoiceId, $data, $subtotal, $currentDiscountTotal);
        self::recalculate($invoiceId);

        return self::getDetailed($invoiceId);
    }

    public static function addInstallment(int $invoiceId, array $data): array
    {
        $invoice = self::requireInvoice($invoiceId);

        if ($invoice['status'] === 'cancelled') throw new AppException('برای فاکتور لغوشده نمی‌توان قسط ثبت کرد', 422);

        $amount = self::normalizeAmount($data['amount'] ?? null);
        $dueDate = self::normalizeRequiredDate($data['due_date'] ?? null);

        if ($amount <= 0) throw new AppException('مبلغ قسط باید بیشتر از صفر باشد', 422);

        $payable = (int) $invoice['subtotal'] - (int) $invoice['discount_total'];
        if ($payable < 0) $payable = 0;

        $existingTotal = InvoiceRepository::installmentsTotal($invoiceId);

        if ($existingTotal + $amount > $payable) throw new AppException('مجموع اقساط نمی‌تواند از مبلغ قابل پرداخت بیشتر شود', 422);

        $installmentNumber = InvoiceRepository::maxInstallmentNumber($invoiceId) + 1;

        InvoiceRepository::createInstallment([
            'invoice_id' => $invoiceId,
            'installment_number' => $installmentNumber,
            'amount' => $amount,
            'paid_amount' => 0,
            'due_date' => $dueDate,
            'status' => 'pending',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return self::getDetailed($invoiceId);
    }

    public static function playerInvoices(int $playerId, array $query): array
    {
        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);
        $query['player_id'] = $playerId;
        return self::list($query);
    }

    private static function requireInvoice(int $id): array
    {
        $invoice = InvoiceRepository::findById($id);
        if (!$invoice) throw new AppException('فاکتور یافت نشد', 404);
        return $invoice;
    }

    private static function assertModifiable(array $invoice): void
    {
        if ($invoice['status'] === 'cancelled') throw new AppException('فاکتور لغوشده قابل ویرایش نیست', 422);
        if ((int) $invoice['paid_total'] > 0) throw new AppException('فاکتوری که پرداخت تأییدشده دارد قابل ویرایش نیست', 422);
    }

    private static function createItemInternal(int $invoiceId, array $item): int
    {
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') throw new AppException('عنوان آیتم الزامی است', 422);

        $itemType = (string) ($item['item_type'] ?? 'manual');
        if (!in_array($itemType, self::ITEM_TYPES, true)) throw new AppException('نوع آیتم معتبر نیست', 422);

        $amount = self::normalizeAmount($item['amount'] ?? null);
        $quantity = self::normalizeQuantity($item['quantity'] ?? 1);
        $total = $amount * $quantity;

        InvoiceRepository::createItem([
            'invoice_id' => $invoiceId,
            'title' => $title,
            'item_type' => $itemType,
            'amount' => $amount,
            'quantity' => $quantity,
            'total' => $total,
            'class_id' => self::normalizeOptionalInt($item['class_id'] ?? null),
            'session_id' => self::normalizeOptionalInt($item['session_id'] ?? null),
            'attendance_id' => self::normalizeOptionalInt($item['attendance_id'] ?? null),
            'description' => trim((string) ($item['description'] ?? '')) ?: null,
        ]);

        return $total;
    }

    private static function applyDiscountInternal(int $invoiceId, array $discount, int $subtotal, int $currentDiscountTotal): int
    {
        $discountId = null;
        $title = '';
        $discountType = '';
        $value = 0;

        if (!empty($discount['discount_id'])) {
            $discountId = (int) $discount['discount_id'];
            $discountRecord = DiscountRepository::findById($discountId);

            if (!$discountRecord) throw new AppException('تخفیف یافت نشد', 404);
            if ($discountRecord['status'] !== 'active') throw new AppException('تخفیف فعال نیست', 422);

            $title = $discountRecord['title'];
            $discountType = $discountRecord['discount_type'];
            $value = (int) $discountRecord['value'];
        } else {
            $title = trim((string) ($discount['title'] ?? ''));
            if ($title === '') throw new AppException('عنوان تخفیف الزامی است', 422);

            $discountType = (string) ($discount['discount_type'] ?? '');
            if (!in_array($discountType, self::DISCOUNT_TYPES, true)) throw new AppException('نوع تخفیف معتبر نیست', 422);

            $value = self::normalizeAmount($discount['value'] ?? null);
        }

        if ($discountType === 'percent') {
            if ($value < 0 || $value > 100) throw new AppException('درصد تخفیف باید بین ۰ تا ۱۰۰ باشد', 422);
            $amount = (int) round($subtotal * $value / 100);
        } else {
            $amount = $value;
        }

        if ($amount < 0) throw new AppException('مبلغ تخفیف نمی‌تواند منفی باشد', 422);

        if ($currentDiscountTotal + $amount > $subtotal) {
            $amount = max(0, $subtotal - $currentDiscountTotal);
        }

        InvoiceRepository::createDiscount([
            'invoice_id' => $invoiceId,
            'discount_id' => $discountId,
            'title' => $title,
            'discount_type' => $discountType,
            'value' => $value,
            'amount' => $amount,
            'description' => trim((string) ($discount['description'] ?? '')) ?: null,
        ]);

        return $amount;
    }

    private static function recalculate(int $invoiceId): void
    {
        $invoice = self::requireInvoice($invoiceId);
        $items = InvoiceRepository::items($invoiceId);
        $discounts = InvoiceRepository::discounts($invoiceId);

        $subtotal = 0;
        foreach ($items as $item) $subtotal += (int) $item['total'];

        $discountTotal = 0;
        foreach ($discounts as $d) $discountTotal += (int) $d['amount'];

        if ($discountTotal > $subtotal) $discountTotal = $subtotal;

        $paidTotal = (int) $invoice['paid_total'];
        $remaining = $subtotal - $discountTotal - $paidTotal;
        if ($remaining < 0) $remaining = 0;

        if ($invoice['status'] === 'cancelled') return;

        $status = 'open';
        if ($remaining <= 0) $status = 'paid';
        elseif ($paidTotal > 0) $status = 'partial';

        InvoiceRepository::updateTotals($invoiceId, $subtotal, $discountTotal, $paidTotal, $remaining, $status);
    }

    private static function normalizeAmount(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('مبلغ باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 0) throw new AppException('مبلغ نمی‌تواند منفی باشد', 422);
        return $v;
    }

    private static function normalizeQuantity(mixed $value): int
    {
        if ($value === null || $value === '') return 1;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('تعداد باید عدد صحیح باشد', 422);
        $v = (int) $value;
        if ($v < 1) throw new AppException('تعداد باید حداقل ۱ باشد', 422);
        return $v;
    }

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('شناسه معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }

    private static function normalizeOptionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = (string) $value;
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function normalizeRequiredDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') throw new AppException('تاریخ الزامی است', 422);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new AppException('تاریخ معتبر نیست', 422);
        return $value;
    }

    private static function assertDateRange(?string $start, ?string $end): void
    {
        if ($start === null || $end === null) return;
        if (new DateTime($end) < new DateTime($start)) throw new AppException('تاریخ پایان نمی‌تواند قبل از شروع باشد', 422);
    }
}