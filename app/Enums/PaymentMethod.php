<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';

    /**
     * Human-readable label for payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::BankTransfer => 'Transfer Bank',
        };
    }
}
