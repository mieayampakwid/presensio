<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentFeeDiscount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'student_id' => Student::factory()->create()->id,
            'fee_type_id' => FeeType::factory()->create()->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'percent' => '50',
            'fixed_amount' => null,
            'reason' => 'Beasiswa prestasi',
            ...$overrides,
        ];
    }

    public function test_admin_finance_and_principal_can_view_discounts(): void
    {
        $discount = StudentFeeDiscount::factory()->create(['reason' => 'Yatim']);

        foreach ([User::factory()->admin(), User::factory()->finance(), User::factory()->principal()] as $user) {
            $this->actingAs($user->create())
                ->get(route('fees.discounts.index'))
                ->assertOk()
                ->assertSee($discount->student->full_name);
        }
    }

    public function test_teacher_and_parent_are_forbidden(): void
    {
        foreach ([User::factory()->teacher(), User::factory()->parent()] as $user) {
            $this->actingAs($user->create())->get(route('fees.discounts.index'))->assertForbidden();
            $this->actingAs($user->create())->post(route('fees.discounts.store'), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseCount('student_fee_discounts', 0);
    }

    public function test_principal_cannot_mutate_discounts(): void
    {
        $principal = User::factory()->principal()->create();
        $discount = StudentFeeDiscount::factory()->create();

        $this->actingAs($principal)->post(route('fees.discounts.store'), $this->payload())->assertForbidden();
        $this->actingAs($principal)->put(route('fees.discounts.update', $discount), $this->payload())->assertForbidden();
        $this->actingAs($principal)->delete(route('fees.discounts.destroy', $discount))->assertForbidden();

        $this->assertDatabaseCount('student_fee_discounts', 1);
        $this->assertSame('Beasiswa prestasi', $discount->fresh()->reason);
    }

    public function test_finance_can_create_percent_and_fixed_discounts(): void
    {
        $finance = User::factory()->finance()->create();
        $payload = $this->payload();

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $payload)
            ->assertRedirect(route('fees.discounts.index'));

        $this->assertDatabaseHas('student_fee_discounts', [
            'student_id' => $payload['student_id'],
            'percent' => '50.00',
            'fixed_amount' => null,
        ]);

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $this->payload(['percent' => null, 'fixed_amount' => '100000']))
            ->assertRedirect(route('fees.discounts.index'));

        $this->assertDatabaseHas('student_fee_discounts', ['percent' => null, 'fixed_amount' => '100000.00']);
    }

    public function test_exactly_one_of_percent_or_fixed_amount_is_required(): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $this->payload(['percent' => null, 'fixed_amount' => null]))
            ->assertSessionHasErrors('percent');

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $this->payload(['percent' => '10', 'fixed_amount' => '50000']))
            ->assertSessionHasErrors('percent');

        $this->assertDatabaseCount('student_fee_discounts', 0);
    }

    public function test_percent_must_be_within_zero_and_one_hundred(): void
    {
        $finance = User::factory()->finance()->create();

        foreach (['100.01', '-1'] as $percent) {
            $this->actingAs($finance)
                ->post(route('fees.discounts.store'), $this->payload(['percent' => $percent]))
                ->assertSessionHasErrors('percent');
        }

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $this->payload(['percent' => '100']))
            ->assertSessionHasNoErrors();
    }

    public function test_reason_is_required(): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $this->payload(['reason' => '']))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('student_fee_discounts', 0);
    }

    public function test_duplicate_student_fee_type_year_is_a_validation_error(): void
    {
        $finance = User::factory()->finance()->create();
        $existing = StudentFeeDiscount::factory()->create();

        $this->actingAs($finance)
            ->post(route('fees.discounts.store'), $this->payload([
                'student_id' => $existing->student_id,
                'fee_type_id' => $existing->fee_type_id,
                'academic_year_id' => $existing->academic_year_id,
            ]))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('student_fee_discounts', 1);
    }

    public function test_admin_can_switch_discount_to_fixed_and_delete_it(): void
    {
        $admin = User::factory()->admin()->create();
        $discount = StudentFeeDiscount::factory()->create();

        $this->actingAs($admin)
            ->put(route('fees.discounts.update', $discount), [
                'student_id' => $discount->student_id,
                'fee_type_id' => $discount->fee_type_id,
                'academic_year_id' => $discount->academic_year_id,
                'percent' => null,
                'fixed_amount' => '75000',
                'reason' => 'Saudara kandung',
            ])
            ->assertRedirect(route('fees.discounts.index'));

        $discount->refresh();
        $this->assertNull($discount->percent);
        $this->assertSame('75000.00', $discount->fixed_amount);
        $this->assertSame('Saudara kandung', $discount->reason);

        $this->actingAs($admin)->delete(route('fees.discounts.destroy', $discount))->assertRedirect();
        $this->assertModelMissing($discount);
    }
}
