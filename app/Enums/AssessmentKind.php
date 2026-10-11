<?php

namespace App\Enums;

enum AssessmentKind: string
{
    case Formative = 'formative';
    case SummativeScope = 'summative_scope';
    case SummativeFinal = 'summative_final';

    /**
     * Human-readable label for assessment kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Formative => 'Formatif',
            self::SummativeScope => 'Sumatif Lingkup Materi',
            self::SummativeFinal => 'Sumatif Akhir Semester',
        };
    }
}
