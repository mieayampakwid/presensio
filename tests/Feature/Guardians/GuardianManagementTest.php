<?php

namespace Tests\Feature\Guardians;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_guardians_list(): void
    {
        $admin = User::factory()->admin()->create();
        Guardian::factory()->create(['name' => 'Slamet Riyadi']);

        $this->actingAs($admin)
            ->get(route('guardians.index'))
            ->assertOk()
            ->assertSee('Slamet Riyadi');
    }

    public function test_admin_can_search_guardians_by_name_or_phone(): void
    {
        $admin = User::factory()->admin()->create();
        Guardian::factory()->create(['name' => 'Slamet Riyadi', 'phone_number' => '+6281111111111']);
        Guardian::factory()->create(['name' => 'Siti Aminah', 'phone_number' => '+6282222222222']);

        $this->actingAs($admin)
            ->get(route('guardians.index', ['search' => '+6282222222222']))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Slamet Riyadi');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_guardian_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('guardians.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_guardian(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('guardians.store'), [
                'name' => 'Slamet Riyadi',
                'phone_number' => '+6281111111111',
                'work' => 'Farmer',
                'address' => 'Jl. Merdeka 1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('guardians.index'));

        $this->assertDatabaseHas('guardians', [
            'name' => 'Slamet Riyadi',
            'phone_number' => '+6281111111111',
        ]);
    }

    public function test_guardian_creation_requires_a_unique_phone_number(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = Guardian::factory()->create();

        $this->actingAs($admin)
            ->post(route('guardians.store'), [
                'name' => 'Other Guardian',
                'phone_number' => $existing->phone_number,
            ])
            ->assertSessionHasErrors('phone_number');
    }

    public function test_admin_can_update_a_guardian_keeping_their_own_phone(): void
    {
        $admin = User::factory()->admin()->create();
        $guardian = Guardian::factory()->create();

        $this->actingAs($admin)
            ->put(route('guardians.update', $guardian), [
                'name' => 'Slamet Riyadi',
                'phone_number' => $guardian->phone_number,
                'work' => $guardian->work,
                'address' => $guardian->address,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Slamet Riyadi', $guardian->fresh()->name);
    }

    public function test_edit_page_lists_linked_students(): void
    {
        $admin = User::factory()->admin()->create();
        $guardian = Guardian::factory()->create();
        $student = Student::factory()->create(['full_name' => 'Ayu Lestari']);
        $student->guardians()->attach($guardian->id);

        $this->actingAs($admin)
            ->get(route('guardians.edit', $guardian))
            ->assertOk()
            ->assertSee('Ayu Lestari');
    }

    public function test_guardian_deletion_detaches_the_student_pivot(): void
    {
        $admin = User::factory()->admin()->create();
        $guardian = Guardian::factory()->create();
        $student = Student::factory()->create();
        $student->guardians()->attach($guardian->id);

        $this->actingAs($admin)
            ->from(route('guardians.index'))
            ->delete(route('guardians.destroy', $guardian))
            ->assertRedirect(route('guardians.index'));

        $this->assertDatabaseMissing('guardians', ['id' => $guardian->id]);
        $this->assertDatabaseMissing('guardian_student', [
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_guardian_deletion_is_blocked_while_linked_to_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $user->id]);

        $guardian = Guardian::where('user_id', $user->id)->first();

        $this->actingAs($admin)
            ->from(route('guardians.index'))
            ->delete(route('guardians.destroy', $guardian))
            ->assertRedirect(route('guardians.index'));

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id]);
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
