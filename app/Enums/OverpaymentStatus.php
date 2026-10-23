<?php

namespace App\Enums;

enum OverpaymentStatus: string
{
    case Held = 'held';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Dititipkan',
            self::Refunded => 'Dikembalikan',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
