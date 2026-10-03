<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NonSchoolDay;
use App\Models\SchoolSetting;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;

/**
 * Read model for the single-row settings table, with the school-timezone
 * helpers every attendance decision anchors on (spec 03 §Decisions
 * "Application Configurability") and school profile helpers (spec 15).
 * The app itself runs UTC; all business dates/times are derived here in the school timezone.
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
     * Clear memoized settings row.
     */
    public function refresh(): void
    {
        $this->memoized = null;
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
     * Configured operational weekdays (1 = Monday, 7 = Sunday per ISO-8601).
     *
     * @return array<int, int>
     */
    public function operationalWeekdays(): array
    {
        $days = $this->row()->school_operational_days;

        if ($days !== []) {
            return array_map('intval', $days);
        }

        return [1, 2, 3, 4, 5];
    }

    /**
     * School day = day of week is an operational day and not a recorded
     * non-school day (AUDIT D-05).
     */
    public function isSchoolDay(CarbonInterface|string $date): bool
    {
        $day = is_string($date)
            ? Date::parse($date, $this->timezone())->startOfDay()
            : $date->copy()->tz($this->timezone())->startOfDay();

        // dayOfWeekIso returns 1 (Monday) through 7 (Sunday)
        if (! in_array($day->dayOfWeekIso, $this->operationalWeekdays(), true)) {
            return false;
        }

        return ! NonSchoolDay::query()->whereDate('date', $day->toDateString())->exists();
    }

    /**
     * School name for documents and notifications.
     */
    public function schoolName(): string
    {
        $name = trim($this->row()->school_name);

        return $name !== '' ? $name : (string) config('app.name');
    }

    /**
     * Principal details: name and NIP.
     *
     * @return array{name: string|null, nip: string|null}
     */
    public function principal(): array
    {
        $row = $this->row();

        return [
            'name' => $row->principal_name,
            'nip' => $row->principal_nip,
        ];
    }

    /**
     * Default passing threshold for class subject assignments.
     */
    public function defaultPassingThreshold(): string
    {
        return (string) ($this->row()->default_passing_threshold ?? '75.00');
    }

    /**
     * Notification channels toggles merged with catalog defaults and clamped.
     *
     * @return array<string, array{whatsapp: bool, email: bool}>
     */
    public function notificationChannels(): array
    {
        $stored = (array) ($this->row()->notification_channels ?? []);
        $channels = [];

        foreach (NotificationType::cases() as $type) {
            $typeStored = (array) ($stored[$type->value] ?? []);

            $channels[$type->value] = [
                'whatsapp' => $type->whatsappAllowed() && (bool) ($typeStored['whatsapp'] ?? $type->defaultWhatsapp()),
                'email' => $type->emailAllowed() && (bool) ($typeStored['email'] ?? $type->defaultEmail()),
            ];
        }

        return $channels;
    }

    /**
     * Daily WhatsApp quota for quota-bound notification types (null = unlimited).
     */
    public function whatsappDailyQuota(): ?int
    {
        $quota = $this->row()->whatsapp_daily_quota;

        return $quota !== null ? (int) $quota : null;
    }

    /**
     * Quiet hours window (school timezone).
     *
     * @return array{start: string, end: string}
     */
    public function quietHours(): array
    {
        $row = $this->row();

        return [
            'start' => (string) ($row->quiet_hours_start ?? '21:00:00'),
            'end' => (string) ($row->quiet_hours_end ?? '06:00:00'),
        ];
    }

    /**
     * Number of days before due_date to send bill reminder.
     */
    public function billReminderDaysBefore(): int
    {
        return (int) ($this->row()->bill_reminder_days_before ?? 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $catalogDefaults = [];
        foreach (NotificationType::cases() as $type) {
            $catalogDefaults[$type->value] = [
                'whatsapp' => $type->defaultWhatsapp(),
                'email' => $type->defaultEmail(),
            ];
        }

        return [
            'school_timezone' => 'Asia/Jakarta',
            'school_start_time' => '07:30',
            'require_checkout' => false,
            'auto_absent_cron_time' => '15:30',
            'scan_debounce_minutes' => 1,
            'scan_drift_tolerance_minutes' => 2,
            'school_operational_days' => [1, 2, 3, 4, 5],
            'school_name' => '',
            'default_curriculum' => 'merdeka',
            'default_passing_threshold' => '75.00',
            'notification_channels' => $catalogDefaults,
            'whatsapp_daily_quota' => 500,
            'quiet_hours_start' => '21:00:00',
            'quiet_hours_end' => '06:00:00',
            'bill_reminder_days_before' => 3,
        ];
    }
}
