<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\CopyLearningObjectivesRequest;
use App\Http\Requests\Teacher\StoreLearningObjectiveRequest;
use App\Http\Requests\Teacher\UpdateLearningObjectiveRequest;
use App\Models\LearningObjective;
use App\Services\Attendance\ClassAccess;
use App\Services\Grading\CopyLearningObjectivesAction;
use App\Services\Grading\LearningObjectiveViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearningObjectiveController extends Controller
{
    public function __construct(
        private readonly LearningObjectiveViewService $viewService,
        private readonly CopyLearningObjectivesAction $copyAction,
    ) {}

    /**
     * Display a listing of learning objectives filtered by year, subject, and grade level.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $this->viewService->getIndexData(
            $user,
            $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null,
            $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
            $request->filled('grade_level') ? (int) $request->input('grade_level') : null,
        );

        return Inertia::render('teacher/objectives/index', $data);
    }

    /**
     * Store a newly created learning objective.
     */
    public function store(StoreLearningObjectiveRequest $request): RedirectResponse
    {
        LearningObjective::create($request->validated());

        return back()->with('success', __('toasts.grading.objective_created'));
    }

    /**
     * Update the specified learning objective.
     */
    public function update(UpdateLearningObjectiveRequest $request, LearningObjective $learningObjective): RedirectResponse
    {
        $learningObjective->update($request->validated());

        return back()->with('success', __('toasts.grading.objective_updated'));
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
            abort(422, __('toasts.grading.objective_delete_linked'));
        }

        $learningObjective->delete();

        return back()->with('success', __('toasts.grading.objective_deleted'));
    }

    /**
     * Copy learning objectives from the previous academic year for this subject and grade level.
     */
    public function copyPreviousYear(CopyLearningObjectivesRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $copiedCount = $this->copyAction->execute(
            (int) $data['academic_year_id'],
            (int) $data['subject_id'],
            (int) $data['grade_level']
        );

        if ($copiedCount === null) {
            return back()->with('info', __('toasts.grading.no_previous_year'));
        }

        return back()->with('success', __('toasts.grading.objectives_copied', ['count' => $copiedCount]));
    }
}
