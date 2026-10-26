<?php

use App\Enums\DeliveryStatus;
use App\Jobs\DeliverNotification;
use App\Models\Employee;
use App\Models\NotificationDelivery;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Services\SchoolSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule definitions are re-evaluated every minute by schedule:run, so
// reading the settings row here picks up admin changes within a minute.
// SchoolSettings guards pre-migration boots, keeping artisan migrate:fresh
// from exploding.
$settings = app(SchoolSettings::class);

Schedule::command('attendance:mark-absences')
    ->dailyAt($settings->autoAbsentCronTime())
    ->timezone($settings->timezone());

Schedule::command('staff-attendance:mark-absences')
    ->dailyAt($settings->staffAbsentSweepTime())
    ->timezone($settings->timezone());

// Per-shift absent sweeps (spec 20 §Req 6): one run per shift active today —
// employee defaults plus today's non-null roster rows. The try/catch guards
// pre-migration boots the same way SchoolSettings does above.
try {
    $shiftIds = Employee::query()->active()->whereNotNull('shift_id')->pluck('shift_id')
        ->merge(
            ShiftRoster::query()
                ->whereDate('date', $settings->todayDate())
                ->whereNotNull('shift_id')
                ->pluck('shift_id')
        )
        ->unique();

    foreach (Shift::query()->findMany($shiftIds) as $shift) {
        Schedule::command("staff-attendance:mark-absences --shift={$shift->id}")
            ->dailyAt($shift->sweepCronTime())
            ->timezone($settings->timezone());
    }
} catch (QueryException) {
    // Pre-migration boot (e.g. artisan migrate:fresh) — nothing to register.
}

Schedule::command('attendance:sync-holidays')
    ->weeklyOn(0, '03:00')
    ->timezone($settings->timezone());

Schedule::command('finance:recognize-bills')
    ->dailyAt('00:30')
    ->timezone($settings->timezone());

Schedule::command('fees:send-bill-reminders')
    ->dailyAt('07:00')
    ->timezone($settings->timezone());

Artisan::command('notifications:release-delayed', function () {
    $delayedDeliveries = NotificationDelivery::query()
        ->where('status', DeliveryStatus::Pending)
        ->whereNotNull('scheduled_for')
        ->where('scheduled_for', '<=', now())
        ->get();

    foreach ($delayedDeliveries as $delivery) {
        $delivery->update(['scheduled_for' => null]);
        DeliverNotification::dispatch($delivery->id, '', '');
    }
})->purpose('Release delayed notifications whose quiet hours have ended');

Schedule::command('notifications:prune')->daily();
Schedule::command('notifications:release-delayed')->everyMinute();

Schedule::command('announcements:publish-due')->everyMinute()->withoutOverlapping();
