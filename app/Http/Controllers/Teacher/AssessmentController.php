<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAssessmentRequest;
use App\Http\Requests\Teacher\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Services\Attendance\ClassAccess;
use App\Services\Grading\AssessmentService;
use App\Services\Grading\AssessmentViewService;
use App\Services\Grading\DeleteAssessmentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentViewService $viewService,
        private readonly AssessmentService $assessmentService,
        private readonly DeleteAssessmentAction $deleteAction,
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

        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;
        $data = $this->viewService->getIndexData($classSubject, $user, $semesterId);

        return Inertia::render('teacher/assessments/index', $data);
    }

    /**
     * Store a newly created assessment.
     */
    public function store(StoreAssessmentRequest $request, ClassSubject $classSubject): RedirectResponse
    {
        $this->assessmentService->create($classSubject, $request->validated());

        return back()->with('success', __('toasts.grading.assessment_created'));
    }

    /**
     * Update the specified assessment.
     */
    public function update(UpdateAssessmentRequest $request, ClassSubject $classSubject, Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->class_subject_id !== $classSubject->id, 404);

        $this->assessmentService->update($classSubject, $assessment, $request->validated());

        return back()->with('success', __('toasts.grading.assessment_updated'));
    }

    /**
     * Remove the specified assessment.
     */
    public function destroy(Request $request, ClassSubject $classSubject, Assessment $assessment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ClassAccess::canWriteCourse($user, $classSubject), 403);
        abort_if($assessment->class_subject_id !== $classSubject->id, 404);

        $this->deleteAction->execute($classSubject, $assessment, $request->boolean('confirm'));

        return back()->with('success', __('toasts.grading.assessment_deleted'));
    }
}
