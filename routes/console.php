<?php

use App\Enums\DeliveryStatus;
use App\Jobs\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Services\SchoolSettings;
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

Schedule::command('attendance:sync-holidays')
    ->weeklyOn(0, '03:00')
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

Schedule::command('notifications:release-delayed')->everyMinute();
