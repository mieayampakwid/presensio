<?php

namespace Tests\Feature\Finance;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\JournalNumberGenerator;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PeriodService;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use App\Services\Finance\Reports\GeneralLedgerReport;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerReportTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->cashAccount = Account::where('code', '1-1100')->firstOrFail();
        $this->revenueAccount = Account::where('code', '4-1100')->firstOrFail();
        $this->expenseAccount = Account::where('code', '5-1300')->firstOrFail();
    }

    public function test_principal_and_finance_and_admin_can_read_all_reports(): void
    {
        $principal = User::factory()->principal()->create();
        $finance = User::factory()->finance()->create();

        $routes = [
            'finance.reports.general-journal',
            'finance.reports.general-ledger',
            'finance.reports.trial-balance',
            'finance.reports.cash-book',
        ];

        foreach ($routes as $route) {
            $this->actingAs($principal)->get(route($route))->assertOk();
            $this->actingAs($finance)->get(route($route))->assertOk();
        }
    }

    public function test_unauthorized_roles_cannot_read_reports(): void
    {
        $teacher = User::factory()->teacher()->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs($teacher)
            ->get(route('finance.reports.trial-balance'))
            ->assertForbidden();

        $this->actingAs($parent)
            ->get(route('finance.reports.trial-balance'))
            ->assertForbidden();
    }

    public function test_general_ledger_running_balance_respects_normal_balance_and_contra(): void
    {
        $poster = new JournalPoster(new PeriodService, new JournalNumberGenerator);

        // Transaction 1 on 2026-10-01: Dr Kas 500.000 / Cr Pendapatan 500.000
        DB::transaction(fn () => $poster->post(new PostableEntry(
            entryDate: '2026-10-01',
            description: 'Penerimaan 1',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->cashAccount->id, debit: '500000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '500000.00'),
            ]
        )));

        // Transaction 2 on 2026-10-03: Dr Beban 150.000 / Cr Kas 150.000
        DB::transaction(fn () => $poster->post(new PostableEntry(
            entryDate: '2026-10-03',
            description: 'Biaya Listrik',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->expenseAccount->id, debit: '150000.00'),
                new PostableLine(accountId: $this->cashAccount->id, credit: '150000.00'),
            ]
        )));

        $reportService = new GeneralLedgerReport;

        // Check Cash Account (normal balance debit)
        $cashLedger = $reportService->generate($this->cashAccount->id, '2026-10-01', '2026-10-31');
        $cashData = $cashLedger['accounts'][0];

        $this->assertSame('500000.00', $cashData['lines'][0]['running_balance']);
        $this->assertSame('350000.00', $cashData['lines'][1]['running_balance']);
        $this->assertSame('350000.00', $cashData['closing_balance']);

        // Check Revenue Account (normal balance credit)
        $revLedger = $reportService->generate($this->revenueAccount->id, '2026-10-01', '2026-10-31');
        $revData = $revLedger['accounts'][0];
        $this->assertSame('500000.00', $revData['closing_balance']);
    }

    public function test_general_ledger_lists_student_on_receivable_lines(): void
    {
        $student = Student::factory()->create(['full_name' => 'Ahmad Fauzi']);
        $receivable = Account::where('code', '1-1300')->firstOrFail();
        $poster = new JournalPoster(new PeriodService, new JournalNumberGenerator);

        DB::transaction(fn () => $poster->post(new PostableEntry(
            entryDate: '2026-10-01',
            description: 'Pengakuan SPP',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $receivable->id, debit: '350000.00', studentId: $student->id),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '350000.00'),
            ]
        )));

        $ledger = (new GeneralLedgerReport)->generate($receivable->id, '2026-10-01', '2026-10-31');

        $this->assertSame('Ahmad Fauzi', $ledger['accounts'][0]['lines'][0]['student_name']);
    }

    public function test_reports_export_csv_with_utf8_bom(): void
    {
        $finance = User::factory()->finance()->create();

        $routes = [
            route('finance.reports.general-journal', ['format' => 'csv']),
            route('finance.reports.general-ledger', ['format' => 'csv']),
            route('finance.reports.trial-balance', ['format' => 'csv']),
            route('finance.reports.cash-book', ['format' => 'csv']),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($finance)->get($url);
            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

            $content = $response->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        }
    }
}
