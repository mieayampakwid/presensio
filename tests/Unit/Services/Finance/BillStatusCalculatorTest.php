<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Finance\BillStatusCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BillStatusCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, BillStatus}>
     */
    public static function statusCases(): array
    {
        return [
            'nothing paid' => [[], '0.00', BillStatus::Unpaid],
            'partly paid' => [[], '100000.00', BillStatus::PartiallyPaid],
            'exactly paid' => [[], '350000.00', BillStatus::Paid],
            'overpaid still paid' => [[], '400000.00', BillStatus::Paid],
            'full scholarship is waived (AC-14-03)' => [['discount_amount' => '350000.00'], '0.00', BillStatus::Waived],
            'discounted bill paid in full' => [['discount_amount' => '175000.00'], '175000.00', BillStatus::Paid],
            'cancelled stamp wins over paid' => [['cancelled_at' => '2026-11-15 00:00:00'], '350000.00', BillStatus::Cancelled],
            'written off stamp wins over waived' => [['written_off_at' => '2026-11-15 00:00:00', 'discount_amount' => '350000.00'], '0.00', BillStatus::WrittenOff],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('statusCases')]
    public function test_status_follows_spec_order(array $attributes, string $paid, BillStatus $expected): void
    {
        $bill = Bill::factory()->make(['amount' => '350000.00', ...$attributes]);

        $this->assertSame($expected, (new BillStatusCalculator)->statusFor($bill, $paid));
    }

    public function test_only_verified_payments_count_toward_paid(): void
    {
        $bill = Bill::factory()->create(['amount' => '350000.00']);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($bill)->create(['amount' => '200000.00']);
        PaymentAllocation::factory()->for(Payment::factory()->rejected(), 'payment')->for($bill)->create(['amount' => '150000.00']);
        PaymentAllocation::factory()->for(Payment::factory(), 'payment')->for($bill)->create(['amount' => '150000.00']);
        PaymentAllocation::factory()->for(Payment::factory()->void(), 'payment')->for($bill)->create(['amount' => '150000.00']);

        $calculator = new BillStatusCalculator;

        $this->assertSame('200000.00', $calculator->paidAmount($bill));
        $this->assertSame(BillStatus::PartiallyPaid, $calculator->calculate($bill));
    }
}
