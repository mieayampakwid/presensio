<?php

namespace Tests\Feature\ReportCards;

use App\Jobs\BuildClassReportCardPdf;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCards\ReportCardPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardBundleTest extends TestCase
{
    use RefreshDatabase;

    private User $homeroomUser;

    private User $otherTeacherUser;

    private User $adminUser;

    private User $principalUser;

    private AcademicYear $academicYear;

    private Semester $semester;

    private SchoolClass $schoolClass;

    private ClassSubject $mathCourse;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
        ]);
        $this->semester = $this->academicYear->semesters()->where('number', 1)->firstOrFail();

        $this->homeroomUser = User::factory()->teacher()->create();
        $this->otherTeacherUser = User::factory()->teacher()->create();
        $this->adminUser = User::factory()->admin()->create();
        $this->principalUser = User::factory()->principal()->create();

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => Teacher::factory()->create(['user_id' => $this->homeroomUser->id, 'name' => 'Pak Dedi'])->id,
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

        foreach (['Budi Santoso', 'Siti Aminah'] as $name) {
            $student = Student::factory()->create(['full_name' => $name]);
            Enrollment::create([
                'student_id' => $student->id,
                'class_id' => $this->schoolClass->id,
                'started_on' => '2026-07-01',
                'ended_on' => null,
            ]);
            CourseSemester::forCourse($this->mathCourse, $this->semester)->subjectResults()->create([
                'student_id' => $student->id,
                'final_score' => 85,
                'description' => 'Sangat baik',
            ]);
        }
    }

    public function test_job_renders_every_current_publication_student_into_private_bundle(): void
    {
        $publication = $this->publish();

        $this->runBundleJob();

        $path = 'report-card-bundles/'.$publication->id.'-v'.$publication->version.'.pdf';
        Storage::disk('local')->assertExists($path);

        $pdf = (string) Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF', $pdf);
        // Two students → two pages in the combined document.
        $this->assertSame(
            2,
            substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages'),
        );
    }

    public function test_republish_writes_bundle_keyed_by_new_version_and_keeps_old_file(): void
    {
        $v1 = $this->publish();
        $this->runBundleJob();

        $this->actingAs($this->homeroomUser);
        $publisher = app(ReportCardPublisher::class);
        $publisher->retract($this->schoolClass, $this->semester, 'Perbaikan');
        $publisher->publish($this->schoolClass, $this->semester);

        $v2 = ReportCardPublication::query()->where('version', 2)->firstOrFail();
        $this->runBundleJob();

        $v1Path = 'report-card-bundles/'.$v1->id.'-v1.pdf';
        $v2Path = 'report-card-bundles/'.$v2->id.'-v2.pdf';

        $this->assertNotSame($v1Path, $v2Path);
        Storage::disk('local')->assertExists($v1Path);
        Storage::disk('local')->assertExists($v2Path);
    }

    public function test_store_authorizes_homeroom_and_admin_and_requires_current_publication(): void
    {
        // Without a current publication there is nothing to bundle.
        $res = $this->actingAs($this->homeroomUser)
            ->postJson(route('classes.report-cards.bundle.store', $this->schoolClass), [
                'semester_id' => $this->semester->id,
            ]);
        $res->assertStatus(422);

        $this->publish();

        foreach ([$this->homeroomUser, $this->adminUser] as $actor) {
            $res = $this->actingAs($actor)
                ->postJson(route('classes.report-cards.bundle.store', $this->schoolClass), [
                    'semester_id' => $this->semester->id,
                ]);

            $res->assertStatus(202);
        }

        foreach ([$this->principalUser, $this->otherTeacherUser] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('classes.report-cards.bundle.store', $this->schoolClass), [
                    'semester_id' => $this->semester->id,
                ])
                ->assertStatus(403);
        }
    }

    public function test_download_authorizes_homeroom_and_admin_but_not_principal_or_other_teachers(): void
    {
        $this->publish();
        $this->runBundleJob();

        foreach ([$this->homeroomUser, $this->adminUser] as $actor) {
            $res = $this->actingAs($actor)
                ->get(route('classes.report-cards.bundle.download', array_merge([$this->schoolClass], ['semester' => $this->semester->id])));

            $res->assertOk();
            $res->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', (string) $res->getContent());
        }

        foreach ([$this->principalUser, $this->otherTeacherUser] as $actor) {
            $this->actingAs($actor)
                ->get(route('classes.report-cards.bundle.download', array_merge([$this->schoolClass], ['semester' => $this->semester->id])))
                ->assertStatus(403);
        }
    }

    public function test_download_is_not_available_before_the_bundle_is_built(): void
    {
        $this->publish();

        $this->actingAs($this->homeroomUser)
            ->get(route('classes.report-cards.bundle.download', array_merge([$this->schoolClass], ['semester' => $this->semester->id])))
            ->assertStatus(404);
    }

    /**
     * Publish the semester as the homeroom teacher and return the publication.
     */
    private function publish(): ReportCardPublication
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->publish($this->schoolClass, $this->semester);

        return ReportCardPublication::query()->current()->firstOrFail();
    }

    private function runBundleJob(): void
    {
        app()->call([new BuildClassReportCardPdf($this->schoolClass, $this->semester), 'handle']);
    }
}
