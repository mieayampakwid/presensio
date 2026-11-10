<?php

namespace App\Enums;

enum WithdrawalReason: string
{
    case TransferOut = 'transfer_out';
    case DroppedOut = 'dropped_out';
    case Expelled = 'expelled';
    case Deceased = 'deceased';
    case Graduated = 'graduated';
    case Other = 'other';

    /**
     * Human-readable label in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::TransferOut => 'Pindah Sekolah / Mutasi Keluar',
            self::DroppedOut => 'Mengundurkan Diri',
            self::Expelled => 'Dikeluarkan',
            self::Deceased => 'Meninggal Dunia',
            self::Graduated => 'Lulus',
            self::Other => 'Lainnya',
        };
    }
}
