<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCards\ReportCardPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportCardTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_PUBLISHED_MESSAGE = 'Rapor belum diterbitkan untuk periode ini.';

    private User $studentUser;

    private Student $student;

    private User $homeroomUser;

    private AcademicYear $academicYear;

    private Semester $semester;

    private SchoolClass $schoolClass;

    private ClassSubject $mathCourse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
        ]);
        $this->semester = $this->academicYear->semesters()->where('number', 1)->firstOrFail();

        $this->studentUser = User::factory()->student()->create();
        $this->student = Student::factory()->create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Doni Siregar',
        ]);

        $this->homeroomUser = User::factory()->teacher()->create();

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => Teacher::factory()->create(['user_id' => $this->homeroomUser->id])->id,
        ]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $this->mathCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika', 'sort_order' => 1])->id,
            'teacher_id' => $this->schoolClass->teacher_id,
            'passing_threshold' => '75.00',
        ]);

        CourseSemester::forCourse($this->mathCourse, $this->semester)->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->homeroomUser->id,
        ]);
        CourseSemester::forCourse($this->mathCourse, $this->semester)->subjectResults()->create([
            'student_id' => $this->student->id,
            'final_score' => 90,
            'description' => 'Sangat baik',
        ]);
    }

    public function test_archive_lists_published_semester_and_streams_pdf(): void
    {
        $this->publish();

        $this->actingAs($this->studentUser)
            ->get(route('my-report-cards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('semesters.0.id', $this->semester->id)
                ->where('semesters.0.version', 1));

        $res = $this->actingAs($this->studentUser)
            ->get(route('my-report-cards.pdf', ['semester' => $this->semester->id]));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());
    }

    public function test_report_card_hidden_before_publish_and_after_retract_until_republish(): void
    {
        $this->index()->assertStatus(403);
        $this->pdf()
            ->assertStatus(403)
            ->assertSeeText(self::NOT_PUBLISHED_MESSAGE);

        $this->publish();
        $this->retract();

        $this->index()->assertStatus(403);
        $this->pdf()->assertStatus(403);

        $this->publish();

        $this->index()->assertInertia(fn (Assert $page) => $page
            ->where('semesters.0.version', 2));
    }

    public function test_user_without_student_profile_is_forbidden(): void
    {
        $this->publish();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('my-report-cards.index'))
            ->assertStatus(403);

        $this->actingAs(User::factory()->student()->create())
            ->get(route('my-report-cards.pdf', ['semester' => $this->semester->id]))
            ->assertStatus(403);
    }

    private function index(): TestResponse
    {
        return $this->actingAs($this->studentUser)->get(route('my-report-cards.index'));
    }

    private function pdf(): TestResponse
    {
        return $this->actingAs($this->studentUser)->get(route('my-report-cards.pdf', ['semester' => $this->semester->id]));
    }

    private function publish(): void
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->publish($this->schoolClass, $this->semester);
    }

    private function retract(): void
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->retract($this->schoolClass, $this->semester, 'Perbaikan');
    }
}
