<?php

namespace Tests\Feature\Classes;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_classes_list(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create(['name' => 'Budi Santoso']);
        SchoolClass::factory()->withTeacher($teacher)->create(['name' => 'Kelas 1A']);

        $this->actingAs($admin)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Kelas 1A')
            ->assertSee('Budi Santoso');
    }

    public function test_admin_can_search_classes_by_name_or_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create(['name' => 'Budi Santoso']);
        SchoolClass::factory()->create(['name' => 'Kelas 1A']);
        SchoolClass::factory()->withTeacher($teacher)->create(['name' => 'Kelas 2B']);

        $this->actingAs($admin)
            ->get(route('classes.index', ['search' => 'Budi']))
            ->assertOk()
            ->assertSee('Kelas 2B')
            ->assertDontSee('Kelas 1A');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_class_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('classes.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_class_with_a_homeroom_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('classes.index'));

        $class = SchoolClass::where('name', 'Kelas 1A')->first();

        $this->assertNotNull($class);
        $this->assertSame($teacher->id, $class->teacher_id);
    }

    public function test_admin_can_create_a_class_without_a_homeroom_teacher(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 1A',
            ])
            ->assertSessionHasNoErrors();

        $class = SchoolClass::where('name', 'Kelas 1A')->first();

        $this->assertNotNull($class);
        $this->assertNull($class->teacher_id);
    }

    public function test_class_creation_rejects_a_teacher_who_already_homerooms_another_class(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 2B',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, SchoolClass::where('teacher_id', $teacher->id)->count());
    }

    public function test_homeroom_rule_is_advisable_when_the_config_allows_multiple(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create();

        config(['school.allow_multiple_homerooms' => true]);

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'name' => 'Kelas 2B',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SchoolClass::where('teacher_id', $teacher->id)->count());
    }

    public function test_class_update_may_keep_its_own_homeroom_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        $class = SchoolClass::factory()->withTeacher($teacher)->create();

        $this->actingAs($admin)
            ->put(route('classes.update', $class), [
                'name' => 'Kelas 1A (updated)',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Kelas 1A (updated)', $class->fresh()->name);
        $this->assertSame($teacher->id, $class->fresh()->teacher_id);
    }

    public function test_class_update_cannot_take_another_classs_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create(['name' => 'Kelas 1A']);
        $other = SchoolClass::factory()->create(['name' => 'Kelas 2B']);

        $this->actingAs($admin)
            ->put(route('classes.update', $other), [
                'name' => 'Kelas 2B',
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame('Kelas 1A', SchoolClass::where('teacher_id', $teacher->id)->value('name'));
    }

    public function test_class_deletion_is_blocked_while_students_are_enrolled(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        Student::factory()->create(['class_id' => $class->id]);

        $this->actingAs($admin)
            ->from(route('classes.index'))
            ->delete(route('classes.destroy', $class))
            ->assertRedirect(route('classes.index'));

        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_admin_can_delete_an_empty_class(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();

        $this->actingAs($admin)
            ->delete(route('classes.destroy', $class))
            ->assertRedirect(route('classes.index'));

        $this->assertDatabaseMissing('classes', ['id' => $class->id]);
    }

    public function test_duplicate_class_names_are_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolClass::factory()->create(['name' => 'Kelas 1A']);

        $this->actingAs($admin)
            ->post(route('classes.store'), ['name' => 'Kelas 1A'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SchoolClass::where('name', 'Kelas 1A')->count());
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
