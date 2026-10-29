<?php

namespace App\Services\Attendance;

use App\Models\Shift;

/**
 * Tri-state effective shift for one employee on one date (spec 20
 * §Decisions "Effective shift resolution"): an assigned shift, an explicit
 * day off (shift null, dayOff true), or no shift (both empty — the global
 * staff hours apply).
 */
final class ShiftResolution
{
    public function __construct(
        public readonly ?Shift $shift,
        public readonly bool $dayOff,
    ) {}
}
