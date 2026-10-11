<?php

namespace App\Grading;

use App\Enums\AssessmentKind;

readonly class AssessmentInput
{
    /**
     * @param  array<int|string>  $tpIds
     */
    public function __construct(
        public AssessmentKind|string $kind,
        public float $maxScore = 100.0,
        public array $tpIds = [],
        public int|string|null $id = null,
    ) {}

    public function kind(): AssessmentKind
    {
        return is_string($this->kind)
            ? AssessmentKind::from($this->kind)
            : $this->kind;
    }
}
