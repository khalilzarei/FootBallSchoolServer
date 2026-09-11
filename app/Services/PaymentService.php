<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Core\Auth;
use App\Core\Database;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PlayerRepository;

class PaymentService
{
    private const PAYMENT_METHODS = ['cash', 'card_transfer', 'pos', 'cheque', 'online', 'other'];

    public static function list(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $status = trim((string) ($query['status'] ?? ''));
        if ($status !== '' && !in_array($status, ['pending', 'approved', 'rejected'], true)) throw new AppException('وضعیت معتبر نیست', 422);

        return PaymentRepository::paginate([
            'player_id' => (int) ($query['player_id'] ?? 0) > 0 ? (int) $query['player_id'] : null,
            'invoice_id' => (int) ($query['invoice_id'] ?? 0) > 0 ? (int) $query['invoice_id'] : null,
            'status' => $status !== '' ? $status : null,
        ], $page, $perPage);
    }

    public static function get(int $id): array
    {
        $payment = PaymentRepository::findById($id);
        if (!$payment) throw new AppException('پرداخت یافت نشد', 404);
        return $payment;
    }

    public static function create(array $data): array
    {
        $playerId = (int) ($data['player_id'] ?? 0);
        if ($playerId <= 0) throw new AppException('شناسه بازیکن معتبر نیست', 422);

        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);

        $amount = self::normalizeAmount($data['amount'] ?? null);
        if ($amount <= 0) throw new AppException('مبلغ پرداخت باید بیشتر از صفر باشد', 422);

        $paymentMethod = (string) ($data['payment_method'] ?? '');
        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) throw new AppException('روش پرداخت معتبر نیست', 422);

        $status = (string) ($data['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'approved'], true)) throw new AppException('وضعیت پرداخت در زمان ایجاد فقط می‌تواند pending یا approved باشد', 422);

        $invoiceId = self::normalizeOptionalInt($data['invoice_id'] ?? null);
        $installmentId = self::normalizeOptionalInt($data['installment_id'] ?? null);

        $invoice = null;

        if ($invoiceId !== null) {
            $invoice = InvoiceRepository::findById($invoiceId);
            if (!$invoice) throw new AppException('فاکتور یافت نشد', 404);
            if ((int) $invoice['player_id'] !== $playerId) throw new AppException('فاکتور به این بازیکن تعلق ندارد', 422);
            if ($invoice['status'] === 'cancelled') throw new AppException('پرداخت برای فاکتور لغوشده امکان‌پذیر نیست', 422);
        }

        $installment = null;

        if ($installmentId !== null) {
            $installment = PaymentRepository::findInstallmentById($installmentId);
            if (!$installment) throw new AppException('قسط یافت نشد', 404);

            if ($invoiceId === null) {
                $invoiceId = (int) $installment['invoice_id'];
                $invoice = InvoiceRepository::findById($invoiceId);
                if (!$invoice) throw new AppException('فاکتور مرتبط با قسط یافت نشد', 404);
            }

            if ((int) $installment['invoice_id'] !== $invoiceId) throw new AppException('قسط به فاکتور انتخابی تعلق ندارد', 422);

            $remainingInstallment = (int) $installment['amount'] - (int) $installment['paid_amount'];

            if ($status === 'approved' && $amount > $remainingInstallment) {
                throw new AppException('مبلغ پرداخت نمی‌تواند بیشتر از باقی‌مانده قسط باشد', 422);
            }
        }

        $paidAt = null;
        $confirmedBy = null;
        $confirmedAt = null;

        if ($status === 'approved') {
            $paidAt = date('Y-m-d H:i:s');
            $confirmedBy = Auth::id();
            $confirmedAt = date('Y-m-d H:i:s');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $paymentId = PaymentRepository::create([
                'payment_number' => 'PAY-' . strtoupper(bin2hex(random_bytes(6))),
                'player_id' => $playerId,
                'invoice_id' => $invoiceId,
                'installment_id' => $installmentId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => $status,
                'paid_at' => $paidAt,
                'receipt_media_id' => self::normalizeOptionalInt($data['receipt_media_id'] ?? null),
                'gateway_name' => null,
                'gateway_reference' => null,
                'gateway_status' => null,
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => $confirmedAt,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => Auth::id(),
            ]);

            if ($status === 'approved') {
                self::applyApprovedEffects($paymentId);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return self::get($paymentId);
    }

    public static function approve(int $id): array
    {
        $payment = self::get($id);

        if ($payment['status'] !== 'pending') throw new AppException('فقط پرداخت در انتظار تأیید قابل تأیید است', 422);

        $installment = null;

        if (!empty($payment['installment_id'])) {
            $installment = PaymentRepository::findInstallmentById((int) $payment['installment_id']);
            if (!$installment) throw new AppException('قسط مرتبط یافت نشد', 404);

            $remaining = (int) $installment['amount'] - (int) $installment['paid_amount'];
            if ((int) $payment['amount'] > $remaining) throw new AppException('مبلغ پرداخت بیشتر از باقی‌مانده قسط است', 422);
        }

        PaymentRepository::markApproved($id, (int) Auth::id());
        self::applyApprovedEffects($id);

        return self::get($id);
    }

    public static function reject(int $id): array
    {
        $payment = self::get($id);
        if ($payment['status'] !== 'pending') throw new AppException('فقط پرداخت در انتظار تأیید قابل رد کردن است', 422);
        PaymentRepository::markRejected($id, (int) Auth::id());
        return self::get($id);
    }

    public static function playerBalance(int $playerId): array
    {
        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);
        return PaymentRepository::playerBalance($playerId);
    }

    public static function playerPayments(int $playerId, array $query): array
    {
        if (!PlayerRepository::findById($playerId)) throw new AppException('بازیکن یافت نشد', 404);
        $query['player_id'] = $playerId;
        return self::list($query);
    }

    private static function applyApprovedEffects(int $paymentId): void
    {
        $payment = PaymentRepository::findById($paymentId);
        if (!$payment || $payment['status'] !== 'approved') return;

        if (!empty($payment['installment_id'])) {
            PaymentRepository::updateInstallmentPaid((int) $payment['installment_id'], (int) $payment['amount']);
        }

        if (!empty($payment['invoice_id'])) {
            self::recalculateInvoice((int) $payment['invoice_id']);
        }
    }

    private static function recalculateInvoice(int $invoiceId): void
    {
        $invoice = InvoiceRepository::findById($invoiceId);
        if (!$invoice || $invoice['status'] === 'cancelled') return;

        $paidTotal = PaymentRepository::approvedPaymentsTotalForInvoice($invoiceId);
        $subtotal = (int) $invoice['subtotal'];
        $discountTotal = (int) $invoice['discount_total'];
        $remaining = $subtotal - $discountTotal - $paidTotal;
        if ($remaining < 0) $remaining = 0;

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

    private static function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new AppException('شناسه معتبر نیست', 422);
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }
}