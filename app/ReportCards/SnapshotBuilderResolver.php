<?php

namespace App\ReportCards;

use App\Enums\Curriculum;
use App\Models\SchoolClass;
use InvalidArgumentException;

class SnapshotBuilderResolver
{
    /**
     * Resolve the snapshot builder for the given class curriculum
     * (mirrors the GradingSchemeResolver seam).
     */
    public static function for(SchoolClass $schoolClass): ReportCardSnapshotBuilder
    {
        $curriculum = $schoolClass->curriculum instanceof Curriculum
            ? $schoolClass->curriculum
            : Curriculum::tryFrom((string) $schoolClass->curriculum);

        return match ($curriculum) {
            Curriculum::Merdeka => app(MerdekaSnapshotBuilder::class),
            default => throw new InvalidArgumentException("Unsupported curriculum: {$schoolClass->curriculum}"),
        };
    }
}
