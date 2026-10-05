<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (AcademicYear::active() !== null) {
            return;
        }

        // The factory creates both semesters through SemesterService.
        AcademicYear::factory()->current()->create();
    }
}
