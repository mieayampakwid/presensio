<?php

namespace Tests\Feature\ReportCards;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\ReportCardEntry;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCards\ReportCardPdf;
use App\Services\ReportCards\ReportCardPublisher;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $homeroomUser;

    private User $subjectTeacherUser;

    private User $otherTeacherUser;

    private User $adminUser;

    private User $principalUser;

    private AcademicYear $academicYear;

    private Semester $semester;

    private Semester $semester2;

    private SchoolClass $schoolClass;

    private ClassSubject $mathCourse;

    private ClassSubject $ipasCourse;

    private Student $budi;

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

        $this->homeroomUser = User::factory()->teacher()->create();
        $this->subjectTeacherUser = User::factory()->teacher()->create();
        $this->otherTeacherUser = User::factory()->teacher()->create();
        $this->adminUser = User::factory()->admin()->create();
        $this->principalUser = User::factory()->principal()->create();

        $homeroomTeacher = Teacher::factory()->create(['user_id' => $this->homeroomUser->id, 'name' => 'Pak Dedi']);
        $subjectTeacher = Teacher::factory()->create(['user_id' => $this->subjectTeacherUser->id, 'name' => 'Pak Guru']);

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => $homeroomTeacher->id,
        ]);

        $this->mathCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika', 'sort_order' => 1])->id,
            'teacher_id' => $subjectTeacher->id,
            'passing_threshold' => '75.00',
        ]);
        $this->ipasCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'IPAS', 'sort_order' => 2])->id,
            'teacher_id' => $homeroomTeacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->budi = Student::factory()->create([
            'full_name' => 'Budi Santoso',
            'student_number' => '0012345678',
            'address' => 'Jl. Kenanga 9',
        ]);
        Enrollment::create([
            'student_id' => $this->budi->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        foreach ([$this->semester, $this->semester2] as $semester) {
            foreach ([$this->mathCourse, $this->ipasCourse] as $index => $course) {
                CourseSemester::forCourse($course, $semester)->update([
                    'finalized_at' => now(),
                    'finalized_by_user_id' => $this->homeroomUser->id,
                ]);
                CourseSemester::forCourse($course, $semester)->subjectResults()->create([
                    'student_id' => $this->budi->id,
                    'final_score' => 80 + $index,
                    'description' => 'Sangat baik',
                ]);
            }
        }

        Storage::fake('local');
        Storage::disk('local')->put('school/logo.png', 'fake-png-bytes');

        $row = app(SchoolSettings::class)->row();
        $row->update([
            'school_name' => 'SD Nusantara',
            'school_address' => 'Jl. Melati 1',
            'principal_name' => 'Bpk. Suryana',
            'principal_nip' => '19700101 199003 1 001',
            'logo_path' => 'school/logo.png',
        ]);
    }

    public function test_published_pdf_streams_stored_snapshot_with_version_two_footer(): void
    {
        $card = $this->publishedCard($this->semester);
        $this->retractAndRepublish($this->semester);

        $current = ReportCard::query()
            ->where('student_id', $this->budi->id)
            ->orderByDesc('report_card_publication_id')
            ->firstOrFail();

        $res = $this->actingAs($this->homeroomUser)
            ->get(route('classes.report-cards.pdf', array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->budi->id])));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());
        $this->assertNotSame($card->id, $current->id);

        $html = app(ReportCardPdf::class)->html($current->payload);
        $this->assertStringContainsString('Versi 2', $html);
        // Rendered from the stored snapshot only — the v1 card is untouched.
        $this->assertStringContainsString('Versi 1', app(ReportCardPdf::class)->html($card->payload));
    }

    public function test_rendered_html_contains_merdeka_contract_sections(): void
    {
        ReportCardEntry::create([
            'semester_id' => $this->semester->id,
            'student_id' => $this->budi->id,
            'homeroom_notes' => 'Ananda rajin',
            'extracurricular_notes' => 'Pramuka',
        ]);
        $card = $this->publishedCard($this->semester);

        $html = app(ReportCardPdf::class)->html($card->payload);

        // Kop and identity block
        $this->assertStringContainsString('SD Nusantara', $html);
        $this->assertStringContainsString('Jl. Melati 1', $html);
        $this->assertStringContainsString('data:image', $html); // logo embedded content
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('0012345678', $html); // NIS = student_number
        $this->assertStringContainsString('NISN', $html);
        $this->assertStringContainsString('—', $html); // NISN not available
        $this->assertStringContainsString('5A', $html);
        $this->assertStringContainsString('Fase', $html);
        $this->assertStringContainsString('2026/2027', $html);

        // Subject table grouped, with descriptions
        $this->assertStringContainsString('Capaian Kompetensi', $html);
        $this->assertStringContainsString('Matematika', $html);
        $this->assertStringContainsString('IPAS', $html);
        $this->assertStringContainsString('Sangat baik', $html);

        // Attendance, notes
        $this->assertStringContainsString('Sakit', $html);
        $this->assertStringContainsString('Izin', $html);
        $this->assertStringContainsString('Tanpa Keterangan', $html);
        $this->assertStringContainsString('Catatan Wali Kelas', $html);
        $this->assertStringContainsString('Ananda rajin', $html);
        $this->assertStringContainsString('Pramuka', $html);

        // Signature blocks and footer
        $this->assertStringContainsString('Orang Tua/Wali', $html);
        $this->assertStringContainsString('Wali Kelas', $html);
        $this->assertStringContainsString('Pak Dedi', $html);
        $this->assertStringContainsString('Kepala Sekolah', $html);
        $this->assertStringContainsString('Bpk. Suryana', $html);
        $this->assertStringContainsString('19700101 199003 1 001', $html);
        // The signature block is the only place combining place + issue date.
        $this->assertStringContainsString(
            $card->payload['school']['address'].', '.$card->payload['published_at'],
            strip_tags($html),
        );
        $this->assertStringContainsString('Versi 1', $html);

        // No external asset URLs: fonts local, logo inlined.
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
    }

    public function test_decision_section_is_genap_only(): void
    {
        $ganjilHtml = app(ReportCardPdf::class)->html($this->publishedCard($this->semester)->payload);
        $this->assertStringNotContainsString('Keputusan', $ganjilHtml);

        ReportCardEntry::create([
            'semester_id' => $this->semester2->id,
            'student_id' => $this->budi->id,
            'promotion_decision' => 'promoted',
        ]);
        $genapHtml = app(ReportCardPdf::class)->html($this->publishedCard($this->semester2)->payload);
        $this->assertStringContainsString('Keputusan', $genapHtml);
        $this->assertStringContainsString('Naik ke kelas 6', $genapHtml);
    }

    public function test_rendered_html_immutable_after_student_and_principal_changes(): void
    {
        $card = $this->publishedCard($this->semester);
        $htmlBefore = app(ReportCardPdf::class)->html($card->payload);

        $this->budi->update(['address' => 'Jl. Baru 2']);
        app(SchoolSettings::class)->row()->update(['principal_name' => 'Bpk. Baru']);

        $htmlAfter = app(ReportCardPdf::class)->html($card->payload);

        $this->assertSame($htmlBefore, $htmlAfter);
        $this->assertStringNotContainsString('Jl. Baru 2', $htmlAfter);
        $this->assertStringNotContainsString('Bpk. Baru', $htmlAfter);
    }

    public function test_preview_pdf_uses_workspace_payload_without_persisting(): void
    {
        $res = $this->actingAs($this->homeroomUser)
            ->get(route('classes.report-cards.preview-pdf', array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->budi->id])));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());

        $this->assertDatabaseCount('report_card_entries', 0);
        $this->assertDatabaseCount('report_cards', 0);
        $this->assertDatabaseCount('report_card_publications', 0);
    }

    public function test_stored_pdf_requires_current_publication(): void
    {
        $res = $this->actingAs($this->homeroomUser)
            ->get(route('classes.report-cards.pdf', array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->budi->id])));

        $res->assertStatus(403);
    }

    public function test_pdf_endpoints_authorize_viewers_but_not_unrelated_teachers(): void
    {
        $this->publishedCard($this->semester);

        foreach ([$this->homeroomUser, $this->adminUser, $this->principalUser, $this->subjectTeacherUser] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('classes.report-cards.pdf', array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->budi->id])))
                ->assertOk();
            $this->actingAs($viewer)
                ->get(route('classes.report-cards.preview-pdf', array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->budi->id])))
                ->assertOk();
        }

        foreach (['classes.report-cards.pdf', 'classes.report-cards.preview-pdf'] as $route) {
            $this->actingAs($this->otherTeacherUser)
                ->get(route($route, array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->budi->id])))
                ->assertStatus(403);
        }
    }

    /**
     * Publish the semester with the homeroom user and return the student's
     * stored snapshot row.
     */
    private function publishedCard(Semester $semester): ReportCard
    {
        // The publisher records the acting user; publish as the homeroom.
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->publish($this->schoolClass, $semester);

        return ReportCard::query()
            ->where('student_id', $this->budi->id)
            ->whereHas('publication', fn ($query) => $query
                ->where('semester_id', $semester->id)
                ->where('class_id', $this->schoolClass->id))
            ->firstOrFail();
    }

    /**
     * Retract the current version and republish, producing version 2.
     */
    private function retractAndRepublish(Semester $semester): void
    {
        $this->actingAs($this->homeroomUser);
        $publisher = app(ReportCardPublisher::class);
        $publisher->retract($this->schoolClass, $semester, 'Perbaikan nilai');
        $publisher->publish($this->schoolClass, $semester);
    }
}
