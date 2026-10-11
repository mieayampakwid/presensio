<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseWeightController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Update summative scope and final weights for a course and semester.
     */
    public function update(Request $request, ClassSubject $classSubject): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ClassAccess::canWriteCourse($user, $classSubject), 403);

        $data = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'scope_weight' => ['required', 'numeric', 'min:0'],
            'final_weight' => ['required', 'numeric', 'min:0'],
        ]);

        if ((float) $data['scope_weight'] + (float) $data['final_weight'] <= 0) {
            abort(422, 'Total bobot nilai harus lebih besar dari 0.');
        }

        /** @var Semester $semester */
        $semester = Semester::findOrFail($data['semester_id']);
        $courseSemester = CourseSemester::forCourse($classSubject, $semester);
        $courseSemester->assertOpen();

        $oldValues = [
            'scope_weight' => $courseSemester->scope_weight,
            'final_weight' => $courseSemester->final_weight,
        ];

        $courseSemester->update([
            'scope_weight' => $data['scope_weight'],
            'final_weight' => $data['final_weight'],
        ]);

        $this->auditLogger->record(
            $courseSemester,
            'weights_updated',
            $oldValues,
            [
                'scope_weight' => $data['scope_weight'],
                'final_weight' => $data['final_weight'],
            ]
        );

        return response()->json([
            'message' => 'Bobot penilaian berhasil diperbarui.',
            'weights' => [
                'scope' => (float) $courseSemester->scope_weight,
                'final' => (float) $courseSemester->final_weight,
            ],
        ]);
    }
}
