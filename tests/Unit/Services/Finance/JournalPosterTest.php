<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\Finance\Exceptions\ClosedPeriodException;
use App\Services\Finance\Exceptions\InvalidLineException;
use App\Services\Finance\Exceptions\UnbalancedEntryException;
use App\Services\Finance\JournalNumberGenerator;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PeriodService;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class JournalPosterTest extends TestCase
{
    use RefreshDatabase;

    private JournalPoster $poster;

    private Account $cashAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poster = new JournalPoster(
            new PeriodService,
            new JournalNumberGenerator
        );

        $this->cashAccount = Account::factory()->create([
            'code' => '1-1100',
            'name' => 'Kas Tunai',
            'type' => AccountType::Asset,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::factory()->create([
            'code' => '4-1100',
            'name' => 'Pendapatan SPP',
            'type' => AccountType::Revenue,
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    public function test_post_happy_path_creates_balanced_entry_and_lines(): void
    {
        $entry = new PostableEntry(
            entryDate: '2026-10-05',
            description: 'Penerimaan SPP Tunai',
            sourceType: JournalSource::Payment,
            lines: [
                new PostableLine(accountId: $this->cashAccount->id, debit: '350000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '350000.00'),
            ]
        );

        $posted = DB::transaction(fn () => $this->poster->post($entry));

        $this->assertSame(JournalStatus::Posted, $posted->status);
        $this->assertSame('JU-202610-00001', $posted->number);
        $this->assertSame('Penerimaan SPP Tunai', $posted->description);
        $this->assertCount(2, $posted->lines);
        $this->assertTrue($posted->isBalanced());

        $this->assertDatabaseHas('journal_entries', [
            'id' => $posted->id,
            'number' => 'JU-202610-00001',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $posted->id,
            'account_id' => $this->cashAccount->id,
            'debit' => '350000.00',
            'credit' => '0.00',
        ]);
    }

    public function test_post_must_be_called_inside_an_active_transaction(): void
    {
        $entry = new PostableEntry(
            entryDate: '2026-10-05',
            description: 'Direct Post without transaction',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->cashAccount->id, debit: '1000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '1000.00'),
            ]
        );

        $level = DB::transactionLevel();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('JournalPoster::post must be called inside an active database transaction.');
            $this->poster->post($entry);
        } finally {
            for ($i = 0; $i < $level; $i++) {
                DB::beginTransaction();
            }
        }
    }

    public function test_unbalanced_entry_is_rejected_and_writes_nothing(): void
    {
        $entry = new PostableEntry(
            entryDate: '2026-10-05',
            description: 'Unbalanced',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->cashAccount->id, debit: '500000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '400000.00'),
            ]
        );

        try {
            DB::transaction(fn () => $this->poster->post($entry));
            $this->fail('Expected UnbalancedEntryException');
        } catch (UnbalancedEntryException $e) {
            $this->assertStringContainsString('Jurnal tidak seimbang', $e->getMessage());
        }

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_two_sided_line_is_rejected(): void
    {
        $entry = new PostableEntry(
            entryDate: '2026-10-05',
            description: 'Two sided line',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->cashAccount->id, debit: '100000.00', credit: '50000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '50000.00'),
            ]
        );

        $this->expectException(InvalidLineException::class);
        $this->expectExceptionMessage('tidak boleh memiliki debit dan kredit sekaligus');

        DB::transaction(fn () => $this->poster->post($entry));
    }

    public function test_non_postable_or_inactive_account_is_rejected(): void
    {
        $nonPostable = Account::factory()->nonPostable()->create();

        $entry = new PostableEntry(
            entryDate: '2026-10-05',
            description: 'Post to group account',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $nonPostable->id, debit: '10000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '10000.00'),
            ]
        );

        $this->expectException(InvalidLineException::class);
        $this->expectExceptionMessage('bukan akun postable');

        DB::transaction(fn () => $this->poster->post($entry));
    }

    public function test_closed_period_is_rejected(): void
    {
        AccountingPeriod::create(['period' => '2026-09', 'status' => 'closed']);

        $entry = new PostableEntry(
            entryDate: '2026-09-15',
            description: 'Post into closed September',
            sourceType: JournalSource::Manual,
            lines: [
                new PostableLine(accountId: $this->cashAccount->id, debit: '10000.00'),
                new PostableLine(accountId: $this->revenueAccount->id, credit: '10000.00'),
            ]
        );

        $this->expectException(ClosedPeriodException::class);
        $this->expectExceptionMessage('Periode akuntansi untuk tanggal 2026-09-15 telah ditutup.');

        DB::transaction(fn () => $this->poster->post($entry));
    }

    public function test_reversal_mirrors_lines_and_leaves_original_unchanged(): void
    {
        $original = DB::transaction(function () {
            return $this->poster->post(new PostableEntry(
                entryDate: '2026-10-01',
                description: 'Original receipt',
                sourceType: JournalSource::Payment,
                lines: [
                    new PostableLine(accountId: $this->cashAccount->id, debit: '250000.00'),
                    new PostableLine(accountId: $this->revenueAccount->id, credit: '250000.00'),
                ]
            ));
        });

        $reversal = DB::transaction(function () use ($original) {
            return $this->poster->reverse(
                original: $original,
                on: Carbon::parse('2026-10-05'),
                reason: 'Salah input nominal'
            );
        });

        $this->assertSame($original->id, $reversal->reverses_entry_id);
        $this->assertSame(JournalSource::PaymentVoid, $reversal->source_type);
        $this->assertSame('JU-202610-00002', $reversal->number);

        // Original is completely unchanged
        $originalFresh = $original->fresh(['lines']);
        $this->assertSame(JournalStatus::Posted, $originalFresh->status);
        $this->assertSame('250000.00', $originalFresh->lines->where('account_id', $this->cashAccount->id)->first()->debit);

        // Reversal lines are mirrored: Cash credited, Revenue debited
        $reversalCashLine = $reversal->lines->where('account_id', $this->cashAccount->id)->first();
        $reversalRevenueLine = $reversal->lines->where('account_id', $this->revenueAccount->id)->first();

        $this->assertSame('0.00', $reversalCashLine->debit);
        $this->assertSame('250000.00', $reversalCashLine->credit);

        $this->assertSame('250000.00', $reversalRevenueLine->debit);
        $this->assertSame('0.00', $reversalRevenueLine->credit);
    }

    public function test_reversal_into_closed_period_lands_in_next_open_period(): void
    {
        $original = DB::transaction(function () {
            return $this->poster->post(new PostableEntry(
                entryDate: '2026-09-01',
                description: 'September payment',
                sourceType: JournalSource::Payment,
                lines: [
                    new PostableLine(accountId: $this->cashAccount->id, debit: '100000.00'),
                    new PostableLine(accountId: $this->revenueAccount->id, credit: '100000.00'),
                ]
            ));
        });

        // Close September
        AccountingPeriod::updateOrCreate(['period' => '2026-09'], ['status' => 'closed']);

        // Void in September, but September is now closed
        $reversal = DB::transaction(function () use ($original) {
            return $this->poster->reverse(
                original: $original,
                on: Carbon::parse('2026-09-25'),
                reason: 'Pembatalan transaksi bulan lalu'
            );
        });

        // Lands on 2026-10-01
        $this->assertSame('2026-10-01', $reversal->entry_date->toDateString());
        $this->assertStringContainsString('Tanggal asli: 2026-09-25', $reversal->lines->first()->memo);
    }
}
