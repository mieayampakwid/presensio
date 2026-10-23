<?php

namespace App\Enums;

enum OverpaymentReason: string
{
    case DoublePayment = 'double_payment';
    case WrongAmount = 'wrong_amount';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DoublePayment => 'Pembayaran ganda',
            self::WrongAmount => 'Salah nominal',
            self::Other => 'Lainnya',
        };
    }
}
