<?php

namespace App\Services\Grading;

use App\Enums\AssessmentKind;
use App\Grading\GradingSchemeResolver;
use App\Grading\ScoreInput;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Collection;

class GradebookBuilder
{
    /**
     * Build the gradebook read model for the given course and semester.
     *
     * @return array{
     *     course: array<string, mixed>,
     *     semester: array<string, mixed>,
     *     is_finalized: bool,
     *     weights: array{scope: float, final: float},
     *     assessments: array<int, array<string, mixed>>,
     *     students: array<int, array<string, mixed>>
     * }
     */
    public function build(ClassSubject $classSubject, Semester $semester): array
    {
        $classSubject->loadMissing([
            'schoolClass',
            'subject:id,code,name',
            'teacher.employee:id,name',
        ]);

        $schoolClass = $classSubject->schoolClass;
        $scheme = GradingSchemeResolver::for($schoolClass);

        $courseSemester = CourseSemester::forCourse($classSubject, $semester);
        $wScope = (float) $courseSemester->scope_weight;
        $wFinal = (float) $courseSemester->final_weight;
        $threshold = (float) $classSubject->passing_threshold;

        /** @var Collection<int, Assessment> $assessments */
        $assessments = Assessment::query()
            ->where('class_subject_id', $classSubject->id)
            ->where('semester_id', $semester->id)
            ->with(['learningObjectives:id,code,description'])
            ->orderBy('sort_order')
            ->orderBy('assessed_on')
            ->orderBy('id')
            ->get();

        // Enrollments overlapping semester date window
        $enrollments = Enrollment::query()
            ->where('class_id', $classSubject->class_id)
            ->where(function ($q) use ($semester) {
                $q->where('started_on', '<=', $semester->ends_at->toDateString())
                    ->where(function ($q2) use ($semester) {
                        $q2->whereNull('ended_on')
                            ->orWhere('ended_on', '>=', $semester->starts_at->toDateString());
                    });
            })
            ->with(['student:id,full_name,student_number,gender'])
            ->get();

        $students = $enrollments->pluck('student')->unique('id')->sortBy('full_name')->values();

        $assessmentIds = $assessments->pluck('id')->all();
        $studentIds = $students->pluck('id')->all();

        $scores = Score::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn (Score $s) => "{$s->assessment_id}_{$s->student_id}");

        $studentRows = [];

        foreach ($students as $student) {
            $studentEnrollments = $enrollments->where('student_id', $student->id);
            $cells = [];
            $scoreInputs = [];
            $eligibleSummativeCount = 0;
            $filledSummativeCount = 0;

            foreach ($assessments as $assessment) {
                $date = $assessment->assessed_on->toDateString();
                $isEnrolled = $studentEnrollments->contains(
                    fn (Enrollment $e) => $e->started_on->toDateString() <= $date
                        && ($e->ended_on === null || $e->ended_on->toDateString() >= $date)
                );

                if (! $isEnrolled) {
                    continue;
                }

                $scoreRecord = $scores->get("{$assessment->id}_{$student->id}");
                $scoreVal = $scoreRecord?->score !== null ? (float) $scoreRecord->score : null;
                $remedialVal = $scoreRecord?->remedial_score !== null ? (float) $scoreRecord->remedial_score : null;
                $notesVal = $scoreRecord?->notes;

                $cells[$assessment->id] = [
                    'score' => $scoreVal,
                    'remedial' => $remedialVal,
                    'notes' => $notesVal,
                ];

                if ($assessment->kind !== AssessmentKind::Formative) {
                    $eligibleSummativeCount++;
                    if ($scoreVal !== null) {
                        $filledSummativeCount++;
                        $scoreInputs[] = new ScoreInput(
                            kind: $assessment->kind,
                            maxScore: (float) $assessment->max_score,
                            score: $scoreVal,
                            remedial: $remedialVal,
                            tpIds: $assessment->learningObjectives->pluck('id')->all(),
                        );
                    }
                }
            }

            $complete = $eligibleSummativeCount > 0 && $filledSummativeCount === $eligibleSummativeCount;
            $liveScore = $scheme->semesterScore($scoreInputs, $wScope, $wFinal, $threshold);
            $roundedScore = $scheme->roundedScore($liveScore);
            $status = $roundedScore !== null && $roundedScore >= $threshold ? 'Tuntas' : 'Belum Tuntas';

            $studentRows[] = [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_number' => $student->student_number,
                'gender' => $student->gender?->value,
                'cells' => $cells,
                'live_score' => $roundedScore,
                'status' => $status,
                'complete' => $complete,
            ];
        }

        return [
            'course' => [
                'id' => $classSubject->id,
                'class_name' => $schoolClass->name,
                'grade_level' => $schoolClass->grade_level,
                'subject_name' => $classSubject->subject->name,
                'subject_code' => $classSubject->subject->code,
                'teacher_name' => $classSubject->teacher?->employee?->name,
                'passing_threshold' => (string) $classSubject->passing_threshold,
            ],
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
                'number' => $semester->number,
                'starts_at' => $semester->starts_at->toDateString(),
                'ends_at' => $semester->ends_at->toDateString(),
            ],
            'is_finalized' => $courseSemester->isFinalized(),
            'weights' => [
                'scope' => $wScope,
                'final' => $wFinal,
            ],
            'assessments' => $assessments->map(fn (Assessment $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'kind' => $a->kind->value,
                'assessed_on' => $a->assessed_on->toDateString(),
                'max_score' => (float) $a->max_score,
                'released_at' => $a->released_at?->toIso8601String(),
                'sort_order' => $a->sort_order,
                'learning_objectives' => $a->learningObjectives->map(fn ($tp) => [
                    'id' => $tp->id,
                    'code' => $tp->code,
                    'description' => $tp->description,
                ])->all(),
            ])->values()->all(),
            'students' => $studentRows,
        ];
    }
}
