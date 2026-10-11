<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAssessmentRequest;
use App\Http\Requests\Teacher\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\LearningObjective;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a listing of assessments for a course and semester.
     */
    public function index(Request $request, ClassSubject $classSubject): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                ClassAccess::canWriteCourse($user, $classSubject) ||
                ClassAccess::academicClassIds($user)->contains($classSubject->class_id)
            ),
            403
        );

        $classSubject->load([
            'schoolClass.academicYear.semesters',
            'subject:id,code,name',
            'teacher.employee:id,name',
        ]);

        $semesters = $classSubject->schoolClass->academicYear->semesters;
        $semesterId = $request->filled('semester')
            ? (int) $request->input('semester')
            : (int) ($semesters->first()?->id ?? 0);

        $currentSemester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();

        $assessments = Assessment::query()
            ->where('class_subject_id', $classSubject->id)
            ->where('semester_id', $semesterId)
            ->with(['learningObjectives:id,code,description'])
            ->withCount('scores')
            ->orderBy('sort_order')
            ->orderBy('assessed_on')
            ->get();

        $objectives = LearningObjective::query()
            ->where('academic_year_id', $classSubject->schoolClass->academic_year_id)
            ->where('subject_id', $classSubject->subject_id)
            ->where('grade_level', $classSubject->schoolClass->grade_level)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'description']);

        $canWrite = ClassAccess::canWriteCourse($user, $classSubject);

        return Inertia::render('teacher/assessments/index', [
            'course' => [
                'id' => $classSubject->id,
                'class_name' => $classSubject->schoolClass->name,
                'grade_level' => $classSubject->schoolClass->grade_level,
                'subject_name' => $classSubject->subject->name,
                'subject_code' => $classSubject->subject->code,
                'teacher_name' => $classSubject->teacher?->employee?->name,
                'passing_threshold' => (string) $classSubject->passing_threshold,
            ],
            'currentSemester' => $currentSemester,
            'semesters' => $semesters,
            'assessments' => $assessments,
            'objectives' => $objectives,
            'canWrite' => $canWrite,
        ]);
    }

    /**
     * Store a newly created assessment.
     */
    public function store(StoreAssessmentRequest $request, ClassSubject $classSubject): RedirectResponse
    {
        $data = $request->validated();
        $objectiveIds = $data['objective_ids'] ?? [];
        unset($data['objective_ids']);

        $data['class_subject_id'] = $classSubject->id;

        DB::transaction(function () use ($data, $objectiveIds, &$assessment) {
            /** @var Assessment $assessment */
            $assessment = Assessment::create($data);

            if (! empty($objectiveIds)) {
                $assessment->learningObjectives()->sync($objectiveIds);
            }

            $this->auditLogger->record(
                $assessment,
                'created',
                null,
                [
                    'name' => $assessment->name,
                    'kind' => $assessment->kind->value,
                    'assessed_on' => $assessment->assessed_on->toDateString(),
                    'max_score' => $assessment->max_score,
                    'objective_ids' => $objectiveIds,
                ]
            );
        });

        return back()->with('success', 'Asesmen berhasil ditambahkan.');
    }

    /**
     * Update the specified assessment.
     */
    public function update(UpdateAssessmentRequest $request, ClassSubject $classSubject, Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->class_subject_id !== $classSubject->id, 404);

        $courseSemester = CourseSemester::forCourse($classSubject, $assessment->semester);
        $courseSemester->assertOpen();

        $data = $request->validated();
        $objectiveIds = $data['objective_ids'] ?? [];
        unset($data['objective_ids']);

        $oldValues = [
            'name' => $assessment->name,
            'kind' => $assessment->kind->value,
            'assessed_on' => $assessment->assessed_on->toDateString(),
            'max_score' => $assessment->max_score,
            'sort_order' => $assessment->sort_order,
            'objective_ids' => $assessment->learningObjectives()->pluck('id')->all(),
        ];

        DB::transaction(function () use ($assessment, $data, $objectiveIds, $oldValues) {
            $assessment->update($data);
            $assessment->learningObjectives()->sync($objectiveIds);

            $this->auditLogger->record(
                $assessment,
                'updated',
                $oldValues,
                [
                    'name' => $assessment->name,
                    'kind' => $assessment->kind->value,
                    'assessed_on' => $assessment->assessed_on->toDateString(),
                    'max_score' => $assessment->max_score,
                    'sort_order' => $assessment->sort_order,
                    'objective_ids' => $objectiveIds,
                ]
            );
        });

        return back()->with('success', 'Asesmen berhasil diperbarui.');
    }

    /**
     * Remove the specified assessment.
     */
    public function destroy(Request $request, ClassSubject $classSubject, Assessment $assessment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ClassAccess::canWriteCourse($user, $classSubject), 403);
        abort_if($assessment->class_subject_id !== $classSubject->id, 404);

        $courseSemester = CourseSemester::forCourse($classSubject, $assessment->semester);
        $courseSemester->assertOpen();

        if ($assessment->scores()->exists() && ! $request->boolean('confirm')) {
            abort(422, 'Asesmen ini sudah memiliki nilai. Konfirmasi diperlukan untuk menghapus.');
        }

        DB::transaction(function () use ($assessment) {
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

        return back()->with('success', 'Asesmen berhasil dihapus.');
    }
}
