<?php

namespace App\Services\Attendance;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\ScanEvent;
use App\Models\Shift;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class EmployeeScanService
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly CredentialResolver $credentials,
        private readonly ShiftResolver $shifts,
    ) {}

    public function scan(ScanMethod $method, string $credential, ?CarbonInterface $deviceScannedAt): ScanResult
    {
        $serverTime = Date::now($this->settings->timezone());
        $scannedAt = $this->resolveScanTime($deviceScannedAt, $serverTime);
        $identifier = $method === ScanMethod::Rfid ? $credential : null;

        $resolution = $this->credentials->resolve($method, $credential);

        if ($resolution->isError() || ! $resolution->isEmployee()) {
            $outcome = $resolution->error ?? ScanOutcome::ErrorUnknownCredential;
            $this->log($method, $identifier, null, $outcome, $scannedAt);

            return new ScanResult($outcome);
        }

        /** @var Employee $employee */
        $employee = $resolution->employee;

        return $this->scanResolved($method, $employee, $credential, $deviceScannedAt);
    }

    public function scanResolved(
        ScanMethod $method,
        Employee $employee,
        string $credential,
        ?CarbonInterface $deviceScannedAt
    ): ScanResult {
        $serverTime = Date::now($this->settings->timezone());
        $scannedAt = $this->resolveScanTime($deviceScannedAt, $serverTime);
        $identifier = $method === ScanMethod::Rfid ? $credential : null;

        // 1. Employee inactive -> ignored_inactive (AC-16-11, no record created)
        if (! $employee->is_active) {
            $this->log($method, $identifier, $employee, ScanOutcome::IgnoredInactive, $scannedAt);

            return new ScanResult(ScanOutcome::IgnoredInactive, employee: $employee);
        }

        $date = $scannedAt->tz($this->settings->timezone())->toDateString();
        $localTap = $scannedAt->tz($this->settings->timezone());

        return DB::transaction(function () use ($method, $identifier, $employee, $date, $scannedAt, $localTap) {
            $record = $this->recordQuery($employee, $date)->first();

            // A tap at or before the end of yesterday's still-open
            // cross-midnight record (per its snapshotted shift) closes
            // that record instead of opening today's (spec 20 §Decisions).
            if ($record === null) {
                $closed = $this->closeCrossMidnightRecord($method, $identifier, $employee, $scannedAt, $localTap);

                if ($closed !== null) {
                    return $closed;
                }
            }

            // 3a. No record -> check-in. A simultaneous tap or the absence
            // sweep may win the unique insert; then resolve against the
            // winner's row below.
            if ($record === null) {
                $created = $this->createRecord($method, $employee, $date, $scannedAt, $localTap);

                if ($created !== null) {
                    $this->log($method, $identifier, $employee, ScanOutcome::CheckIn, $scannedAt);

                    return new ScanResult(ScanOutcome::CheckIn, employee: $employee, employeeAttendance: $created);
                }

                $record = $this->recordQuery($employee, $date)->firstOrFail();
            }

            // 2. Excused statuses -> ignored_excused (no record updated)
            if ($record->status->isExcused()) {
                $this->log($method, $identifier, $employee, ScanOutcome::IgnoredExcused, $scannedAt);

                return new ScanResult(ScanOutcome::IgnoredExcused, employee: $employee, employeeAttendance: $record);
            }

            // 3b. An absent record from the sweep -> check-in
            if ($record->status === EmployeeAttendanceStatus::Absent && $record->checked_in_at === null) {
                $shift = $this->shifts->resolve($employee, $date)->shift;
                [$status, $lateMinutes] = $this->checkInStatus($localTap, $shift);

                $record->update([
                    'status' => $status,
                    'checked_in_at' => $this->utc($scannedAt),
                    'late_minutes' => $lateMinutes,
                    'shift_id' => $shift?->id,
                    'scan_method' => $method,
                ]);
                $record->refresh();

                $this->log($method, $identifier, $employee, ScanOutcome::AbsentUpgraded, $scannedAt);

                return new ScanResult(ScanOutcome::AbsentUpgraded, employee: $employee, employeeAttendance: $record);
            }

            // 4. Already checked in, within debounce -> ignored_debounce
            $lastTap = $record->checked_out_at ?? $record->checked_in_at;
            $debounceEdge = $lastTap?->copy()->addMinutes($this->settings->debounceMinutes());

            if ($debounceEdge !== null && $scannedAt->lessThanOrEqualTo($debounceEdge)) {
                $this->log($method, $identifier, $employee, ScanOutcome::IgnoredDebounce, $scannedAt);

                return new ScanResult(ScanOutcome::IgnoredDebounce, employee: $employee, employeeAttendance: $record);
            }

            // 5. Already checked in, beyond debounce -> checkout (latest tap wins).
            // Closing math uses the record's snapshotted shift, never the
            // tap date's effective shift (spec 20 §Decisions).
            $shiftEnd = $this->shifts->windowFor($record->shift, $record->date->toDateString())[1];
            $earlyLeaveMinutes = max(0, (int) ceil($localTap->diffInMinutes($shiftEnd, false)));

            $record->update([
                'checked_out_at' => $this->utc($scannedAt),
                'early_leave_minutes' => $earlyLeaveMinutes,
            ]);
            $record->refresh();

            $this->log($method, $identifier, $employee, ScanOutcome::CheckOut, $scannedAt);

            return new ScanResult(ScanOutcome::CheckOut, employee: $employee, employeeAttendance: $record);
        });
    }

    /**
     * First tap of the day creates the record. Returns null when a
     * concurrent writer won the unique insert. The savepoint keeps the
     * outer transaction usable after the violation on PostgreSQL.
     */
    private function createRecord(
        ScanMethod $method,
        Employee $employee,
        string $date,
        CarbonInterface $scannedAt,
        CarbonInterface $localTap
    ): ?EmployeeAttendance {
        $shift = $this->shifts->resolve($employee, $date)->shift;
        [$status, $lateMinutes] = $this->checkInStatus($localTap, $shift);

        try {
            return DB::transaction(fn () => EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => $status,
                'checked_in_at' => $this->utc($scannedAt),
                'checked_out_at' => null,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => 0,
                'shift_id' => $shift?->id,
                'scan_method' => $method,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * A tap at or before the end of yesterday's still-open record whose
     * snapshotted shift crosses midnight closes that record instead of
     * opening today's; the debounce still applies against its last tap.
     * Null when the tap belongs to today's record instead (spec 20).
     */
    private function closeCrossMidnightRecord(
        ScanMethod $method,
        ?string $identifier,
        Employee $employee,
        CarbonInterface $scannedAt,
        CarbonInterface $localTap
    ): ?ScanResult {
        $previous = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $localTap->copy()->subDay()->toDateString())
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->with('shift')
            ->first();

        if ($previous === null || ! $previous->shift?->crosses_midnight) {
            return null;
        }

        $shiftEnd = $this->shifts->windowFor($previous->shift, $previous->date->toDateString())[1];

        if ($localTap->greaterThan($shiftEnd)) {
            return null;
        }

        $lastTap = $previous->checked_out_at ?? $previous->checked_in_at;
        $debounceEdge = $lastTap?->copy()->addMinutes($this->settings->debounceMinutes());

        if ($debounceEdge !== null && $scannedAt->lessThanOrEqualTo($debounceEdge)) {
            $this->log($method, $identifier, $employee, ScanOutcome::IgnoredDebounce, $scannedAt);

            return new ScanResult(ScanOutcome::IgnoredDebounce, employee: $employee, employeeAttendance: $previous);
        }

        $earlyLeaveMinutes = max(0, (int) ceil($localTap->diffInMinutes($shiftEnd, false)));

        $previous->update([
            'checked_out_at' => $this->utc($scannedAt),
            'early_leave_minutes' => $earlyLeaveMinutes,
        ]);
        $previous->refresh();

        $this->log($method, $identifier, $employee, ScanOutcome::CheckOut, $scannedAt);

        return new ScanResult(ScanOutcome::CheckOut, employee: $employee, employeeAttendance: $previous);
    }

    /**
     * @return Builder<EmployeeAttendance>
     */
    private function recordQuery(Employee $employee, string $date): Builder
    {
        return EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date);
    }

    /**
     * @return array{0: EmployeeAttendanceStatus, 1: int}
     */
    private function checkInStatus(CarbonInterface $localTap, ?Shift $shift): array
    {
        $startTime = $this->shifts->windowFor($shift, $localTap)[0];
        $isLate = $localTap->greaterThan($startTime);

        return [
            $isLate ? EmployeeAttendanceStatus::Late : EmployeeAttendanceStatus::Present,
            $isLate ? (int) ceil($startTime->diffInMinutes($localTap, false)) : 0,
        ];
    }

    private function resolveScanTime(?CarbonInterface $device, CarbonInterface $server): CarbonInterface
    {
        if ($device !== null && abs($device->diffInSeconds($server)) <= $this->settings->driftToleranceMinutes() * 60) {
            return $device;
        }

        return $server;
    }

    private function utc(CarbonInterface $instant): CarbonInterface
    {
        return $instant->tz('UTC');
    }

    private function log(
        ScanMethod $method,
        ?string $identifier,
        ?Employee $employee,
        ScanOutcome $outcome,
        CarbonInterface $scannedAt
    ): void {
        ScanEvent::create([
            'student_id' => null,
            'employee_id' => $employee?->id,
            'scan_method' => $method->value,
            'identifier' => $identifier,
            'scanned_at' => $this->utc($scannedAt),
            'outcome' => $outcome->value,
        ]);
    }
}
