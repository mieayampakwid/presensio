<?php

namespace App\Services\Attendance;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\ScanEvent;
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
                [$status, $lateMinutes] = $this->checkInStatus($localTap);

                $record->update([
                    'status' => $status,
                    'checked_in_at' => $this->utc($scannedAt),
                    'late_minutes' => $lateMinutes,
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

            // 5. Already checked in, beyond debounce -> checkout (latest tap wins)
            $staffEndTime = $this->settings->staffEndTimeOn($localTap);
            $earlyLeaveMinutes = max(0, (int) ceil($localTap->diffInMinutes($staffEndTime, false)));

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
        [$status, $lateMinutes] = $this->checkInStatus($localTap);

        try {
            return DB::transaction(fn () => EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => $status,
                'checked_in_at' => $this->utc($scannedAt),
                'checked_out_at' => null,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => 0,
                'scan_method' => $method,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
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
    private function checkInStatus(CarbonInterface $localTap): array
    {
        $staffStartTime = $this->settings->staffStartTimeOn($localTap);
        $isLate = $localTap->greaterThan($staffStartTime);

        return [
            $isLate ? EmployeeAttendanceStatus::Late : EmployeeAttendanceStatus::Present,
            $isLate ? (int) ceil($staffStartTime->diffInMinutes($localTap, false)) : 0,
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
