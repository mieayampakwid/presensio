<?php

namespace Tests\Feature\Parent;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCards\ReportCardPublisher;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChildReportCardTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_PUBLISHED_MESSAGE = 'Rapor belum diterbitkan untuk periode ini.';

    private User $guardianUser;

    private Guardian $guardian;

    private User $otherGuardianUser;

    private Guardian $otherGuardian;

    private User $homeroomUser;

    private Student $child;

    private AcademicYear $academicYear;

    private Semester $semester;

    private Semester $semester2;

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
        $this->semester2 = $this->academicYear->semesters()->where('number', 2)->firstOrFail();

        $this->guardianUser = User::factory()->parent()->create();
        $this->guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id]);

        $this->otherGuardianUser = User::factory()->parent()->create();
        $this->otherGuardian = Guardian::factory()->create(['user_id' => $this->otherGuardianUser->id]);

        $this->homeroomUser = User::factory()->teacher()->create();

        $this->child = Student::factory()->create(['full_name' => 'Rian Hidayat']);
        $this->guardian->students()->attach($this->child->id, ['relationship_type' => 'father']);

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => Teacher::factory()->create(['user_id' => $this->homeroomUser->id])->id,
        ]);

        Enrollment::create([
            'student_id' => $this->child->id,
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

        foreach ([$this->semester, $this->semester2] as $semester) {
            CourseSemester::forCourse($this->mathCourse, $semester)->update([
                'finalized_at' => now(),
                'finalized_by_user_id' => $this->homeroomUser->id,
            ]);
            CourseSemester::forCourse($this->mathCourse, $semester)->subjectResults()->create([
                'student_id' => $this->child->id,
                'final_score' => 88,
                'description' => 'Sangat baik',
            ]);
        }

        app(SchoolSettings::class)->row()->update(['school_name' => 'SD Nusantara']);
    }

    public function test_archive_visible_after_publish_hidden_after_retract_restored_after_republish(): void
    {
        // Before any publication both the page and the PDF are forbidden
        // with the exact spec message.
        $this->index($this->guardianUser)->assertStatus(403);
        $this->pdf($this->guardianUser)->assertStatus(403);

        $this->publish($this->semester);

        $this->index($this->guardianUser)->assertInertia(fn (Assert $page) => $page
            ->where('semesters.0.id', $this->semester->id)
            ->where('semesters.0.version', 1));

        $this->retract();

        $this->index($this->guardianUser)->assertStatus(403);
        $this->pdf($this->guardianUser)
            ->assertStatus(403)
            ->assertSeeText(self::NOT_PUBLISHED_MESSAGE);

        // Republishing restores the archive as version 2.
        $this->publish($this->semester);

        $this->index($this->guardianUser)->assertInertia(fn (Assert $page) => $page
            ->where('semesters.0.version', 2));
    }

    public function test_archive_lists_only_semesters_with_current_publication(): void
    {
        $this->publish($this->semester);

        $this->index($this->guardianUser)->assertInertia(fn (Assert $page) => $page
            ->has('semesters', 1)
            ->where('semesters.0.id', $this->semester->id));
    }

    public function test_unlinked_guardian_is_forbidden(): void
    {
        $this->publish($this->semester);

        foreach (['children.report-cards.index', 'children.report-cards.pdf'] as $route) {
            $this->actingAs($this->otherGuardianUser)
                ->get(route($route, array_merge([$this->child], $route === 'children.report-cards.pdf' ? ['semester' => $this->semester->id] : [])))
                ->assertStatus(403);
        }
    }

    public function test_pdf_streams_stored_snapshot_ignoring_live_changes(): void
    {
        $this->publish($this->semester);

        $payloadBefore = ReportCard::query()->where('student_id', $this->child->id)->firstOrFail()->payload;

        // Live data changes after publication.
        $this->child->update(['address' => 'Jl. Baru 2']);
        app(SchoolSettings::class)->row()->update(['principal_name' => 'Bpk. Baru']);
        CourseSemester::forCourse($this->mathCourse, $this->semester)->subjectResults()->update(['final_score' => 30]);

        $res = $this->pdf($this->guardianUser);

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());

        // The archived snapshot was never altered by the live changes.
        $this->assertSame($payloadBefore, ReportCard::query()->where('student_id', $this->child->id)->firstOrFail()->payload);
        $this->assertSame(88, $payloadBefore['subjects'][0]['final_score']);
    }

    public function test_archive_issue_date_is_frozen_school_business_date(): void
    {
        // 18:30 UTC = 01:30 next day in Asia/Jakarta: the archive label must
        // show the frozen snapshot business date, not the UTC day.
        Date::setTestNow('2026-12-20 18:30:00');

        try {
            $this->publish($this->semester);

            $this->index($this->guardianUser)->assertInertia(fn (Assert $page) => $page
                ->where('semesters.0.published_at', '2026-12-21'));
        } finally {
            Date::setTestNow();
        }
    }

    private function index(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('children.report-cards.index', $this->child));
    }

    private function pdf(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('children.report-cards.pdf', [$this->child, 'semester' => $this->semester->id]));
    }

    private function publish(Semester $semester): void
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->publish($this->schoolClass, $semester);
    }

    private function retract(): void
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->retract($this->schoolClass, $this->semester, 'Perbaikan');
    }
}
