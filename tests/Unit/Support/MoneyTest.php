<?php

namespace Tests\Unit\Support;

use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_add_adds_without_floating_point_drift(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('350000.00', Money::add('200000.00', '150000.00'));
        $this->assertSame('1500.00', Money::add(1000, 500));
        $this->assertSame('0.00', Money::add('100.00', '-100.00'));
    }

    public function test_money_sub_subtracts_correctly(): void
    {
        $this->assertSame('50000.00', Money::sub('350000.00', '300000.00'));
        $this->assertSame('-50000.00', Money::sub('300000.00', '350000.00'));
    }

    public function test_money_cmp_and_comparisons(): void
    {
        $this->assertSame(0, Money::cmp('100.00', '100'));
        $this->assertSame(1, Money::cmp('100.01', '100.00'));
        $this->assertSame(-1, Money::cmp('99.99', '100.00'));

        $this->assertTrue(Money::gt('10.00', '5.00'));
        $this->assertFalse(Money::gt('5.00', '10.00'));
        $this->assertTrue(Money::gte('10.00', '10.00'));

        $this->assertTrue(Money::lt('5.00', '10.00'));
        $this->assertTrue(Money::lte('5.00', '5.00'));
    }

    public function test_money_is_zero(): void
    {
        $this->assertTrue(Money::isZero('0.00'));
        $this->assertTrue(Money::isZero('0'));
        $this->assertTrue(Money::isZero(null));
        $this->assertFalse(Money::isZero('0.01'));
        $this->assertFalse(Money::isZero('-0.01'));
    }

    public function test_money_format_indonesian_rupiah(): void
    {
        $this->assertSame('Rp 350.000', Money::format('350000.00'));
        $this->assertSame('Rp 350.000,50', Money::format('350000.50'));
        $this->assertSame('350.000', Money::format('350000.00', false));
        $this->assertSame('-Rp 50.000', Money::format('-50000.00'));
    }

    public function test_posted_journal_entry_cannot_be_updated_or_deleted(): void
    {
        $entry = JournalEntry::factory()->create([
            'status' => JournalStatus::Posted,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Posted journal entries cannot be modified.');
        $entry->update(['description' => 'Tampered description']);
    }

    public function test_posted_journal_entry_cannot_be_deleted(): void
    {
        $entry = JournalEntry::factory()->create([
            'status' => JournalStatus::Posted,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Posted journal entries cannot be deleted.');
        $entry->delete();
    }

    public function test_draft_journal_entry_can_be_updated_and_deleted(): void
    {
        $entry = JournalEntry::factory()->draft()->create([
            'description' => 'Original Draft',
        ]);

        $entry->update(['description' => 'Updated Draft']);
        $this->assertSame('Updated Draft', $entry->fresh()->description);

        $entry->delete();
        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    public function test_posted_journal_line_cannot_be_updated_or_deleted(): void
    {
        $account = Account::factory()->create();
        $entry = JournalEntry::factory()->create([
            'status' => JournalStatus::Posted,
        ]);

        $line = JournalLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => '100000.00',
            'credit' => '0.00',
        ]);

        try {
            $line->update(['debit' => '200000.00']);
            $this->fail('Expected RuntimeException on updating posted line');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Posted journal lines cannot be modified.', $e->getMessage());
        }

        try {
            $line->delete();
            $this->fail('Expected RuntimeException on deleting posted line');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Posted journal lines cannot be deleted.', $e->getMessage());
        }
    }
}
