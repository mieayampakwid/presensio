<?php

namespace App\Enums;

enum PeriodKind: string
{
    case Lesson = 'lesson';
    case Break = 'break';

    /**
     * Human-readable label for the period kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lesson => 'Jam Pelajaran',
            self::Break => 'Istirahat',
        };
    }
}
