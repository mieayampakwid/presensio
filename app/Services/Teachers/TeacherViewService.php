<?php

namespace App\Services\Teachers;

use App\Models\Teacher;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TeacherViewService
{
    /**
     * Get paginated teachers with search filter across name, employee number, and phone.
     *
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     name: string,
     *     teacher_number: string|null,
     *     phone_number: string|null
     * }>
     */
    public function getPaginatedTeachers(string $search): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Teacher> $paginator */
        $paginator = Teacher::query()
            ->with('employee')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('employee', function (Builder $query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('employee_number', "%{$search}%")
                        ->orWhereLike('phone_number', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return $paginator->through(fn (Teacher $teacher): array => [
            'id' => $teacher->id,
            'name' => $teacher->employee->name,
            'teacher_number' => $teacher->employee?->employee_number,
            'phone_number' => $teacher->employee?->phone_number,
        ]);
    }

    /**
     * View data payload for editing a teacher.
     *
     * @return array{
     *     teacher: array{
     *         id: int,
     *         name: string,
     *         teacher_number: string|null,
     *         phone_number: string|null
     *     }
     * }
     */
    public function getEditViewData(Teacher $teacher): array
    {
        $teacher->loadMissing('employee');

        return [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->employee->name,
                'teacher_number' => $teacher->employee?->employee_number,
                'phone_number' => $teacher->employee?->phone_number,
            ],
        ];
    }
}
