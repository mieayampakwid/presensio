<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectResult;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassGradeLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $homeroomUser;

    private Teacher $homeroomTeacher;

    private User $subjectTeacherUser;

    private Teacher $subjectTeacher;

    private User $otherTeacherUser;

    private Teacher $otherTeacher;

    private AcademicYear $academicYear;

    private Semester $semester;

    private SchoolClass $schoolClass;

    private Subject $mathSubject;

    private Subject $scienceSubject;

    private ClassSubject $mathCourse;

    private ClassSubject $scienceCourse;

    private Student $student1;

    private Student $student2;

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

        $this->homeroomUser = User::factory()->teacher()->create();
        $this->homeroomTeacher = Teacher::factory()->create(['user_id' => $this->homeroomUser->id]);

        $this->subjectTeacherUser = User::factory()->teacher()->create();
        $this->subjectTeacher = Teacher::factory()->create(['user_id' => $this->subjectTeacherUser->id]);

        $this->otherTeacherUser = User::factory()->teacher()->create();
        $this->otherTeacher = Teacher::factory()->create(['user_id' => $this->otherTeacherUser->id]);

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => $this->homeroomTeacher->id,
        ]);

        $this->mathSubject = Subject::factory()->create(['name' => 'Matematika']);
        $this->scienceSubject = Subject::factory()->create(['name' => 'IPAS']);

        $this->mathCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->mathSubject->id,
            'teacher_id' => $this->homeroomTeacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->scienceCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->scienceSubject->id,
            'teacher_id' => $this->subjectTeacher->id,
            'passing_threshold' => '70.00',
        ]);

        $this->student1 = Student::factory()->create(['full_name' => 'Adit Pratama']);
        $this->student2 = Student::factory()->create(['full_name' => 'Bunga Citra']);

        Enrollment::create([
            'student_id' => $this->student1->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        Enrollment::create([
            'student_id' => $this->student2->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
    }

    public function test_homeroom_ledger_displays_students_and_subjects_with_scores_and_threshold_flags(): void
    {
        // Math course is finalized with scores: Adit 80 (Tuntas), Bunga 65 (Belum Tuntas, KKTP 75)
        $mathSemester = CourseSemester::forCourse($this->mathCourse, $this->semester);
        $mathSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->homeroomUser->id,
        ]);

        SubjectResult::create([
            'course_semester_id' => $mathSemester->id,
            'student_id' => $this->student1->id,
            'final_score' => 80,
            'description' => 'Bagus sekali',
        ]);

        SubjectResult::create([
            'course_semester_id' => $mathSemester->id,
            'student_id' => $this->student2->id,
            'final_score' => 65,
            'description' => 'Perlu bimbingan',
        ]);

        // Science course is open with live score: Adit 90 (Tuntas, KKTP 70)
        $scienceAssessment = Assessment::factory()->create([
            'class_subject_id' => $this->scienceCourse->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeFinal,
            'assessed_on' => '2026-10-15',
            'max_score' => 100,
        ]);

        Score::factory()->create([
            'assessment_id' => $scienceAssessment->id,
            'student_id' => $this->student1->id,
            'score' => 90,
        ]);

        $response = $this->actingAs($this->homeroomUser)
            ->get(route('classes.grades.index', [
                'school_class' => $this->schoolClass->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('teacher/class-grades')
            ->where('school_class.id', $this->schoolClass->id)
            ->where('semester.id', $this->semester->id)
            ->has('subjects', 2)
            ->where('subjects.0.is_finalized', true)
            ->where('subjects.1.is_finalized', false)
            ->has('students', 2)
            ->where('students.0.scores.'.$this->mathCourse->id.'.score', 80)
            ->where('students.0.scores.'.$this->mathCourse->id.'.below_threshold', false)
            ->where('students.1.scores.'.$this->mathCourse->id.'.score', 65)
            ->where('students.1.scores.'.$this->mathCourse->id.'.below_threshold', true)
            ->where('students.0.scores.'.$this->scienceCourse->id.'.score', 90)
            ->where('students.0.scores.'.$this->scienceCourse->id.'.below_threshold', false)
        );
    }

    public function test_ledger_excludes_students_not_enrolled_as_of_roster_date(): void
    {
        // Transferred out student
        $transferredStudent = Student::factory()->create(['full_name' => 'Zaki Pindah']);
        Enrollment::create([
            'student_id' => $transferredStudent->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => '2026-08-01', // Ended long before semester end / today
        ]);

        $response = $this->actingAs($this->homeroomUser)
            ->get(route('classes.grades.index', [
                'school_class' => $this->schoolClass->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('students', 2)
            ->where('students.0.id', $this->student1->id)
            ->where('students.1.id', $this->student2->id)
        );
    }

    public function test_access_permissions_for_ledger(): void
    {
        // Homeroom teacher -> 200
        $this->actingAs($this->homeroomUser)
            ->get(route('classes.grades.index', $this->schoolClass))
            ->assertOk();

        // Subject teacher of this class -> 200
        $this->actingAs($this->subjectTeacherUser)
            ->get(route('classes.grades.index', $this->schoolClass))
            ->assertOk();

        // Admin -> 200
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('classes.grades.index', $this->schoolClass))
            ->assertOk();

        // Principal -> 200
        $principal = User::factory()->principal()->create();
        $this->actingAs($principal)
            ->get(route('classes.grades.index', $this->schoolClass))
            ->assertOk();

        // Teacher of unrelated class -> 403
        $this->actingAs($this->otherTeacherUser)
            ->get(route('classes.grades.index', $this->schoolClass))
            ->assertForbidden();

        // Finance user -> 403
        $finance = User::factory()->finance()->create();
        $this->actingAs($finance)
            ->get(route('classes.grades.index', $this->schoolClass))
            ->assertForbidden();
    }
}
