<?php

namespace App\Grading;

use App\Enums\AssessmentKind;

interface GradingScheme
{
    /**
     * Compute effective normalized score (0–100) taking remedial and threshold into account.
     */
    public function effectiveNormalized(ScoreInput $s, float $threshold): ?float;

    /**
     * Compute semester score from array of score inputs, given scope weight, final weight, and threshold.
     *
     * @param  array<ScoreInput>  $inputs
     */
    public function semesterScore(array $inputs, float $wScope, float $wFinal, float $threshold): ?float;

    /**
     * Round score half-up to integer.
     */
    public function roundedScore(?float $score): ?int;

    /**
     * Compute per-TP average effective normalized scores.
     *
     * @param  array<ScoreInput>  $inputs
     * @return array<int|string, float>
     */
    public function tpResults(array $inputs, float $threshold): array;

    /**
     * Generate draft achievement description from per-TP results and descriptions.
     *
     * @param  array<int|string, float>  $tpResults
     * @param  array<int|string, string>  $tpDescriptions
     */
    public function draftDescription(array $tpResults, array $tpDescriptions, float $threshold): string;

    /**
     * Allowed assessment kinds for this grading scheme.
     *
     * @return array<AssessmentKind>
     */
    public function allowedKinds(): array;
}
