<?php

namespace App\Services;

use App\Models\NonSchoolDay;
use App\Models\SchoolSetting;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;

/**
 * Read model for the single-row settings table, with the school-timezone
 * helpers every attendance decision anchors on (spec 03 §Decisions
 * "Application Configurability"). The app itself runs UTC; all business
 * dates/times are derived here in the school timezone.
 */
class SchoolSettings
{
    private ?SchoolSetting $memoized = null;

    /**
     * The settings row, memoized per process. Falls back to unpersisted
     * defaults before the table exists (pre-migration console boots, e.g.
     * `artisan migrate:fresh` evaluating routes/console.php).
     */
    public function row(): SchoolSetting
    {
        if ($this->memoized instanceof SchoolSetting) {
            return $this->memoized;
        }

        try {
            return $this->memoized = SchoolSetting::query()->firstOrCreate([], $this->defaults());
        } catch (QueryException) {
            return new SchoolSetting($this->defaults());
        }
    }

    /**
     * IANA timezone all business logic evaluates in.
     */
    public function timezone(): string
    {
        return $this->row()->school_timezone;
    }

    /**
     * Current instant in the school timezone.
     */
    public function now(): CarbonInterface
    {
        return Date::now($this->timezone());
    }

    /**
     * Today's business date (Y-m-d) in the school timezone — the date
     * attendances are keyed on. Flips at school midnight, not UTC midnight.
     */
    public function todayDate(): string
    {
        return $this->now()->toDateString();
    }

    /**
     * The school-start instant on the given day (school timezone). Derived
     * per-day rather than as a fixed offset so gap/overlap days stay sane.
     */
    public function startTimeOn(CarbonInterface $day): CarbonInterface
    {
        return $day->setTimeFromTimeString($this->row()->school_start_time);
    }

    /**
     * Strictly after the start time counts as late; on the dot is on time.
     */
    public function isLate(CarbonInterface $at): bool
    {
        $local = $at->tz($this->timezone());

        return $local->greaterThan($this->startTimeOn($local->copy()->startOfDay()));
    }

    public function requireCheckout(): bool
    {
        return $this->row()->require_checkout;
    }

    public function debounceMinutes(): int
    {
        return $this->row()->scan_debounce_minutes;
    }

    public function driftToleranceMinutes(): int
    {
        return $this->row()->scan_drift_tolerance_minutes;
    }

    /**
     * Cron sweep time (H:i) for the auto-absent scheduler.
     */
    public function autoAbsentCronTime(): string
    {
        return substr($this->row()->auto_absent_cron_time, 0, 5);
    }

    /**
     * School day = not a weekend (Sat/Sun in school tz) and not a recorded
     * non-school day.
     */
    public function isSchoolDay(CarbonInterface|string $date): bool
    {
        $day = is_string($date)
            ? Date::parse($date, $this->timezone())->startOfDay()
            : $date->copy()->tz($this->timezone())->startOfDay();

        if ($day->isWeekend()) {
            return false;
        }

        return ! NonSchoolDay::query()->whereDate('date', $day->toDateString())->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'school_timezone' => 'Asia/Jakarta',
            'school_start_time' => '07:30',
            'require_checkout' => false,
            'auto_absent_cron_time' => '15:30',
            'scan_debounce_minutes' => 1,
            'scan_drift_tolerance_minutes' => 2,
        ];
    }
}
