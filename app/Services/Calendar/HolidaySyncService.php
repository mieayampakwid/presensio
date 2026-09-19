<?php

namespace App\Services\Calendar;

use App\Enums\NonSchoolDaySource;
use App\Models\NonSchoolDay;
use App\Services\SchoolSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One-way, idempotent sync from the Nager.Date feed (spec 03 §Requirements 7).
 * The feed owns the *content* of synced rows only: manual rows are never
 * touched, and removals from the feed never propagate — admins delete
 * synced dates themselves if they want (they'd be re-imported next week
 * while the feed still lists them).
 */
class HolidaySyncService
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * @return array{imported: int, updated: int}|array{failed: string}
     */
    public function sync(): array
    {
        $today = $this->settings->todayDate();
        $year = (int) substr($today, 0, 4);

        $imported = 0;
        $updated = 0;

        try {
            foreach ([$year, $year + 1] as $holidayYear) {
                $response = Http::baseUrl((string) config('attendance.holiday_feed.base_url'))
                    ->get("/PublicHolidays/{$holidayYear}/".config('attendance.holiday_feed.country_code'))
                    ->throw();

                $holidays = $response->json();

                if (! is_array($holidays)) {
                    continue;
                }

                foreach ($holidays as $holiday) {
                    // National rows only (`global === true` drops provincial
                    // ones) and future-only — past holidays are history,
                    // not schedule constraints.
                    if (! is_array($holiday) || ($holiday['global'] ?? null) !== true) {
                        continue;
                    }

                    $date = $holiday['date'] ?? null;

                    if (! is_string($date) || $date < $today) {
                        continue;
                    }

                    $name = $holiday['localName'] ?? $holiday['name'] ?? null;

                    if (! is_string($name) || $name === '') {
                        continue;
                    }

                    $existing = NonSchoolDay::query()->where('date', $date)->first();

                    if ($existing === null) {
                        NonSchoolDay::create([
                            'date' => $date,
                            'name' => $name,
                            'source' => NonSchoolDaySource::Sync,
                        ]);
                        $imported++;

                        continue;
                    }

                    if ($existing->source === NonSchoolDaySource::Sync && $existing->name !== $name) {
                        $existing->update(['name' => $name]);
                        $updated++;
                    }
                }
            }
        } catch (ConnectionException|RequestException $e) {
            Log::warning('holiday-sync-failed', ['error' => $e->getMessage()]);

            return ['failed' => $e->getMessage()];
        }

        return ['imported' => $imported, 'updated' => $updated];
    }
}
