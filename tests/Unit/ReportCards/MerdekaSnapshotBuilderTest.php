<?php

namespace Tests\Unit\ReportCards;

use App\Enums\SubjectGroup;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\ReportCardEntry;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\ReportCards\MerdekaSnapshotBuilder;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerdekaSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private Semester $semester;

    private SchoolClass $class;

    private User $homeroomUser;

    private Teacher $homeroom;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-11-15 08:00:00');

        $this->year = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
        ]);
        $this->semester = $this->year->semesters()->where('number', 1)->firstOrFail();
        $this->semester->update(['starts_at' => '2026-07-01', 'ends_at' => '2026-12-19']);

        $this->homeroomUser = User::factory()->teacher()->create();
        $this->homeroom = Teacher::factory()->forUser($this->homeroomUser)->create(['name' => 'Pak Dedi']);
        $this->class = SchoolClass::factory()->withTeacher($this->homeroom)->create([
            'academic_year_id' => $this->year->id,
            'grade_level' => 5,
            'name' => '5A',
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * The as-of date is min(today, semester.ends_at) = 2026-11-15 in these tests.
     */
    public function test_roster_enrolls_students_as_of_date_inclusive_of_ended_on(): void
    {
        $active = Student::factory()->create();
        $endedOnAsOf = Student::factory()->create();
        $endedBefore = Student::factory()->create();
        $startedLater = Student::factory()->create();

        Enrollment::create(['student_id' => $active->id, 'class_id' => $this->class->id, 'started_on' => '2026-07-01', 'ended_on' => null]);
        Enrollment::create(['student_id' => $endedOnAsOf->id, 'class_id' => $this->class->id, 'started_on' => '2026-07-01', 'ended_on' => '2026-11-15']);
        Enrollment::create(['student_id' => $endedBefore->id, 'class_id' => $this->class->id, 'started_on' => '2026-07-01', 'ended_on' => '2026-11-14']);
        Enrollment::create(['student_id' => $startedLater->id, 'class_id' => $this->class->id, 'started_on' => '2026-11-16', 'ended_on' => null]);

        $rosterIds = app(MerdekaSnapshotBuilder::class)->roster($this->class, $this->semester)->pluck('id');

        $this->assertContains($active->id, $rosterIds);
        $this->assertContains($endedOnAsOf->id, $rosterIds);
        $this->assertNotContains($endedBefore->id, $rosterIds);
        $this->assertNotContains($startedLater->id, $rosterIds);
    }

    public function test_subject_rows_ordered_by_group_then_sort_order_with_results(): void
    {
        $student = Student::factory()->create();
        Enrollment::create(['student_id' => $student->id, 'class_id' => $this->class->id, 'started_on' => '2026-07-01', 'ended_on' => null]);

        $local = Subject::factory()->create(['name' => 'Batak', 'group' => SubjectGroup::LocalContent, 'sort_order' => 1]);
        $general2 = Subject::factory()->create(['name' => 'IPAS', 'group' => SubjectGroup::General, 'sort_order' => 2]);
        $general1 = Subject::factory()->create(['name' => 'Matematika', 'group' => SubjectGroup::General, 'sort_order' => 1]);

        $this->finalizeCourse($this->class, $general1, 90, 'Sangat baik di matematika', $student);
        $this->finalizeCourse($this->class, $general2, 80, 'Baik di IPAS', $student);
        $this->finalizeCourse($this->class, $local, 70, 'Cukup di Batak', $student);

        $payload = $this->buildFor($student, $this->class);

        $this->assertSame(
            ['Matematika', 'IPAS', 'Batak'],
            collect($payload['subjects'])->pluck('name')->all(),
        );
        $this->assertSame(
            [90, 80, 70],
            collect($payload['subjects'])->pluck('final_score')->all(),
        );
        $this->assertSame(
            ['Sangat baik di matematika', 'Baik di IPAS', 'Cukup di Batak'],
            collect($payload['subjects'])->pluck('description')->all(),
        );
        $this->assertTrue($payload['complete']);
    }

    public function test_transfer_in_prefers_current_class_result_over_previous_class(): void
    {
        [$student, $classB] = $this->transferredStudent();

        $subject = Subject::factory()->create(['name' => 'Matematika', 'group' => SubjectGroup::General, 'sort_order' => 1]);
        $classC = SchoolClass::factory()->create([
            'academic_year_id' => $this->year->id,
            'grade_level' => 5,
            'name' => '5C',
        ]);

        // Current class 5B has the student's own finalized result.
        $this->finalizeCourse($classB, $subject, 88, 'Baik di kelas 5B', $student);
        // Previous class 5A also has a finalized result for the same subject.
        $this->finalizeCourse($this->class, $subject, 75, 'Draf 5A', $student);
        // Unrelated class 5C result must never leak in.
        $this->finalizeCourse($classC, $subject, 70, 'Kelas 5C', $student);

        $row = collect($this->buildFor($student, $classB)['subjects'])->firstWhere('name', 'Matematika');

        $this->assertSame(88, $row['final_score']);
        $this->assertSame('Baik di kelas 5B', $row['description']);
        $this->assertNull($row['from_class']);
    }

    public function test_transfer_in_falls_back_to_finalized_result_of_previous_class_same_subject(): void
    {
        [$student, $classB] = $this->transferredStudent();

        $subject = Subject::factory()->create(['name' => 'Matematika', 'group' => SubjectGroup::General, 'sort_order' => 1]);
        $classC = SchoolClass::factory()->create([
            'academic_year_id' => $this->year->id,
            'grade_level' => 5,
            'name' => '5C',
        ]);

        // 5B course is finalized but has no result for this student.
        $this->finalizeCourse($classB, $subject, null, null);
        // Previous class 5A has the finalized result to fall back to.
        $this->finalizeCourse($this->class, $subject, 85, 'Baik di matematika', $student);
        // Unrelated class 5C must never be used as fallback.
        $this->finalizeCourse($classC, $subject, 70, 'Kelas 5C', $student);

        $row = collect($this->buildFor($student, $classB)['subjects'])->firstWhere('name', 'Matematika');

        $this->assertSame(85, $row['final_score']);
        $this->assertSame('Baik di matematika', $row['description']);
        $this->assertSame('5A', $row['from_class']);
    }

    public function test_missing_result_leaves_row_empty_and_flags_student_incomplete(): void
    {
        $student = Student::factory()->create();
        Enrollment::create(['student_id' => $student->id, 'class_id' => $this->class->id, 'started_on' => '2026-07-01', 'ended_on' => null]);

        $present = Subject::factory()->create(['name' => 'Matematika', 'group' => SubjectGroup::General, 'sort_order' => 1]);
        $missing = Subject::factory()->create(['name' => 'Bahasa Indonesia', 'group' => SubjectGroup::General, 'sort_order' => 2]);

        $this->finalizeCourse($this->class, $present, 90, 'Sangat baik', $student);
        $this->finalizeCourse($this->class, $missing, null, null);

        $payload = $this->buildFor($student, $this->class);

        $row = collect($payload['subjects'])->firstWhere('name', 'Bahasa Indonesia');
        $this->assertNull($row['final_score']);
        $this->assertNull($row['description']);
        $this->assertNull($row['from_class']);
        $this->assertFalse($payload['complete']);
    }

    public function test_attendance_counts_semester_to_as_of_across_enrollments_excluding_late(): void
    {
        [$student] = $this->transferredStudent();

        foreach ([
            ['2026-06-30', 'sick'], // before the semester
            ['2026-08-10', 'sick'],
            ['2026-08-11', 'sick'],
            ['2026-09-02', 'leave'],
            ['2026-09-15', 'late'], // late counts as present, not shown
            ['2026-10-05', 'absent'],
            ['2026-11-15', 'sick'], // as-of date inclusive
            ['2026-11-16', 'absent'], // after the as-of date
        ] as [$date, $status]) {
            Attendance::factory()->create(['student_id' => $student->id, 'date' => $date, 'status' => $status]);
        }

        $payload = $this->buildFor($student, $this->class);

        $this->assertSame(['sick' => 3, 'leave' => 1, 'absent' => 1], $payload['attendance']);
    }

    public function test_payload_embeds_school_profile_teacher_identity_version_and_date_as_copies(): void
    {
        Storage::fake('local');
        $logoBytes = random_bytes(64);
        Storage::disk('local')->put('school/logo.png', $logoBytes);

        $row = app(SchoolSettings::class)->row();
        $row->update([
            'school_name' => 'SD Nusantara',
            'npsn' => '12345678',
            'school_address' => 'Jl. Melati 1',
            'principal_name' => 'Bpk. Suryana',
            'principal_nip' => '19700101 199003 1 001',
            'logo_path' => 'school/logo.png',
        ]);

        $student = Student::factory()->create(['full_name' => 'Budi Santoso', 'address' => 'Jl. Kenanga 9']);

        $payload = app(MerdekaSnapshotBuilder::class)->build($this->class, $this->semester, $student, 2, Date::parse('2026-12-01'));

        $this->assertSame('SD Nusantara', $payload['school']['name']);
        $this->assertSame('12345678', $payload['school']['npsn']);
        $this->assertSame('Jl. Melati 1', $payload['school']['address']);
        $this->assertSame('school/logo.png', $payload['school']['logo']['path']);
        $this->assertSame(base64_encode($logoBytes), $payload['school']['logo']['content_base64']);
        $this->assertSame('Bpk. Suryana', $payload['principal']['name']);
        $this->assertSame('19700101 199003 1 001', $payload['principal']['nip']);
        $this->assertSame('Pak Dedi', $payload['homeroom_teacher']);
        $this->assertSame('5A', $payload['class']['name']);
        $this->assertSame('C', $payload['class']['phase']);
        $this->assertSame('2026/2027', $payload['semester']['academic_year']);
        $this->assertSame('Budi Santoso', $payload['student']['name']);
        $this->assertSame('Jl. Kenanga 9', $payload['student']['address']);
        $this->assertSame(2, $payload['version']);
        $this->assertSame('2026-12-01', $payload['published_at']);

        // AC-11-04: data changing after the build never alters the payload copies.
        $student->update(['address' => 'Jl. Baru 2']);
        $row->update(['principal_name' => 'Bpk. Baru', 'logo_path' => 'school/new-logo.png']);
        Storage::disk('local')->put('school/new-logo.png', 'new-bytes');

        $this->assertSame('Jl. Kenanga 9', $payload['student']['address']);
        $this->assertSame('Bpk. Suryana', $payload['principal']['name']);
        $this->assertSame('school/logo.png', $payload['school']['logo']['path']);
        $this->assertSame(base64_encode($logoBytes), $payload['school']['logo']['content_base64']);
    }

    public function test_notes_and_semester_two_decision_are_carried_into_payload(): void
    {
        $semester2 = $this->year->semesters()->where('number', 2)->firstOrFail();
        $student = Student::factory()->create();

        $entry = ReportCardEntry::create([
            'semester_id' => $semester2->id,
            'student_id' => $student->id,
            'homeroom_notes' => 'Ananda rajin',
            'extracurricular_notes' => 'Pramuka',
            'promotion_decision' => 'promoted',
        ]);

        $payload = app(MerdekaSnapshotBuilder::class)->build($this->class, $semester2, $student, 1, Date::parse('2026-06-20'), $entry);

        $this->assertSame(['homeroom' => 'Ananda rajin', 'extracurricular' => 'Pramuka'], $payload['notes']);
        $this->assertSame(['value' => 'promoted', 'label' => 'Naik Kelas'], $payload['decision']);
    }

    /**
     * A student enrolled in 5A on 2026-07-01 who transferred to a fresh 5B on
     * 2026-10-01. Returns the student and their current class.
     *
     * @return array{0: Student, 1: SchoolClass}
     */
    private function transferredStudent(): array
    {
        $student = Student::factory()->create();
        $classB = SchoolClass::factory()->create([
            'academic_year_id' => $this->year->id,
            'grade_level' => 5,
            'name' => '5B',
        ]);

        Enrollment::create(['student_id' => $student->id, 'class_id' => $this->class->id, 'started_on' => '2026-07-01', 'ended_on' => '2026-09-30']);
        Enrollment::create(['student_id' => $student->id, 'class_id' => $classB->id, 'started_on' => '2026-10-01', 'ended_on' => null]);

        return [$student, $classB];
    }

    /**
     * Finalize the course for the given subject in the semester, optionally
     * creating the student's frozen subject result.
     */
    private function finalizeCourse(SchoolClass $class, Subject $subject, ?int $score, ?string $description, ?Student $forStudent = null): void
    {
        $classSubject = $class->classSubjects()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->class->teacher_id,
            'passing_threshold' => '75.00',
        ]);

        $courseSemester = CourseSemester::forCourse($classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->homeroomUser->id,
        ]);

        if ($forStudent !== null) {
            $courseSemester->subjectResults()->create([
                'student_id' => $forStudent->id,
                'final_score' => $score,
                'description' => $description,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFor(Student $student, SchoolClass $class): array
    {
        return app(MerdekaSnapshotBuilder::class)->build($class, $this->semester, $student, 1, Date::parse('2026-12-01'));
    }
}
