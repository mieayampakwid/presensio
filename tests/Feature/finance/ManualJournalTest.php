<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualJournalTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashAccount = Account::factory()->create([
            'code' => '1-1100',
            'name' => 'Kas Tunai',
            'type' => AccountType::Asset,
            'is_postable' => true,
            'is_cash' => true,
            'is_active' => true,
        ]);

        $this->expenseAccount = Account::factory()->create([
            'code' => '5-1300',
            'name' => 'Beban Listrik',
            'type' => AccountType::Expense,
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    public function test_finance_can_save_manual_journal_as_draft(): void
    {
        $finance = User::factory()->finance()->create();

        $response = $this->actingAs($finance)->post(route('finance.journals.store'), [
            'entry_date' => '2026-10-05',
            'description' => 'Draf Pembayaran Listrik',
            'action' => 'draft',
            'lines' => [
                ['account_id' => $this->expenseAccount->id, 'debit' => '450000.00', 'credit' => '0.00'],
                ['account_id' => $this->cashAccount->id, 'debit' => '0.00', 'credit' => '450000.00'],
            ],
        ]);

        $entry = JournalEntry::where('description', 'Draf Pembayaran Listrik')->first();
        $this->assertNotNull($entry);
        $this->assertSame(JournalStatus::Draft, $entry->status);
        $this->assertNull($entry->number);
        $response->assertRedirect(route('finance.journals.show', $entry));
    }

    public function test_finance_can_post_manual_journal_directly(): void
    {
        $finance = User::factory()->finance()->create();

        $response = $this->actingAs($finance)->post(route('finance.journals.store'), [
            'entry_date' => '2026-10-05',
            'description' => 'Pembayaran Listrik Tunai',
            'action' => 'post',
            'lines' => [
                ['account_id' => $this->expenseAccount->id, 'debit' => '450000.00', 'credit' => '0.00'],
                ['account_id' => $this->cashAccount->id, 'debit' => '0.00', 'credit' => '450000.00'],
            ],
        ]);

        $entry = JournalEntry::where('description', 'Pembayaran Listrik Tunai')->first();
        $this->assertNotNull($entry);
        $this->assertSame(JournalStatus::Posted, $entry->status);
        $this->assertSame('JU-202610-00001', $entry->number);
        $response->assertRedirect(route('finance.journals.show', $entry));
    }

    public function test_posting_unbalanced_manual_journal_returns_422_ac_14_16(): void
    {
        $finance = User::factory()->finance()->create();

        $response = $this->actingAs($finance)->post(route('finance.journals.store'), [
            'entry_date' => '2026-10-05',
            'description' => 'Jurnal Tidak Seimbang',
            'action' => 'post',
            'lines' => [
                ['account_id' => $this->expenseAccount->id, 'debit' => '500000.00', 'credit' => '0.00'],
                ['account_id' => $this->cashAccount->id, 'debit' => '0.00', 'credit' => '400000.00'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('journal_entries', [
            'description' => 'Jurnal Tidak Seimbang',
        ]);
    }

    public function test_posting_manual_journal_in_closed_period_returns_422_ac_14_18(): void
    {
        $finance = User::factory()->finance()->create();
        AccountingPeriod::create(['period' => '2026-09', 'status' => 'closed']);

        $response = $this->actingAs($finance)->post(route('finance.journals.store'), [
            'entry_date' => '2026-09-15',
            'description' => 'Posting di Bulan Tertutup',
            'action' => 'post',
            'lines' => [
                ['account_id' => $this->expenseAccount->id, 'debit' => '100000.00', 'credit' => '0.00'],
                ['account_id' => $this->cashAccount->id, 'debit' => '0.00', 'credit' => '100000.00'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_finance_can_post_existing_draft_journal(): void
    {
        $finance = User::factory()->finance()->create();

        $draft = JournalEntry::factory()->draft()->create([
            'entry_date' => '2026-10-05',
            'description' => 'Draf untuk Diposting',
            'source_type' => JournalSource::Manual,
        ]);

        JournalLine::factory()->create([
            'journal_entry_id' => $draft->id,
            'account_id' => $this->expenseAccount->id,
            'debit' => '200000.00',
            'credit' => '0.00',
        ]);
        JournalLine::factory()->create([
            'journal_entry_id' => $draft->id,
            'account_id' => $this->cashAccount->id,
            'debit' => '0.00',
            'credit' => '200000.00',
        ]);

        $response = $this->actingAs($finance)->post(route('finance.journals.post', $draft));

        $posted = JournalEntry::where('description', 'Draf untuk Diposting')->first();
        $this->assertNotNull($posted);
        $this->assertSame(JournalStatus::Posted, $posted->status);
        $this->assertSame('JU-202610-00001', $posted->number);
        $response->assertRedirect(route('finance.journals.show', $posted));
    }

    public function test_finance_can_reverse_posted_journal_with_reason(): void
    {
        $finance = User::factory()->finance()->create();

        $posted = DB::transaction(fn () => app(JournalPoster::class)->post(
            new PostableEntry(
                entryDate: '2026-10-05',
                description: 'Transaksi Asli',
                sourceType: JournalSource::Manual,
                lines: [
                    new PostableLine(accountId: $this->expenseAccount->id, debit: '150000.00'),
                    new PostableLine(accountId: $this->cashAccount->id, credit: '150000.00'),
                ]
            )
        ));

        // Missing reason fails validation
        $this->actingAs($finance)->post(route('finance.journals.reverse', $posted), [
            'reason' => '',
        ])->assertSessionHasErrors(['reason']);

        // With reason succeeds
        $this->actingAs($finance)->post(route('finance.journals.reverse', $posted), [
            'reason' => 'Koreksi kesalahan input',
            'date' => '2026-10-06',
        ])->assertRedirect();

        $reversal = JournalEntry::where('reverses_entry_id', $posted->id)->first();
        $this->assertNotNull($reversal);
        $this->assertSame(JournalStatus::Posted, $reversal->status);
        $this->assertSame('JU-202610-00002', $reversal->number);
        $this->assertStringContainsString('Koreksi kesalahan input', $reversal->description);
    }

    public function test_principal_can_view_journals_but_cannot_post_or_create_ac_14_24(): void
    {
        $principal = User::factory()->principal()->create();

        $journal = JournalEntry::factory()->create([
            'status' => JournalStatus::Posted,
        ]);

        // Principal can view index & show
        $this->actingAs($principal)
            ->get(route('finance.journals.index'))
            ->assertOk();

        $this->actingAs($principal)
            ->get(route('finance.journals.show', $journal))
            ->assertOk();

        // Principal cannot create or post
        $this->actingAs($principal)
            ->post(route('finance.journals.store'), [
                'entry_date' => '2026-10-05',
                'description' => 'Unauthorized journal',
                'action' => 'post',
                'lines' => [
                    ['account_id' => $this->expenseAccount->id, 'debit' => '100.00', 'credit' => '0.00'],
                    ['account_id' => $this->cashAccount->id, 'debit' => '0.00', 'credit' => '100.00'],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('finance.journals.reverse', $journal), [
                'reason' => 'Unauthorized reverse',
            ])
            ->assertForbidden();
    }

    public function test_journal_attachment_upload_and_download(): void
    {
        Storage::fake('local');
        $finance = User::factory()->finance()->create();

        $file = UploadedFile::fake()->create('nota-listrik.pdf', 100, 'application/pdf');

        $this->actingAs($finance)->post(route('finance.journals.store'), [
            'entry_date' => '2026-10-05',
            'description' => 'Pembayaran dengan Lampiran',
            'action' => 'post',
            'attachment' => $file,
            'lines' => [
                ['account_id' => $this->expenseAccount->id, 'debit' => '50000.00', 'credit' => '0.00'],
                ['account_id' => $this->cashAccount->id, 'debit' => '0.00', 'credit' => '50000.00'],
            ],
        ]);

        $entry = JournalEntry::where('description', 'Pembayaran dengan Lampiran')->first();
        $this->assertNotNull($entry->attachment_path);
        Storage::disk('local')->assertExists($entry->attachment_path);

        $downloadResponse = $this->actingAs($finance)
            ->get(route('finance.journals.attachment', $entry));

        $downloadResponse->assertOk();
    }
}
