<?php

namespace App\Grading;

use App\Enums\Curriculum;
use App\Models\SchoolClass;
use InvalidArgumentException;

class GradingSchemeResolver
{
    /**
     * Resolve the grading scheme for the given class curriculum.
     */
    public static function for(SchoolClass $schoolClass): GradingScheme
    {
        $curriculum = $schoolClass->curriculum instanceof Curriculum
            ? $schoolClass->curriculum
            : Curriculum::tryFrom((string) $schoolClass->curriculum);

        return match ($curriculum) {
            Curriculum::Merdeka => new MerdekaGradingScheme,
            default => throw new InvalidArgumentException("Unsupported curriculum: {$schoolClass->curriculum}"),
        };
    }
}
