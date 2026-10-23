<?php

namespace App\Enums;

enum JournalSource: string
{
    case BillRecognition = 'bill_recognition';
    case Payment = 'payment';
    case PaymentVoid = 'payment_void';
    case BillAdjustment = 'bill_adjustment';
    case BillCancellation = 'bill_cancellation';
    case WriteOff = 'write_off';
    case OverpaymentReceipt = 'overpayment_receipt';
    case OverpaymentRefund = 'overpayment_refund';
    case OverpaymentCancellation = 'overpayment_cancellation';
    case Manual = 'manual';
    case Closing = 'closing';

    public function isOverpayment(): bool
    {
        return in_array($this, [self::OverpaymentReceipt, self::OverpaymentRefund, self::OverpaymentCancellation], true);
    }

    /**
     * Human-readable label for journal source.
     */
    public function label(): string
    {
        return match ($this) {
            self::BillRecognition => 'Pengakuan Tagihan',
            self::Payment => 'Pembayaran',
            self::PaymentVoid => 'Pembatalan Pembayaran',
            self::BillAdjustment => 'Penyesuaian Tagihan',
            self::BillCancellation => 'Pembatalan Tagihan',
            self::WriteOff => 'Penghapusan Piutang',
            self::OverpaymentReceipt => 'Penerimaan Kelebihan Bayar',
            self::OverpaymentRefund => 'Pengembalian Kelebihan Bayar',
            self::OverpaymentCancellation => 'Pembatalan Kelebihan Bayar',
            self::Manual => 'Jurnal Manual',
            self::Closing => 'Tutup Buku',
        };
    }
}
