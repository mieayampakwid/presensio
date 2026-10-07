<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\Finance\Contracts\ClosePrecondition;
use App\Services\Finance\PeriodService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->cashAccount = Account::where('code', '1-1100')->firstOrFail();
        $this->revenueAccount = Account::where('code', '4-1100')->firstOrFail();
    }

    public function test_finance_can_close_open_period_when_trial_balance_is_balanced(): void
    {
        $finance = User::factory()->finance()->create();

        $period = AccountingPeriod::create([
            'period' => '2026-09',
            'status' => 'open',
        ]);

        $response = $this->actingAs($finance)
            ->post(route('finance.periods.close', '2026-09'));

        $response->assertRedirect(route('finance.periods.index'));

        $periodFresh = $period->fresh();
        $this->assertSame('closed', $periodFresh->status);
        $this->assertNotNull($periodFresh->closed_at);
        $this->assertSame($finance->id, $periodFresh->closed_by_user_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'close_period',
            'auditable_type' => 'App\Models\AccountingPeriod',
        ]);
    }

    public function test_close_fails_when_precondition_fails(): void
    {
        $finance = User::factory()->finance()->create();

        AccountingPeriod::create([
            'period' => '2026-09',
            'status' => 'open',
        ]);

        // Register a failing precondition
        $failingPrecondition = new class implements ClosePrecondition
        {
            public function check(AccountingPeriod $period): void
            {
                throw new RuntimeException('Prasyarat rekonsiliasi gagal.');
            }
        };

        $service = app(PeriodService::class);
        $service->addPrecondition($failingPrecondition);
        $this->instance(PeriodService::class, $service);

        $response = $this->actingAs($finance)
            ->post(route('finance.periods.close', '2026-09'));

        $response->assertStatus(422);
    }

    public function test_finance_can_reopen_closed_period_with_reason(): void
    {
        $finance = User::factory()->finance()->create();

        $period = AccountingPeriod::create([
            'period' => '2026-09',
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_user_id' => $finance->id,
        ]);

        // Missing reason fails validation
        $this->actingAs($finance)
            ->post(route('finance.periods.reopen', '2026-09'), ['reason' => ''])
            ->assertSessionHasErrors(['reason']);

        // With reason succeeds
        $response = $this->actingAs($finance)
            ->post(route('finance.periods.reopen', '2026-09'), [
                'reason' => 'Koreksi audit akhir tahun',
            ]);

        $response->assertRedirect(route('finance.periods.index'));

        $periodFresh = $period->fresh();
        $this->assertSame('open', $periodFresh->status);
        $this->assertNull($periodFresh->closed_at);
        $this->assertNull($periodFresh->closed_by_user_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reopen_period',
            'reason' => 'Koreksi audit akhir tahun',
        ]);
    }

    public function test_principal_can_view_periods_but_cannot_close_or_reopen(): void
    {
        $principal = User::factory()->principal()->create();

        AccountingPeriod::create([
            'period' => '2026-09',
            'status' => 'open',
        ]);

        // Principal can view
        $this->actingAs($principal)
            ->get(route('finance.periods.index'))
            ->assertOk();

        // Principal cannot close
        $this->actingAs($principal)
            ->post(route('finance.periods.close', '2026-09'))
            ->assertForbidden();

        // Principal cannot reopen
        $this->actingAs($principal)
            ->post(route('finance.periods.reopen', '2026-09'), [
                'reason' => 'Trying to reopen',
            ])
            ->assertForbidden();
    }

    public function test_closed_period_blocks_journal_posting(): void
    {
        $finance = User::factory()->finance()->create();

        AccountingPeriod::create([
            'period' => '2026-09',
            'status' => 'closed',
        ]);

        $response = $this->actingAs($finance)->post(route('finance.journals.store'), [
            'entry_date' => '2026-09-10',
            'description' => 'Posting di bulan closed',
            'action' => 'post',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => '100000.00', 'credit' => '0.00'],
                ['account_id' => $this->revenueAccount->id, 'debit' => '0.00', 'credit' => '100000.00'],
            ],
        ]);

        $response->assertStatus(422);
    }
}
