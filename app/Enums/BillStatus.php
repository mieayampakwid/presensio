<?php

namespace App\Enums;

enum BillStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Waived = 'waived';
    case Cancelled = 'cancelled';
    case WrittenOff = 'written_off';

    /**
     * Human-readable label for bill status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::PartiallyPaid => 'Dibayar Sebagian',
            self::Paid => 'Lunas',
            self::Waived => 'Dibebaskan',
            self::Cancelled => 'Dibatalkan',
            self::WrittenOff => 'Dihapusbukukan',
        };
    }
}
