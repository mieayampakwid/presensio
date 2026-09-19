<?php

namespace Tests\Feature\Teachers;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_teachers_list(): void
    {
        $admin = User::factory()->admin()->create();
        Teacher::factory()->create(['name' => 'Budi Santoso']);

        $this->actingAs($admin)
            ->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Budi Santoso');
    }

    public function test_admin_can_search_teachers(): void
    {
        $admin = User::factory()->admin()->create();
        Teacher::factory()->create(['name' => 'Budi Santoso']);
        Teacher::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($admin)
            ->get(route('teachers.index', ['search' => 'Siti']))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Budi Santoso');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_teacher_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('teachers.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_teacher(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('teachers.store'), [
                'name' => 'Budi Santoso',
                'teacher_number' => '197501012000031002',
                'phone_number' => '+6281234567890',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teachers.index'));

        $teacher = Teacher::where('name', 'Budi Santoso')->first();

        $this->assertNotNull($teacher);
        $this->assertSame('197501012000031002', $teacher->teacher_number);
    }

    public function test_teacher_creation_requires_a_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('teachers.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_a_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->put(route('teachers.update', $teacher), [
                'name' => 'Budi Santoso',
                'teacher_number' => $teacher->teacher_number,
                'phone_number' => $teacher->phone_number,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teachers.edit', $teacher));

        $this->assertSame('Budi Santoso', $teacher->fresh()->name);
    }

    public function test_teacher_deletion_is_blocked_while_homerooming_a_class(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        SchoolClass::factory()->withTeacher($teacher)->create();

        $this->actingAs($admin)
            ->from(route('teachers.index'))
            ->delete(route('teachers.destroy', $teacher))
            ->assertRedirect(route('teachers.index'));

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_teacher_deletion_is_blocked_while_linked_to_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $user->id]);

        $teacher = Teacher::where('user_id', $user->id)->first();

        $this->actingAs($admin)
            ->from(route('teachers.index'))
            ->delete(route('teachers.destroy', $teacher))
            ->assertRedirect(route('teachers.index'));

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_admin_can_delete_an_unlinked_teacher_without_classes(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->delete(route('teachers.destroy', $teacher))
            ->assertRedirect(route('teachers.index'));

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
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
