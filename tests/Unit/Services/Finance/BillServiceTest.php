<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Finance\BillService;
use App\Services\Finance\Exceptions\BillOperationException;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class BillServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillService $service;

    private FeeType $feeType;

    protected function setUp(): void
    {
        parent::setUp();

        // Friday 2026-07-10, 10:00 Jakarta.
        Date::setTestNow('2026-07-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->service = app(BillService::class);
        $this->feeType = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bill(array $attributes = []): Bill
    {
        return Bill::factory()->create([
            'fee_type_id' => $this->feeType->id,
            'period_key' => '2026-07',
            'amount' => '350000.00',
            'due_date' => '2026-07-10',
            ...$attributes,
        ]);
    }

    /**
     * Assert the entry's lines as [account code, debit, credit] and that it balances.
     *
     * @param  list<array{string, string, string}>  $expected
     */
    private function assertLines(JournalEntry $entry, array $expected): void
    {
        $actual = $entry->lines()->with('account')->orderBy('id')->get()
            ->map(fn ($line) => [$line->account->code, (string) $line->debit, (string) $line->credit])
            ->all();

        $this->assertSame($expected, $actual);
        $this->assertSame((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'));
    }

    private function entryFor(Bill $bill, JournalSource $source): JournalEntry
    {
        return JournalEntry::where('source_type', $source)->where('source_id', $bill->id)->with('lines')->latest('id')->firstOrFail();
    }

    public function test_recognition_posts_receivable_and_revenue_once_ac_14_13(): void
    {
        $bill = $this->bill();

        $this->assertTrue($this->service->recognize($bill));
        $this->assertFalse($this->service->recognize($bill));

        $entry = $this->entryFor($bill, JournalSource::BillRecognition);
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame('2026-07-01', $entry->entry_date->toDateString());
        $this->assertSame('2026-07-01', $bill->fresh()->recognized_at->toDateString());
        $this->assertLines($entry, [['1-1300', '350000.00', '0.00'], ['4-1100', '0.00', '350000.00']]);
        $this->assertSame($bill->student_id, $entry->lines->first()->student_id);
    }

    public function test_future_period_is_not_recognized_yet(): void
    {
        $bill = $this->bill(['period_key' => '2026-08']);

        $this->assertFalse($this->service->recognize($bill));
        $this->assertNull($bill->fresh()->recognized_at);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_discounted_recognition_posts_discount_line_ac_14_14(): void
    {
        $bill = $this->bill(['discount_amount' => '175000.00']);

        $this->service->recognize($bill);

        $this->assertLines($this->entryFor($bill, JournalSource::BillRecognition), [
            ['1-1300', '175000.00', '0.00'],
            ['4-1900', '175000.00', '0.00'],
            ['4-1100', '0.00', '350000.00'],
        ]);
    }

    public function test_opening_bill_credits_net_assets_instead_of_revenue(): void
    {
        $bill = $this->bill(['is_opening' => true, 'period_key' => '2026-06']);

        $this->service->recognize($bill);

        $this->assertLines($this->entryFor($bill, JournalSource::BillRecognition), [
            ['1-1300', '350000.00', '0.00'],
            ['3-1000', '0.00', '350000.00'],
        ]);
    }

    public function test_verified_prepaid_allocation_is_consumed_instead_of_receivable(): void
    {
        $bill = $this->bill();
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($bill)->create(['amount' => '350000.00']);

        $this->service->recognize($bill);

        $this->assertLines($this->entryFor($bill, JournalSource::BillRecognition), [
            ['2-1100', '350000.00', '0.00'],
            ['4-1100', '0.00', '350000.00'],
        ]);
    }

    public function test_recognition_in_closed_period_posts_in_next_open_period_with_original_date(): void
    {
        AccountingPeriod::create(['period' => '2026-07', 'status' => 'closed']);
        $bill = $this->bill();

        $this->service->recognize($bill);

        $entry = $this->entryFor($bill, JournalSource::BillRecognition);
        $this->assertSame('2026-08-01', $entry->entry_date->toDateString());
        $this->assertSame('Tanggal asli: 2026-07-01', $entry->lines->first()->memo);
    }

    public function test_discount_adjustment_on_recognized_bill_posts_the_difference(): void
    {
        $bill = $this->bill();
        $this->service->recognize($bill);

        $this->service->adjustDiscount($bill, '100000.00', 'Beasiswa');
        $this->assertLines($this->entryFor($bill, JournalSource::BillAdjustment), [['4-1900', '100000.00', '0.00'], ['1-1300', '0.00', '100000.00']]);

        $this->service->adjustDiscount($bill, '40000.00', 'Koreksi');
        $this->assertLines($this->entryFor($bill, JournalSource::BillAdjustment), [['4-1900', '0.00', '60000.00'], ['1-1300', '60000.00', '0.00']]);

        $fresh = $bill->fresh();
        $this->assertSame('40000.00', $fresh->discount_amount);
        $this->assertSame('Koreksi', $fresh->discount_reason);
        $this->assertSame(2, AuditLog::where('action', 'bill_discount_changed')->count());
    }

    public function test_full_discount_waives_bill_and_unrecognized_bill_posts_nothing(): void
    {
        $bill = $this->bill(['period_key' => '2026-08']);

        $this->service->adjustDiscount($bill, '350000.00', 'Beasiswa penuh');

        $this->assertSame(BillStatus::Waived, $bill->fresh()->status);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_discount_cannot_exceed_amount_or_drop_net_below_paid(): void
    {
        $bill = $this->bill(['period_key' => '2026-08']);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($bill)->create(['amount' => '200000.00']);
        $this->service->recompute($bill);

        foreach (['400000.00', '200000.00'] as $discount) {
            try {
                $this->service->adjustDiscount($bill, $discount, 'x');
                $this->fail('Expected BillOperationException');
            } catch (BillOperationException) {
                $this->assertSame('0.00', $bill->fresh()->discount_amount);
            }
        }
    }

    public function test_cancel_reverses_recognition_and_stamps_bill(): void
    {
        $bill = $this->bill(['discount_amount' => '175000.00']);
        $this->service->recognize($bill);

        $this->service->cancel($bill, 'Salah generate');

        $entry = $this->entryFor($bill, JournalSource::BillCancellation);
        $this->assertSame($this->entryFor($bill, JournalSource::BillRecognition)->id, $entry->reverses_entry_id);
        $this->assertLines($entry, [
            ['4-1100', '350000.00', '0.00'],
            ['1-1300', '0.00', '175000.00'],
            ['4-1900', '0.00', '175000.00'],
        ]);
        $fresh = $bill->fresh();
        $this->assertSame(BillStatus::Cancelled, $fresh->status);
        $this->assertSame('Salah generate', $fresh->cancel_reason);
        $this->assertSame(1, AuditLog::where('action', 'bill_cancelled')->count());
    }

    public function test_cancel_with_verified_allocation_is_rejected(): void
    {
        $bill = $this->bill();
        $this->service->recognize($bill);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($bill)->create(['amount' => '100000.00']);

        $this->expectException(BillOperationException::class);

        try {
            $this->service->cancel($bill, 'Salah');
        } finally {
            $this->assertNull($bill->fresh()->cancelled_at);
            $this->assertSame(0, JournalEntry::where('source_type', JournalSource::BillCancellation)->count());
        }
    }

    public function test_write_off_posts_bad_debt_for_outstanding_ac_14_21(): void
    {
        $bill = $this->bill(['amount' => '200000.00']);
        $this->service->recognize($bill);

        $this->service->writeOff($bill, 'Siswa pindah, tidak dapat ditagih');

        $this->assertLines($this->entryFor($bill, JournalSource::WriteOff), [['5-9100', '200000.00', '0.00'], ['1-1300', '0.00', '200000.00']]);
        $fresh = $bill->fresh();
        $this->assertSame(BillStatus::WrittenOff, $fresh->status);
        $this->assertSame('Siswa pindah, tidak dapat ditagih', $fresh->write_off_reason);
        $this->assertSame(1, AuditLog::where('action', 'bill_written_off')->count());
    }

    public function test_write_off_requires_recognized_and_not_fully_paid(): void
    {
        $unrecognized = $this->bill(['period_key' => '2026-08']);
        $paid = $this->bill(['period_key' => '2026-07', 'amount' => '100000.00']);
        $this->service->recognize($paid);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($paid)->create(['amount' => '100000.00']);

        foreach ([$unrecognized, $paid] as $bill) {
            try {
                $this->service->writeOff($bill, 'x');
                $this->fail('Expected BillOperationException');
            } catch (BillOperationException) {
                $this->assertNull($bill->fresh()->written_off_at);
            }
        }

        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::WriteOff)->count());
    }
}
