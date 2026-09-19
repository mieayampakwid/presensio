<?php

namespace Tests\Feature\Students;

use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_students_list(): void
    {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['full_name' => 'Ayu Lestari']);

        $this->actingAs($admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Ayu Lestari');
    }

    public function test_admin_can_search_students_by_guardian_name(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['full_name' => 'Ayu Lestari']);
        $student->guardians()->attach(
            Guardian::factory()->create(['name' => 'Slamet Riyadi'])->id,
        );
        Student::factory()->create(['full_name' => 'Budi Santoso']);

        $this->actingAs($admin)
            ->get(route('students.index', ['search' => 'Slamet']))
            ->assertOk()
            ->assertSee('Ayu Lestari')
            ->assertDontSee('Budi Santoso');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_student_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('students.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_student_with_class_and_guardians(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $guardians = Guardian::factory(2)->create();

        $this->actingAs($admin)
            ->post(route('students.store'), [
                'full_name' => 'Ayu Lestari',
                'dob' => '2012-05-17',
                'student_number' => '007',
                'class_id' => $class->id,
                'guardian_ids' => $guardians->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('students.index'));

        $student = Student::where('full_name', 'Ayu Lestari')->first();
        $this->assertSame('007', $student->student_number);
        $this->assertSame($class->id, $student->class_id);
        $this->assertSame(
            $guardians->pluck('id')->sort()->values()->all(),
            $student->guardians()->pluck('guardian_id')->sort()->values()->all(),
        );
    }

    public function test_student_creation_rejects_a_future_date_of_birth(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('students.store'), [
                'full_name' => 'Time Traveller',
                'dob' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('dob');
    }

    public function test_student_creation_requires_a_unique_student_number(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = Student::factory()->create();

        $this->actingAs($admin)
            ->post(route('students.store'), [
                'full_name' => 'Other Student',
                'dob' => '2012-05-17',
                'student_number' => $existing->student_number,
            ])
            ->assertSessionHasErrors('student_number');
    }

    public function test_admin_can_update_a_student_keeping_their_own_student_number(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $class = SchoolClass::factory()->create();

        $this->actingAs($admin)
            ->put(route('students.update', $student), [
                'full_name' => 'Ayu Lestari',
                'dob' => $student->dob->toDateString(),
                'student_number' => $student->student_number,
                'class_id' => $class->id,
                'guardian_ids' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ayu Lestari', $student->fresh()->full_name);
        $this->assertSame($class->id, $student->fresh()->class_id);
    }

    public function test_updating_a_student_replaces_their_guardian_links(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $first = Guardian::factory()->create();
        $second = Guardian::factory()->create();
        $student->guardians()->attach($first->id);

        $this->actingAs($admin)
            ->put(route('students.update', $student), [
                'full_name' => $student->full_name,
                'dob' => $student->dob->toDateString(),
                'guardian_ids' => [$second->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$second->id], $student->guardians()->pluck('guardian_id')->all());
    }

    public function test_edit_page_lists_available_guardians_and_the_linked_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $linked = Guardian::factory()->create(['name' => 'Slamet Riyadi']);
        $other = Guardian::factory()->create(['name' => 'Siti Aminah']);
        $student->guardians()->attach($linked->id);

        $this->actingAs($admin)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('Slamet Riyadi')
            ->assertSee('Siti Aminah');
    }

    public function test_student_deletion_detaches_guardians_and_releases_rfid_cards(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create();
        $student->guardians()->attach($guardian->id);
        $card = RfidCard::factory()->assigned($student)->create();

        $this->actingAs($admin)
            ->from(route('students.index'))
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('guardian_student', ['student_id' => $student->id]);
        $this->assertDatabaseHas('rfid_cards', ['id' => $card->id, 'student_id' => null]);
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id]);
    }

    public function test_student_deletion_is_blocked_while_linked_to_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->from(route('students.index'))
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_student_deletion_is_blocked_with_attendance_history(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        Attendance::factory()->create(['student_id' => $student->id]);

        $this->actingAs($admin)
            ->from(route('students.index'))
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'student' => ['student'],
            'parent' => ['parent'],
        ];
    }
}
