<?php

namespace App\Enums;

enum PromotionDecision: string
{
    case Promoted = 'promoted';
    case Retained = 'retained';
    case Graduated = 'graduated';

    /**
     * Human-readable label for the report card.
     */
    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Naik Kelas',
            self::Retained => 'Tinggal Kelas',
            self::Graduated => 'Lulus',
        };
    }
}
