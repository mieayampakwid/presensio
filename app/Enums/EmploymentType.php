<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Pns = 'pns';
    case Pppk = 'pppk';
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Honorary = 'honorary';

    /**
     * Human-readable label for employment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pns => 'PNS',
            self::Pppk => 'PPPK',
            self::Permanent => 'Tetap (GTY/PTY)',
            self::Contract => 'Kontrak (GTT/PTT)',
            self::Honorary => 'Honorer',
        };
    }
}
