<?php

namespace Tests\Feature\fees;

use App\Enums\OverpaymentStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Overpayment;
use App\Models\User;
use App\Services\Finance\Exceptions\OverpaymentOperationException;
use App\Services\Finance\GuardianDepositReconciliation;
use App\Services\Finance\OverpaymentService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OverpaymentConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL row locks.');
        }
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();
    }

    /**
     * @param  array{kind: string, user: int, overpayment: int, bank: int}  $operation
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
    Illuminate\Support\Facades\Date::setTestNow('2026-10-23 03:00:00');
    $user = App\Models\User::findOrFail($input['operation']['user']);
    Illuminate\Support\Facades\Auth::login($user);
    echo json_encode(['pid' => Illuminate\Support\Facades\DB::selectOne('select pg_backend_pid() as pid')->pid]).PHP_EOL;
    flush();
    try {
        $op = $input['operation'];
        $service = app(App\Services\Finance\OverpaymentService::class);
        if ($op['kind'] === 'refund') {
            $service->refund(App\Models\Overpayment::findOrFail($op['overpayment']), [
                'refund_date' => '2026-10-23', 'refund_account_id' => $op['bank'],
                'bank_fee' => '6500', 'bank_reference' => 'RACE',
            ], $user);
        } else {
            $service->cancel(App\Models\Overpayment::findOrFail($op['overpayment']), '2026-10-23', 'Salah catat', $user);
        }
        echo json_encode(['status' => 200]).PHP_EOL;
    } catch (App\Services\Finance\Exceptions\OverpaymentOperationException $exception) {
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

    /** @return array<string, array{string, string}> */
    public static function races(): array
    {
        return ['two refunds' => ['refund', 'refund'], 'refund wins' => ['refund', 'cancel'], 'cancel wins' => ['cancel', 'refund']];
    }

    #[DataProvider('races')]
    public function test_only_one_terminal_operation_commits(string $winner, string $loser): void
    {
        $finance = User::factory()->finance()->create();
        $this->actingAs($finance);
        $bank = Account::where('code', '1-1200')->sole()->id;
        $service = app(OverpaymentService::class);
        $record = $service->record([
            'guardian_id' => Guardian::factory()->create()->id, 'amount' => '2700000',
            'bank_mutation_date' => '2026-10-20', 'posting_date' => '2026-10-20',
            'deposit_account_id' => $bank, 'reason' => 'double_payment',
            'proof_path' => 'proof.pdf', 'proof_hash' => hash('sha256', 'race'),
        ], $finance);
        $refund = ['refund_date' => '2026-10-23', 'refund_account_id' => $bank, 'bank_fee' => '6500', 'bank_reference' => 'WINNER'];
        $result = $this->contend(
            ['kind' => $loser, 'user' => $finance->id, 'overpayment' => $record->id, 'bank' => $bank],
            fn () => Overpayment::whereKey($record->id)->lockForUpdate()->firstOrFail(),
            fn () => $winner === 'refund' ? $service->refund($record, $refund, $finance) : $service->cancel($record, '2026-10-23', 'Salah catat', $finance),
        );
        $this->assertSame(422, $result['status']);
        $record->refresh();
        $this->assertSame($winner === 'refund' ? OverpaymentStatus::Refunded : OverpaymentStatus::Cancelled, $record->status);
        $this->assertNotSame($record->refund_entry_id !== null, $record->cancellation_entry_id !== null);
        $this->assertSame(2, JournalEntry::where('source_id', $record->id)->whereIn('source_type', ['overpayment_receipt', 'overpayment_refund', 'overpayment_cancellation'])->count());
        $this->assertSame(1, AuditLog::where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)->whereIn('action', ['overpayment_refunded', 'overpayment_cancelled'])->count());
        $this->assertTrue(app(GuardianDepositReconciliation::class)->asOf('2026-10-31')['is_balanced']);
        $terminal = JournalEntry::findOrFail($record->refund_entry_id ?? $record->cancellation_entry_id);
        $this->assertSame($record->id, $terminal->source_id);
        $this->assertSame('2700000.00', $terminal->totalDebit());
        foreach (['refund', 'cancel'] as $retry) {
            try {
                if ($retry === 'refund') {
                    $service->refund($record, $refund, $finance);
                } else {
                    $service->cancel($record, '2026-10-23', 'Ulang', $finance);
                }
                $this->fail('Terminal retry was accepted.');
            } catch (OverpaymentOperationException) {
                $this->assertSame(2, JournalEntry::count());
            }
        }
    }
}
