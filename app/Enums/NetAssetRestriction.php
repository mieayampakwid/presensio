<?php

namespace App\Enums;

enum NetAssetRestriction: string
{
    case WithoutRestriction = 'without_restriction';
    case WithRestriction = 'with_restriction';

    /**
     * Human-readable label for net asset restriction.
     */
    public function label(): string
    {
        return match ($this) {
            self::WithoutRestriction => 'Tanpa Pembatasan',
            self::WithRestriction => 'Dengan Pembatasan',
        };
    }
}
