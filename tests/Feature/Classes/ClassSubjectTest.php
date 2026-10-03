<?php

namespace Tests\Feature\Classes;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassSubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_class_subjects_page(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $subject = Subject::factory()->create(['name' => 'Matematika', 'code' => 'MAT']);
        $teacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);

        ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->actingAs($admin)
            ->get(route('classes.subjects.index', $class))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Budi Raharjo')
            ->assertSee('75.00');
    }

    /**
     * AC-09-02: Assigning "Matematika" to Class 5A (active year) with Teacher Budi
     * and threshold 75 creates a class_subjects row.
     */
    public function test_ac_09_02_assigning_subject_to_class_in_active_year_creates_row(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $subject = Subject::factory()->create(['name' => 'Matematika', 'code' => 'MAT']);
        $teacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);

        $this->actingAs($admin)
            ->post(route('classes.subjects.store', $class), [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'passing_threshold' => 75.00,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'passing_threshold' => '75.00',
        ]);
    }

    /**
     * AC-09-03: Assigning "Matematika" a second time to Class 5A returns HTTP 422.
     */
    public function test_ac_09_03_duplicate_assignment_to_same_class_rejected_with_422(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $subject = Subject::factory()->create(['name' => 'Matematika', 'code' => 'MAT']);
        $teacher1 = Teacher::factory()->create();
        $teacher2 = Teacher::factory()->create();

        ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher1->id,
        ]);

        $this->actingAs($admin)
            ->post(route('classes.subjects.store', $class), [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher2->id,
                'passing_threshold' => 80.00,
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, ClassSubject::where('class_id', $class->id)->where('subject_id', $subject->id)->count());
    }

    /**
     * AC-09-08: Changing the teacher of an assignment writes an audit log row with old and new teacher_id.
     */
    public function test_ac_09_08_changing_teacher_writes_audit_log_with_old_and_new_teacher_id(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $subject = Subject::factory()->create(['name' => 'Matematika', 'code' => 'MAT']);
        $teacher1 = Teacher::factory()->create(['name' => 'Guru Lama']);
        $teacher2 = Teacher::factory()->create(['name' => 'Guru Baru']);

        $classSubject = ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher1->id,
            'passing_threshold' => '75.00',
        ]);

        $this->actingAs($admin)
            ->put(route('classes.subjects.update', [$class, $classSubject]), [
                'teacher_id' => $teacher2->id,
                'passing_threshold' => 80.00,
            ])
            ->assertSessionHasNoErrors();

        $classSubject->refresh();
        $this->assertSame($teacher2->id, $classSubject->teacher_id);
        $this->assertSame('80.00', (string) $classSubject->passing_threshold);

        $log = AuditLog::query()
            ->where('auditable_type', $classSubject->getMorphClass())
            ->where('auditable_id', $classSubject->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($teacher1->id, $log->old_values['teacher_id']);
        $this->assertSame($teacher2->id, $log->new_values['teacher_id']);
    }

    /**
     * AC-09-09: Attempting to edit assignments of a class in a non-active academic year returns HTTP 422.
     */
    public function test_ac_09_09_modifying_assignments_in_inactive_year_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        $inactiveYear = AcademicYear::factory()->create(['is_active' => false]);
        $class = SchoolClass::factory()->create([
            'academic_year_id' => $inactiveYear->id,
            'name' => 'Kelas 5A Lalu',
        ]);
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create();

        // 1. Store in inactive year -> 422
        $this->actingAs($admin)
            ->post(route('classes.subjects.store', $class), [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'passing_threshold' => 75.00,
            ])
            ->assertSessionHasErrors('class_id');

        // Create directly in DB for testing update and delete
        $classSubject = ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        // 2. Update in inactive year -> 422
        $this->actingAs($admin)
            ->put(route('classes.subjects.update', [$class, $classSubject]), [
                'teacher_id' => $teacher->id,
                'passing_threshold' => 85.00,
            ])
            ->assertSessionHasErrors('class_id');

        // 3. Destroy in inactive year -> 422
        $this->actingAs($admin)
            ->delete(route('classes.subjects.destroy', [$class, $classSubject]))
            ->assertStatus(422);

        $this->assertDatabaseHas('class_subjects', ['id' => $classSubject->id]);
    }

    public function test_assigning_inactive_subject_is_rejected_with_422(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $inactiveSubject = Subject::factory()->inactive()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->post(route('classes.subjects.store', $class), [
                'subject_id' => $inactiveSubject->id,
                'teacher_id' => $teacher->id,
                'passing_threshold' => 75.00,
            ])
            ->assertSessionHasErrors('subject_id');
    }

    public function test_assigning_inactive_teacher_is_rejected_with_422(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();

        $inactiveUser = User::factory()->teacher()->create(['is_active' => false]);
        $inactiveTeacher = Teacher::factory()->create(['user_id' => $inactiveUser->id]);

        $this->actingAs($admin)
            ->post(route('classes.subjects.store', $class), [
                'subject_id' => $subject->id,
                'teacher_id' => $inactiveTeacher->id,
                'passing_threshold' => 75.00,
            ])
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_admin_can_delete_assignment_when_not_in_use(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $classSubject = ClassSubject::factory()->create(['class_id' => $class->id]);

        $this->actingAs($admin)
            ->delete(route('classes.subjects.destroy', [$class, $classSubject]))
            ->assertRedirect();

        $this->assertDatabaseMissing('class_subjects', ['id' => $classSubject->id]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_modify_class_subjects(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();
        $class = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($user)
            ->post(route('classes.subjects.store', $class), [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'passing_threshold' => 75.00,
            ])
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'counselor' => ['counselor'],
            'finance' => ['finance'],
            'staff' => ['staff'],
            'parent' => ['parent'],
            'student' => ['student'],
        ];
    }
}
