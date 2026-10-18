<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Contracts\ClosePrecondition;
use App\Services\Finance\Preconditions\TrialBalanceClosePrecondition;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PeriodService
{
    /** @var list<ClosePrecondition> */
    private array $preconditions = [];

    public function __construct(
        private readonly ?AuditLogger $auditLogger = null,
        ?TrialBalanceClosePrecondition $trialBalancePrecondition = null,
    ) {
        if ($trialBalancePrecondition !== null) {
            $this->preconditions[] = $trialBalancePrecondition;
        }
    }

    /**
     * Add a precondition required before closing a period.
     */
    public function addPrecondition(ClosePrecondition $precondition): void
    {
        $this->preconditions[] = $precondition;
    }

    /**
     * Ensure the accounting period row exists, lazily creating it as open if missing.
     */
    public function ensure(string $period): AccountingPeriod
    {
        return AccountingPeriod::firstOrCreate(
            ['period' => $period],
            ['status' => 'open']
        );
    }

    /**
     * Check if the accounting period covering the given date is open.
     */
    public function isOpen(CarbonInterface|string $date): bool
    {
        $carbon = is_string($date) ? Carbon::parse($date) : $date;
        $periodKey = $carbon->format('Y-m');

        return $this->ensure($periodKey)->isOpen();
    }

    /**
     * Resolve the start date of the next open period if the given date's period is closed.
     * If the period is already open, returns the given date.
     */
    public function nextOpenPeriodStart(CarbonInterface|string $date): CarbonInterface
    {
        $current = is_string($date) ? Carbon::parse($date)->startOfDay() : $date->copy()->startOfDay();

        while (! $this->isOpen($current)) {
            $current = $current->copy()->addMonth()->startOfMonth();
        }

        return $current;
    }

    /**
     * Close an accounting period after validating all registered preconditions.
     *
     * @throws RuntimeException
     */
    public function close(string $periodKey, int $userId): AccountingPeriod
    {
        $period = $this->ensure($periodKey);

        if ($period->isLocked()) {
            throw new RuntimeException('Periode terkunci oleh tutup buku tahunan.');
        }

        if ($period->isClosed()) {
            throw new RuntimeException("Periode {$periodKey} sudah dalam status ditutup.");
        }

        foreach ($this->preconditions as $precondition) {
            $precondition->check($period);
        }

        return DB::transaction(function () use ($period, $userId) {
            $period->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $userId,
            ]);

            if ($this->auditLogger) {
                $this->auditLogger->record(
                    $period,
                    'close_period',
                    ['status' => 'open'],
                    ['status' => 'closed'],
                    null
                );
            }

            return $period->fresh(['closedBy']);
        });
    }

    /**
     * Reopen a closed accounting period with a mandatory reason.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function reopen(string $periodKey, string $reason, int $userId): AccountingPeriod
    {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('Alasan pembukaan kembali periode wajib diisi.');
        }

        $period = $this->ensure($periodKey);

        if ($period->isLocked()) {
            throw new RuntimeException('Periode terkunci oleh tutup buku tahunan dan tidak dapat dibuka kembali.');
        }

        if ($period->isOpen()) {
            throw new RuntimeException("Periode {$periodKey} sudah dalam status terbuka.");
        }

        return DB::transaction(function () use ($period, $trimmedReason) {
            $period->update([
                'status' => 'open',
                'closed_at' => null,
                'closed_by_user_id' => null,
            ]);

            if ($this->auditLogger) {
                $this->auditLogger->record(
                    $period,
                    'reopen_period',
                    ['status' => 'closed'],
                    ['status' => 'open'],
                    $trimmedReason
                );
            }

            return $period->fresh();
        });
    }
}
