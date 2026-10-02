<?php

namespace Tests\Unit\Models;

use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_semester_belongs_to_academic_year(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);

        $semester = $year->semesters()->where('number', 1)->first();

        $this->assertInstanceOf(AcademicYear::class, $semester->academicYear);
        $this->assertSame($year->id, $semester->academicYear->id);
    }

    public function test_academic_year_has_semesters(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);

        $semesters = $year->semesters;
        $this->assertCount(2, $semesters);
        $this->assertSame(1, $semesters->first()->number);
        $this->assertSame(2, $semesters->last()->number);
    }
}
