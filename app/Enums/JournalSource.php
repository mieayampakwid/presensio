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
    case Manual = 'manual';
    case Closing = 'closing';

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
            self::Manual => 'Jurnal Manual',
            self::Closing => 'Tutup Buku',
        };
    }
}
