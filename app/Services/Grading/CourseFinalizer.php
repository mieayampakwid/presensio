<?php

namespace App\Services\Grading;

use App\Enums\AssessmentKind;
use App\Grading\GradingSchemeResolver;
use App\Grading\ScoreInput;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\LearningObjective;
use App\Models\Score;
use App\Models\Semester;
use App\Models\SubjectResult;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ReportCardGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CourseFinalizer
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ReportCardGuard $reportCardGuard,
    ) {}

    /**
     * Finalize the course for the given semester.
     *
     * @throws HttpResponseException
     */
    public function finalize(ClassSubject $classSubject, Semester $semester, User $by): void
    {
        $classSubject->loadMissing(['subject', 'schoolClass.academicYear']);
        $schoolClass = $classSubject->schoolClass;

        $courseSemester = CourseSemester::forCourse($classSubject, $semester);
        if ($courseSemester->isFinalized()) {
            return;
        }

        /** @var Collection<int, Assessment> $summativeAssessments */
        $summativeAssessments = Assessment::query()
            ->where('class_subject_id', $classSubject->id)
            ->where('semester_id', $semester->id)
            ->whereIn('kind', [AssessmentKind::SummativeScope, AssessmentKind::SummativeFinal])
            ->with('learningObjectives')
            ->orderBy('sort_order')
            ->orderBy('assessed_on')
            ->get();

        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->where('class_id', $classSubject->class_id)
            ->where(function ($q) use ($semester) {
                $q->whereNull('ended_on')
                    ->orWhere('ended_on', '>=', $semester->starts_at);
            })
            ->where('started_on', '<=', $semester->ends_at)
            ->with(['student:id,full_name,student_number'])
            ->get();

        $scores = Score::query()
            ->whereIn('assessment_id', $summativeAssessments->pluck('id'))
            ->get()
            ->keyBy(fn (Score $s) => "{$s->assessment_id}_{$s->student_id}");

        $students = $enrollments->pluck('student')->unique('id')->values();

        // 1. Verify completeness for all summative assessments
        $missing = [];
        foreach ($summativeAssessments as $assessment) {
            $date = $assessment->assessed_on->toDateString();

            foreach ($students as $student) {
                $studentEnrollments = $enrollments->where('student_id', $student->id);
                $isEnrolled = $studentEnrollments->contains(
                    fn (Enrollment $e) => $e->started_on->toDateString() <= $date
                        && ($e->ended_on === null || $e->ended_on->toDateString() >= $date)
                );

                if (! $isEnrolled) {
                    continue;
                }

                $score = $scores->get("{$assessment->id}_{$student->id}");
                if ($score === null || $score->score === null) {
                    $missing[] = [
                        'student' => [
                            'id' => $student->id,
                            'name' => $student->full_name,
                        ],
                        'assessment' => [
                            'id' => $assessment->id,
                            'name' => $assessment->name,
                        ],
                    ];
                }
            }
        }

        if (! empty($missing)) {
            abort(response()->json([
                'message' => 'Terdapat nilai asesmen sumatif yang belum terisi.',
                'missing' => $missing,
            ], 422));
        }

        // 2. Perform finalization in one transaction
        DB::transaction(function () use ($classSubject, $schoolClass, $semester, $courseSemester, $by, $enrollments) {
            $now = now();
            $scheme = GradingSchemeResolver::for($schoolClass);
            $wScope = (float) $courseSemester->scope_weight;
            $wFinal = (float) $courseSemester->final_weight;
            $threshold = (float) $classSubject->passing_threshold;

            $allAssessments = Assessment::query()
                ->where('class_subject_id', $classSubject->id)
                ->where('semester_id', $semester->id)
                ->with('learningObjectives')
                ->get();

            $allScores = Score::query()
                ->whereIn('assessment_id', $allAssessments->pluck('id'))
                ->get()
                ->keyBy(fn (Score $s) => "{$s->assessment_id}_{$s->student_id}");

            $academicYearId = $schoolClass->academic_year_id;
            $subjectId = $classSubject->subject_id;
            $gradeLevel = $schoolClass->grade_level;

            $learningObjectives = LearningObjective::query()
                ->where('academic_year_id', $academicYearId)
                ->where('subject_id', $subjectId)
                ->where('grade_level', $gradeLevel)
                ->get();

            $tpDescriptions = $learningObjectives->pluck('description', 'id')->all();
            foreach ($allAssessments as $assessment) {
                foreach ($assessment->learningObjectives as $lo) {
                    if (! isset($tpDescriptions[$lo->id])) {
                        $tpDescriptions[$lo->id] = $lo->description;
                    }
                }
            }

            $students = $enrollments->pluck('student')->unique('id')->values();

            foreach ($students as $student) {
                $studentEnrollments = $enrollments->where('student_id', $student->id);
                $scoreInputs = [];

                foreach ($allAssessments as $assessment) {
                    $date = $assessment->assessed_on->toDateString();
                    $isEnrolled = $studentEnrollments->contains(
                        fn (Enrollment $e) => $e->started_on->toDateString() <= $date
                            && ($e->ended_on === null || $e->ended_on->toDateString() >= $date)
                    );

                    if (! $isEnrolled) {
                        continue;
                    }

                    $scoreRecord = $allScores->get("{$assessment->id}_{$student->id}");
                    $scoreVal = $scoreRecord?->score !== null ? (float) $scoreRecord->score : null;
                    $remedialVal = $scoreRecord?->remedial_score !== null ? (float) $scoreRecord->remedial_score : null;

                    if ($assessment->kind !== AssessmentKind::Formative && $scoreVal !== null) {
                        $scoreInputs[] = new ScoreInput(
                            kind: $assessment->kind,
                            maxScore: (float) $assessment->max_score,
                            score: $scoreVal,
                            remedial: $remedialVal,
                            tpIds: $assessment->learningObjectives->pluck('id')->all(),
                        );
                    }
                }

                $liveScore = $scheme->semesterScore($scoreInputs, $wScope, $wFinal, $threshold);
                $roundedScore = $scheme->roundedScore($liveScore);

                $tpResults = $scheme->tpResults($scoreInputs, $threshold);
                $draftDescription = $scheme->draftDescription($tpResults, $tpDescriptions, $threshold);

                /** @var SubjectResult|null $existing */
                $existing = SubjectResult::where('course_semester_id', $courseSemester->id)
                    ->where('student_id', $student->id)
                    ->first();

                $description = $existing !== null && $existing->description_edited
                    ? $existing->description
                    : $draftDescription;

                SubjectResult::updateOrCreate(
                    [
                        'course_semester_id' => $courseSemester->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'final_score' => $roundedScore,
                        'description' => $description,
                    ]
                );
            }

            $courseSemester->update([
                'finalized_at' => $now,
                'finalized_by_user_id' => $by->id,
            ]);

            $this->auditLogger->record(
                $courseSemester,
                'finalized',
                null,
                [
                    'finalized_at' => $now->toIso8601String(),
                    'finalized_by_user_id' => $by->id,
                ]
            );
        });
    }

    /**
     * Undo finalization of the course for the given semester.
     */
    public function undo(ClassSubject $classSubject, Semester $semester, User $by): void
    {
        $courseSemester = CourseSemester::forCourse($classSubject, $semester);
        if (! $courseSemester->isFinalized()) {
            return;
        }

        if ($this->reportCardGuard->isPublished($classSubject, $semester)) {
            abort(422, 'Tidak dapat membatalkan finalisasi karena rapor untuk kelas ini pada semester tersebut sudah diterbitkan.');
        }

        $oldValues = [
            'finalized_at' => $courseSemester->finalized_at?->toIso8601String(),
            'finalized_by_user_id' => $courseSemester->finalized_by_user_id,
        ];

        DB::transaction(function () use ($courseSemester, $oldValues) {
            $courseSemester->update([
                'finalized_at' => null,
                'finalized_by_user_id' => null,
            ]);

            $this->auditLogger->record(
                $courseSemester,
                'unfinalized',
                $oldValues,
                null
            );
        });
    }
}
