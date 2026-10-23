<?php

namespace Tests\Feature\fees;

use App\Enums\JournalSource;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\SchoolSettings;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PaymentReturnConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL row locks.');
        }
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        Date::setTestNow('2026-07-10 03:00:00');
        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    /** @return array{parent: User, otherParent: User, finance: User, bill: Bill, billForY: Bill, bankId: int} */
    private function fixture(): array
    {
        $parent = User::factory()->parent()->create();
        $otherParent = User::factory()->parent()->create();
        $finance = User::factory()->finance()->create();
        $firstStudent = Student::factory()->create();
        $secondStudent = Student::factory()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
        $guardian->students()->attach([$firstStudent->id => ['relationship_type' => 'father'], $secondStudent->id => ['relationship_type' => 'father']]);
        Guardian::factory()->create(['user_id' => $otherParent->id])->students()
            ->attach($firstStudent->id, ['relationship_type' => 'mother']);
        $type = FeeType::factory()->create([
            'receivable_account_id' => Account::where('code', '1-1300')->value('id'),
            'revenue_account_id' => Account::where('code', '4-1100')->value('id'),
            'discount_account_id' => Account::where('code', '4-1900')->value('id'),
        ]);
        $make = fn (Student $student) => Bill::factory()->recognized('2026-07-01')->create([
            'student_id' => $student->id, 'fee_type_id' => $type->id,
            'amount' => '350000.00', 'period_key' => '2026-07', 'due_date' => '2026-07-10',
        ]);

        return ['parent' => $parent, 'otherParent' => $otherParent, 'finance' => $finance,
            'bill' => $make($firstStudent), 'billForY' => $make($secondStudent),
            'bankId' => (int) Account::where('code', '1-1200')->value('id')];
    }

    /**
     * @param  array{kind: string, user: int, payment?: int, bill?: int, student?: int, account?: int}  $operation
     * @return array{status: int}
     */
    private function contend(array $operation, \Closure $holdLock, \Closure $winner): array
    {
        $script = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    config(['database.default' => 'pgsql', 'database.connections.pgsql' => $input['connection'], 'queue.default' => 'database']);
    Illuminate\Support\Facades\DB::purge('pgsql');
    Illuminate\Support\Facades\Date::setTestNow('2026-07-10 03:00:00');
    $user = App\Models\User::findOrFail($input['operation']['user']);
    Illuminate\Support\Facades\Auth::login($user);
    echo json_encode(['pid' => Illuminate\Support\Facades\DB::selectOne('select pg_backend_pid() as pid')->pid]).PHP_EOL;
    flush();
    try {
        $op = $input['operation'];
        $service = app(App\Services\Finance\PaymentService::class);
        if ($op['kind'] === 'upload') {
            $service->submitTransfer('350000.00', [$op['bill'] => '350000.00'], [$op['student']], $op['account'], '2026-07-09', $user);
        } elseif ($op['kind'] === 'verify') {
            $service->verify(App\Models\Payment::findOrFail($op['payment']), null, $op['account'], $user);
        } else {
            $service->cancelByGuardian(App\Models\Payment::findOrFail($op['payment']), $user);
        }
        echo json_encode(['status' => 200]).PHP_EOL;
    } catch (App\Services\Finance\Exceptions\PaymentOperationException $exception) {
        echo json_encode(['status' => 422]).PHP_EOL;
    } catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
        echo json_encode(['status' => $exception->getStatusCode()]).PHP_EOL;
    }
    PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['APP_ENV' => 'testing']);
        $process->setInput(json_encode([
            'connection' => DB::connection()->getConfig(), 'operation' => $operation,
        ], JSON_THROW_ON_ERROR));
        $process->setTimeout(15);
        DB::beginTransaction();
        try {
            $holdLock();
            $process->start();
            $deadline = microtime(true) + 10;
            $blocked = false;
            while (microtime(true) < $deadline && $process->isRunning()) {
                $first = strtok($process->getOutput(), "\n");
                $ready = $first === false ? null : json_decode($first, true);
                if (is_array($ready) && isset($ready['pid'])) {
                    DB::select('select pg_stat_clear_snapshot()');
                    $activity = DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$ready['pid']]);
                    if ($activity?->wait_event_type === 'Lock') {
                        $blocked = true;
                        break;
                    }
                }
                usleep(10000);
            }
            $this->assertTrue($blocked, 'Worker did not contend on the held row lock. '.$process->getErrorOutput());
            $winner();
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

            return json_decode($lines[count($lines) - 1], true, flags: JSON_THROW_ON_ERROR);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function terminalWinners(): array
    {
        return ['verify first' => ['verify'], 'cancel first' => ['cancel']];
    }

    #[DataProvider('terminalWinners')]
    public function test_cancel_and_verify_have_exactly_one_winner(string $kind): void
    {
        ['bill' => $bill, 'parent' => $parent, 'finance' => $finance, 'bankId' => $bankId] = $this->fixture();
        $service = app(PaymentService::class);
        $payment = $service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $bankId, '2026-07-09', $parent);
        $actor = $kind === 'verify' ? $finance : $parent;
        $this->actingAs($actor);
        $result = $this->contend(
            ['kind' => $kind === 'verify' ? 'cancel' : 'verify', 'payment' => $payment->id,
                'account' => $bankId, 'user' => $kind === 'verify' ? $parent->id : $finance->id],
            fn () => Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail(),
            fn () => $kind === 'verify'
                ? $service->verify($payment, null, $bankId, $finance)
                : $service->cancelByGuardian($payment, $parent),
        );
        $this->assertSame(422, $result['status']);
        $payment->refresh();
        $this->assertSame($kind === 'verify' ? PaymentStatus::Verified : PaymentStatus::Rejected, $payment->status);
        $this->assertSame($kind === 'verify' ? 1 : 0, JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->count());
        $this->assertSame($kind === 'cancel' ? 1 : 0, AuditLog::where('action', 'cancelled_by_guardian')->count());
        if ($kind === 'verify') {
            $this->assertNotNull($payment->receipt_number);
        } else {
            $this->assertNull($payment->receipt_number);
            $this->assertSame('Dibatalkan oleh wali', $payment->reject_reason);
            $this->assertSame(0, JournalEntry::count());
        }
    }

    public function test_waiting_cancellation_authorizes_latest_allocations_before_status(): void
    {
        ['bill' => $bill, 'billForY' => $billForY, 'parent' => $parent,
            'otherParent' => $otherParent, 'finance' => $finance, 'bankId' => $bankId] = $this->fixture();
        $service = app(PaymentService::class);
        $payment = $service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $bankId, '2026-07-09', $parent);
        $this->actingAs($finance);
        $result = $this->contend(
            ['kind' => 'cancel', 'payment' => $payment->id, 'user' => $otherParent->id],
            fn () => Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail(),
            fn () => $service->verify($payment, [$billForY->id => '350000.00'], $bankId, $finance),
        );
        $this->assertSame(403, $result['status']);
        $this->assertSame(PaymentStatus::Verified, $payment->refresh()->status);
        $this->assertSame($billForY->id, $payment->allocations()->sole()->bill_id);
        $this->assertSame(1, JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->count());
        $this->assertSame(0, AuditLog::where('action', 'cancelled_by_guardian')->count());
    }

    public function test_two_uploads_serialize_on_the_bill_lock(): void
    {
        ['bill' => $bill, 'parent' => $parent, 'bankId' => $bankId] = $this->fixture();
        $service = app(PaymentService::class);
        $result = $this->contend(
            ['kind' => 'upload', 'bill' => $bill->id, 'student' => $bill->student_id, 'account' => $bankId, 'user' => $parent->id],
            fn () => Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail(),
            fn () => $service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $bankId, '2026-07-09', $parent),
        );
        $this->assertSame(422, $result['status']);
        $this->assertSame(1, Payment::where('status', PaymentStatus::Pending)->count());
        $this->assertSame('350000.00', Money::toDecimal(PaymentAllocation::sum('amount')));
        $this->assertSame(0, JournalEntry::count());
    }
}
