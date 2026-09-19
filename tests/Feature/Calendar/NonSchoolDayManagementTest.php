<?php

namespace Tests\Feature\Calendar;

use App\Enums\NonSchoolDaySource;
use App\Models\NonSchoolDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NonSchoolDayManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_views_the_calendar_ordered_by_date(): void
    {
        $admin = User::factory()->admin()->create();
        NonSchoolDay::factory()->synced()->create(['date' => '2026-12-25', 'name' => 'Natal']);
        NonSchoolDay::factory()->create(['date' => '2026-10-10', 'name' => 'Cuti bersama']);

        $this->actingAs($admin)
            ->get(route('non-school-days.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('non-school-days/index')
                ->has('days.data', 2)
                ->where('days.data.0.date', '2026-10-10')
                ->where('days.data.0.source', 'manual')
                ->where('days.data.1.date', '2026-12-25')
                ->where('days.data.1.source', 'sync'));
    }

    public function test_admin_creates_a_manual_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('non-school-days.store'), [
                'date' => '2026-10-10',
                'name' => 'Teacher training day',
            ])
            ->assertRedirect(route('non-school-days.index'));

        $day = NonSchoolDay::query()->sole();
        $this->assertSame('Teacher training day', $day->name);
        $this->assertTrue($day->source === NonSchoolDaySource::Manual);
    }

    public function test_duplicate_dates_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        NonSchoolDay::factory()->synced()->create(['date' => '2026-12-25']);

        $this->actingAs($admin)
            ->post(route('non-school-days.store'), [
                'date' => '2026-12-25',
                'name' => 'Second event',
            ])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, NonSchoolDay::count());
    }

    public function test_past_dates_are_allowed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('non-school-days.store'), [
                'date' => '2020-01-01',
                'name' => 'Backfilled holiday',
            ])
            ->assertRedirect(route('non-school-days.index'));

        $this->assertSame(1, NonSchoolDay::count());
    }

    public function test_admin_deletes_a_synced_row(): void
    {
        $admin = User::factory()->admin()->create();
        $day = NonSchoolDay::factory()->synced()->create(['date' => '2026-12-25']);

        $this->actingAs($admin)
            ->delete(route('non-school-days.destroy', $day))
            ->assertRedirect(route('non-school-days.index'));

        $this->assertSame(0, NonSchoolDay::count());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();
        $day = NonSchoolDay::factory()->create();

        $this->actingAs($user)->get(route('non-school-days.index'))->assertForbidden();
        $this->actingAs($user)->get(route('non-school-days.create'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('non-school-days.store'), ['date' => '2026-10-10', 'name' => 'Nope'])
            ->assertForbidden();
        $this->actingAs($user)
            ->delete(route('non-school-days.destroy', $day))
            ->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [
            ['teacher'],
            ['student'],
            ['parent'],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('non-school-days.index'))->assertRedirect(route('login'));
    }

    public function test_deleting_a_missing_row_falls_back_to_the_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('non-school-days.destroy', ['non_school_day' => 999]))
            ->assertRedirect(route('non-school-days.index'));
    }
}
