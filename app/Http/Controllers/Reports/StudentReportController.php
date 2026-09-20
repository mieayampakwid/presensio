<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student attendance report (spec 06 §Requirements 1). All four roles
 * reach this controller — every request re-resolves the student through
 * the spec 01 scoping: admin → all, teacher → homeroom classes, parent →
 * linked children, student → self. Reports feed students and parents, so
 * notes/override attribution never leaves the server (spec 03 carve-out).
 */
class StudentReportController extends Controller
{
    public function __construct(private readonly AttendanceReportService $reports) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $student = $this->resolveStudent($request, $user, required: false);
        [$from, $to] = $this->reports->resolveRange($request->string('from')->toString(), $request->string('to')->toString());
        $includeExcused = $request->boolean('include_excused');

        $report = $student === null
            ? null
            : $this->reports->studentReport($student, $from, $to, $includeExcused);

        return Inertia::render('reports/student-report', [
            'students' => $this->selectableStudents($user),
            'filters' => [
                'student_id' => $student?->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'include_excused' => $includeExcused,
            ],
            'summary' => $report === null ? null : [
                'counts' => $report['counts'],
                'rate' => $report['rate'],
            ],
            'records' => $report['records'] ?? null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $student = $this->resolveStudent($request, $user, required: true);

        if ($student === null) {
            abort(404);
        }

        [$from, $to] = $this->reports->resolveRange($request->string('from')->toString(), $request->string('to')->toString());

        return $this->reports->studentCsv($student, $from, $to, $request->boolean('include_excused'));
    }

    /**
     * Absent student_id → null (the picker renders, never a 403); a
     * present-but-unknown or out-of-scope id → 404/403.
     */
    private function resolveStudent(Request $request, User $user, bool $required): ?Student
    {
        $studentId = $request->integer('student_id');

        if ($studentId === 0) {
            abort_if($required, 404);

            return null;
        }

        $student = Student::query()->findOrFail($studentId);

        abort_unless($this->canView($user, $student), 403);

        return $student;
    }

    private function canView(User $user, Student $student): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Teacher => $student->class_id !== null && ClassAccess::canAccess($user, $student->class_id),
            UserRole::Parent => (bool) $user->guardian?->students()->whereKey($student->id)->exists(),
            UserRole::Student => (bool) $user->student?->is($student),
        };
    }

    /**
     * The picker options per role — the student role always sees self
     * only, so the page hides the selector for them.
     *
     * @return Collection<int, array{id: int, full_name: string, student_number: string|null}>
     */
    private function selectableStudents(User $user): Collection
    {
        $columns = ['id', 'full_name', 'student_number'];

        return match ($user->role) {
            UserRole::Admin => Student::query()->orderBy('full_name')->get($columns),
            UserRole::Teacher => Student::query()
                ->whereIn('class_id', ClassAccess::classIds($user))
                ->orderBy('full_name')
                ->get($columns),
            UserRole::Parent => $user->guardian
                ? $user->guardian->students()->orderBy('full_name')->get($columns)
                : collect(),
            UserRole::Student => $user->student
                ? collect([[
                    'id' => $user->student->id,
                    'full_name' => $user->student->full_name,
                    'student_number' => $user->student->student_number,
                ]])
                : collect(),
        };
    }
}
