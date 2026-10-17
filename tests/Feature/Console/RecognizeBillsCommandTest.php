<?php

namespace Tests\Feature\Console;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class RecognizeBillsCommandTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $feeType;

    protected function setUp(): void
    {
        parent::setUp();

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->feeType = FeeType::factory()->create([
            'receivable_account_id' => Account::where('code', '1-1300')->value('id'),
            'revenue_account_id' => Account::where('code', '4-1100')->value('id'),
            'discount_account_id' => Account::where('code', '4-1900')->value('id'),
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function bill(string $periodKey, array $attributes = []): Bill
    {
        return Bill::factory()->create([
            'fee_type_id' => $this->feeType->id,
            'period_key' => $periodKey,
            'amount' => '350000.00',
            ...$attributes,
        ]);
    }

    public function test_command_recognizes_due_bills_on_school_date_and_is_idempotent_ac_14_13(): void
    {
        // 2026-08-01 01:00 Jakarta is still 2026-07-31 in UTC.
        Date::setTestNow('2026-07-31 18:00:00');
        $july = $this->bill('2026-07');
        $august = $this->bill('2026-08');
        $september = $this->bill('2026-09');
        $cancelled = $this->bill('2026-08', ['cancelled_at' => now(), 'cancel_reason' => 'x']);

        $this->artisan('finance:recognize-bills')->assertSuccessful()->expectsOutput('Recognized 2 bills.');
        $this->artisan('finance:recognize-bills')->assertSuccessful()->expectsOutput('Recognized 0 bills.');

        $this->assertSame('2026-07-01', $july->fresh()->recognized_at->toDateString());
        $this->assertSame('2026-08-01', $august->fresh()->recognized_at->toDateString());
        $this->assertNull($september->fresh()->recognized_at);
        $this->assertNull($cancelled->fresh()->recognized_at);
        $this->assertSame(2, JournalEntry::where('source_type', JournalSource::BillRecognition)->count());

        $entry = JournalEntry::where('source_id', $august->id)->with('lines')->sole();
        $this->assertSame('2026-08-01', $entry->entry_date->toDateString());
        $this->assertEquals(350000.0, (float) $entry->lines->sum('debit'));
        $this->assertEquals(350000.0, (float) $entry->lines->sum('credit'));
    }
}
