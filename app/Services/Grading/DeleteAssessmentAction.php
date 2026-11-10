<?php

namespace App\Services\Grading;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class DeleteAssessmentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Delete an assessment, asserting that the course semester is open,
     * checking score confirmation, archiving deleted score snapshots to audit log,
     * and removing the assessment.
     */
    public function execute(ClassSubject $classSubject, Assessment $assessment, bool $confirmed = false): void
    {
        $courseSemester = CourseSemester::forCourse($classSubject, $assessment->semester);
        $courseSemester->assertOpen();

        if ($assessment->scores()->exists() && ! $confirmed) {
            abort(422, __('toasts.grading.assessment_has_scores'));
        }

        DB::transaction(function () use ($assessment): void {
            $deletedScores = $assessment->scores()->get(['student_id', 'score', 'remedial_score', 'notes'])->toArray();

            $this->auditLogger->record(
                $assessment,
                'deleted',
                [
                    'name' => $assessment->name,
                    'kind' => $assessment->kind->value,
                    'scores' => $deletedScores,
                ],
                null,
                'Assessment deleted'
            );

            $assessment->delete();
        });
    }
}
