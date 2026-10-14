<?php

namespace Tests\Feature\Teacher;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\ReportCardEntry;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportCardWorkspaceTest extends TestCase
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

    private ClassSubject $scienceCourse;

    private Student $student;

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

        $homeroomTeacher = Teacher::factory()->create(['user_id' => $this->homeroomUser->id]);
        $subjectTeacher = Teacher::factory()->create(['user_id' => $this->subjectTeacherUser->id]);

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
        $this->scienceCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'IPAS', 'sort_order' => 2])->id,
            'teacher_id' => $homeroomTeacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->student = Student::factory()->create(['full_name' => 'Budi Santoso']);
        Enrollment::create([
            'student_id' => $this->student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
    }

    public function test_show_lists_finalization_checklist_and_student_completeness(): void
    {
        $this->finalizeCourse($this->mathCourse, $this->student, 88);

        $res = $this->actingAs($this->homeroomUser)
            ->get(route('classes.report-cards.show', array_merge([$this->schoolClass], ['semester' => $this->semester->id])));

        $res->assertInertia(fn (Assert $page) => $page
            ->component('teacher/report-cards')
            ->has('checklist', 2)
            ->where('checklist.0.subject', 'Matematika')
            ->where('checklist.0.finalized', true)
            ->where('checklist.1.subject', 'IPAS')
            ->where('checklist.1.finalized', false)
            ->has('students', 1)
            ->where('students.0.id', $this->student->id)
            ->where('students.0.subjects_total', 2)
            ->where('students.0.subjects_with_results', 1)
            ->where('students.0.complete', false)
            ->where('students.0.entries.homeroom_notes', null));
    }

    public function test_homeroom_teacher_and_admin_can_edit_entry_within_limits(): void
    {
        foreach ([$this->homeroomUser, $this->adminUser] as $editor) {
            $res = $this->actingAs($editor)
                ->put(route('classes.report-cards.entries.update', [$this->schoolClass, $this->student]), [
                    'semester_id' => $this->semester->id,
                    'homeroom_notes' => str_repeat('a', 1000),
                    'extracurricular_notes' => str_repeat('b', 500),
                ]);

            $res->assertRedirect();
        }

        $this->assertDatabaseHas('report_card_entries', [
            'semester_id' => $this->semester->id,
            'student_id' => $this->student->id,
            'homeroom_notes' => str_repeat('a', 1000),
            'extracurricular_notes' => str_repeat('b', 500),
            'promotion_decision' => null,
        ]);
    }

    public function test_entry_update_rejects_over_limit_notes_and_semester_one_decision(): void
    {
        $cases = [
            ['homeroom_notes' => str_repeat('a', 1001)],
            ['extracurricular_notes' => str_repeat('b', 501)],
            ['promotion_decision' => 'promoted'],
        ];

        foreach ($cases as $payload) {
            $res = $this->actingAs($this->homeroomUser)
                ->putJson(route('classes.report-cards.entries.update', [$this->schoolClass, $this->student]), array_merge([
                    'semester_id' => $this->semester->id,
                ], $payload));

            $res->assertStatus(422);
        }

        $this->assertDatabaseCount('report_card_entries', 0);
    }

    public function test_promotion_decision_accepted_in_semester_two_only(): void
    {
        $res = $this->actingAs($this->homeroomUser)
            ->put(route('classes.report-cards.entries.update', [$this->schoolClass, $this->student]), [
                'semester_id' => $this->semester2->id,
                'promotion_decision' => 'promoted',
            ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('report_card_entries', [
            'semester_id' => $this->semester2->id,
            'student_id' => $this->student->id,
            'promotion_decision' => 'promoted',
        ]);
    }

    public function test_entry_scoped_to_requested_semester(): void
    {
        ReportCardEntry::create([
            'semester_id' => $this->semester->id,
            'student_id' => $this->student->id,
            'homeroom_notes' => 'Catatan ganjil',
        ]);

        $res = $this->actingAs($this->homeroomUser)
            ->get(route('classes.report-cards.show', array_merge([$this->schoolClass], ['semester' => $this->semester2->id])));

        $res->assertInertia(fn (Assert $page) => $page
            ->where('students.0.entries.homeroom_notes', null));
    }

    public function test_subject_teacher_and_principal_view_drafts_but_cannot_edit(): void
    {
        foreach ([$this->subjectTeacherUser, $this->principalUser] as $viewer) {
            $res = $this->actingAs($viewer)
                ->get(route('classes.report-cards.show', array_merge([$this->schoolClass], ['semester' => $this->semester->id])));

            $res->assertOk();

            $resEdit = $this->actingAs($viewer)
                ->put(route('classes.report-cards.entries.update', [$this->schoolClass, $this->student]), [
                    'semester_id' => $this->semester->id,
                    'homeroom_notes' => 'Tidak diizinkan',
                ]);

            $resEdit->assertStatus(403);
        }

        $this->assertDatabaseCount('report_card_entries', 0);
    }

    public function test_unrelated_teacher_is_forbidden_from_workspace_and_editing(): void
    {
        $res = $this->actingAs($this->otherTeacherUser)
            ->get(route('classes.report-cards.show', array_merge([$this->schoolClass], ['semester' => $this->semester->id])));

        $res->assertStatus(403);

        $resEdit = $this->actingAs($this->otherTeacherUser)
            ->put(route('classes.report-cards.entries.update', [$this->schoolClass, $this->student]), [
                'semester_id' => $this->semester->id,
                'homeroom_notes' => 'Tidak diizinkan',
            ]);

        $resEdit->assertStatus(403);
    }

    public function test_preview_returns_payload_without_persisting(): void
    {
        $this->finalizeCourse($this->mathCourse, $this->student, 88);
        $this->finalizeCourse($this->scienceCourse, $this->student, 79);

        $res = $this->actingAs($this->homeroomUser)
            ->getJson(route('classes.report-cards.preview', array_merge([$this->schoolClass], ['semester' => $this->semester->id, 'student' => $this->student->id])));

        $res->assertOk();
        $res->assertJsonPath('class.name', '5A');
        $res->assertJsonPath('class.phase', 'C');
        $res->assertJsonPath('student.name', 'Budi Santoso');
        $res->assertJsonPath('subjects.0.final_score', 88);
        $res->assertJsonPath('complete', true);

        $this->assertDatabaseCount('report_card_entries', 0);
        $this->assertDatabaseCount('report_cards', 0);
        $this->assertDatabaseCount('report_card_publications', 0);
    }

    public function test_preview_includes_saved_notes_and_decision(): void
    {
        ReportCardEntry::create([
            'semester_id' => $this->semester2->id,
            'student_id' => $this->student->id,
            'homeroom_notes' => 'Ananda rajin',
            'extracurricular_notes' => 'Pramuka',
            'promotion_decision' => 'promoted',
        ]);

        $res = $this->actingAs($this->homeroomUser)
            ->getJson(route('classes.report-cards.preview', array_merge([$this->schoolClass], ['semester' => $this->semester2->id, 'student' => $this->student->id])));

        $res->assertOk();
        $res->assertJsonPath('notes.homeroom', 'Ananda rajin');
        $res->assertJsonPath('notes.extracurricular', 'Pramuka');
        $res->assertJsonPath('decision.label', 'Naik Kelas');
    }

    public function test_completeness_counts_previous_class_fallback_result(): void
    {
        $classB = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5B',
        ]);

        // 5B course finalized without a result for the transferring student.
        $classBCourse = ClassSubject::factory()->create([
            'class_id' => $classB->id,
            'subject_id' => $this->mathCourse->subject_id,
            'teacher_id' => $this->mathCourse->teacher_id,
            'passing_threshold' => '75.00',
        ]);
        $this->finalizeCourse($classBCourse);
        // 5A has the finalized result the 5B snapshot falls back to.
        $this->finalizeCourse($this->mathCourse, $this->student, 85);

        // Transfer: close the student's existing 5A enrollment, then enroll in 5B.
        Enrollment::where('student_id', $this->student->id)->update(['ended_on' => '2026-09-30']);
        Enrollment::create(['student_id' => $this->student->id, 'class_id' => $classB->id, 'started_on' => '2026-10-01', 'ended_on' => null]);

        $res = $this->actingAs($this->adminUser)
            ->get(route('classes.report-cards.show', array_merge([$classB], ['semester' => $this->semester->id])));

        $res->assertInertia(fn (Assert $page) => $page
            ->where('students.0.subjects_with_results', 1)
            ->where('students.0.complete', true));
    }

    /**
     * Finalize the course in the current semester, optionally freezing the
     * student's subject result.
     */
    private function finalizeCourse(ClassSubject $course, ?Student $withResult = null, ?int $score = null): void
    {
        $courseSemester = CourseSemester::forCourse($course, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->homeroomUser->id,
        ]);

        if ($withResult !== null) {
            $courseSemester->subjectResults()->create([
                'student_id' => $withResult->id,
                'final_score' => $score,
                'description' => 'Sangat baik',
            ]);
        }
    }
}
