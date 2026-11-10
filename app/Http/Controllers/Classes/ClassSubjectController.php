<?php

namespace App\Http\Controllers\Classes;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\StoreClassSubjectRequest;
use App\Http\Requests\Classes\UpdateClassSubjectRequest;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Services\Classes\ClassSubjectService;
use App\Services\Classes\ClassSubjectViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassSubjectController extends Controller
{
    public function __construct(
        private readonly ClassSubjectService $classSubjectService,
        private readonly ClassSubjectViewService $viewService,
    ) {}

    /**
     * Display a listing of subjects assigned to a class.
     */
    public function index(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! $user->hasRole(UserRole::Admin)) {
            abort_unless($this->viewService->canViewClassSubjects($user, $schoolClass), 403);

            return Inertia::render('classes/show-subjects', $this->viewService->getReadOnlyViewData($schoolClass));
        }

        return Inertia::render('classes/subjects', $this->viewService->getAdminViewData($schoolClass));
    }

    /**
     * Store a new class subject assignment.
     */
    public function store(StoreClassSubjectRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->classSubjectService->assignSubject($schoolClass, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.class_subjects.assigned'),
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

        $this->classSubjectService->updateSubject($classSubject, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.class_subjects.updated'),
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
            abort(422, __('toasts.class_subjects.inactive_year'));
        }

        if ($classSubject->isInUse()) {
            abort(422, __('toasts.class_subjects.delete_in_use'));
        }

        $this->classSubjectService->removeSubject($classSubject);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.class_subjects.removed'),
        ]);

        return back();
    }
}
