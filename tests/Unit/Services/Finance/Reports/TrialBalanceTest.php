<?php

namespace Tests\Unit\Services\Finance\Reports;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Services\Finance\JournalNumberGenerator;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PeriodService;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use App\Services\Finance\Reports\TrialBalanceReport;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_is_balanced_after_random_sequence_of_posted_entries(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $poster = new JournalPoster(new PeriodService, new JournalNumberGenerator);
        $postableAccounts = Account::where('is_postable', true)->get();

        $this->assertGreaterThan(5, $postableAccounts->count());

        // Post 10 random balanced entries
        for ($i = 0; $i < 10; $i++) {
            $accountA = $postableAccounts->random();
            $accountB = $postableAccounts->where('id', '!=', $accountA->id)->random();

            $amount = sprintf('%d.00', rand(10000, 1000000));

            DB::transaction(fn () => $poster->post(new PostableEntry(
                entryDate: '2026-10-05',
                description: "Random Transaction #{$i}",
                sourceType: JournalSource::Manual,
                lines: [
                    new PostableLine(accountId: $accountA->id, debit: $amount),
                    new PostableLine(accountId: $accountB->id, credit: $amount),
                ]
            )));
        }

        $report = new TrialBalanceReport;
        $result = $report->generate();

        $this->assertTrue($result['is_balanced'], 'Trial balance must be balanced after random posted entries');
        $this->assertSame(0, Money::cmp($result['total_debit'], $result['total_credit']));
        $this->assertSame('0.00', $result['difference']);
        $this->assertTrue(Money::gt($result['total_debit'], '0.00'));
    }
}
