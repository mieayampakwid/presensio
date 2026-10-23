<?php

namespace Tests\Unit\Models;

use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Overpayment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OverpaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{string, mixed}> */
    public static function immutableFacts(): iterable
    {
        yield 'amount' => ['amount', '1.00'];
        yield 'proof' => ['proof_path', 'different.pdf'];
        yield 'date' => ['posting_date', '2026-10-25'];
        yield 'account' => ['liability_account_id', 999];
    }

    #[DataProvider('immutableFacts')]
    public function test_posted_receipt_facts_are_immutable(string $field, mixed $value): void
    {
        $record = Overpayment::factory()->create(['receipt_entry_id' => JournalEntry::factory()->create()->id]);
        $this->expectException(LogicException::class);
        $record->update([$field => $value]);
    }

    public function test_posted_receipt_cannot_be_deleted(): void
    {
        $record = Overpayment::factory()->create(['receipt_entry_id' => JournalEntry::factory()->create()->id]);
        $this->expectException(LogicException::class);
        $record->delete();
    }

    public function test_guardian_foreign_key_preserves_history(): void
    {
        $record = Overpayment::factory()->create();
        $this->expectException(QueryException::class);
        Guardian::whereKey($record->guardian_id)->delete();
    }
}
