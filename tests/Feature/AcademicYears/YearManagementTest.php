<?php

namespace Tests\Feature\AcademicYears;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YearManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('academic-years.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        foreach (['teacher', 'student', 'parent'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('academic-years.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_creates_a_year_starting_inactive(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('academic-years.store'), [
                'name' => '2027/2028',
                'starts_at' => '2027-07-01',
                'ends_at' => '2028-06-30',
            ])
            ->assertRedirect(route('academic-years.index'));

        $year = AcademicYear::query()->where('name', '2027/2028')->first();

        $this->assertNotNull($year);
        $this->assertFalse($year->is_active);
        // The bootstrap year stays the single active one.
        $this->assertSame('2026/2027', AcademicYear::active()->name);
    }

    public function test_activating_a_year_is_exclusive(): void
    {
        $admin = User::factory()->admin()->create();
        $other = AcademicYear::factory()->create(['name' => '2027/2028']);
        $bootstrap = AcademicYear::active();

        $this->actingAs($admin)
            ->post(route('academic-years.activate', $other))
            ->assertRedirect(route('academic-years.index'));

        $this->assertTrue($other->fresh()->is_active);
        $this->assertFalse($bootstrap->fresh()->is_active);
    }

    public function test_admin_updates_a_year(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create(['name' => '2027/2028']);

        $this->actingAs($admin)
            ->put(route('academic-years.update', $year), [
                'name' => '2027/2028 (revised)',
                'starts_at' => '2027-07-05',
                'ends_at' => '2028-06-30',
            ])
            ->assertRedirect(route('academic-years.index'));

        $this->assertSame('2027/2028 (revised)', $year->fresh()->name);
    }

    public function test_a_year_with_classes_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::active();
        SchoolClass::factory()->create(['academic_year_id' => $year->id]);

        $this->actingAs($admin)
            ->delete(route('academic-years.destroy', $year))
            ->assertRedirect();

        $this->assertNotNull(AcademicYear::find($year->id));
    }

    public function test_an_empty_year_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create(['name' => '2029/2030']);

        $this->actingAs($admin)
            ->delete(route('academic-years.destroy', $year))
            ->assertRedirect(route('academic-years.index'));

        $this->assertNull(AcademicYear::find($year->id));
    }

    public function test_the_index_lists_years_with_the_active_one_flagged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('academic-years.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academic-years/index')
                ->has('years', 1)
                ->where('years.0.name', '2026/2027')
                ->where('years.0.is_active', true));
    }
}
