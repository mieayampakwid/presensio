<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use App\Services\Grading\SubjectResultBuilder;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassGradeLedgerController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
        private readonly SubjectResultBuilder $subjectResultBuilder,
    ) {}

    /**
     * Display the homeroom grade ledger for the class in the given semester.
     */
    public function index(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::academicClassIds($user)->contains($schoolClass->id),
            403
        );

        $schoolClass->loadMissing('academicYear.semesters');
        $semesters = $schoolClass->academicYear->semesters;

        $semesterId = $request->filled('semester')
            ? (int) $request->input('semester')
            : (int) ($semesters->first()->id ?? 0);

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        $today = $this->schoolSettings->todayDate();
        $rosterDate = min($today, $semester->ends_at->toDateString());

        // Students enrolled in this class as of rosterDate
        $enrollments = Enrollment::query()
            ->where('class_id', $schoolClass->id)
            ->where('started_on', '<=', $rosterDate)
            ->where(function ($q) use ($rosterDate) {
                $q->whereNull('ended_on')
                    ->orWhere('ended_on', '>=', $rosterDate);
            })
            ->with(['student:id,full_name,student_number,gender'])
            ->get();

        $students = $enrollments->pluck('student')->unique('id')->sortBy('full_name')->values();

        // Subjects taught in this class
        $classSubjects = ClassSubject::query()
            ->where('class_id', $schoolClass->id)
            ->with(['subject:id,code,name', 'teacher.employee:id,name'])
            ->orderBy('id')
            ->get();

        $subjectData = [];
        $subjectResultsMap = [];

        foreach ($classSubjects as $cs) {
            $courseSemester = CourseSemester::forCourse($cs, $semester);
            $subjectData[] = [
                'id' => $cs->id,
                'name' => $cs->subject?->name ?? 'Mata Pelajaran',
                'code' => $cs->subject?->code ?? '',
                'teacher_name' => $cs->teacher?->employee?->name,
                'passing_threshold' => (float) $cs->passing_threshold,
                'is_finalized' => $courseSemester->isFinalized(),
                'finalized_at' => $courseSemester->finalized_at?->toIso8601String(),
            ];

            $resultPayload = $this->subjectResultBuilder->build($cs, $semester);
            $studentRowsById = collect($resultPayload['students'])->keyBy('id');
            $subjectResultsMap[$cs->id] = [
                'is_finalized' => $courseSemester->isFinalized(),
                'threshold' => (float) $cs->passing_threshold,
                'student_rows' => $studentRowsById,
            ];
        }

        $studentRows = [];
        foreach ($students as $student) {
            $scores = [];
            foreach ($classSubjects as $cs) {
                $csMap = $subjectResultsMap[$cs->id];
                $studentData = $csMap['student_rows']->get($student->id);
                $score = $studentData !== null
                    ? ($csMap['is_finalized'] ? $studentData['final_score'] : $studentData['live_score'])
                    : null;
                $belowThreshold = $score !== null && $score < $csMap['threshold'];

                $scores[$cs->id] = [
                    'score' => $score,
                    'is_final' => $csMap['is_finalized'],
                    'below_threshold' => $belowThreshold,
                ];
            }

            $studentRows[] = [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_number' => $student->student_number,
                'gender' => $student->gender?->value,
                'scores' => $scores,
            ];
        }

        return Inertia::render('teacher/class-grades', [
            'school_class' => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'grade_level' => $schoolClass->grade_level,
            ],
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
            ],
            'semesters' => $semesters,
            'subjects' => $subjectData,
            'students' => $studentRows,
        ]);
    }
}
