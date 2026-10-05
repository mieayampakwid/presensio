<?php

namespace App\Http\Controllers\Classes;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\StoreClassSubjectRequest;
use App\Http\Requests\Classes\UpdateClassSubjectRequest;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $isAdmin = $user->hasRole(UserRole::Admin);

        if (! $isAdmin) {
            $today = $this->schoolSettings->todayDate();
            abort_unless($this->canViewClassSubjects($user, $schoolClass, $today), 403);

            $schoolClass->load([
                'academicYear:id,name,is_active',
                'teacher.employee:id,name',
                'classSubjects' => fn ($query) => $query->with([
                    'subject:id,code,name,group,sort_order',
                    'teacher.employee:id,name',
                ])->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                    ->orderBy('subjects.group')
                    ->orderBy('subjects.sort_order')
                    ->orderBy('subjects.name')
                    ->select('class_subjects.*'),
            ]);

            $classSubjects = $schoolClass->classSubjects->map(fn (ClassSubject $item) => [
                'id' => $item->id,
                'subject_name' => $item->subject->name,
                'subject_code' => $item->subject->code,
                'subject_group' => $item->subject->group->value,
                'teacher_name' => $item->teacher !== null ? $item->teacher->employee->name : '',
            ]);

            return Inertia::render('classes/show-subjects', [
                'schoolClass' => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'grade_level' => $schoolClass->grade_level,
                    'curriculum' => is_string($schoolClass->curriculum) ? $schoolClass->curriculum : $schoolClass->curriculum->value,
                    'academic_year' => $schoolClass->academicYear ? [
                        'id' => $schoolClass->academicYear->id,
                        'name' => $schoolClass->academicYear->name,
                        'is_active' => $schoolClass->academicYear->is_active,
                    ] : null,
                    'teacher' => $schoolClass->teacher ? [
                        'id' => $schoolClass->teacher->id,
                        'name' => $schoolClass->teacher->employee->name,
                    ] : null,
                ],
                'classSubjects' => $classSubjects,
            ]);
        }

        $schoolClass->load([
            'academicYear:id,name,is_active',
            'teacher.employee:id,name',
            'classSubjects' => fn ($query) => $query->with([
                'subject:id,code,name,group,sort_order,is_active',
                'teacher.employee:id,name',
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
            ->with('employee')
            ->get()
            ->sortBy(fn (Teacher $t) => $t->employee->name)
            ->map(fn (Teacher $t) => [
                'id' => $t->id,
                'name' => $t->employee->name,
            ])
            ->values();

        $classSubjectsPayload = $schoolClass->classSubjects->map(fn (ClassSubject $cs) => [
            'id' => $cs->id,
            'class_id' => $cs->class_id,
            'subject_id' => $cs->subject_id,
            'teacher_id' => $cs->teacher_id,
            'passing_threshold' => (string) $cs->passing_threshold,
            'subject' => [
                'id' => $cs->subject->id,
                'name' => $cs->subject->name,
                'code' => $cs->subject->code,
                'group' => $cs->subject->group->value,
            ],
            'teacher' => $cs->teacher ? [
                'id' => $cs->teacher->id,
                'name' => $cs->teacher->employee->name,
            ] : null,
        ]);

        $schoolClassPayload = [
            'id' => $schoolClass->id,
            'name' => $schoolClass->name,
            'grade_level' => $schoolClass->grade_level,
            'curriculum' => is_string($schoolClass->curriculum) ? $schoolClass->curriculum : $schoolClass->curriculum->value,
            'academic_year' => $schoolClass->academicYear ? [
                'id' => $schoolClass->academicYear->id,
                'name' => $schoolClass->academicYear->name,
                'is_active' => $schoolClass->academicYear->is_active,
            ] : null,
            'teacher' => $schoolClass->teacher ? [
                'id' => $schoolClass->teacher->id,
                'name' => $schoolClass->teacher->employee->name,
            ] : null,
        ];

        return Inertia::render('classes/subjects', [
            'schoolClass' => $schoolClassPayload,
            'classSubjects' => $classSubjectsPayload,
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

        $classSubject = DB::transaction(function () use ($validated) {
            return ClassSubject::create($validated);
        });

        $this->auditLogger->record(
            $classSubject,
            'created',
            null,
            $classSubject->only(['class_id', 'subject_id', 'teacher_id', 'passing_threshold'])
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subject assigned to class.',
        ]);

        return back();
    }

    /**
     * Update a class subject assignment.
     */
    public function update(
        UpdateClassSubjectRequest $request,
        SchoolClass $schoolClass,
        ClassSubject $classSubject
    ): RedirectResponse {
        abort_unless($classSubject->class_id === $schoolClass->id, 404);

        $old = $classSubject->only(['teacher_id', 'passing_threshold']);
        $validated = $request->validated();

        DB::transaction(function () use ($classSubject, $validated) {
            $classSubject->update($validated);
        });

        $this->auditLogger->record(
            $classSubject,
            'updated',
            $old,
            $classSubject->only(['teacher_id', 'passing_threshold'])
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Class subject updated.',
        ]);

        return back();
    }

    /**
     * Delete a class subject assignment.
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

        $old = $classSubject->only(['class_id', 'subject_id', 'teacher_id', 'passing_threshold']);

        DB::transaction(function () use ($classSubject) {
            $classSubject->delete();
        });

        $this->auditLogger->record(
            $classSubject,
            'deleted',
            $old,
            null
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Class subject removed.',
        ]);

        return back();
    }

    /**
     * Check if a non-admin user can view class subjects.
     */
    private function canViewClassSubjects(User $user, SchoolClass $schoolClass, string $today): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Principal)) {
            return true;
        }

        if ($user->hasRole(UserRole::Teacher)) {
            $teacherId = $user->teacher?->id;
            if ($teacherId !== null && $schoolClass->teacher_id === $teacherId) {
                return true;
            }
        }

        if ($user->hasRole(UserRole::Parent)) {
            $guardian = $user->guardian;
            if ($guardian !== null) {
                $guardian->loadMissing('students.enrollments');
                foreach ($guardian->students as $child) {
                    if ($child->classOn($today)?->id === $schoolClass->id) {
                        return true;
                    }
                }
            }
        }

        if ($user->hasRole(UserRole::Student)) {
            $student = $user->student;
            if ($student !== null && $student->classOn($today)?->id === $schoolClass->id) {
                return true;
            }
        }

        return false;
    }
}
