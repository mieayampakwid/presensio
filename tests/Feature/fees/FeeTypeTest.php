<?php

namespace Tests\Feature\Fees;

use App\Enums\FeeKind;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeeTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'SPP Bulanan',
            'code' => 'spp',
            'type' => FeeKind::MonthlyRecurring->value,
            'default_amount' => '350000',
            'receivable_account_id' => Account::factory()->asset()->create()->id,
            'revenue_account_id' => Account::factory()->revenue()->create()->id,
            'discount_account_id' => Account::factory()->revenue()->contra()->create()->id,
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_finance_and_principal_can_view_fee_types(): void
    {
        FeeType::factory()->create(['name' => 'SPP Bulanan']);

        foreach ([User::factory()->admin(), User::factory()->finance(), User::factory()->principal()] as $user) {
            $this->actingAs($user->create())
                ->get(route('fees.types.index'))
                ->assertOk()
                ->assertSee('SPP Bulanan');
        }
    }

    public function test_teacher_and_parent_are_forbidden(): void
    {
        foreach ([User::factory()->teacher(), User::factory()->parent()] as $user) {
            $this->actingAs($user->create())->get(route('fees.types.index'))->assertForbidden();
            $this->actingAs($user->create())->post(route('fees.types.store'), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseCount('fee_types', 0);
    }

    public function test_principal_cannot_mutate_fee_types(): void
    {
        $principal = User::factory()->principal()->create();
        $feeType = FeeType::factory()->create();

        $this->actingAs($principal)->post(route('fees.types.store'), $this->payload())->assertForbidden();
        $this->actingAs($principal)->put(route('fees.types.update', $feeType), $this->payload())->assertForbidden();
        $this->actingAs($principal)->delete(route('fees.types.destroy', $feeType))->assertForbidden();

        $this->assertDatabaseCount('fee_types', 1);
        $this->assertNotSame('SPP Bulanan', $feeType->fresh()->name);
    }

    public function test_finance_can_create_fee_type(): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->post(route('fees.types.store'), $this->payload())
            ->assertRedirect(route('fees.types.index'));

        $this->assertDatabaseHas('fee_types', ['code' => 'SPP', 'default_amount' => '350000.00', 'is_active' => true]);
    }

    public function test_admin_can_update_and_delete_unused_fee_type(): void
    {
        $admin = User::factory()->admin()->create();
        $feeType = FeeType::factory()->create();

        $this->actingAs($admin)
            ->put(route('fees.types.update', $feeType), $this->payload(['name' => 'Uang Gedung', 'type' => 'one_time']))
            ->assertRedirect(route('fees.types.index'));

        $this->assertSame('Uang Gedung', $feeType->fresh()->name);
        $this->assertSame(FeeKind::OneTime, $feeType->fresh()->type);

        $this->actingAs($admin)->delete(route('fees.types.destroy', $feeType))->assertRedirect();
        $this->assertModelMissing($feeType);
    }

    public function test_fee_type_with_bills_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $feeType = FeeType::factory()->create();
        Bill::factory()->create(['fee_type_id' => $feeType->id]);

        $this->actingAs($admin)->delete(route('fees.types.destroy', $feeType))->assertStatus(422);

        $this->assertModelExists($feeType);
    }

    public function test_code_must_be_unique(): void
    {
        $finance = User::factory()->finance()->create();
        FeeType::factory()->create(['code' => 'SPP']);

        $this->actingAs($finance)
            ->post(route('fees.types.store'), $this->payload(['code' => 'spp']))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('fee_types', 1);
    }

    #[DataProvider('invalidAmounts')]
    public function test_default_amount_must_be_positive(string $amount): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->post(route('fees.types.store'), $this->payload(['default_amount' => $amount]))
            ->assertSessionHasErrors('default_amount');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return ['zero' => ['0'], 'negative' => ['-5000']];
    }

    public function test_account_mappings_are_required_and_type_checked(): void
    {
        $finance = User::factory()->finance()->create();
        $expense = Account::factory()->expense()->create()->id;
        $plainRevenue = Account::factory()->revenue()->create()->id;

        $this->actingAs($finance)
            ->post(route('fees.types.store'), $this->payload([
                'receivable_account_id' => $expense,
                'revenue_account_id' => Account::factory()->revenue()->contra()->create()->id,
                'discount_account_id' => $plainRevenue,
            ]))
            ->assertSessionHasErrors(['receivable_account_id', 'revenue_account_id', 'discount_account_id']);

        $this->actingAs($finance)
            ->post(route('fees.types.store'), $this->payload(['receivable_account_id' => '', 'revenue_account_id' => null, 'discount_account_id' => null]))
            ->assertSessionHasErrors(['receivable_account_id', 'revenue_account_id', 'discount_account_id']);

        $this->assertDatabaseCount('fee_types', 0);
    }
}
