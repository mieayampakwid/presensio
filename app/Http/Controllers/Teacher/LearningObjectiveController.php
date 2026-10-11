<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreLearningObjectiveRequest;
use App\Http\Requests\Teacher\UpdateLearningObjectiveRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\LearningObjective;
use App\Models\Subject;
use App\Services\Attendance\ClassAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearningObjectiveController extends Controller
{
    /**
     * Display a listing of learning objectives filtered by year, subject, and grade level.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $activeYear = AcademicYear::active() ?? AcademicYear::latest('starts_at')->first();

        $yearId = (int) ($request->input('academic_year_id') ?: $activeYear?->id);
        $subjectId = $request->filled('subject_id') ? (int) $request->input('subject_id') : null;
        $gradeLevel = $request->filled('grade_level') ? (int) $request->input('grade_level') : null;

        $academicYears = AcademicYear::query()
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'is_active']);

        if ($user->hasRole(UserRole::Admin)) {
            $subjects = Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
            $gradeLevels = range(1, 12);
        } else {
            $teacher = $user->teacher;
            $classSubjects = $teacher !== null
                ? ClassSubject::query()
                    ->where('teacher_id', $teacher->id)
                    ->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $yearId))
                    ->with(['subject:id,code,name', 'schoolClass:id,grade_level'])
                    ->get()
                : collect();

            $subjects = $classSubjects->pluck('subject')->filter()->unique('id')->values();
            $gradeLevels = $classSubjects->pluck('schoolClass.grade_level')->filter()->unique()->sort()->values()->all();

            if ($subjectId === null && $subjects->isNotEmpty()) {
                $subjectId = $subjects->first()->id;
            }
            if ($gradeLevel === null && ! empty($gradeLevels)) {
                $gradeLevel = $gradeLevels[0];
            }
        }

        $canManage = false;
        $objectives = [];
        $hasPreviousYear = false;

        if ($yearId && $subjectId && $gradeLevel) {
            $canManage = ClassAccess::canManageObjectives($user, $yearId, $subjectId, $gradeLevel);

            $objectives = LearningObjective::query()
                ->where('academic_year_id', $yearId)
                ->where('subject_id', $subjectId)
                ->where('grade_level', $gradeLevel)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get();

            $targetYear = AcademicYear::find($yearId);
            if ($targetYear) {
                $prevYear = AcademicYear::query()
                    ->where('id', '!=', $targetYear->id)
                    ->where('starts_at', '<', $targetYear->starts_at->toDateString())
                    ->orderByDesc('starts_at')
                    ->first();

                $hasPreviousYear = $prevYear !== null && LearningObjective::query()
                    ->where('academic_year_id', $prevYear->id)
                    ->where('subject_id', $subjectId)
                    ->where('grade_level', $gradeLevel)
                    ->exists();
            }
        }

        return Inertia::render('teacher/objectives/index', [
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'gradeLevels' => $gradeLevels,
            'selectedYearId' => $yearId,
            'selectedSubjectId' => $subjectId,
            'selectedGradeLevel' => $gradeLevel,
            'objectives' => $objectives,
            'canManage' => $canManage,
            'hasPreviousYear' => $hasPreviousYear,
        ]);
    }

    /**
     * Store a newly created learning objective.
     */
    public function store(StoreLearningObjectiveRequest $request): RedirectResponse
    {
        LearningObjective::create($request->validated());

        return back()->with('success', 'Tujuan pembelajaran berhasil dibuat.');
    }

    /**
     * Update the specified learning objective.
     */
    public function update(UpdateLearningObjectiveRequest $request, LearningObjective $learningObjective): RedirectResponse
    {
        $learningObjective->update($request->validated());

        return back()->with('success', 'Tujuan pembelajaran berhasil diperbarui.');
    }

    /**
     * Remove the specified learning objective.
     */
    public function destroy(Request $request, LearningObjective $learningObjective): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::canManageObjectives(
                $user,
                $learningObjective->academic_year_id,
                $learningObjective->subject_id,
                $learningObjective->grade_level
            ),
            403
        );

        if ($learningObjective->assessments()->exists()) {
            abort(422, 'Tujuan pembelajaran tidak dapat dihapus karena sudah ditautkan ke asesmen.');
        }

        $learningObjective->delete();

        return back()->with('success', 'Tujuan pembelajaran berhasil dihapus.');
    }

    /**
     * Copy learning objectives from the previous academic year for this subject and grade level.
     */
    public function copyPreviousYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::canManageObjectives(
                $user,
                (int) $data['academic_year_id'],
                (int) $data['subject_id'],
                (int) $data['grade_level']
            ),
            403
        );

        $targetYear = AcademicYear::findOrFail($data['academic_year_id']);

        $prevYear = AcademicYear::query()
            ->where('id', '!=', $targetYear->id)
            ->where('starts_at', '<', $targetYear->starts_at->toDateString())
            ->orderByDesc('starts_at')
            ->first();

        if ($prevYear === null) {
            return back()->with('info', 'Tidak ada tahun ajaran sebelumnya untuk menyalin tujuan pembelajaran.');
        }

        $previousObjectives = LearningObjective::query()
            ->where('academic_year_id', $prevYear->id)
            ->where('subject_id', $data['subject_id'])
            ->where('grade_level', $data['grade_level'])
            ->get();
        $existingCodes = LearningObjective::query()
            ->where('academic_year_id', $targetYear->id)
            ->where('subject_id', $data['subject_id'])
            ->where('grade_level', $data['grade_level'])
            ->pluck('code')
            ->all();

        $copiedCount = 0;
        foreach ($previousObjectives as $prev) {
            if (in_array($prev->code, $existingCodes, true)) {
                continue;
            }

            LearningObjective::create([
                'academic_year_id' => $targetYear->id,
                'subject_id' => $data['subject_id'],
                'grade_level' => $data['grade_level'],
                'code' => $prev->code,
                'description' => $prev->description,
                'sort_order' => $prev->sort_order,
            ]);
            $copiedCount++;
        }

        return back()->with('success', "{$copiedCount} tujuan pembelajaran berhasil disalin.");
    }
}
