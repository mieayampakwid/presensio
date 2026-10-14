<?php

namespace App\Services\ReportCards;

use App\Enums\Curriculum;
use App\Models\CourseSemester;
use App\Models\ReportCard;
use App\Models\ReportCardEntry;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\ReportCards\SnapshotBuilderResolver;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportCardPublisher
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Publish the class's report cards for the semester: one new immutable
     * version plus one snapshot row per roster student, audited — all or
     * nothing.
     *
     * @return array{version: int, warnings: list<array{student: string}>}
     *
     * @throws ValidationException listing what blocks publication
     */
    public function publish(SchoolClass $class, Semester $semester): array
    {
        $builder = SnapshotBuilderResolver::for($class);
        $roster = $builder->roster($class, $semester);

        $this->assertPublishable($class, $semester, $roster);

        $entries = ReportCardEntry::query()
            ->where('semester_id', $semester->id)
            ->whereIn('student_id', $roster->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $publishedAt = $this->settings->now();

        return DB::transaction(function () use ($class, $semester, $roster, $entries, $builder, $publishedAt) {
            // Serialize writers per class: an empty publication set locks
            // nothing, so the parent class row is the lock anchor (conservative
            // across the class's semesters). All precondition reads and the
            // current/max checks happen after acquiring it.
            SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();

            $this->assertPublishable($class, $semester, $roster);

            $existing = ReportCardPublication::query()
                ->where('semester_id', $semester->id)
                ->where('class_id', $class->id)
                ->lockForUpdate()
                ->get();

            if ($existing->whereNull('retracted_at')->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'publication' => ['Sudah ada versi rapor yang terbit dan belum ditarik untuk kelas ini.'],
                ]);
            }

            $version = (int) $existing->max('version') + 1;
            $warnings = [];

            $publication = ReportCardPublication::create([
                'semester_id' => $semester->id,
                'class_id' => $class->id,
                'version' => $version,
                'published_at' => $publishedAt,
                'published_by_user_id' => auth()->id(),
            ]);

            foreach ($roster as $student) {
                $payload = $builder->build($class, $semester, $student, $version, $publishedAt, $entries->get($student->id));

                if ($payload['complete'] === false) {
                    throw ValidationException::withMessages([
                        'missing_results' => [['student' => $student->full_name]],
                    ]);
                }

                if ($payload['notes']['homeroom'] === null || $payload['notes']['extracurricular'] === null) {
                    $warnings[] = ['student' => $student->full_name];
                }

                $curriculum = $class->curriculum instanceof Curriculum
                    ? $class->curriculum
                    : Curriculum::from((string) $class->curriculum);

                ReportCard::create([
                    'report_card_publication_id' => $publication->id,
                    'student_id' => $student->id,
                    'curriculum' => $curriculum->value,
                    'payload' => $payload,
                ]);
            }

            $this->auditLogger->record($publication, 'published', null, [
                'semester_id' => $semester->id,
                'class_id' => $class->id,
                'version' => $version,
                'students' => $roster->count(),
            ]);

            return ['version' => $version, 'warnings' => $warnings];
        });
    }

    /**
     * Retract the current version with a reason, audited.
     *
     * @throws ValidationException when nothing is current
     */
    public function retract(SchoolClass $class, Semester $semester, string $reason): void
    {
        DB::transaction(function () use ($class, $semester, $reason) {
            // Same lock anchor and order as publish to avoid races/deadlocks.
            SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();

            /** @var ReportCardPublication|null $publication */
            $publication = ReportCardPublication::query()
                ->where('semester_id', $semester->id)
                ->where('class_id', $class->id)
                ->current()
                ->lockForUpdate()
                ->first();

            if ($publication === null) {
                throw ValidationException::withMessages([
                    'publication' => ['Tidak ada versi rapor yang terbit untuk kelas ini.'],
                ]);
            }

            $publication->update([
                'retracted_at' => $this->settings->now(),
                'retracted_by_user_id' => auth()->id(),
                'retract_reason' => $reason,
            ]);

            $this->auditLogger->record($publication, 'retracted', null, [
                'retracted_at' => $publication->retracted_at->toIso8601String(),
            ], $reason);
        });
    }

    /**
     * The current (non-retracted) publication summary for the workspace.
     *
     * @return array{version: int, published_at: string}|null
     */
    public function currentPublication(SchoolClass $class, Semester $semester): ?array
    {
        /** @var ReportCardPublication|null $publication */
        $publication = ReportCardPublication::query()
            ->where('semester_id', $semester->id)
            ->where('class_id', $class->id)
            ->current()
            ->first();

        return $publication === null ? null : [
            'version' => $publication->version,
            'published_at' => $publication->published_at->toDateString(),
        ];
    }

    /**
     * Preconditions evaluated inside the publish transaction (after the class
     * lock) so gating never uses stale data: every course finalized (named
     * subject + teacher), and — in semester 2 — a decision for every roster
     * student.
     *
     * @param  Collection<int, Student>  $roster
     *
     * @throws ValidationException
     */
    private function assertPublishable(SchoolClass $class, Semester $semester, Collection $roster): void
    {
        $unfinalized = $class->classSubjects()
            ->with(['subject', 'teacher.employee'])
            ->get()
            ->filter(fn ($classSubject) => ! CourseSemester::forCourse($classSubject, $semester)->isFinalized())
            ->map(fn ($classSubject) => [
                'subject' => $classSubject->subject->name,
                'teacher' => $classSubject->teacher?->employee?->name,
            ])
            ->values();

        if ($unfinalized->isNotEmpty()) {
            throw ValidationException::withMessages([
                'unfinalized_subjects' => $unfinalized->all(),
            ]);
        }

        if ($semester->number === 2) {
            $missingDecisions = $roster
                ->filter(fn (Student $student) => ! ReportCardEntry::query()
                    ->where('semester_id', $semester->id)
                    ->where('student_id', $student->id)
                    ->whereNotNull('promotion_decision')
                    ->exists())
                ->map(fn (Student $student) => ['student' => $student->full_name])
                ->values();

            if ($missingDecisions->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'missing_decisions' => $missingDecisions->all(),
                ]);
            }
        }
    }
}
