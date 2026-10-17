<?php

namespace Tests\Feature\Fees;

use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReceiptNumberGenerator;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReceiptNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_numbers_are_sequential_and_independent_per_month(): void
    {
        $generator = new ReceiptNumberGenerator;

        $numbers = [
            DB::transaction(fn () => $generator->generate(Date::parse('2026-10-05'))),
            DB::transaction(fn () => $generator->generate(Date::parse('2026-10-20'))),
            DB::transaction(fn () => $generator->generate(Date::parse('2026-11-01'))),
        ];

        $this->assertSame(['KWT-202610-00001', 'KWT-202610-00002', 'KWT-202611-00001'], $numbers);
        $this->assertDatabaseHas('receipt_sequences', ['period' => '202610', 'last_number' => 2]);
    }

    public function test_consecutive_verifications_get_distinct_consecutive_receipts_ac_14_09(): void
    {
        Date::setTestNow('2026-10-10 03:00:00');
        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $cash = Account::where('code', '1-1100')->firstOrFail()->id;
        $feeType = FeeType::factory()->create([
            'receivable_account_id' => Account::where('code', '1-1300')->firstOrFail()->id,
            'revenue_account_id' => Account::where('code', '4-1100')->firstOrFail()->id,
            'discount_account_id' => Account::where('code', '4-1900')->firstOrFail()->id,
        ]);
        $user = User::factory()->create();
        $service = app(PaymentService::class);

        $receipts = Bill::factory()->recognized('2026-10-01')->count(2)->create(['fee_type_id' => $feeType->id, 'period_key' => '2026-10'])
            ->map(fn (Bill $bill) => $service->createCash([$bill->id => '350000.00'], [$bill->student_id], $cash, $user)->receipt_number);

        $this->assertSame(['KWT-202610-00001', 'KWT-202610-00002'], $receipts->all());
    }
}
