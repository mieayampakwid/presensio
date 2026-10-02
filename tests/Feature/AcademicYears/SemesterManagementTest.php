<?php

namespace Tests\Feature\AcademicYears;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SemesterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $year = AcademicYear::factory()->create();

        $this->get(route('academic-years.semesters.edit', $year))
            ->assertRedirect(route('login'));

        $this->put(route('academic-years.semesters.update', $year), [])
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        $year = AcademicYear::factory()->create();

        foreach (['teacher', 'student', 'parent'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->get(route('academic-years.semesters.edit', $year))
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('academic-years.semesters.update', $year), [])
                ->assertForbidden();
        }
    }

    public function test_admin_can_view_semesters_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create([
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);

        $this->actingAs($admin)
            ->get(route('academic-years.semesters.edit', $year))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academic-years/semesters')
                ->has('academic_year')
                ->has('semesters', 2)
            );
    }

    public function test_admin_can_update_semester_boundaries_and_it_records_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create([
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);

        $this->actingAs($admin)
            ->put(route('academic-years.semesters.update', $year), [
                'semesters' => [
                    [
                        'number' => 1,
                        'starts_at' => '2027-07-01',
                        'ends_at' => '2027-12-20',
                    ],
                    [
                        'number' => 2,
                        'starts_at' => '2027-12-21',
                        'ends_at' => '2028-06-30',
                    ],
                ],
            ])
            ->assertRedirect(route('academic-years.semesters.edit', $year));

        $this->assertDatabaseHas('semesters', [
            'academic_year_id' => $year->id,
            'number' => 1,
            'starts_at' => '2027-07-01',
            'ends_at' => '2027-12-20',
        ]);

        $this->assertDatabaseHas('semesters', [
            'academic_year_id' => $year->id,
            'number' => 2,
            'starts_at' => '2027-12-21',
            'ends_at' => '2028-06-30',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'updated',
            'auditable_type' => $year->getMorphClass(),
            'auditable_id' => $year->id,
        ]);
    }

    /**
     * AC-15-07: Editing semesters so they overlap or leave the year's range is rejected with HTTP 422.
     */
    public function test_semesters_leaving_year_range_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create([
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);

        // Ganjil starts before year starts
        $this->actingAs($admin)
            ->put(route('academic-years.semesters.update', $year), [
                'semesters' => [
                    [
                        'number' => 1,
                        'starts_at' => '2027-06-01',
                        'ends_at' => '2027-12-31',
                    ],
                    [
                        'number' => 2,
                        'starts_at' => '2028-01-01',
                        'ends_at' => '2028-06-30',
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        // Genap ends after year ends
        $this->actingAs($admin)
            ->put(route('academic-years.semesters.update', $year), [
                'semesters' => [
                    [
                        'number' => 1,
                        'starts_at' => '2027-07-01',
                        'ends_at' => '2027-12-31',
                    ],
                    [
                        'number' => 2,
                        'starts_at' => '2028-01-01',
                        'ends_at' => '2028-07-15',
                    ],
                ],
            ])
            ->assertSessionHasErrors();
    }

    /**
     * AC-15-07: Overlap or non-contiguous semesters rejected with 422.
     */
    public function test_overlapping_or_non_contiguous_semesters_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->create([
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);

        // Overlapping: Ganjil ends on 2028-01-05 while Genap starts on 2028-01-01
        $this->actingAs($admin)
            ->put(route('academic-years.semesters.update', $year), [
                'semesters' => [
                    [
                        'number' => 1,
                        'starts_at' => '2027-07-01',
                        'ends_at' => '2028-01-05',
                    ],
                    [
                        'number' => 2,
                        'starts_at' => '2028-01-01',
                        'ends_at' => '2028-06-30',
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        // Non-contiguous gap: Ganjil ends on 2027-12-20, Genap starts on 2028-01-01 (gap > 1 day)
        $this->actingAs($admin)
            ->put(route('academic-years.semesters.update', $year), [
                'semesters' => [
                    [
                        'number' => 1,
                        'starts_at' => '2027-07-01',
                        'ends_at' => '2027-12-20',
                    ],
                    [
                        'number' => 2,
                        'starts_at' => '2028-01-01',
                        'ends_at' => '2028-06-30',
                    ],
                ],
            ])
            ->assertSessionHasErrors();
    }
}
