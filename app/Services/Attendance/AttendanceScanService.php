<?php

namespace App\Services\Attendance;

use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\ScanEvent;
use App\Models\Student;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The tap engine (spec 03 §Requirements 1). Resolves a credential, applies
 * the ordered tap resolution, and appends exactly one scan_events row per
 * attempt — including credential errors. The mutable attendances row is the
 * projection; scan_events is the immutable history.
 */
class AttendanceScanService
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly QrTokenService $tokens,
    ) {}

    public function scan(ScanMethod $method, string $credential, ?CarbonInterface $deviceScannedAt): ScanResult
    {
        $serverNow = $this->settings->now();
        $scannedAt = $this->resolveScanTime($deviceScannedAt, $serverNow);

        // Credential resolution. QR tokens are never stored; RFID attempts
        // keep the raw number for debugging and fraud analytics.
        if ($method === ScanMethod::Rfid) {
            $identifier = $credential;
            $studentId = RfidCard::query()->where('rfid_number', $credential)->value('student_id');
            $student = $studentId !== null ? Student::query()->whereKey($studentId)->first() : null;

            if ($student === null) {
                return $this->log($method, $identifier, null, ScanOutcome::ErrorUnknownCredential, $scannedAt);
            }
        } else {
            $identifier = null;
            $verification = $this->tokens->verify($credential);

            if (! $verification->verified()) {
                $outcome = $verification->error === QrTokenError::Expired
                    ? ScanOutcome::ErrorExpiredToken
                    : ScanOutcome::ErrorUnknownCredential;

                return $this->log($method, $identifier, null, $outcome, $scannedAt);
            }

            $student = $verification->student;
        }

        $date = $scannedAt->tz($this->settings->timezone())->toDateString();

        /** @var ScanResult $result */
        $result = DB::transaction(function () use ($method, $student, $date, $scannedAt) {
            $record = $this->findRecord($student, $date);

            if ($record === null) {
                $created = $this->createRecord($method, $student, $date, $scannedAt);

                if ($created !== null) {
                    return new ScanResult(ScanOutcome::CheckIn, $student, $created);
                }

                // A simultaneous tap on another scanner won the unique insert:
                // re-resolve against the winner's row (create-once,
                // update-thereafter).
                $record = $this->findRecord($student, $date);
            }

            return $this->resolveTap($method, $student, $record, $scannedAt);
        });

        return $this->log($method, $identifier, $student, $result->outcome, $scannedAt, $result);
    }

    /**
     * Spec §1 tap resolution for an existing record, in order: absent
     * upgrade, debounce shield, checkout, complete, excused.
     */
    private function resolveTap(ScanMethod $method, Student $student, ?Attendance $record, CarbonInterface $effective): ScanResult
    {
        // 2. Auto-swept absence without a check-in upgrades to late/present.
        // An already-sent absence notification is not retracted (spec 05).
        if ($record === null) {
            // Vanishingly rare: the winner's row disappeared inside the
            // transaction. Treated as an unresolvable tap.
            return new ScanResult(ScanOutcome::ErrorUnknownCredential, $student, null);
        }

        if ($record->status->value === 'absent' && $record->checked_in_at === null) {
            $record->update([
                'status' => $this->settings->isLate($effective) ? 'late' : 'present',
                'checked_in_at' => $this->utc($effective),
                'scan_method' => $method,
            ]);

            return new ScanResult(ScanOutcome::AbsentUpgraded, $student, $record);
        }

        // 5. Excuse-injected records are untouchable (Data Overlap Shield).
        if (in_array($record->status->value, ['sick', 'leave'], true)) {
            return new ScanResult(ScanOutcome::IgnoredExcused, $student, $record);
        }

        // 4. Day already complete.
        if ($record->checked_out_at !== null) {
            return new ScanResult(ScanOutcome::IgnoredComplete, $student, $record);
        }

        // 3. Checked in: debounce first, then checkout per policy.
        $debounceEdge = $record->checked_in_at->copy()->addMinutes($this->settings->debounceMinutes());

        if ($effective->lessThanOrEqualTo($debounceEdge)) {
            return new ScanResult(ScanOutcome::IgnoredDebounce, $student, $record);
        }

        if (! $this->settings->requireCheckout()) {
            return new ScanResult(ScanOutcome::IgnoredComplete, $student, $record);
        }

        $updated = Attendance::query()
            ->whereKey($record->getKey())
            ->whereNull('checked_out_at')
            ->update(['checked_out_at' => $this->utc($effective)]);

        return new ScanResult(
            $updated === 1 ? ScanOutcome::CheckOut : ScanOutcome::IgnoredComplete,
            $student,
            $record->refresh(),
        );
    }

    /**
     * Case 1 — first tap of the day creates the record. Returns the created
     * model, or null when a concurrent scanner won the unique insert.
     */
    private function createRecord(ScanMethod $method, Student $student, string $date, CarbonInterface $effective): ?Attendance
    {
        try {
            return Attendance::create([
                'student_id' => $student->id,
                'date' => $date,
                'status' => $this->settings->isLate($effective) ? 'late' : 'present',
                'checked_in_at' => $this->utc($effective),
                'scan_method' => $method,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function findRecord(Student $student, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * Device clock is authoritative within the drift tolerance window;
     * beyond it the server clock wins (spec 03 §Decisions).
     */
    private function resolveScanTime(?CarbonInterface $device, CarbonInterface $server): CarbonInterface
    {
        if ($device !== null && abs($device->diffInSeconds($server)) <= $this->settings->driftToleranceMinutes() * 60) {
            return $device;
        }

        return $server;
    }

    /**
     * Normalize an instant to UTC before persistence — Eloquent formats
     * datetimes in the value's own timezone, so a school-tz Carbon would
     * otherwise be stored as naive wall time.
     */
    private function utc(CarbonInterface $instant): CarbonInterface
    {
        return $instant->tz('UTC');
    }

    private function log(
        ScanMethod $method,
        ?string $identifier,
        ?Student $student,
        ScanOutcome $outcome,
        CarbonInterface $scannedAt,
        ?ScanResult $result = null,
    ): ScanResult {
        ScanEvent::query()->create([
            'student_id' => $student?->id,
            'scan_method' => $method,
            'identifier' => $identifier,
            'scanned_at' => $this->utc($scannedAt),
            'outcome' => $outcome,
        ]);

        return $result ?? new ScanResult($outcome, $student, null);
    }
}
