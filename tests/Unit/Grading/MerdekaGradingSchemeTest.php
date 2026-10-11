<?php

namespace Tests\Unit\Grading;

use App\Enums\AssessmentKind;
use App\Enums\Curriculum;
use App\Grading\GradingSchemeResolver;
use App\Grading\MerdekaGradingScheme;
use App\Grading\ScoreInput;
use App\Models\SchoolClass;
use Tests\TestCase;

class MerdekaGradingSchemeTest extends TestCase
{
    private MerdekaGradingScheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheme = new MerdekaGradingScheme;
    }

    public function test_ac_10_02_semester_score_with_scope_and_final_weights(): void
    {
        // AC-10-02: scope 80 & 70 (avg 75), final 90, 50/50 -> 82.5 -> report value 83
        $inputs = [
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 80),
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 70),
            new ScoreInput(kind: AssessmentKind::SummativeFinal, maxScore: 100, score: 90),
        ];

        $score = $this->scheme->semesterScore($inputs, wScope: 50, wFinal: 50, threshold: 75);

        $this->assertEquals(82.5, $score);
        $this->assertSame(83, $this->scheme->roundedScore($score));
    }

    public function test_ac_10_03_formative_assessments_are_ignored_in_semester_score(): void
    {
        // AC-10-03: formative 20 ignored
        $inputs = [
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 80),
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 70),
            new ScoreInput(kind: AssessmentKind::SummativeFinal, maxScore: 100, score: 90),
            new ScoreInput(kind: AssessmentKind::Formative, maxScore: 100, score: 20),
        ];

        $score = $this->scheme->semesterScore($inputs, wScope: 50, wFinal: 50, threshold: 75);

        $this->assertEquals(82.5, $score);
        $this->assertSame(83, $this->scheme->roundedScore($score));
    }

    public function test_ac_10_04_remedial_policy(): void
    {
        // Threshold 75:
        // 60 + remedial 90 -> 75 (capped at threshold)
        $input1 = new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 60, remedial: 90);
        $this->assertEquals(75.0, $this->scheme->effectiveNormalized($input1, threshold: 75));

        // 60 + remedial 70 -> 70
        $input2 = new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 60, remedial: 70);
        $this->assertEquals(70.0, $this->scheme->effectiveNormalized($input2, threshold: 75));

        // Remedial lower than original keeps original (max)
        $input3 = new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 60, remedial: 50);
        $this->assertEquals(60.0, $this->scheme->effectiveNormalized($input3, threshold: 75));
    }

    public function test_normalization_with_custom_max_score(): void
    {
        // max_score = 50, score 40 -> 80
        $input = new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 50, score: 40);
        $this->assertEquals(80.0, $this->scheme->effectiveNormalized($input, threshold: 75));
    }

    public function test_semester_score_with_only_scope_or_only_final(): void
    {
        // Only scope
        $scopeOnly = [
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 80),
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 70),
        ];
        $this->assertEquals(75.0, $this->scheme->semesterScore($scopeOnly, wScope: 50, wFinal: 50, threshold: 75));

        // Only final
        $finalOnly = [
            new ScoreInput(kind: AssessmentKind::SummativeFinal, maxScore: 100, score: 90),
        ];
        $this->assertEquals(90.0, $this->scheme->semesterScore($finalOnly, wScope: 50, wFinal: 50, threshold: 75));
    }

    public function test_semester_score_with_null_scores_and_all_null(): void
    {
        // Null scores excluded
        $withNull = [
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 80),
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: null),
        ];
        $this->assertEquals(80.0, $this->scheme->semesterScore($withNull, wScope: 50, wFinal: 50, threshold: 75));

        // All null -> null
        $allNull = [
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: null),
            new ScoreInput(kind: AssessmentKind::SummativeFinal, maxScore: 100, score: null),
        ];
        $this->assertNull($this->scheme->semesterScore($allNull, wScope: 50, wFinal: 50, threshold: 75));

        // Empty array -> null
        $this->assertNull($this->scheme->semesterScore([], wScope: 50, wFinal: 50, threshold: 75));
    }

    public function test_half_up_rounding(): void
    {
        $this->assertSame(83, $this->scheme->roundedScore(82.5));
        $this->assertSame(82, $this->scheme->roundedScore(82.49));
        $this->assertNull($this->scheme->roundedScore(null));
    }

    public function test_tp_results_calculation(): void
    {
        $inputs = [
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 90, tpIds: [1, 2]),
            new ScoreInput(kind: AssessmentKind::SummativeScope, maxScore: 100, score: 70, tpIds: [2]),
            new ScoreInput(kind: AssessmentKind::SummativeFinal, maxScore: 100, score: 100, tpIds: [1]), // final ignored
            new ScoreInput(kind: AssessmentKind::Formative, maxScore: 100, score: 50, tpIds: [1]), // formative ignored
        ];

        $results = $this->scheme->tpResults($inputs, threshold: 75);

        // TP 1 has only one summative_scope score: 90
        $this->assertEquals(90.0, $results[1]);
        // TP 2 has two summative_scope scores: 90 and 70 -> avg 80
        $this->assertEquals(80.0, $results[2]);
    }

    public function test_ac_10_06_draft_description_with_lowest_below_threshold(): void
    {
        // TP 1 result 92, TP 3 result 64, threshold 75
        $tpResults = [
            1 => 92.0,
            2 => 80.0,
            3 => 64.0,
        ];
        $tpDescriptions = [
            1 => 'memahami konsep pecahan',
            2 => 'menghitung penjumlahan pecahan',
            3 => 'menyelesaikan soal cerita pecahan',
        ];

        $draft = $this->scheme->draftDescription($tpResults, $tpDescriptions, threshold: 75);

        $this->assertSame(
            'Menunjukkan penguasaan yang baik dalam memahami konsep pecahan. Perlu bantuan dalam menyelesaikan soal cerita pecahan.',
            $draft
        );
    }

    public function test_draft_description_with_lowest_at_or_above_threshold(): void
    {
        // Lowest TP at or above threshold -> 'Perlu peningkatan dalam ...'
        $tpResults = [
            1 => 92.0,
            2 => 85.0,
            3 => 78.0,
        ];
        $tpDescriptions = [
            1 => 'memahami konsep pecahan',
            2 => 'menghitung penjumlahan pecahan',
            3 => 'menyelesaikan soal cerita pecahan',
        ];

        $draft = $this->scheme->draftDescription($tpResults, $tpDescriptions, threshold: 75);

        $this->assertSame(
            'Menunjukkan penguasaan yang baik dalam memahami konsep pecahan. Perlu peningkatan dalam menyelesaikan soal cerita pecahan.',
            $draft
        );
    }

    public function test_draft_description_with_single_tp(): void
    {
        // Single TP >= threshold
        $draft1 = $this->scheme->draftDescription([1 => 85.0], [1 => 'memahami konsep pecahan'], threshold: 75);
        $this->assertSame('Menunjukkan penguasaan yang baik dalam memahami konsep pecahan.', $draft1);

        // Single TP < threshold
        $draft2 = $this->scheme->draftDescription([1 => 60.0], [1 => 'memahami konsep pecahan'], threshold: 75);
        $this->assertSame('Perlu bantuan dalam memahami konsep pecahan.', $draft2);
    }

    public function test_draft_description_with_equal_highest_and_lowest_tp(): void
    {
        // Multiple TPs with identical score >= threshold
        $draft1 = $this->scheme->draftDescription([1 => 80.0, 2 => 80.0], [1 => 'TP 1', 2 => 'TP 2'], threshold: 75);
        $this->assertSame('Menunjukkan penguasaan yang baik dalam TP 1.', $draft1);

        // Multiple TPs with identical score < threshold
        $draft2 = $this->scheme->draftDescription([1 => 60.0, 2 => 60.0], [1 => 'TP 1', 2 => 'TP 2'], threshold: 75);
        $this->assertSame('Perlu bantuan dalam TP 1.', $draft2);
    }

    public function test_grading_scheme_resolver_returns_merdeka_grading_scheme(): void
    {
        $class = new SchoolClass(['curriculum' => Curriculum::Merdeka]);
        $scheme = GradingSchemeResolver::for($class);

        $this->assertInstanceOf(MerdekaGradingScheme::class, $scheme);
        $this->assertContains(AssessmentKind::Formative, $scheme->allowedKinds());
        $this->assertContains(AssessmentKind::SummativeScope, $scheme->allowedKinds());
        $this->assertContains(AssessmentKind::SummativeFinal, $scheme->allowedKinds());
    }
}
