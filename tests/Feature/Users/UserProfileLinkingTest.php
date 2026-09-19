<?php

namespace Tests\Feature\Users;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_a_user_can_link_an_unlinked_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $guardian = Guardian::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'guardian.wati',
                'role' => 'parent',
                'password' => 'super-secret-123',
                'password_confirmation' => 'super-secret-123',
                'profile_id' => $guardian->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertSame($guardian->id, User::where('username', 'guardian.wati')->first()->guardian->id);
    }

    public function test_a_profile_linked_to_another_user_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'other.parent',
                'role' => 'parent',
                'password' => 'super-secret-123',
                'profile_id' => $guardian->id,
            ])
            ->assertSessionHasErrors('profile_id');
    }

    public function test_an_admin_user_cannot_be_linked_to_a_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $guardian = Guardian::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'admin.extra',
                'role' => 'admin',
                'password' => 'super-secret-123',
                'profile_id' => $guardian->id,
            ])
            ->assertSessionHasErrors('profile_id');
    }

    public function test_changing_role_clears_the_old_profile_link(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'username' => $user->username,
                'role' => 'teacher',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($guardian->fresh()->user_id);
        $this->assertNull($user->fresh()->teacher()->value('id'));
    }

    public function test_changing_role_and_linking_a_new_profile_in_one_request(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'username' => $user->username,
                'role' => 'teacher',
                'is_active' => true,
                'profile_id' => $teacher->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($guardian->fresh()->user_id);
        $this->assertSame($teacher->id, $user->fresh()->teacher->id);
    }

    public function test_resubmitting_the_same_profile_keeps_it_linked(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'username' => $user->username,
                'role' => 'parent',
                'is_active' => true,
                'profile_id' => $guardian->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($guardian->id, $user->fresh()->guardian->id);
    }

    public function test_a_profile_from_the_wrong_role_table_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $guardian = Guardian::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'teacher.mismatch',
                'role' => 'teacher',
                'password' => 'super-secret-123',
                'profile_id' => $guardian->id,
            ])
            ->assertSessionHasErrors('profile_id');

        $this->assertNull($guardian->fresh()->user_id);
    }

    public function test_edit_page_offers_unlinked_profiles_and_the_current_link(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->parent()->create();
        $linked = Guardian::factory()->create(['user_id' => $user->id, 'name' => 'Slamet Riyadi']);
        Guardian::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($admin)
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Slamet Riyadi')
            ->assertSee('Siti Aminah');

        $this->assertSame($linked->id, $user->guardian->id);
    }

    public function test_linking_a_student_profile_by_username_smoke(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => $student->full_name,
                'role' => 'student',
                'password' => 'super-secret-123',
                'password_confirmation' => 'super-secret-123',
                'profile_id' => $student->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($student->id, User::where('username', $student->full_name)->first()->student->id);
    }
}
