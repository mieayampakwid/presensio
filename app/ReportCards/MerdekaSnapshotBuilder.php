<?php

namespace App\ReportCards;

use App\Models\ClassSubject;
use App\Models\ReportCardEntry;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubjectResult;
use App\ReportCards\Snapshot\AttendanceCounter;
use App\Services\SchoolSettings;
use App\Support\MerdekaPhase;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

class MerdekaSnapshotBuilder implements ReportCardSnapshotBuilder
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly AttendanceCounter $attendanceCounter,
    ) {}

    /**
     * @return Collection<int, Student>
     */
    public function roster(SchoolClass $class, Semester $semester): Collection
    {
        $asOf = $this->asOf($semester)->toDateString();

        return Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('class_id', $class->id)
                ->where('started_on', '<=', $asOf)
                ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $asOf)))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        SchoolClass $class,
        Semester $semester,
        Student $student,
        int $version,
        CarbonInterface $publishedAt,
        ?ReportCardEntry $entry = null,
    ): array {
        $asOfDate = $this->asOf($semester);
        $asOf = $asOfDate->toDateString();
        $semester->loadMissing('academicYear');
        $class->loadMissing('teacher.employee');
        $rows = $this->subjectRows($class, $semester, $student, $asOf);

        $principal = $this->settings->principal();
        $settingRow = $this->settings->row();

        $decision = $entry?->promotion_decision;

        return [
            'school' => [
                'name' => $this->settings->schoolName(),
                'npsn' => $settingRow->npsn,
                'address' => $settingRow->school_address,
                'logo' => $this->logoSnapshot($settingRow->logo_path),
            ],
            'principal' => [
                'name' => $principal['name'],
                'nip' => $principal['nip'],
            ],
            'homeroom_teacher' => $class->teacher?->employee?->name,
            'class' => [
                'name' => $class->name,
                'grade_level' => $class->grade_level,
                'phase' => MerdekaPhase::forGradeLevel($class->grade_level)?->value,
            ],
            'semester' => [
                'name' => $semester->name,
                'number' => $semester->number,
                'academic_year' => $semester->academicYear->name,
                'starts_at' => $semester->starts_at->toDateString(),
                'ends_at' => $semester->ends_at->toDateString(),
            ],
            'student' => [
                'name' => $student->full_name,
                'student_number' => $student->student_number,
                'address' => $student->address,
            ],
            'version' => $version,
            'published_at' => $publishedAt->toDateString(),
            'subjects' => $rows,
            'attendance' => $this->attendanceCounter->count($student->id, $semester->starts_at, $asOfDate),
            'notes' => [
                'homeroom' => $entry?->homeroom_notes,
                'extracurricular' => $entry?->extracurricular_notes,
            ],
            'decision' => $decision === null
                ? null
                : ['value' => $decision->value, 'label' => $decision->label()],
            'complete' => collect($rows)->every(fn (array $row) => $row['final_score'] !== null),
        ];
    }

    /**
     * The as-of date: min(today, semester.ends_at) in the school timezone.
     */
    private function asOf(Semester $semester): CarbonInterface
    {
        return Date::parse(min($this->settings->todayDate(), $semester->ends_at->toDateString()));
    }

    /**
     * One row per class_subject of the class, ordered by subject group then
     * sort_order. Uses the student's finalized result in the current class;
     * falls back to a finalized result for the same subject from a class the
     * student actually attended during the semester (labelled with that
     * class). Rows without any result stay empty.
     *
     * @return array<int, array<string, mixed>>
     */
    private function subjectRows(SchoolClass $class, Semester $semester, Student $student, string $asOf): array
    {
        $results = $this->finalizedResults($class, $semester, $student, $asOf);

        return $class->classSubjects()
            ->with('subject')
            ->get()
            ->sortBy(fn (ClassSubject $classSubject) => [
                $classSubject->subject->group->value,
                $classSubject->subject->sort_order,
            ])
            ->values()
            ->map(function (ClassSubject $classSubject) use ($results, $class) {
                $subjectId = $classSubject->subject_id;

                /** @var SubjectResult|null $current */
                $current = $results->first(fn (SubjectResult $result) => $this->resultSubjectId($result) === $subjectId
                    && $this->resultClassId($result) === $class->id);

                /** @var SubjectResult|null $fallback */
                $fallback = $current ?? $results->first(fn (SubjectResult $result) => $this->resultSubjectId($result) === $subjectId
                    && $this->resultClassId($result) !== $class->id);

                $fromClass = $fallback !== null && $current === null
                    ? $fallback->courseSemester->classSubject->schoolClass->name
                    : null;

                return [
                    'subject_id' => $subjectId,
                    'name' => $classSubject->subject->name,
                    'group' => $classSubject->subject->group->value,
                    'group_label' => $classSubject->subject->group->label(),
                    'sort_order' => $classSubject->subject->sort_order,
                    'final_score' => $fallback?->final_score,
                    'description' => $fallback?->description,
                    'from_class' => $fromClass,
                ];
            })
            ->all();
    }

    /**
     * Finalized subject results of the student for courses of every class the
     * student attended during the semester (current plus previous classes —
     * never classes they were not enrolled in), keyed by course.
     *
     * @return Collection<int, SubjectResult>
     */
    private function finalizedResults(SchoolClass $class, Semester $semester, Student $student, string $asOf): Collection
    {
        $classIds = $student->enrollments()
            ->where('started_on', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhere('ended_on', '>=', $semester->starts_at->toDateString()))
            ->pluck('class_id');

        return SubjectResult::query()
            ->where('student_id', $student->id)
            ->whereHas('courseSemester', fn ($query) => $query
                ->where('semester_id', $semester->id)
                ->whereNotNull('finalized_at')
                ->whereHas('classSubject', fn ($q) => $q->whereIn('class_id', $classIds)))
            ->with('courseSemester.classSubject.schoolClass')
            ->get();
    }

    private function resultSubjectId(SubjectResult $result): int
    {
        return $result->courseSemester->classSubject->subject_id;
    }

    private function resultClassId(SubjectResult $result): int
    {
        return $result->courseSemester->classSubject->class_id;
    }

    /**
     * The logo content embedded at build time so the snapshot never changes
     * when the school replaces its logo file later.
     *
     * @return array{path: string|null, content_base64: string|null}
     */
    private function logoSnapshot(?string $logoPath): array
    {
        if ($logoPath === null || ! Storage::disk('local')->exists($logoPath)) {
            return ['path' => null, 'content_base64' => null];
        }

        return [
            'path' => $logoPath,
            'content_base64' => base64_encode((string) Storage::disk('local')->get($logoPath)),
        ];
    }
}
