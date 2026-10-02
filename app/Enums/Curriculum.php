<?php

namespace App\Enums;

enum Curriculum: string
{
    case Merdeka = 'merdeka';

    /**
     * Human-readable label for curriculum.
     */
    public function label(): string
    {
        return match ($this) {
            self::Merdeka => 'Kurikulum Merdeka',
        };
    }
}
