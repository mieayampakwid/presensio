<?php

namespace App\Services\ReportCards;

use App\Jobs\BuildClassReportCardPdf;
use App\Models\CourseSemester;
use App\Models\ReportCard;
use App\Models\ReportCardEntry;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\ReportCards\SnapshotBuilderResolver;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use Illuminate\Support\Facades\Storage;

class ReportCardWorkspaceService
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly ReportCardPublisher $publisher,
    ) {}

    /**
     * Find the first accessible class alphabetically for the user's report cards landing page.
     */
    public function firstAccessibleClass(User $user): ?SchoolClass
    {
        return SchoolClass::query()
            ->whereIn('id', ClassAccess::academicClassIds($user))
            ->orderBy('name')
            ->first();
    }

    /**
     * Resolve the requested semester or fall back to the first semester of the class's year.
     */
    public function resolveSemester(SchoolClass $schoolClass, ?int $requestedSemesterId): Semester
    {
        $schoolClass->loadMissing('academicYear.semesters');
        $semesters = $schoolClass->academicYear->semesters;

        /** @var Semester|null $semester */
        $semester = $requestedSemesterId !== null
            ? $semesters->firstWhere('id', $requestedSemesterId)
            : $semesters->first();

        abort_if($semester === null, 404, __('toasts.report_cards.semester_not_found'));

        return $semester;
    }

    /**
     * Build the view payload for the homeroom report card workspace.
     *
     * @return array{
     *     school_class: array{id: int, name: string, grade_level: int},
     *     semester: array{id: int, name: string, number: int},
     *     semesters: list<array{id: int, name: string, number: int}>,
     *     accessible_classes: list<array{id: int, name: string}>,
     *     checklist: array<int, array{subject: string, teacher: string|null, finalized: bool}>,
     *     students: array<int, array<string, mixed>>,
     *     publication: array<string, mixed>|null,
     *     bundle_available: bool,
     *     can: array{edit: bool}
     * }
     */
    public function getWorkspaceViewData(User $user, SchoolClass $schoolClass, Semester $semester): array
    {
        return [
            'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name, 'grade_level' => $schoolClass->grade_level],
            'semester' => ['id' => $semester->id, 'name' => $semester->name, 'number' => $semester->number],
            'semesters' => array_values($schoolClass->academicYear->semesters
                ->map(fn (Semester $s): array => ['id' => $s->id, 'name' => $s->name, 'number' => $s->number])
                ->all()),
            'accessible_classes' => array_values(SchoolClass::query()
                ->whereIn('id', ClassAccess::academicClassIds($user))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SchoolClass $c): array => ['id' => $c->id, 'name' => $c->name])
                ->all()),
            'checklist' => $this->checklist($schoolClass, $semester),
            'students' => $this->students($schoolClass, $semester),
            'publication' => $this->publisher->currentPublication($schoolClass, $semester),
            'bundle_available' => $this->bundleAvailable($schoolClass, $semester),
            'can' => [
                'edit' => ClassAccess::canWrite($user, $schoolClass->id),
            ],
        ];
    }

    /**
     * Whether a combined PDF bundle exists for the class's current publication.
     */
    public function bundleAvailable(SchoolClass $schoolClass, Semester $semester): bool
    {
        $publication = ReportCardPublication::query()
            ->where('class_id', $schoolClass->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();

        return $publication !== null
            && Storage::disk('local')->exists(BuildClassReportCardPdf::path($publication));
    }

    /**
     * Find the published report card record for a student in the current publication.
     *
     * @return array{publication: ReportCardPublication, card: ReportCard}
     */
    public function findPublishedCard(SchoolClass $schoolClass, Semester $semester, Student $student): array
    {
        /** @var ReportCardPublication|null $publication */
        $publication = ReportCardPublication::query()
            ->where('class_id', $schoolClass->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();

        abort_if($publication === null, 403, __('toasts.report_cards.not_published'));

        /** @var ReportCard $card */
        $card = $publication->reportCards()
            ->where('student_id', $student->id)
            ->firstOrFail();

        return [
            'publication' => $publication,
            'card' => $card,
        ];
    }

    /**
     * Finalization checklist: one row per class subject with the course's
     * finalization state for the semester.
     *
     * @return array<int, array{subject: string, teacher: string|null, finalized: bool}>
     */
    public function checklist(SchoolClass $class, Semester $semester): array
    {
        return $class->classSubjects()
            ->with(['subject', 'teacher.employee'])
            ->get()
            ->sortBy(fn ($classSubject) => [
                $classSubject->subject->group->value,
                $classSubject->subject->sort_order,
            ])
            ->values()
            ->map(fn ($classSubject) => [
                'subject' => $classSubject->subject->name,
                'teacher' => $classSubject->teacher?->employee?->name,
                'finalized' => CourseSemester::forCourse($classSubject, $semester)->isFinalized(),
            ])
            ->all();
    }

    /**
     * Roster as of min(today, semester.ends_at) with per-student entries and
     * result completeness for this class's courses in this semester only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function students(SchoolClass $class, Semester $semester): array
    {
        $builder = SnapshotBuilderResolver::for($class);
        $roster = $builder->roster($class, $semester);

        $entries = ReportCardEntry::query()
            ->where('semester_id', $semester->id)
            ->whereIn('student_id', $roster->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $total = $class->classSubjects()->count();

        return $roster
            ->map(function (Student $student) use ($builder, $class, $semester, $entries, $total) {
                // Completeness mirrors the snapshot source of truth: rows with
                // an actual score, including previous-class fallbacks.
                $payload = $builder->build($class, $semester, $student, 0, $this->settings->now());

                /** @var array<int, array<string, mixed>> $subjects */
                $subjects = $payload['subjects'];
                $withResults = collect($subjects)
                    ->filter(fn (array $row) => $row['final_score'] !== null)
                    ->count();
                $entry = $entries->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'subjects_total' => $total,
                    'subjects_with_results' => $withResults,
                    'complete' => $payload['complete'],
                    'entries' => [
                        'homeroom_notes' => $entry?->homeroom_notes,
                        'extracurricular_notes' => $entry?->extracurricular_notes,
                        'promotion_decision' => $entry?->promotion_decision?->value,
                    ],
                ];
            })
            ->all();
    }

    /**
     * Whether the student is on the class roster as of the as-of date.
     */
    public function rosterContains(SchoolClass $class, Semester $semester, int $studentId): bool
    {
        return SnapshotBuilderResolver::for($class)->roster($class, $semester)->contains('id', $studentId);
    }

    /**
     * Save (or update) the homeroom entry of one student for the semester.
     *
     * @param  array{homeroom_notes?: string|null, extracurricular_notes?: string|null, promotion_decision?: string|null}  $data
     */
    public function saveEntry(SchoolClass $class, Semester $semester, Student $student, array $data): ReportCardEntry
    {
        return ReportCardEntry::updateOrCreate(
            ['semester_id' => $semester->id, 'student_id' => $student->id],
            [
                'homeroom_notes' => $data['homeroom_notes'] ?? null,
                'extracurricular_notes' => $data['extracurricular_notes'] ?? null,
                'promotion_decision' => $data['promotion_decision'] ?? null,
            ],
        );
    }

    /**
     * The would-be snapshot of one student without persisting anything,
     * using the next publication version as the preview version.
     *
     * @return array<string, mixed>
     */
    public function preview(SchoolClass $class, Semester $semester, Student $student): array
    {
        $entry = ReportCardEntry::query()
            ->where('semester_id', $semester->id)
            ->where('student_id', $student->id)
            ->first();

        return SnapshotBuilderResolver::for($class)
            ->build($class, $semester, $student, $this->nextVersion($class, $semester), $this->settings->now(), $entry);
    }

    /**
     * The version the next publication of this class+semester would get.
     */
    public function nextVersion(SchoolClass $class, Semester $semester): int
    {
        return (int) $class->reportCardPublications()
            ->where('semester_id', $semester->id)
            ->max('version') + 1;
    }
}
