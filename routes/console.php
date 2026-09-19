<?php

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
