<?php

namespace App\Services\Teachers;

use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class TeacherService
{
    /**
     * Create an employee and associated teacher record in a transaction.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createTeacher(array $validated): Teacher
    {
        return DB::transaction(function () use ($validated): Teacher {
            /** @var string|null $number */
            $number = $validated['teacher_number'] ?? $validated['employee_number'] ?? null;

            $employmentType = ! empty($validated['employment_type'])
                ? ($validated['employment_type'] instanceof EmploymentType
                    ? $validated['employment_type']
                    : EmploymentType::from((string) $validated['employment_type']))
                : EmploymentType::Permanent;

            /** @var Employee $employee */
            $employee = Employee::create([
                'name' => $validated['name'],
                'employee_number' => $number,
                'phone_number' => $validated['phone_number'] ?? null,
                'employment_type' => $employmentType,
                'position' => $validated['position'] ?? 'Guru',
                'working_days' => $validated['working_days'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            return Teacher::create([
                'employee_id' => $employee->id,
            ]);
        });
    }

    /**
     * Update the associated employee record of a teacher.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateTeacher(Teacher $teacher, array $validated): Teacher
    {
        return DB::transaction(function () use ($teacher, $validated): Teacher {
            $teacher->loadMissing('employee');

            /** @var string|null $number */
            $number = $validated['teacher_number'] ?? $validated['employee_number'] ?? null;

            $updateData = [
                'name' => $validated['name'],
                'employee_number' => $number,
                'phone_number' => $validated['phone_number'] ?? null,
            ];

            if (! empty($validated['employment_type'])) {
                $updateData['employment_type'] = $validated['employment_type'] instanceof EmploymentType
                    ? $validated['employment_type']
                    : EmploymentType::from((string) $validated['employment_type']);
            }

            if (array_key_exists('position', $validated)) {
                $updateData['position'] = $validated['position'];
            }

            if (array_key_exists('working_days', $validated)) {
                $updateData['working_days'] = $validated['working_days'];
            }

            if (array_key_exists('is_active', $validated)) {
                $updateData['is_active'] = (bool) $validated['is_active'];
            }

            $teacher->employee?->update($updateData);

            return $teacher;
        });
    }

    /**
     * Reasons the teacher cannot be deleted, in display order.
     *
     * @return list<string>
     */
    public function deletionBlockers(Teacher $teacher): array
    {
        $blockers = [];

        if ($teacher->classes()->exists()) {
            $blockers[] = __('toasts.teachers.delete_blocked_homeroom');
        }

        if ($teacher->classSubjects()->exists()) {
            $blockers[] = __('toasts.teachers.delete_blocked_assignments');
        }

        $teacher->loadMissing('employee');
        if ($teacher->employee?->user_id !== null) {
            $blockers[] = __('toasts.common.delete_blocked_user_link');
        }

        if ($teacher->employee?->attendances()->exists()) {
            $blockers[] = __('toasts.teachers.delete_blocked_attendance');
        }

        return $blockers;
    }

    /**
     * Remove the specified teacher and cleanup unlinked employee profile.
     */
    public function deleteTeacher(Teacher $teacher): void
    {
        DB::transaction(function () use ($teacher): void {
            $teacher->loadMissing('employee');
            $employee = $teacher->employee;

            $teacher->delete();

            if ($employee !== null && $employee->user_id === null) {
                $employee->delete();
            }
        });
    }
}
