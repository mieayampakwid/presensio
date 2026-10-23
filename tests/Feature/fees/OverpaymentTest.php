<?php

namespace Tests\Feature\fees;

use App\Enums\JournalSource;
use App\Enums\OverpaymentStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Overpayment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\BillService;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Services\Finance\GuardianDepositReconciliation;
use App\Services\Finance\OverpaymentService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReceivablesReconciliation;
use App\Services\Finance\Reports\CashBookReport;
use App\Services\Finance\Reports\GeneralLedgerReport;
use App\Services\SchoolSettings;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OverpaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private Guardian $guardian;

    private int $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-10-23 03:00:00');
        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();
        Storage::fake('local');
        $this->finance = User::factory()->finance()->create();
        $this->guardian = Guardian::factory()->create();
        $this->bank = Account::where('code', '1-1200')->sole()->id;
        $this->actingAs($this->finance);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'guardian_id' => $this->guardian->id, 'amount' => '2700000.00',
            'bank_mutation_date' => '2026-10-20', 'posting_date' => '2026-10-20',
            'deposit_account_id' => $this->bank, 'reason' => 'wrong_amount',
            'proof' => UploadedFile::fake()->image('bank.png'),
        ], $overrides);
    }

    private function record(array $overrides = []): Overpayment
    {
        $this->post(route('fees.overpayments.store'), $this->payload($overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Overpayment::latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function refundData(array $overrides = []): array
    {
        return array_replace(['refund_date' => '2026-10-22', 'refund_account_id' => $this->bank,
            'bank_fee' => '6500.00', 'bank_reference' => 'REF-123'], $overrides);
    }

    public function test_receipt_and_guardian_fee_refund_have_exact_lines_and_audit_values(): void
    {
        $record = $this->record();
        $this->assertSame(OverpaymentStatus::Held, $record->status);
        $receipt = $record->receiptEntry;
        $this->assertSame(JournalSource::OverpaymentReceipt, $receipt->source_type);
        $this->assertSame('2026-10-20', $receipt->entry_date->toDateString());
        $this->assertSame([[$this->bank, '2700000.00', '0.00'], [$record->liability_account_id, '0.00', '2700000.00']], $receipt->lines->map(fn ($l) => [$l->account_id, $l->debit, $l->credit])->all());
        $this->post(route('fees.overpayments.refund', $record), $this->refundData(['proof' => UploadedFile::fake()->image('refund.png')]))->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(OverpaymentStatus::Refunded, $record->status);
        $this->assertSame('2693500.00', $record->net_refund);
        $this->assertSame('6500.00', $record->bank_fee);
        $this->assertSame('REF-123', $record->bank_reference);
        $this->assertSame([
            [$record->liability_account_id, '2700000.00', '0.00', null],
            [$this->bank, '0.00', '2693500.00', 'Dana bersih ke wali'],
            [$this->bank, '0.00', '6500.00', 'Biaya bank ditanggung wali'],
        ], $record->refundEntry->lines->map(fn ($l) => [$l->account_id, $l->debit, $l->credit, $l->memo])->all());
        $audit = AuditLog::where('action', 'overpayment_refunded')->sole();
        $this->assertSame('2693500.00', $audit->new_values['net_refund']);
        $this->assertSame('2700000.00', $audit->new_values['bank_total']);
        $this->assertSame($this->finance->id, $audit->user_id);
        $this->get(route('fees.overpayments.proof', [$record, 'receipt']))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('fees.overpayments.proof', [$record, 'refund']))->assertOk();
        $this->assertSame(2, AuditLog::where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)->count());
    }

    public function test_zero_fee_omits_zero_line_and_default_changes_do_not_move_history(): void
    {
        $record = $this->record();
        SchoolSetting::first()->update(['guardian_deposit_account_id' => null]);
        app(SchoolSettings::class)->refresh();
        $this->post(route('fees.overpayments.refund', $record), $this->refundData(['bank_fee' => '0']))->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(2, $record->refundEntry->lines()->count());
        $this->assertSame('2700000.00', $record->net_refund);
        $this->assertSame($record->liability_account_id, $record->refundEntry->lines()->where('debit', '>', 0)->sole()->account_id);
        $this->assertTrue(app(GuardianDepositReconciliation::class)->asOf('2026-10-31')['is_balanced']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRefunds(): iterable
    {
        yield 'before receipt' => [['refund_date' => '2026-10-19']];
        yield 'negative fee' => [['bank_fee' => '-1']];
        yield 'entire fee' => [['bank_fee' => '2700000']];
        yield 'excess fee' => [['bank_fee' => '2700001']];
        yield 'spoofed net' => [['net_refund' => '2700000']];
        yield 'spoofed bank total' => [['bank_total' => '2706500']];
        yield 'missing reference' => [['bank_reference' => '']];
        yield 'fraction of cent' => [['bank_fee' => '1.001']];
    }

    #[DataProvider('invalidRefunds')]
    public function test_invalid_refund_is_atomic_and_cleans_new_proof(array $changes): void
    {
        $record = $this->record();
        $files = Storage::disk('local')->allFiles();
        $this->postJson(route('fees.overpayments.refund', $record), $this->refundData([...$changes, 'proof' => UploadedFile::fake()->image('new.png')]))->assertUnprocessable();
        $this->assertSame(OverpaymentStatus::Held, $record->refresh()->status);
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(1, AuditLog::where('auditable_type', $record->getMorphClass())->count());
        $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    public function test_closed_period_receipt_and_refund_reject_but_cancel_moves_forward(): void
    {
        AccountingPeriod::create(['period' => '2026-09', 'status' => 'closed']);
        $this->postJson(route('fees.overpayments.store'), $this->payload(['bank_mutation_date' => '2026-09-20', 'posting_date' => '2026-09-20']))->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $record = $this->record(['bank_mutation_date' => '2026-09-20']);
        AccountingPeriod::where('period', '2026-10')->update(['status' => 'closed']);
        $this->postJson(route('fees.overpayments.refund', $record), $this->refundData())->assertUnprocessable();
        $this->postJson(route('fees.overpayments.cancel', $record), ['date' => '2026-09-30', 'reason' => 'Salah tanggal'])->assertUnprocessable();
        $this->postJson(route('fees.overpayments.cancel', $record), ['date' => '2026-10-22', 'reason' => ''])->assertUnprocessable();
        $this->post(route('fees.overpayments.cancel', $record), ['date' => '2026-10-22', 'reason' => 'Salah catat'])->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame(OverpaymentStatus::Cancelled, $record->status);
        $this->assertSame('2026-10-22', $record->requested_cancel_date->toDateString());
        $this->assertSame('2026-11-01', $record->cancellationEntry->entry_date->toDateString());
        $this->assertSame(JournalSource::OverpaymentCancellation, $record->cancellationEntry->source_type);
        $this->assertSame($record->receipt_entry_id, $record->cancellationEntry->reverses_entry_id);
        Storage::disk('local')->assertExists($record->proof_path);
        $this->assertTrue(app(GuardianDepositReconciliation::class)->asOf('2026-10-31')['is_balanced']);
    }

    public function test_terminal_retries_and_general_journal_reverse_are_rejected_without_side_effects(): void
    {
        foreach (['refund', 'cancel'] as $terminal) {
            $record = $this->record();
            $receipt = $record->receipt_entry_id;
            $this->postJson(route('finance.journals.reverse', $receipt), ['date' => '2026-10-22', 'reason' => 'Bypass'])->assertUnprocessable();
            if ($terminal === 'refund') {
                $this->post(route('fees.overpayments.refund', $record), $this->refundData())->assertSessionHasNoErrors();
            } else {
                $this->post(route('fees.overpayments.cancel', $record), ['date' => '2026-10-22', 'reason' => 'Salah'])->assertSessionHasNoErrors();
            }
            $record->refresh();
            $before = $record->getAttributes();
            $count = JournalEntry::count();
            $this->postJson(route('fees.overpayments.refund', $record), $this->refundData())->assertUnprocessable();
            $this->postJson(route('fees.overpayments.cancel', $record), ['date' => '2026-10-22', 'reason' => 'Ulang'])->assertUnprocessable();
            foreach (array_filter([$receipt, $record->refund_entry_id, $record->cancellation_entry_id]) as $entry) {
                $this->postJson(route('finance.journals.reverse', $entry), ['reason' => 'Bypass'])->assertUnprocessable();
            }
            $this->assertSame($before, $record->refresh()->getAttributes());
            $this->assertSame($count, JournalEntry::count());
            $this->assertSame(2, AuditLog::where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)->count());
        }
    }

    public function test_audit_failure_rolls_back_receipt_and_removes_new_proof(): void
    {
        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('fees.overpayments.store'), $this->payload());
            $this->fail('Audit failure should abort the receipt.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('overpayments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_automatic_journal_links_and_bank_fee_are_visible_in_reports(): void
    {
        $record = $this->record();
        $this->post(route('fees.overpayments.refund', $record), $this->refundData())->assertSessionHasNoErrors();
        $record->refresh();
        $this->get(route('finance.journals.show', $record->refund_entry_id))->assertOk()->assertInertia(fn (Assert $page) => $page->where('overpayment_id', $record->id)->where('canReverse', false)->has('journal.lines', 3)->where('journal.lines.2.memo', 'Biaya bank ditanggung wali'));
        $report = app(CashBookReport::class)->generate($this->bank, '2026-10-01', '2026-10-31');
        $this->assertSame('2700000.00', $report['period_credits']);
        $this->assertSame($record->id, $report['lines'][2]['overpayment_id']);
        $this->assertSame('6500.00', $report['lines'][2]['credit']);
        $this->assertSame($record->refund_entry_id, $report['lines'][2]['journal_entry_id']);
        $general = app(GeneralLedgerReport::class)->generate($this->bank, '2026-10-01', '2026-10-31');
        $this->assertSame($record->id, $general['accounts'][0]['lines'][2]['overpayment_id']);
        $this->assertSame('Biaya bank ditanggung wali', $general['accounts'][0]['lines'][2]['memo']);
        $legacyReversal = JournalEntry::factory()->create(['reverses_entry_id' => $record->receipt_entry_id]);
        $count = JournalEntry::count();
        $this->postJson(route('finance.journals.reverse', $legacyReversal), ['reason' => 'Bypass original source'])->assertUnprocessable();
        $this->assertSame($count, JournalEntry::count());
    }

    public function test_configuration_and_link_validation_leave_no_receipt_or_upload(): void
    {
        $student = Student::factory()->create();
        $badBank = Account::factory()->revenue()->create();
        foreach ([['amount' => '0'], ['guardian_id' => null], ['student_id' => $student->id], ['deposit_account_id' => $badBank->id], ['posting_date' => '2026-10-19'], ['proof' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')], ['proof' => UploadedFile::fake()->image('huge.png')->size(5121)]] as $changes) {
            $this->postJson(route('fees.overpayments.store'), $this->payload($changes))->assertUnprocessable();
        }
        SchoolSetting::first()->update(['guardian_deposit_account_id' => null]);
        app(SchoolSettings::class)->refresh();
        $this->postJson(route('fees.overpayments.store'), $this->payload())->assertUnprocessable();
        $this->get(route('fees.overpayments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('configuration.account_id', null));
        $this->assertSame(0, Overpayment::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_inertia_domain_rejection_returns_form_errors_and_preserves_receipt(): void
    {
        $record = $this->record();
        $this->from(route('fees.overpayments.show', $record))->post(route('fees.overpayments.refund', $record), $this->refundData(['bank_fee' => '2700000']), ['X-Inertia' => 'true'])->assertRedirect(route('fees.overpayments.show', $record))->assertSessionHasErrors('operation');
        $this->assertSame(OverpaymentStatus::Held, $record->refresh()->status);
        $this->assertNull($record->refund_entry_id);
    }

    public function test_inactive_captured_account_requires_reactivation_and_preserves_state(): void
    {
        $record = $this->record();
        Account::whereKey($record->liability_account_id)->update(['is_active' => false]);
        $this->postJson(route('fees.overpayments.refund', $record), $this->refundData())->assertUnprocessable()->assertSee('admin');
        $this->assertSame(OverpaymentStatus::Held, $record->refresh()->status);
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_principal_reads_private_evidence_and_multirole_finance_keeps_capability(): void
    {
        $record = $this->record();
        $principal = User::factory()->principal()->create();
        $this->actingAs($principal)->get(route('fees.overpayments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->has('overpayments.data', 1));
        $this->get(route('fees.overpayments.show', $record))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->missing('overpayment.proof_path')->missing('overpayment.proof_hash'));
        $this->get(route('fees.overpayments.proof', [$record, 'receipt']))->assertOk();
        $this->postJson(route('fees.overpayments.refund', $record), $this->refundData())->assertForbidden();
        $this->actingAs($this->finance);
        $this->finance->roleGrants()->create(['role' => 'parent', 'created_at' => now()]);
        app(OverpaymentService::class)->refund($record, $this->refundData(), $this->finance->fresh());
        $this->assertSame(OverpaymentStatus::Refunded, $record->refresh()->status);
    }

    public function test_split_transfer_records_exact_bank_receipt_without_changing_bill_settlement(): void
    {
        $student = Student::factory()->create();
        $this->guardian->students()->attach($student->id, ['relationship_type' => 'father']);
        $type = FeeType::factory()->create(['receivable_account_id' => Account::where('code', '1-1300')->value('id'), 'revenue_account_id' => Account::where('code', '4-1100')->value('id'), 'discount_account_id' => Account::where('code', '4-1900')->value('id')]);
        $bill = Bill::factory()->create(['student_id' => $student->id, 'fee_type_id' => $type->id, 'amount' => '300000.00', 'discount_amount' => '0']);
        app(BillService::class)->recognize($bill);
        $proof = UploadedFile::fake()->image('gross.png');
        $hash = hash_file('sha256', $proof->getRealPath());
        $paymentService = app(PaymentService::class);
        try {
            $paymentService->submitTransfer('3000000.00', [$bill->id => '300000.00'], [$student->id], $this->bank, '2026-10-23', $this->finance);
            $this->fail('Gross transfer cannot bypass allocation equality.');
        } catch (PaymentOperationException) {
            $this->assertDatabaseCount('payments', 0);
        }
        $payment = $paymentService->submitTransfer('300000.00', [$bill->id => '300000.00'], [$student->id], $this->bank, '2026-10-23', $this->finance, proofHash: $hash, notes: 'Bagian tagihan dari transfer 3000000');
        $paymentService->verify($payment, null, $this->bank, $this->finance);
        $record = $this->record(['student_id' => $student->id, 'related_payment_id' => $payment->id, 'posting_date' => '2026-10-23', 'proof' => $proof]);
        $this->assertSame('300000.00', $bill->refresh()->paid_amount);
        $bankDebits = JournalLine::where('account_id', $this->bank)->sum('debit');
        $this->assertSame('3000000.00', Money::toDecimal($bankDebits));
        $this->get(route('fees.overpayments.show', $record))->assertOk()->assertInertia(fn (Assert $page) => $page->where('overpayment.duplicate_payment_ids.0', $payment->id));
        $this->post(route('fees.overpayments.refund', $record), $this->refundData(['refund_date' => '2026-10-23']))->assertSessionHasNoErrors();
        $this->assertSame('2693500.00', $record->refresh()->net_refund);
        $this->assertSame('300000.00', $bill->refresh()->paid_amount);
        $this->assertTrue(app(GuardianDepositReconciliation::class)->asOf('2026-10-31')['is_balanced']);
        $this->assertTrue(app(ReceivablesReconciliation::class)->asOf('2026-10-31')['is_balanced']);
        $this->guardian->students()->detach();
        $this->assertTrue(app(GuardianDepositReconciliation::class)->asOf('2026-10-31')['is_balanced']);
        $this->postJson(route('fees.overpayments.store'), $this->payload(['related_payment_id' => $payment->id]))->assertUnprocessable();
    }
}
