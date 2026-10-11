<?php

namespace App\Grading;

use App\Enums\AssessmentKind;

class MerdekaGradingScheme implements GradingScheme
{
    /**
     * Compute effective normalized score (0–100) taking remedial and threshold into account.
     */
    public function effectiveNormalized(ScoreInput $s, float $threshold): ?float
    {
        if ($s->score === null || $s->maxScore <= 0) {
            return null;
        }

        $nScore = ((float) $s->score / (float) $s->maxScore) * 100.0;

        if ($s->remedial === null) {
            return $nScore;
        }

        $nRemedial = ((float) $s->remedial / (float) $s->maxScore) * 100.0;

        return max($nScore, min($nRemedial, $threshold));
    }

    /**
     * Compute semester score from array of score inputs, given scope weight, final weight, and threshold.
     *
     * @param  array<ScoreInput>  $inputs
     */
    public function semesterScore(array $inputs, float $wScope, float $wFinal, float $threshold): ?float
    {
        $scopeScores = [];
        $finalScores = [];

        foreach ($inputs as $input) {
            if ($input->score === null) {
                continue;
            }

            $effective = $this->effectiveNormalized($input, $threshold);
            if ($effective === null) {
                continue;
            }

            if ($input->kind() === AssessmentKind::SummativeScope) {
                $scopeScores[] = $effective;
            } elseif ($input->kind() === AssessmentKind::SummativeFinal) {
                $finalScores[] = $effective;
            }
        }

        if (empty($scopeScores) && empty($finalScores)) {
            return null;
        }

        $scopeAvg = ! empty($scopeScores) ? array_sum($scopeScores) / count($scopeScores) : null;
        $finalAvg = ! empty($finalScores) ? array_sum($finalScores) / count($finalScores) : null;

        if ($scopeAvg !== null && $finalAvg !== null) {
            $totalWeight = $wScope + $wFinal;
            if ($totalWeight > 0) {
                return ($scopeAvg * $wScope + $finalAvg * $wFinal) / $totalWeight;
            }

            return ($scopeAvg + $finalAvg) / 2.0;
        }

        if ($scopeAvg !== null) {
            return $scopeAvg;
        }

        return $finalAvg;
    }

    /**
     * Round score half-up to integer.
     */
    public function roundedScore(?float $score): ?int
    {
        if ($score === null) {
            return null;
        }

        return (int) round($score, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Compute per-TP average effective normalized scores.
     *
     * @param  array<ScoreInput>  $inputs
     * @return array<int|string, float>
     */
    public function tpResults(array $inputs, float $threshold): array
    {
        $tpScores = [];

        foreach ($inputs as $input) {
            if ($input->kind() !== AssessmentKind::SummativeScope || $input->score === null) {
                continue;
            }

            $effective = $this->effectiveNormalized($input, $threshold);
            if ($effective === null) {
                continue;
            }

            foreach ($input->tpIds as $tpId) {
                $tpScores[$tpId][] = $effective;
            }
        }

        $results = [];
        foreach ($tpScores as $tpId => $scores) {
            $results[$tpId] = array_sum($scores) / count($scores);
        }

        return $results;
    }

    /**
     * Generate draft achievement description from per-TP results and descriptions.
     *
     * @param  array<int|string, float>  $tpResults
     * @param  array<int|string, string>  $tpDescriptions
     */
    public function draftDescription(array $tpResults, array $tpDescriptions, float $threshold): string
    {
        if (empty($tpResults)) {
            return '';
        }

        $highestId = null;
        $highestScore = -INF;
        $lowestId = null;
        $lowestScore = INF;

        foreach ($tpResults as $tpId => $score) {
            if ($score > $highestScore) {
                $highestScore = $score;
                $highestId = $tpId;
            }
            if ($score < $lowestScore) {
                $lowestScore = $score;
                $lowestId = $tpId;
            }
        }

        if ($highestId === null || $lowestId === null) {
            return '';
        }

        // When only one TP exists, or highest and lowest results are equal
        if (count($tpResults) === 1 || $highestScore === $lowestScore || $highestId === $lowestId) {
            $desc = rtrim($tpDescriptions[$highestId] ?? "TP {$highestId}", '.');

            return $highestScore >= $threshold
                ? "Menunjukkan penguasaan yang baik dalam {$desc}."
                : "Perlu bantuan dalam {$desc}.";
        }

        $highDesc = rtrim($tpDescriptions[$highestId] ?? "TP {$highestId}", '.');
        $lowDesc = rtrim($tpDescriptions[$lowestId] ?? "TP {$lowestId}", '.');

        $sentence1 = "Menunjukkan penguasaan yang baik dalam {$highDesc}.";
        $sentence2 = $lowestScore >= $threshold
            ? "Perlu peningkatan dalam {$lowDesc}."
            : "Perlu bantuan dalam {$lowDesc}.";

        return "{$sentence1} {$sentence2}";
    }

    /**
     * Allowed assessment kinds for this grading scheme.
     *
     * @return array<AssessmentKind>
     */
    public function allowedKinds(): array
    {
        return [
            AssessmentKind::Formative,
            AssessmentKind::SummativeScope,
            AssessmentKind::SummativeFinal,
        ];
    }
}
