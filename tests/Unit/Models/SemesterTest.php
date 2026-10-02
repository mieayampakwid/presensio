<?php

namespace Tests\Unit\Models;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_semester_belongs_to_academic_year(): void
    {
        $year = AcademicYear::factory()->create([
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);

        $semester = Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semester Ganjil',
            'starts_at' => '2027-07-13',
            'ends_at' => '2027-12-31',
        ]);

        $this->assertInstanceOf(AcademicYear::class, $semester->academicYear);
        $this->assertSame($year->id, $semester->academicYear->id);
    }

    public function test_academic_year_has_semesters(): void
    {
        $year = AcademicYear::factory()->create([
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);

        $ganjil = Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semester Ganjil',
            'starts_at' => '2027-07-13',
            'ends_at' => '2027-12-31',
        ]);

        $genap = Semester::factory()->create([
            'academic_year_id' => $year->id,
            'number' => 2,
            'name' => 'Semester Genap',
            'starts_at' => '2028-01-01',
            'ends_at' => '2028-06-24',
        ]);

        $semesters = $year->semesters;
        $this->assertCount(2, $semesters);
        $this->assertSame($ganjil->id, $semesters->first()->id);
        $this->assertSame($genap->id, $semesters->last()->id);
    }
}
