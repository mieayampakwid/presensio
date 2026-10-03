<?php

namespace App\Http\Controllers\Subjects;

use App\Enums\SubjectGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subjects\StoreSubjectRequest;
use App\Http\Requests\Subjects\UpdateSubjectRequest;
use App\Models\Subject;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * Display a listing of the subjects.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $subjects = Subject::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('subjects/index', [
            'subjects' => $subjects,
            'filters' => [
                'search' => $search,
            ],
            'groups' => $this->groupOptions(),
        ]);
    }

    /**
     * Show the form for creating a new subject.
     */
    public function create(): Response
    {
        return Inertia::render('subjects/create', [
            'groups' => $this->groupOptions(),
        ]);
    }

    /**
     * Store a newly created subject in storage.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        Subject::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subject created.',
        ]);

        return to_route('subjects.index');
    }

    /**
     * Show the form for editing the specified subject.
     */
    public function edit(Subject $subject): Response
    {
        return Inertia::render('subjects/edit', [
            'subject' => $subject,
            'groups' => $this->groupOptions(),
        ]);
    }

    /**
     * Update the specified subject in storage.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subject updated.',
        ]);

        return to_route('subjects.index');
    }

    /**
     * Remove the specified subject from storage.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        if ($this->hasAssignments($subject)) {
            abort(422, 'Cannot delete subject that is assigned to classes.');
        }

        $subject->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subject deleted.',
        ]);

        return to_route('subjects.index');
    }

    /**
     * Check if the subject has any class assignments.
     */
    private function hasAssignments(Subject $subject): bool
    {
        if (! Schema::hasTable('class_subjects')) {
            return false;
        }

        return $subject->classSubjects()->exists();
    }

    /**
     * Subject group options for selects and filters.
     *
     * @return list<array{value: string, label: string}>
     */
    private function groupOptions(): array
    {
        return array_map(
            fn (SubjectGroup $group) => [
                'value' => $group->value,
                'label' => $group->label(),
            ],
            SubjectGroup::cases()
        );
    }
}
