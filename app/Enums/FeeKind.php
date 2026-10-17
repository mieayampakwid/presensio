<?php

namespace App\Enums;

enum FeeKind: string
{
    case MonthlyRecurring = 'monthly_recurring';
    case OneTime = 'one_time';

    /**
     * Human-readable label for fee kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::MonthlyRecurring => 'Bulanan',
            self::OneTime => 'Sekali Bayar',
        };
    }
}
