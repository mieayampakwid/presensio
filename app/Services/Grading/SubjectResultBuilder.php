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
use Illuminate\Database\Eloquent\Collection;

class SubjectResultBuilder
{
    /**
     * Build the subject results data model for the course in the given semester.
     *
     * @return array{
     *     course: array<string, mixed>,
     *     semester: array<string, mixed>,
     *     is_finalized: bool,
     *     finalized_at: string|null,
     *     learning_objectives: array<int, array{id: int, code: string, description: string}>,
     *     students: array<int, array<string, mixed>>
     * }
     */
    public function build(ClassSubject $classSubject, Semester $semester): array
    {
        $classSubject->loadMissing([
            'subject',
            'schoolClass.academicYear',
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

        $academicYearId = $schoolClass->academic_year_id;
        $subjectId = $classSubject->subject_id;
        $gradeLevel = $schoolClass->grade_level;

        /** @var Collection<int, LearningObjective> $learningObjectives */
        $learningObjectives = LearningObjective::query()
            ->where('academic_year_id', $academicYearId)
            ->where('subject_id', $subjectId)
            ->where('grade_level', $gradeLevel)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        // Build TP descriptions lookup
        $tpDescriptions = $learningObjectives->pluck('description', 'id')->all();
        foreach ($assessments as $assessment) {
            foreach ($assessment->learningObjectives as $lo) {
                if (! isset($tpDescriptions[$lo->id])) {
                    $tpDescriptions[$lo->id] = $lo->description;
                }
            }
        }

        // Students enrolled during the semester
        $enrollments = Enrollment::query()
            ->where('class_id', $classSubject->class_id)
            ->where(function ($q) use ($semester) {
                $q->whereNull('ended_on')
                    ->orWhere('ended_on', '>=', $semester->starts_at);
            })
            ->where('started_on', '<=', $semester->ends_at)
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

        $existingResults = SubjectResult::query()
            ->where('course_semester_id', $courseSemester->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $studentRows = [];

        foreach ($students as $student) {
            $studentEnrollments = $enrollments->where('student_id', $student->id);
            $scoreInputs = [];

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
            $existing = $existingResults->get($student->id);
            $isEdited = $existing?->description_edited ?? false;
            $description = ($isEdited || $courseSemester->isFinalized()) && $existing?->description !== null
                ? $existing->description
                : $draftDescription;

            $finalScore = $existing?->final_score ?? ($courseSemester->isFinalized() ? $roundedScore : null);

            $studentRows[] = [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_number' => $student->student_number,
                'gender' => $student->gender?->value,
                'live_score' => $roundedScore,
                'final_score' => $finalScore,
                'tp_results' => $tpResults,
                'draft_description' => $draftDescription,
                'description' => $description,
                'description_edited' => $isEdited,
            ];
        }

        return [
            'course' => [
                'id' => $classSubject->id,
                'name' => $classSubject->subject?->name ?? 'Course',
                'code' => $classSubject->subject?->code ?? '',
                'class_id' => $classSubject->class_id,
                'class_name' => $schoolClass->name,
                'passing_threshold' => $threshold,
            ],
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
            ],
            'is_finalized' => $courseSemester->isFinalized(),
            'finalized_at' => $courseSemester->finalized_at?->toIso8601String(),
            'learning_objectives' => $learningObjectives->map(fn (LearningObjective $lo) => [
                'id' => $lo->id,
                'code' => $lo->code,
                'description' => $lo->description,
            ])->values()->all(),
            'students' => $studentRows,
        ];
    }
}
