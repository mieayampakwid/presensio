<?php

namespace App\Support;

enum MerdekaPhase: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';

    /**
     * Resolve MerdekaPhase from grade level (1-12).
     * A = 1-2, B = 3-4, C = 5-6, D = 7-9, E = 10, F = 11-12.
     */
    public static function forGradeLevel(int $gradeLevel): ?self
    {
        return match (true) {
            $gradeLevel >= 1 && $gradeLevel <= 2 => self::A,
            $gradeLevel >= 3 && $gradeLevel <= 4 => self::B,
            $gradeLevel >= 5 && $gradeLevel <= 6 => self::C,
            $gradeLevel >= 7 && $gradeLevel <= 9 => self::D,
            $gradeLevel === 10 => self::E,
            $gradeLevel >= 11 && $gradeLevel <= 12 => self::F,
            default => null,
        };
    }
}
