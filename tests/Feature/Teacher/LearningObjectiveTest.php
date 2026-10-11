<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\LearningObjective;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningObjectiveTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private Teacher $teacher;

    private User $otherTeacherUser;

    private Teacher $otherTeacher;

    private User $adminUser;

    private AcademicYear $academicYear;

    private Subject $subject;

    private SchoolClass $schoolClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->teacherUser = User::factory()->teacher()->create();
        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);

        $this->otherTeacherUser = User::factory()->teacher()->create();
        $this->otherTeacher = Teacher::factory()->create(['user_id' => $this->otherTeacherUser->id]);

        $this->adminUser = User::factory()->admin()->create();

        $this->subject = Subject::factory()->create();

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
        ]);

        // Assign teacher to teach this subject in grade 5 class
        ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_teacher_can_view_objectives_for_assigned_scope(): void
    {
        LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Memahami pecahan',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('objectives.index', [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('teacher/objectives/index')
            ->has('objectives', 1)
            ->where('canManage', true)
        );
    }

    public function test_teacher_can_create_objective_for_assigned_scope(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->post(route('objectives.store'), [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
                'code' => 'TP 1',
                'description' => 'Menghitung bilangan cacah',
                'sort_order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('learning_objectives', [
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Menghitung bilangan cacah',
        ]);
    }

    public function test_unassigned_teacher_cannot_create_objective(): void
    {
        $response = $this->actingAs($this->otherTeacherUser)
            ->post(route('objectives.store'), [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
                'code' => 'TP 1',
                'description' => 'Menghitung bilangan cacah',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_create_duplicate_code_for_same_scope(): void
    {
        LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('objectives.store'), [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
                'code' => 'TP 1',
                'description' => 'Deskripsi lain',
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_teacher_can_update_objective(): void
    {
        $tp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Deskripsi lama',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->put(route('objectives.update', $tp), [
                'code' => 'TP 1-rev',
                'description' => 'Deskripsi baru',
                'sort_order' => 2,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('learning_objectives', [
            'id' => $tp->id,
            'code' => 'TP 1-rev',
            'description' => 'Deskripsi baru',
        ]);
    }

    public function test_unassigned_teacher_cannot_update_objective(): void
    {
        $tp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
        ]);

        $response = $this->actingAs($this->otherTeacherUser)
            ->put(route('objectives.update', $tp), [
                'code' => 'TP 1-rev',
                'description' => 'Deskripsi baru',
            ]);

        $response->assertForbidden();
    }

    public function test_teacher_can_delete_unlinked_objective(): void
    {
        $tp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->delete(route('objectives.destroy', $tp));

        $response->assertRedirect();
        $this->assertDatabaseMissing('learning_objectives', ['id' => $tp->id]);
    }

    public function test_cannot_delete_objective_linked_to_assessment_returns_422(): void
    {
        $tp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
        ]);

        $assessment = Assessment::factory()->create([
            'kind' => AssessmentKind::SummativeScope,
        ]);
        $assessment->learningObjectives()->attach($tp->id);

        $response = $this->actingAs($this->teacherUser)
            ->delete(route('objectives.destroy', $tp));

        $response->assertStatus(422);
        $this->assertDatabaseHas('learning_objectives', ['id' => $tp->id]);
    }

    public function test_admin_can_manage_any_objective(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('objectives.store'), [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
                'code' => 'TP Admin',
                'description' => 'Dibuat admin',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('learning_objectives', ['code' => 'TP Admin']);
    }

    public function test_copy_from_previous_year_copies_and_is_idempotent(): void
    {
        // Previous year: 2025/2026
        $prevYear = AcademicYear::factory()->create([
            'name' => '2025/2026',
            'starts_at' => '2025-07-01',
            'ends_at' => '2026-06-30',
        ]);

        LearningObjective::factory()->create([
            'academic_year_id' => $prevYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Prev TP 1',
            'sort_order' => 1,
        ]);
        LearningObjective::factory()->create([
            'academic_year_id' => $prevYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 2',
            'description' => 'Prev TP 2',
            'sort_order' => 2,
        ]);

        // Target year already has TP 1
        LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Existing TP 1',
        ]);

        // Copy action
        $response = $this->actingAs($this->teacherUser)
            ->post(route('objectives.copy'), [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
            ]);

        $response->assertRedirect();

        // Target year now has both TP 1 (original) and TP 2 (copied)
        $this->assertDatabaseHas('learning_objectives', [
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Existing TP 1',
        ]);
        $this->assertDatabaseHas('learning_objectives', [
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 2',
            'description' => 'Prev TP 2',
        ]);

        // Total in target year = 2
        $count = LearningObjective::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->where('subject_id', $this->subject->id)
            ->where('grade_level', 5)
            ->count();
        $this->assertSame(2, $count);

        // Run copy again -> idempotent, count still 2
        $this->actingAs($this->teacherUser)
            ->post(route('objectives.copy'), [
                'academic_year_id' => $this->academicYear->id,
                'subject_id' => $this->subject->id,
                'grade_level' => 5,
            ]);

        $countSecond = LearningObjective::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->where('subject_id', $this->subject->id)
            ->where('grade_level', 5)
            ->count();
        $this->assertSame(2, $countSecond);
    }
}
