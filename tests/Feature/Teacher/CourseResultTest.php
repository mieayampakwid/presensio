<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\LearningObjective;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseResultTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private Teacher $teacher;

    private User $otherTeacherUser;

    private Teacher $otherTeacher;

    private AcademicYear $academicYear;

    private Semester $semester;

    private Subject $subject;

    private SchoolClass $schoolClass;

    private ClassSubject $classSubject;

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

        $this->semester = $this->academicYear->semesters()->where('number', 1)->first()
            ?? Semester::factory()->create([
                'academic_year_id' => $this->academicYear->id,
                'number' => 1,
                'starts_at' => '2026-07-01',
                'ends_at' => '2026-12-31',
            ]);

        $this->teacherUser = User::factory()->teacher()->create();
        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);

        $this->otherTeacherUser = User::factory()->teacher()->create();
        $this->otherTeacher = Teacher::factory()->create(['user_id' => $this->otherTeacherUser->id]);

        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
        ]);

        $this->classSubject = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
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

    public function test_ac_10_06_draft_description_generated_from_scored_assessments(): void
    {
        $tp1 = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'memahami konsep pecahan',
        ]);

        $tp3 = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 3',
            'description' => 'menyelesaikan soal cerita pecahan',
        ]);

        $a1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Lingkup Materi 1',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-10',
            'max_score' => '100.00',
        ]);
        $a1->learningObjectives()->attach($tp1->id);

        $a2 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Lingkup Materi 2',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-20',
            'max_score' => '100.00',
        ]);
        $a2->learningObjectives()->attach($tp3->id);

        Score::factory()->create([
            'assessment_id' => $a1->id,
            'student_id' => $this->student->id,
            'score' => '92.00',
        ]);

        Score::factory()->create([
            'assessment_id' => $a2->id,
            'student_id' => $this->student->id,
            'score' => '64.00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.results.show', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('teacher/results')
            ->where('students.0.id', $this->student->id)
            ->where('students.0.description', 'Menunjukkan penguasaan yang baik dalam memahami konsep pecahan. Perlu bantuan dalam menyelesaikan soal cerita pecahan.')
            ->where('students.0.description_edited', false)
        );
    }

    public function test_teacher_can_edit_description_and_subsequent_build_keeps_it(): void
    {
        $tp1 = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'memahami konsep pecahan',
        ]);

        $a1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-10',
            'max_score' => '100.00',
        ]);
        $a1->learningObjectives()->attach($tp1->id);

        $score = Score::factory()->create([
            'assessment_id' => $a1->id,
            'student_id' => $this->student->id,
            'score' => '90.00',
        ]);

        // Edit description
        $customText = 'Budi sangat tekun dan menunjukkan kemajuan luar biasa.';
        $updateResponse = $this->actingAs($this->teacherUser)
            ->put(route('courses.results.description.update', [
                'class_subject' => $this->classSubject->id,
                'student' => $this->student->id,
            ]), [
                'semester_id' => $this->semester->id,
                'description' => $customText,
            ]);

        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('subject_results', [
            'student_id' => $this->student->id,
            'description' => $customText,
            'description_edited' => true,
        ]);

        // Change score afterwards
        $score->update(['score' => '50.00']);

        // Next build still keeps custom description
        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.results.show', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('teacher/results')
            ->where('students.0.description', $customText)
            ->where('students.0.description_edited', true)
        );
    }

    public function test_description_max_length_2000(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->put(route('courses.results.description.update', [
                'class_subject' => $this->classSubject->id,
                'student' => $this->student->id,
            ]), [
                'semester_id' => $this->semester->id,
                'description' => str_repeat('a', 2001),
            ]);

        $response->assertSessionHasErrors(['description']);
    }

    public function test_only_course_teacher_or_admin_can_edit_description(): void
    {
        // Other teacher forbidden
        $response = $this->actingAs($this->otherTeacherUser)
            ->put(route('courses.results.description.update', [
                'class_subject' => $this->classSubject->id,
                'student' => $this->student->id,
            ]), [
                'semester_id' => $this->semester->id,
                'description' => 'Teks dari guru lain',
            ]);

        $response->assertForbidden();

        // Admin allowed
        $admin = User::factory()->admin()->create();
        $adminResponse = $this->actingAs($admin)
            ->put(route('courses.results.description.update', [
                'class_subject' => $this->classSubject->id,
                'student' => $this->student->id,
            ]), [
                'semester_id' => $this->semester->id,
                'description' => 'Teks dari admin',
            ]);

        $adminResponse->assertRedirect();
    }

    public function test_homeroom_teacher_and_principal_can_view_results_but_not_edit(): void
    {
        $this->schoolClass->update(['teacher_id' => $this->otherTeacher->id]);
        $viewResponse = $this->actingAs($this->otherTeacherUser)
            ->get(route('courses.results.show', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $viewResponse->assertOk();
        $viewResponse->assertInertia(fn (Assert $page) => $page
            ->component('teacher/results')
            ->where('canWrite', false)
        );

        $editResponse = $this->actingAs($this->otherTeacherUser)
            ->put(route('courses.results.description.update', [
                'class_subject' => $this->classSubject->id,
                'student' => $this->student->id,
            ]), [
                'semester_id' => $this->semester->id,
                'description' => 'Teks dari wali kelas',
            ]);

        $editResponse->assertForbidden();
    }
}
