<?php

namespace App\Enums;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    /**
     * Human-readable label for journal status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Posted => 'Diposting',
        };
    }
}
