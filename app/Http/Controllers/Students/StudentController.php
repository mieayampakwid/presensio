<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * Display a listing of the students.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $students = Student::query()
            ->with('currentEnrollment.schoolClass:id,name')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%")
                        ->orWhereHas('guardians', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('students/index', [
            'students' => $students,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create(): Response
    {
        return Inertia::render('students/create', $this->formOptions());
    }

    /**
     * Store a newly created student.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = DB::transaction(function () use ($request): Student {
            $student = Student::create($request->studentAttributes());
            $student->guardians()->sync($request->guardianIds());
            $this->assignClass($request, $student);

            return $student;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Student {$student->full_name} created."]);

        return to_route('students.index');
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit(Student $student): Response
    {
        return Inertia::render('students/edit', [
            ...$this->formOptions(),
            'student' => $student,
            'current_guardian_ids' => $student->guardians()->pluck('guardian_id')->all(),
        ]);
    }

    /**
     * Update the specified student.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student): void {
            $student->update($request->studentAttributes());
            $student->guardians()->sync($request->guardianIds());
            $this->assignClass($request, $student);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Student updated.']);

        return to_route('students.edit', $student);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student): RedirectResponse
    {
        $blockers = $this->deletionBlockers($student);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        DB::transaction(function () use ($student): void {
            $student->guardians()->detach();
            RfidCard::where('student_id', $student->id)->update(['student_id' => null]);
            $student->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Student deleted.']);

        return to_route('students.index');
    }

    /**
     * Route the chosen class through the enrollment writer. Leaving the
     * select empty never un-enrolls — alumni happen via roll-over, not a
     * stray form save (spec 07).
     */
    private function assignClass(StoreStudentRequest|UpdateStudentRequest $request, Student $student): void
    {
        $classId = $request->classId();

        if ($classId !== null) {
            $this->enrollments->assign($student, SchoolClass::query()->findOrFail($classId));
        }
    }

    /**
     * Options for the create/edit forms: classes and guardians. Classes
     * come from the active year only.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'classes' => SchoolClass::query()
                ->where('academic_year_id', AcademicYear::active()?->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'guardians' => Guardian::orderBy('name')->get(['id', 'name', 'phone_number']),
        ];
    }

    /**
     * Reasons the student cannot be deleted, in display order. Spec 03
     * appends its attendance-history check here.
     *
     * @return list<string>
     */
    private function deletionBlockers(Student $student): array
    {
        $blockers = [];

        if ($student->user_id !== null) {
            $blockers[] = 'Cannot delete: linked to a user account. Unlink it first.';
        }

        if ($student->attendances()->exists()) {
            $blockers[] = 'Cannot delete: student has attendance history.';
        }

        // Spec 04: excuse history survives too (the RESTRICT foreign key
        // would otherwise turn the delete into a 500).
        if ($student->excuses()->exists()) {
            $blockers[] = 'Cannot delete: student has absence excuses.';
        }

        return $blockers;
    }
}
