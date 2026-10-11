<?php

namespace App\Grading;

use App\Enums\AssessmentKind;

readonly class ScoreInput
{
    /**
     * @param  array<int|string>  $tpIds
     */
    public function __construct(
        public AssessmentKind|string $kind,
        public float $maxScore = 100.0,
        public ?float $score = null,
        public ?float $remedial = null,
        public array $tpIds = [],
    ) {}

    public static function fromAssessment(
        AssessmentInput $assessment,
        ?float $score = null,
        ?float $remedial = null,
    ): self {
        return new self(
            kind: $assessment->kind,
            maxScore: $assessment->maxScore,
            score: $score,
            remedial: $remedial,
            tpIds: $assessment->tpIds,
        );
    }

    public function kind(): AssessmentKind
    {
        return is_string($this->kind)
            ? AssessmentKind::from($this->kind)
            : $this->kind;
    }
}
