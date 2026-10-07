<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\JournalNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JournalNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_numbers_are_sequential_and_gap_free_per_period(): void
    {
        $generator = new JournalNumberGenerator;

        $num1 = DB::transaction(fn () => $generator->generate('2026-10-05'));
        $num2 = DB::transaction(fn () => $generator->generate('2026-10-06'));
        $num3 = DB::transaction(fn () => $generator->generate('2026-10-15'));

        $this->assertSame('JU-202610-00001', $num1);
        $this->assertSame('JU-202610-00002', $num2);
        $this->assertSame('JU-202610-00003', $num3);

        $this->assertDatabaseHas('journal_sequences', [
            'period' => '202610',
            'last_number' => 3,
        ]);
    }

    public function test_different_periods_have_independent_sequences(): void
    {
        $generator = new JournalNumberGenerator;

        $octNum = DB::transaction(fn () => $generator->generate('2026-10-20'));
        $novNum = DB::transaction(fn () => $generator->generate('2026-11-01'));
        $octNum2 = DB::transaction(fn () => $generator->generate('2026-10-25'));

        $this->assertSame('JU-202610-00001', $octNum);
        $this->assertSame('JU-202611-00001', $novNum);
        $this->assertSame('JU-202610-00002', $octNum2);
    }
}
