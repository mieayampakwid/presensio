<?php

namespace Tests\Feature\Finance;

use App\Enums\JournalSource;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class YearEndClosingTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR_START = '2026-07-01';

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-09-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->finance = User::factory()->finance()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function accountId(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }

    private function journal(string $date, string $debitCode, string $creditCode, string $amount): void
    {
        DB::transaction(fn () => app(JournalPoster::class)->post(new PostableEntry(
            entryDate: $date,
            description: 'Uji',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->accountId($debitCode), debit: $amount),
                new PostableLine(accountId: $this->accountId($creditCode), credit: $amount),
            ],
        )));
    }

    /**
     * Months 1-11 of the 2026/27 fiscal year closed, June 2027 still open.
     */
    private function closeFirstElevenMonths(): void
    {
        foreach (range(0, 10) as $offset) {
            AccountingPeriod::updateOrCreate(
                ['period' => date('Y-m', strtotime(self::YEAR_START." +{$offset} month"))],
                ['status' => 'closed'],
            );
        }
    }

    private function postSurplusActivity(): void
    {
        $this->journal('2026-08-15', '1-1100', '4-1100', '1000000.00');
        $this->journal('2027-06-10', '4-1900', '1-1100', '100000.00');
        $this->journal('2027-06-20', '5-1100', '1-1100', '300000.00');
    }

    private function closeYear(): void
    {
        $this->actingAs($this->finance)->post(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START]);
    }

    private function netAssetsBalance(): string
    {
        $lines = JournalLine::where('account_id', $this->accountId('3-1000'))->get();

        return bcsub((string) $lines->sum('credit'), (string) $lines->sum('debit'), 2);
    }

    private function assertNothingClosed(): void
    {
        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::Closing)->count());
        $this->assertSame(0, AccountingPeriod::where('status', 'locked')->count());
    }

    public function test_year_end_closing_zeroes_revenue_and_expense_into_net_assets_ac_14_22(): void
    {
        $this->postSurplusActivity();
        $this->closeFirstElevenMonths();

        $this->actingAs($this->finance)
            ->post(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START])
            ->assertRedirect(route('finance.periods.index'));

        $entry = JournalEntry::where('source_type', JournalSource::Closing)->sole();
        $this->assertSame('2027-06-30', $entry->entry_date->toDateString());
        $this->assertSame(3, $entry->lines()->where('account_id', '!=', $this->accountId('3-1000'))->count());
        $this->assertSame((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'));

        foreach (['4-1100', '4-1900', '5-1100'] as $code) {
            $lines = JournalLine::where('account_id', $this->accountId($code))->get();
            $this->assertSame('0.00', bcsub((string) $lines->sum('debit'), (string) $lines->sum('credit'), 2), $code);
        }

        $this->assertSame('600000.00', $this->netAssetsBalance());
        $this->assertSame(12, AccountingPeriod::where('status', 'locked')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'year_end_close']);
    }

    public function test_deficit_year_debits_net_assets(): void
    {
        $this->journal('2026-08-15', '1-1100', '4-1100', '100000.00');
        $this->journal('2027-06-20', '5-1100', '1-1100', '400000.00');
        $this->closeFirstElevenMonths();

        $this->closeYear();

        $this->assertSame('-300000.00', $this->netAssetsBalance());
        $this->assertSame(12, AccountingPeriod::where('status', 'locked')->count());
    }

    public function test_year_without_activity_is_locked_without_posting_an_entry(): void
    {
        $this->closeFirstElevenMonths();

        $this->closeYear();

        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::Closing)->count());
        $this->assertSame(12, AccountingPeriod::where('status', 'locked')->count());
    }

    public function test_rejected_when_a_month_before_the_last_is_still_open(): void
    {
        $this->postSurplusActivity();
        $this->closeFirstElevenMonths();
        AccountingPeriod::where('period', '2026-12')->update(['status' => 'open']);

        $this->actingAs($this->finance)
            ->postJson(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START])
            ->assertUnprocessable();

        $this->assertNothingClosed();
    }

    public function test_rejected_when_net_assets_closing_account_is_not_configured(): void
    {
        $this->postSurplusActivity();
        $this->closeFirstElevenMonths();
        SchoolSetting::query()->update(['net_assets_closing_account_id' => null]);
        app(SchoolSettings::class)->refresh();

        $this->actingAs($this->finance)
            ->postJson(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START])
            ->assertUnprocessable();

        $this->assertNothingClosed();
    }

    public function test_rejected_when_the_last_month_is_already_closed(): void
    {
        $this->closeFirstElevenMonths();
        AccountingPeriod::create(['period' => '2027-06', 'status' => 'closed']);

        $this->actingAs($this->finance)
            ->postJson(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START])
            ->assertUnprocessable();

        $this->assertNothingClosed();
    }

    public function test_reconciliation_failure_on_the_last_month_rolls_everything_back(): void
    {
        $this->postSurplusActivity();
        $this->closeFirstElevenMonths();
        $feeType = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
        Bill::factory()->recognized('2026-09-01')->create(['fee_type_id' => $feeType->id, 'period_key' => '2026-09', 'amount' => '350000.00']);

        $this->actingAs($this->finance)
            ->postJson(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Rekonsiliasi piutang tidak seimbang: selisih Rp 350.000; periksa Laporan Rekonsiliasi Piutang.']);

        $this->assertNothingClosed();
        $this->assertSame('open', AccountingPeriod::findOrFail('2027-06')->status);
    }

    public function test_closing_an_already_closed_year_again_is_rejected(): void
    {
        $this->postSurplusActivity();
        $this->closeFirstElevenMonths();
        $this->closeYear();

        $this->actingAs($this->finance)
            ->postJson(route('finance.periods.year-end'), ['fiscal_year_start' => self::YEAR_START])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Tahun buku sudah ditutup.']);

        $this->assertSame(1, JournalEntry::where('source_type', JournalSource::Closing)->count());
    }

    public function test_locked_period_cannot_be_reopened_or_posted_into(): void
    {
        $this->postSurplusActivity();
        $this->closeFirstElevenMonths();
        $this->closeYear();

        $this->actingAs($this->finance)
            ->postJson(route('finance.periods.reopen', '2026-09'), ['reason' => 'Koreksi'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Periode terkunci oleh tutup buku tahunan dan tidak dapat dibuka kembali.']);
        $this->assertSame('locked', AccountingPeriod::findOrFail('2026-09')->status);

        $this->actingAs($this->finance)->postJson(route('finance.journals.store'), [
            'entry_date' => '2026-09-10',
            'description' => 'Posting ke periode terkunci',
            'action' => 'post',
            'lines' => [
                ['account_id' => $this->accountId('1-1100'), 'debit' => '1000.00', 'credit' => '0.00'],
                ['account_id' => $this->accountId('4-1100'), 'debit' => '0.00', 'credit' => '1000.00'],
            ],
        ])->assertUnprocessable();
    }

    public function test_automatic_payment_in_a_locked_period_posts_on_the_next_open_period_start(): void
    {
        $this->closeFirstElevenMonths();
        $this->closeYear();
        $feeType = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
        $bill = Bill::factory()->create([
            'student_id' => Student::factory(),
            'fee_type_id' => $feeType->id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'period_key' => '2026-09',
            'amount' => '350000.00',
            'due_date' => '2026-09-10',
            'recognized_at' => '2026-09-01',
        ]);

        $payment = app(PaymentService::class)->createCash([$bill->id => '350000.00'], [$bill->student_id], $this->accountId('1-1100'), $this->finance);

        $entry = JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->sole();
        $this->assertSame('2027-07-01', $entry->entry_date->toDateString());
    }

    public function test_only_admin_and_finance_can_close_the_year(): void
    {
        $this->closeFirstElevenMonths();
        $payload = ['fiscal_year_start' => self::YEAR_START];

        $this->actingAs(User::factory()->principal()->create())->post(route('finance.periods.year-end'), $payload)->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->post(route('finance.periods.year-end'), $payload)->assertForbidden();
        $this->assertNothingClosed();

        $this->actingAs(User::factory()->admin()->create())->post(route('finance.periods.year-end'), $payload)->assertRedirect();
        $this->assertSame(12, AccountingPeriod::where('status', 'locked')->count());
    }

    public function test_periods_page_lists_closable_years_for_finance_but_not_for_principal(): void
    {
        $this->closeFirstElevenMonths();

        $this->actingAs($this->finance)->get(route('finance.periods.index'))
            ->assertInertia(fn ($page) => $page->has('closableYears', 2)
                ->where('closableYears.0.fiscal_year_start', self::YEAR_START)
                ->where('closableYears.0.is_closable', true));

        $this->actingAs(User::factory()->principal()->create())->get(route('finance.periods.index'))
            ->assertInertia(fn ($page) => $page->has('closableYears', 0));
    }
}
