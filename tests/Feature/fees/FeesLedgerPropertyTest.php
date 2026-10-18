<?php

namespace Tests\Feature\Fees;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillGenerator;
use App\Services\Finance\BillService;
use App\Services\Finance\BillStatusCalculator;
use App\Services\Finance\Exceptions\BillOperationException;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PeriodService;
use App\Services\Finance\ReceivablesReconciliation;
use App\Services\Finance\Reports\TrialBalanceReport;
use App\Services\SchoolSettings;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * Property-style check (Fees B Task 9): a seeded random sequence of fee operations keeps the
 * ledger balanced and reconciled to the bill subledger after every step, and the bill caches
 * consistent at the end.
 */
class FeesLedgerPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATIONS = 40;

    private Randomizer $rng;

    private int $runSeed;

    private int $step = 0;

    private string $operation = '';

    /** @var array<string, int> */
    private array $executed = [];

    private SchoolClass $class;

    private FeeType $spp;

    private User $finance;

    private int $cashAccount;

    private int $receivableAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Friday 2026-07-10, 10:00 Jakarta.
        Date::setTestNow('2026-07-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->cashAccount = $this->accountId('1-1100');
        $this->receivableAccount = $this->accountId('1-1300');

        AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['grade_level' => 5]);
        Student::factory()->count(4)->enrolledIn($this->class)->create();

        $this->spp = FeeType::factory()->create([
            'default_amount' => '350000.00',
            'receivable_account_id' => $this->receivableAccount,
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
        $this->finance = User::factory()->finance()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function seeds(): array
    {
        return ['seed 1' => [1], 'seed 7' => [7], 'seed 42' => [42], 'seed 2026' => [2026], 'seed 90210' => [90210]];
    }

    #[DataProvider('seeds')]
    public function test_random_fee_operations_keep_ledger_balanced_and_subledgers_consistent(int $seed): void
    {
        $this->runSeed = $seed;
        $this->rng = new Randomizer(new Mt19937($seed));

        $this->runOperation('generate');
        $this->assertLedgerBalanced();
        $this->assertReconciled();

        for ($i = 1; $i < self::OPERATIONS; $i++) {
            $this->runOperation($this->randomOperation());
            $this->assertLedgerBalanced();
            $this->assertReconciled();
        }

        $this->step = self::OPERATIONS;
        $this->operation = 'final';
        $report = app(TrialBalanceReport::class)->generate();
        $this->assertTrue($report['is_balanced'], $this->context("trial balance difference {$report['difference']}"));
        $this->assertGreaterThan(0, JournalLine::count(), $this->context('no journal activity'));
        $this->assertGreaterThan(1, count($this->executed), $this->context('sequence did not vary: '.json_encode($this->executed)));

        $this->assertBillSubledgers();
    }

    private function accountId(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }

    private function context(string $message): string
    {
        return "seed={$this->runSeed} step={$this->step} op={$this->operation}: {$message}";
    }

    private function int(int $min, int $max): int
    {
        return $this->rng->getInt($min, $max);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T|null
     */
    private function pick(array $items): mixed
    {
        return $items === [] ? null : $items[$this->int(0, count($items) - 1)];
    }

    private function randomOperation(): string
    {
        $weights = [
            'generate' => 8, 'pay' => 20, 'transfer' => 8, 'verify' => 8, 'reject' => 4, 'void' => 8,
            'discount' => 10, 'cancel' => 6, 'write_off' => 7, 'recognize' => 5, 'advance' => 10, 'invalid' => 6,
        ];
        $roll = $this->int(1, array_sum($weights));

        foreach ($weights as $name => $weight) {
            if (($roll -= $weight) <= 0) {
                return $name;
            }
        }

        return 'generate';
    }

    private function runOperation(string $operation): void
    {
        $this->step++;
        $this->operation = $operation;
        $this->executed[$operation] = ($this->executed[$operation] ?? 0) + 1;

        match ($operation) {
            'generate' => $this->opGenerate(),
            'pay' => $this->opPay(),
            'transfer' => $this->opTransfer(),
            'verify' => $this->opVerify(),
            'reject' => $this->opReject(),
            'void' => $this->opVoid(),
            'discount' => $this->opDiscount(),
            'cancel' => $this->opCancel(),
            'write_off' => $this->opWriteOff(),
            'recognize' => $this->opRecognize(),
            'advance' => $this->opAdvance(),
            'invalid' => $this->opInvalid(),
        };
    }

    /**
     * Run an operation expected to either succeed or throw the domain exception; a rejected
     * operation must leave the ledger, bills and payments exactly as they were.
     *
     * @param  class-string<\Throwable>  $exception
     */
    private function attempt(callable $operation, bool $shouldThrow, string $exception, string $detail): void
    {
        $before = $this->snapshot();

        try {
            $operation();
        } catch (\Throwable $caught) {
            $this->assertTrue($shouldThrow, $this->context('unexpected '.$caught::class.": {$caught->getMessage()} ({$detail})"));
            $this->assertInstanceOf($exception, $caught, $this->context($detail));
            $this->assertSame($before, $this->snapshot(), $this->context("rejected operation changed state ({$detail})"));

            return;
        }

        $this->assertFalse($shouldThrow, $this->context("expected {$exception} but it succeeded ({$detail})"));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'entries' => DB::table('journal_entries')->count(),
            'lines' => DB::table('journal_lines')->count(),
            'allocations' => DB::table('payment_allocations')->count(),
            'payments' => Payment::orderBy('id')->pluck('status', 'id')->map(fn ($status) => $status->value)->all(),
            'bills' => Bill::orderBy('id')->get(['id', 'paid_amount', 'status', 'discount_amount', 'recognized_at', 'cancelled_at', 'written_off_at'])->toArray(),
        ];
    }

    /**
     * @return list<Bill>
     */
    private function bills(): array
    {
        return Bill::orderBy('id')->get()->all();
    }

    private function isPayable(Bill $bill): bool
    {
        return in_array($bill->status, [BillStatus::Unpaid, BillStatus::PartiallyPaid], true);
    }

    /**
     * @return list<Bill>
     */
    private function payable(): array
    {
        return array_values(array_filter($this->bills(), fn (Bill $bill) => $this->isPayable($bill)));
    }

    /**
     * Allocate to one or two payable bills of a single student, a random part of what is left on each.
     *
     * @return array<int, string>
     */
    private function allocations(?Student $forStudent = null): array
    {
        $first = $this->pick($forStudent === null ? $this->payable() : array_values(array_filter($this->payable(), fn (Bill $bill) => $bill->student_id === $forStudent->id)));

        if ($first === null) {
            return [];
        }

        $siblings = array_values(array_filter($this->payable(), fn (Bill $bill) => $bill->student_id === $first->student_id));
        $chosen = [$first->id => $first];

        if ($this->int(0, 1) === 1) {
            $second = $this->pick($siblings);
            $chosen[$second->id] = $second;
        }

        $allocations = [];

        foreach ($chosen as $bill) {
            $remaining = (int) (float) $bill->remainingAmount();
            $allocations[$bill->id] = Money::toDecimal($this->int(0, 1) === 1 ? $remaining : $this->int(1, intdiv($remaining, 1000)) * 1000);
        }

        return $allocations;
    }

    /**
     * @param  array<int, string>  $allocations
     * @return list<int>
     */
    private function studentIds(array $allocations): array
    {
        return Bill::whereIn('id', array_keys($allocations))->pluck('student_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private function total(array $allocations): string
    {
        return array_reduce($allocations, fn (string $sum, $part) => Money::add($sum, $part), '0.00');
    }

    private function opGenerate(): void
    {
        $month = Date::parse(app(SchoolSettings::class)->todayDate())->startOfMonth()->addMonths($this->int(-1, 3));
        $input = [
            'fee_type_id' => $this->spp->id,
            'target' => 'class',
            'class_id' => $this->class->id,
            'from_month' => $month->format('Y-m'),
            'to_month' => $month->addMonths($this->int(0, 2))->format('Y-m'),
            'due_day' => $this->int(1, 28),
        ];
        app(BillGenerator::class)->commit($input);
    }

    private function opPay(): void
    {
        $allocations = $this->allocations();

        if ($allocations === []) {
            $this->opGenerate();

            return;
        }

        app(PaymentService::class)->createCash($allocations, $this->studentIds($allocations), $this->cashAccount, $this->finance);
    }

    private function opTransfer(): void
    {
        $allocations = $this->allocations();

        if ($allocations === []) {
            $this->opGenerate();

            return;
        }

        app(PaymentService::class)->submitTransfer(
            $this->total($allocations),
            $allocations,
            $this->studentIds($allocations),
            $this->cashAccount,
            app(SchoolSettings::class)->todayDate(),
            $this->finance,
        );
    }

    private function opVerify(): void
    {
        $payment = $this->pick(Payment::where('status', PaymentStatus::Pending)->orderBy('id')->get()->all());

        if ($payment === null) {
            $this->opTransfer();

            return;
        }

        // Valid only while every allocated bill is still payable and the part still fits what is left.
        $valid = true;

        foreach ($payment->allocations()->with('bill')->get() as $allocation) {
            $bill = $allocation->bill;
            $valid = $valid && $this->isPayable($bill) && Money::lte($allocation->amount, $bill->remainingAmount());
        }

        $this->attempt(
            fn () => app(PaymentService::class)->verify($payment, null, $this->cashAccount, $this->finance),
            ! $valid,
            PaymentOperationException::class,
            "verify payment #{$payment->id}",
        );
    }

    private function opReject(): void
    {
        $payment = $this->pick(Payment::where('status', PaymentStatus::Pending)->orderBy('id')->get()->all());

        if ($payment === null) {
            $this->opTransfer();

            return;
        }

        app(PaymentService::class)->reject($payment, 'Bukti tidak sesuai', $this->finance);
    }

    private function opVoid(): void
    {
        $payment = $this->pick(Payment::where('status', PaymentStatus::Verified)->orderBy('id')->get()->all());

        if ($payment === null) {
            $this->opPay();

            return;
        }

        $touchesWrittenOff = $payment->allocations()->whereHas('bill', fn ($query) => $query->whereNotNull('written_off_at'))->exists();

        $this->attempt(
            fn () => app(PaymentService::class)->void($payment, 'Salah input', $this->finance),
            $touchesWrittenOff,
            PaymentOperationException::class,
            "void payment #{$payment->id}",
        );
    }

    private function opDiscount(): void
    {
        $bill = $this->pick($this->int(0, 3) === 0 ? $this->bills() : array_values(array_filter($this->bills(), fn (Bill $bill) => $bill->isOpen())));

        if ($bill === null) {
            $this->opGenerate();

            return;
        }

        // Occasionally above the bill amount so the range guard is exercised too.
        $new = Money::toDecimal($this->int(0, 36) * 10000);
        $shouldThrow = ! $bill->isOpen() || Money::gt($new, $bill->amount) || Money::lt(Money::sub($bill->amount, $new), $bill->paid_amount);

        $this->attempt(
            fn () => app(BillService::class)->adjustDiscount($bill, $new, 'Penyesuaian'),
            $shouldThrow,
            BillOperationException::class,
            "discount {$new} on bill #{$bill->id}",
        );
    }

    private function opCancel(): void
    {
        $bill = $this->pick($this->int(0, 3) === 0 ? $this->bills() : array_values(array_filter($this->bills(), fn (Bill $bill) => $bill->isOpen())));

        if ($bill === null) {
            $this->opGenerate();

            return;
        }

        $this->attempt(
            fn () => app(BillService::class)->cancel($bill, 'Dibatalkan'),
            ! $bill->isOpen() || Money::isPositive($bill->paid_amount),
            BillOperationException::class,
            "cancel bill #{$bill->id}",
        );
    }

    private function opWriteOff(): void
    {
        $bill = $this->pick($this->int(0, 3) === 0 ? $this->bills() : array_values(array_filter($this->bills(), fn (Bill $bill) => $bill->isOpen() && $bill->isRecognized())));

        if ($bill === null) {
            $this->opGenerate();

            return;
        }

        $this->attempt(
            fn () => app(BillService::class)->writeOff($bill, 'Tidak tertagih'),
            ! $bill->isOpen() || ! $bill->isRecognized() || ! Money::isPositive(Money::sub($bill->netAmount(), $bill->paid_amount)),
            BillOperationException::class,
            "write off bill #{$bill->id}",
        );
    }

    private function opRecognize(): void
    {
        foreach ($this->bills() as $bill) {
            app(BillService::class)->recognize($bill);
        }
    }

    /**
     * Move the clock forward, run recognition for bills now due, and close the previous month when
     * the month rolls over (later postings for that period land in the next open period).
     */
    private function opAdvance(): void
    {
        $previous = Date::parse(app(SchoolSettings::class)->todayDate());
        Date::setTestNow($previous->addDays($this->int(5, 25))->setTime(3, 0));
        $today = Date::parse(app(SchoolSettings::class)->todayDate());

        $this->opRecognize();

        if ($today->format('Y-m') !== $previous->format('Y-m')) {
            app(PeriodService::class)->close($previous->format('Y-m'), $this->finance->id);
        }
    }

    private function opInvalid(): void
    {
        $payments = app(PaymentService::class);
        $bill = $this->pick($this->payable());

        switch ($this->int(0, 3)) {
            case 0:
                if ($bill !== null) {
                    $over = [$bill->id => Money::add($bill->remainingAmount(), '1000.00')];
                    $this->attempt(
                        fn () => $payments->createCash($over, [$bill->student_id], $this->cashAccount, $this->finance),
                        true,
                        PaymentOperationException::class,
                        "overpay bill #{$bill->id}",
                    );
                }

                return;
            case 1:
                $other = $bill === null ? null : $this->pick(array_values(array_filter($this->bills(), fn (Bill $candidate) => $candidate->student_id !== $bill->student_id)));

                if ($bill !== null && $other !== null) {
                    $wrong = [$bill->id => '1000.00'];
                    $this->attempt(
                        fn () => $payments->createCash($wrong, [$other->student_id], $this->cashAccount, $this->finance),
                        true,
                        PaymentOperationException::class,
                        "bill #{$bill->id} for another student",
                    );
                }

                return;
            case 2:
                $payment = $this->pick(Payment::where('status', '!=', PaymentStatus::Verified)->orderBy('id')->get()->all());

                if ($payment !== null) {
                    $this->attempt(
                        fn () => $payments->void($payment, 'Salah', $this->finance),
                        true,
                        PaymentOperationException::class,
                        "void non-verified payment #{$payment->id}",
                    );
                }

                return;
            default:
                $payment = $this->pick(Payment::where('status', '!=', PaymentStatus::Pending)->orderBy('id')->get()->all());

                if ($payment !== null) {
                    $this->attempt(
                        fn () => $payments->reject($payment, 'Ditolak', $this->finance),
                        true,
                        PaymentOperationException::class,
                        "reject non-pending payment #{$payment->id}",
                    );
                }
        }
    }

    private function assertLedgerBalanced(): void
    {
        $totals = DB::table('journal_lines')->selectRaw('coalesce(sum(debit), 0) as debit, coalesce(sum(credit), 0) as credit')->first();
        $this->assertSame(0, Money::cmp($totals->debit, $totals->credit), $this->context("debit {$totals->debit} != credit {$totals->credit}"));

        $unbalanced = DB::table('journal_lines')->select('journal_entry_id')->groupBy('journal_entry_id')->havingRaw('sum(debit) <> sum(credit)')->pluck('journal_entry_id');
        $this->assertCount(0, $unbalanced, $this->context('unbalanced entries '.$unbalanced->implode(',')));
    }

    /**
     * AC-14-19: GL and the bill subledger agree, for receivables and prepaid revenue, and no bill is off.
     */
    private function assertReconciled(): void
    {
        $result = app(ReceivablesReconciliation::class)->asOf(app(SchoolSettings::class)->todayDate());

        foreach ([...$result['receivables'], $result['prepaid']] as $row) {
            $this->assertSame(0, Money::cmp($row['difference'], '0.00'), $this->context("{$row['code']} GL {$row['gl_balance']} != subledger {$row['subledger_balance']}"));
        }

        $this->assertSame([], $result['mismatches'], $this->context('bill mismatches '.json_encode($result['mismatches'])));
    }

    private function assertBillSubledgers(): void
    {
        $calculator = app(BillStatusCalculator::class);

        foreach (Bill::orderBy('id')->get() as $bill) {
            $id = "bill #{$bill->id} ({$bill->period_key}, {$bill->status->value})";
            $verified = $calculator->paidAmount($bill);

            $this->assertSame($verified, Money::toDecimal($bill->paid_amount), $this->context("{$id} paid_amount"));
            $this->assertSame($calculator->statusFor($bill, $verified), $bill->status, $this->context("{$id} status"));
        }
    }
}
