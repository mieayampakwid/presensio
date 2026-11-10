<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class StudentViewService
{
    /**
     * Get paginated students with active search and status filters.
     *
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     full_name: string,
     *     student_number: string|null,
     *     school_class: array{id: int, name: string}|null,
     *     is_enrolled: bool,
     *     has_rfid: bool
     * }>
     */
    public function getPaginatedStudents(string $search, string $status): LengthAwarePaginator
    {
        $activeYearId = AcademicYear::active()?->id;

        /** @var LengthAwarePaginator<int, Student> $paginator */
        $paginator = Student::query()
            ->with(['currentEnrollment.schoolClass:id,name', 'rfidCard:id,student_id'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('full_name', "%{$search}%")
                        ->orWhereLike('student_number', "%{$search}%")
                        ->orWhereHas('guardians', function (Builder $query) use ($search): void {
                            $query->whereLike('name', "%{$search}%")
                                ->orWhereLike('phone_number', "%{$search}%");
                        });
                });
            })
            ->when($status === 'active', function (Builder $query) use ($activeYearId): void {
                $query->whereHas('enrollments', function (Builder $q) use ($activeYearId): void {
                    $q->whereNull('ended_on')
                        ->when($activeYearId !== null, fn (Builder $eq) => $eq->whereHas('schoolClass', fn (Builder $cq) => $cq->where('academic_year_id', $activeYearId)));
                });
            })
            ->when($status === 'withdrawn', function (Builder $query) use ($activeYearId): void {
                $query->whereDoesntHave('enrollments', function (Builder $q) use ($activeYearId): void {
                    $q->whereNull('ended_on')
                        ->when($activeYearId !== null, fn (Builder $eq) => $eq->whereHas('schoolClass', fn (Builder $cq) => $cq->where('academic_year_id', $activeYearId)));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return $paginator->through(fn (Student $s): array => [
            'id' => $s->id,
            'full_name' => $s->full_name,
            'student_number' => $s->student_number,
            'school_class' => $s->currentEnrollment?->schoolClass ? [
                'id' => $s->currentEnrollment->schoolClass->id,
                'name' => $s->currentEnrollment->schoolClass->name,
            ] : null,
            'is_enrolled' => $s->currentEnrollment !== null,
            'has_rfid' => $s->rfidCard !== null,
        ]);
    }

    /**
     * Options for create/edit forms: classes in active year and guardians.
     *
     * @return array{classes: Collection<int, SchoolClass>, guardians: Collection<int, Guardian>}
     */
    public function getFormOptions(): array
    {
        return [
            'classes' => SchoolClass::query()
                ->where('academic_year_id', AcademicYear::active()?->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'guardians' => Guardian::query()
                ->orderBy('name')
                ->get(['id', 'name', 'phone_number']),
        ];
    }

    /**
     * View data payload for editing a student.
     *
     * @return array{
     *     classes: Collection<int, SchoolClass>,
     *     guardians: Collection<int, Guardian>,
     *     student: Student,
     *     current_guardian_ids: list<int>,
     *     current_guardians: list<array{id: int, relationship_type: string}>
     * }
     */
    public function getEditViewData(Student $student): array
    {
        $student->loadMissing('guardians');

        /** @var list<int> $guardianIds */
        $guardianIds = array_values($student->guardians()->pluck('guardian_id')->all());

        return [
            ...$this->getFormOptions(),
            'student' => $student,
            'current_guardian_ids' => $guardianIds,
            'current_guardians' => array_values($student->guardians->map(fn (Guardian $g): array => [
                'id' => $g->id,
                'relationship_type' => $g->pivot->relationship_type ?? 'guardian',
            ])->all()),
        ];
    }
}
