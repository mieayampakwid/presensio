<?php

namespace Database\Seeders;

use App\Enums\NonSchoolDaySource;
use App\Models\NonSchoolDay;
use Illuminate\Database\Seeder;

class NonSchoolDaySeeder extends Seeder
{
    /**
     * Two demo calendar closures: a manual past closure and a feed-synced
     * future holiday. Both sit outside the attendance seeders' trailing
     * 31-day window so they never suppress seeded attendance history.
     */
    public function run(): void
    {
        NonSchoolDay::query()->create([
            'date' => today()->subDays(45)->toDateString(),
            'name' => 'Hari pelatihan guru',
            'source' => NonSchoolDaySource::Manual,
        ]);

        NonSchoolDay::query()->create([
            'date' => today()->addDays(30)->toDateString(),
            'name' => 'Isra Mikraj Nabi Muhammad SAW',
            'source' => NonSchoolDaySource::Sync,
        ]);
    }
}
