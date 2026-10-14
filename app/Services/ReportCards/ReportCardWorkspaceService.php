<?php

namespace App\Services\ReportCards;

use App\Models\CourseSemester;
use App\Models\ReportCardEntry;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\ReportCards\SnapshotBuilderResolver;
use App\Services\SchoolSettings;

class ReportCardWorkspaceService
{
    public function __construct(
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Finalization checklist: one row per class subject with the course's
     * finalization state for the semester.
     *
     * @return list<array{subject: string, teacher: string|null, finalized: bool}>
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
     * @return list<array<string, mixed>>
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
                $withResults = collect($payload['subjects'])
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
