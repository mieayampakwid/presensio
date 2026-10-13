<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    /**
     * Display released grades for the authenticated student.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $student = $user->student;
        abort_if($student === null, 403, 'Profil siswa tidak ditemukan.');

        $activeYear = AcademicYear::active() ?? AcademicYear::latest('id')->first();
        $semesters = $activeYear !== null
            ? $activeYear->semesters()->orderBy('number')->get()
            : Semester::query()->orderBy('number')->get();

        $semesterId = $request->filled('semester')
            ? (int) $request->input('semester')
            : (int) ($semesters->first()->id ?? 0);

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where(function ($q) use ($semester) {
                $q->whereNull('ended_on')
                    ->orWhere('ended_on', '>=', $semester->starts_at);
            })
            ->where('started_on', '<=', $semester->ends_at)
            ->with('schoolClass')
            ->latest('started_on')
            ->first();

        $schoolClass = $enrollment?->schoolClass;
        $subjects = [];

        if ($schoolClass !== null) {
            $classSubjects = ClassSubject::query()
                ->where('class_id', $schoolClass->id)
                ->with(['subject:id,code,name', 'teacher.employee:id,name'])
                ->orderBy('id')
                ->get();

            foreach ($classSubjects as $cs) {
                $assessments = Assessment::query()
                    ->where('class_subject_id', $cs->id)
                    ->where('semester_id', $semester->id)
                    ->whereNotNull('released_at')
                    ->orderBy('sort_order')
                    ->orderBy('assessed_on')
                    ->get();

                $scores = Score::query()
                    ->whereIn('assessment_id', $assessments->pluck('id'))
                    ->where('student_id', $student->id)
                    ->get()
                    ->keyBy('assessment_id');

                $assessmentRows = [];
                foreach ($assessments as $assessment) {
                    $score = $scores->get($assessment->id);
                    $assessmentRows[] = [
                        'id' => $assessment->id,
                        'name' => $assessment->name,
                        'kind' => $assessment->kind->value,
                        'assessed_on' => $assessment->assessed_on->toDateString(),
                        'max_score' => (float) $assessment->max_score,
                        'score' => $score?->score !== null ? (float) $score->score : null,
                        'remedial_score' => $score?->remedial_score !== null ? (float) $score->remedial_score : null,
                        'notes' => $score?->notes,
                    ];
                }

                $subjects[] = [
                    'id' => $cs->id,
                    'name' => $cs->subject->name,
                    'code' => $cs->subject->code,
                    'teacher_name' => $cs->teacher?->employee?->name,
                    'passing_threshold' => (float) $cs->passing_threshold,
                    'assessments' => $assessmentRows,
                ];
            }
        }

        return Inertia::render('student/grades', [
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_number' => $student->student_number,
            ],
            'school_class' => $schoolClass !== null ? [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'grade_level' => $schoolClass->grade_level,
            ] : null,
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
            ],
            'semesters' => $semesters,
            'subjects' => $subjects,
        ]);
    }
}
