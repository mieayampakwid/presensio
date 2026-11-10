<?php

namespace App\Services\Students;

use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
    ) {}

    /**
     * Store a newly created student along with guardian relations and class enrollment.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{relationship_type: string}>  $guardianPivotData
     */
    public function createStudent(array $attributes, array $guardianPivotData, ?int $classId): Student
    {
        return DB::transaction(function () use ($attributes, $guardianPivotData, $classId): Student {
            $student = Student::create($attributes);
            $student->guardians()->sync($guardianPivotData);
            $this->assignClass($student, $classId);

            return $student;
        });
    }

    /**
     * Update an existing student, syncing guardian relations and class enrollment.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{relationship_type: string}>  $guardianPivotData
     */
    public function updateStudent(Student $student, array $attributes, array $guardianPivotData, ?int $classId): Student
    {
        return DB::transaction(function () use ($student, $attributes, $guardianPivotData, $classId): Student {
            $student->update($attributes);
            $student->guardians()->sync($guardianPivotData);
            $this->assignClass($student, $classId);

            return $student;
        });
    }

    /**
     * Route the chosen class through the enrollment writer. Leaving the
     * select empty never un-enrolls — alumni happen via roll-over, not a
     * stray form save (spec 07).
     */
    public function assignClass(Student $student, ?int $classId): void
    {
        if ($classId !== null) {
            $schoolClass = SchoolClass::query()->findOrFail($classId);
            $this->enrollments->assign($student, $schoolClass);
        }
    }

    /**
     * Reasons the student cannot be deleted, in display order. Spec 03
     * appends its attendance-history check here.
     *
     * @return list<string>
     */
    public function deletionBlockers(Student $student): array
    {
        $blockers = [];

        if ($student->user_id !== null) {
            $blockers[] = __('toasts.common.delete_blocked_user_link');
        }

        if ($student->attendances()->exists()) {
            $blockers[] = __('toasts.students.delete_blocked_attendance');
        }

        // Spec 04: excuse history survives too (the RESTRICT foreign key
        // would otherwise turn the delete into a 500).
        if ($student->excuses()->exists()) {
            $blockers[] = __('toasts.students.delete_blocked_excuses');
        }

        return $blockers;
    }

    /**
     * Delete the specified student after clearing guardian pivots and resetting RFID assignment.
     */
    public function deleteStudent(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            $student->guardians()->detach();
            RfidCard::query()->where('student_id', $student->id)->update(['student_id' => null]);
            $student->delete();
        });
    }
}
