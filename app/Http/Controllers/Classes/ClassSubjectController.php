<?php

namespace App\Http\Controllers\Classes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\StoreClassSubjectRequest;
use App\Http\Requests\Classes\UpdateClassSubjectRequest;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassSubjectController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a listing of subjects assigned to a class.
     */
    public function index(SchoolClass $schoolClass): Response
    {
        $schoolClass->load([
            'academicYear:id,name,is_active',
            'teacher:id,name',
            'classSubjects' => fn ($query) => $query->with([
                'subject:id,code,name,group,sort_order,is_active',
                'teacher:id,name',
            ])->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                ->orderBy('subjects.group')
                ->orderBy('subjects.sort_order')
                ->orderBy('subjects.name')
                ->select('class_subjects.*'),
        ]);

        $availableSubjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'group']);

        $availableTeachers = Teacher::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('classes/subjects', [
            'schoolClass' => $schoolClass,
            'classSubjects' => $schoolClass->classSubjects,
            'subjects' => $availableSubjects,
            'teachers' => $availableTeachers,
            'defaultPassingThreshold' => $this->schoolSettings->defaultPassingThreshold(),
        ]);
    }

    /**
     * Store a new class subject assignment.
     */
    public function store(StoreClassSubjectRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validated();
        $validated['class_id'] = $schoolClass->id;

        ClassSubject::create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subject assigned to class.',
        ]);

        return back();
    }

    /**
     * Update an existing class subject assignment.
     */
    public function update(
        UpdateClassSubjectRequest $request,
        SchoolClass $schoolClass,
        ClassSubject $classSubject
    ): RedirectResponse {
        abort_unless($classSubject->class_id === $schoolClass->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($classSubject, $validated) {
            $old = [
                'teacher_id' => $classSubject->teacher_id,
                'passing_threshold' => (string) $classSubject->passing_threshold,
            ];

            $classSubject->update($validated);

            $new = [
                'teacher_id' => $classSubject->teacher_id,
                'passing_threshold' => (string) $classSubject->passing_threshold,
            ];

            if ($old['teacher_id'] !== $new['teacher_id'] || $old['passing_threshold'] !== $new['passing_threshold']) {
                $this->auditLogger->record(
                    auditable: $classSubject,
                    action: 'updated',
                    old: $old,
                    new: $new,
                );
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Class subject updated.',
        ]);

        return back();
    }

    /**
     * Remove a class subject assignment.
     */
    public function destroy(SchoolClass $schoolClass, ClassSubject $classSubject): RedirectResponse
    {
        abort_unless($classSubject->class_id === $schoolClass->id, 404);

        // Non-active year check (AC-09-09)
        if (! $schoolClass->academicYear?->is_active) {
            abort(422, 'Cannot modify class subjects in an inactive academic year.');
        }

        if ($classSubject->isInUse()) {
            abort(422, 'Cannot delete class subject that is in use.');
        }

        $classSubject->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Class subject removed.',
        ]);

        return back();
    }
}
