<?php

namespace Tests\Feature\finance;

use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Guardian;
use App\Models\Overpayment;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Finance\GuardianDepositReconciliation;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\OverpaymentService;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use App\Services\Finance\ReceivablesReconciliation;
use App\Services\Finance\Reports\TrialBalanceReport;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuardianDepositReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private int $bank;

    private int $liability;

    protected function setUp(): void
    {
        parent::setUp();
        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();
        $this->finance = User::factory()->finance()->create();
        $this->actingAs($this->finance);
        $this->bank = Account::where('code', '1-1200')->sole()->id;
        $this->liability = Account::where('code', '2-1200')->sole()->id;
    }

    private function receipt(): Overpayment
    {
        return app(OverpaymentService::class)->record([
            'guardian_id' => Guardian::factory()->create()->id, 'amount' => '2700000',
            'bank_mutation_date' => '2026-10-20', 'posting_date' => '2026-10-20',
            'deposit_account_id' => $this->bank, 'reason' => 'double_payment',
            'proof_path' => 'proof.pdf', 'proof_hash' => hash('sha256', 'proof'),
        ], $this->finance);
    }

    private function manual(int $debit, int $credit): void
    {
        DB::transaction(fn () => app(JournalPoster::class)->post(new PostableEntry(
            entryDate: '2026-11-20', description: 'Unmatched liability', sourceType: JournalSource::Manual,
            lines: [new PostableLine($debit, debit: '100'), new PostableLine($credit, credit: '100')],
        )));
    }

    public function test_historical_month_uses_journal_dates_and_held_liability_allows_close(): void
    {
        $record = $this->receipt();
        $before = app(GuardianDepositReconciliation::class)->asOf('2026-10-31');
        $this->assertSame('2700000.00', $before['accounts'][0]['subledger_balance']);
        $this->post(route('finance.periods.close', '2026-10'))->assertSessionHasNoErrors();
        app(OverpaymentService::class)->refund($record, ['refund_date' => '2026-11-05', 'refund_account_id' => $this->bank, 'bank_reference' => 'NOV', 'bank_fee' => '6500'], $this->finance);
        SchoolSetting::first()->update(['guardian_deposit_account_id' => null]);
        app(SchoolSettings::class)->refresh();
        $this->assertSame($before, app(GuardianDepositReconciliation::class)->asOf('2026-10-31'));
        $after = app(GuardianDepositReconciliation::class)->asOf('2026-11-30');
        $this->assertTrue($after['is_balanced']);
        $this->assertSame('0.00', $after['accounts'][0]['gl_balance']);
        $this->assertTrue(app(TrialBalanceReport::class)->generate('2026-11-30')['is_balanced']);
        $this->assertTrue(app(ReceivablesReconciliation::class)->asOf('2026-11-30')['is_balanced']);
    }

    public function test_old_settled_accounts_are_checked_and_opposite_differences_never_offset(): void
    {
        $record = $this->receipt();
        app(OverpaymentService::class)->cancel($record, '2026-10-21', 'Salah', $this->finance);
        $second = Account::factory()->create(['type' => AccountType::Liability, 'is_cash' => false]);
        SchoolSetting::first()->update(['guardian_deposit_account_id' => $second->id]);
        app(SchoolSettings::class)->refresh();
        $this->manual($second->id, $this->liability);
        Account::whereKey($this->liability)->update(['is_active' => false]);
        $result = app(GuardianDepositReconciliation::class)->asOf('2026-11-30');
        $this->assertFalse($result['is_balanced']);
        $this->assertSame('200.00', $result['total_difference']);
        $this->assertCount(2, $result['accounts']);
        $this->postJson(route('finance.periods.close', '2026-11'))->assertUnprocessable()->assertSee('Titipan Wali');
        $this->assertDatabaseHas('accounting_periods', ['period' => '2026-11', 'status' => 'open']);
        SchoolSetting::first()->update(['guardian_deposit_account_id' => null]);
        app(SchoolSettings::class)->refresh();
        $this->assertFalse(app(GuardianDepositReconciliation::class)->asOf('2026-11-30')['is_balanced']);
    }

    public function test_no_configuration_and_no_history_allows_unrelated_close(): void
    {
        SchoolSetting::first()->update(['guardian_deposit_account_id' => null]);
        app(SchoolSettings::class)->refresh();
        $result = app(GuardianDepositReconciliation::class)->asOf('2026-10-31');
        $this->assertSame([], $result['accounts']);
        $this->assertTrue($result['is_balanced']);
        $this->post(route('finance.periods.close', '2026-10'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounting_periods', ['period' => '2026-10', 'status' => 'closed']);
    }
}
