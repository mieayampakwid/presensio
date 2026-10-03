<?php

namespace App\Enums;

enum SubjectGroup: string
{
    case General = 'general';
    case LocalContent = 'local_content';
    case Elective = 'elective';

    /**
     * Human-readable label for subject group.
     */
    public function label(): string
    {
        return match ($this) {
            self::General => 'Mata Pelajaran Umum',
            self::LocalContent => 'Muatan Lokal',
            self::Elective => 'Pilihan',
        };
    }
}
