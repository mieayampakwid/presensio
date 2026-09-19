<?php

namespace Tests\Feature\Settings;

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_edit_their_own_contact_fields(): void
    {
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('guardian-contact.update'), [
                'phone_number' => '+628999888777',
                'work' => 'Merchant',
                'address' => 'Jl. Kenanga 9',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('+628999888777', $guardian->fresh()->phone_number);
        $this->assertSame('Merchant', $guardian->fresh()->work);
        $this->assertSame('Jl. Kenanga 9', $guardian->fresh()->address);
    }

    public function test_posted_name_is_ignored(): void
    {
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id, 'name' => 'Slamet Riyadi']);

        $this->actingAs($user)
            ->put(route('guardian-contact.update'), [
                'name' => 'Hacker Name',
                'phone_number' => $guardian->phone_number,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Slamet Riyadi', $guardian->fresh()->name);
    }

    public function test_phone_number_must_stay_unique_against_other_guardians(): void
    {
        $user = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $user->id]);
        $other = Guardian::factory()->create();

        $this->actingAs($user)
            ->put(route('guardian-contact.update'), [
                'phone_number' => $other->phone_number,
            ])
            ->assertSessionHasErrors('phone_number');
    }

    public function test_profile_page_shows_the_contact_section_for_linked_parents(): void
    {
        $user = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $user->id, 'name' => 'Slamet Riyadi']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Contact details')
            ->assertSee('Slamet Riyadi');
    }

    public function test_other_roles_are_forbidden_from_the_contact_update(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->put(route('guardian-contact.update'), [
                    'phone_number' => '+628111222333',
                ])
                ->assertForbidden();

            $user->delete();
        }
    }

    public function test_parent_without_a_linked_guardian_is_forbidden(): void
    {
        $user = User::factory()->parent()->create();

        $this->actingAs($user)
            ->put(route('guardian-contact.update'), [
                'phone_number' => '+628111222334',
            ])
            ->assertForbidden();
    }
}
