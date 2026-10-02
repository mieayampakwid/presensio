<?php

namespace Tests\Unit\Services\AcademicYears;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\AcademicYears\SemesterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SemesterServiceTest extends TestCase
{
    use RefreshDatabase;

    private SemesterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SemesterService::class);
    }

    /**
     * AC-15-06: Creating academic year 2027/2028 (2027-07-13 -> 2028-06-24)
     * creates Semester Ganjil (2027-07-13 -> 2027-12-31) and Semester Genap (2028-01-01 -> 2028-06-24).
     */
    public function test_create_for_academic_year_splits_at_january_first(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);

        // Clean out any existing semesters for this year (e.g. from migrations/factory)
        Semester::query()->where('academic_year_id', $year->id)->delete();

        [$ganjil, $genap] = $this->service->createFor($year);

        $this->assertSame(1, $ganjil->number);
        $this->assertSame('Semester Ganjil', $ganjil->name);
        $this->assertSame('2027-07-13', $ganjil->starts_at->toDateString());
        $this->assertSame('2027-12-31', $ganjil->ends_at->toDateString());

        $this->assertSame(2, $genap->number);
        $this->assertSame('Semester Genap', $genap->name);
        $this->assertSame('2028-01-01', $genap->starts_at->toDateString());
        $this->assertSame('2028-06-24', $genap->ends_at->toDateString());

        $this->assertDatabaseHas('semesters', [
            'academic_year_id' => $year->id,
            'number' => 1,
            'starts_at' => '2027-07-13',
            'ends_at' => '2027-12-31',
        ]);

        $this->assertDatabaseHas('semesters', [
            'academic_year_id' => $year->id,
            'number' => 2,
            'starts_at' => '2028-01-01',
            'ends_at' => '2028-06-24',
        ]);
    }

    /**
     * AC-15-08: On 2027-09-01 the current semester resolves to Ganjil 2027/2028.
     */
    public function test_current_semester_resolves_ganjil_on_date(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);
        Semester::query()->where('academic_year_id', $year->id)->delete();
        [$ganjil, $genap] = $this->service->createFor($year);

        $current = $this->service->current('2027-09-01');

        $this->assertNotNull($current);
        $this->assertSame($ganjil->id, $current->id);
        $this->assertSame(1, $current->number);
    }

    public function test_current_semester_resolves_genap_on_date(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-13',
            'ends_at' => '2028-06-24',
        ]);
        Semester::query()->where('academic_year_id', $year->id)->delete();
        [$ganjil, $genap] = $this->service->createFor($year);

        $current = $this->service->current('2028-03-15');

        $this->assertNotNull($current);
        $this->assertSame($genap->id, $current->id);
        $this->assertSame(2, $current->number);
    }

    public function test_gap_rule_resolves_to_most_recently_started_semester(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);
        Semester::query()->where('academic_year_id', $year->id)->delete();

        // Suppose there is a gap between 2027-12-20 and 2028-01-05
        $ganjil = Semester::create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semester Ganjil',
            'starts_at' => '2027-07-01',
            'ends_at' => '2027-12-20',
        ]);
        Semester::create([
            'academic_year_id' => $year->id,
            'number' => 2,
            'name' => 'Semester Genap',
            'starts_at' => '2028-01-05',
            'ends_at' => '2028-06-30',
        ]);

        // On 2027-12-25 (during holiday gap), most recently started is Ganjil
        $current = $this->service->current('2027-12-25');

        $this->assertNotNull($current);
        $this->assertSame($ganjil->id, $current->id);
    }

    public function test_resolves_null_when_no_semesters_exist_or_before_any_semester_started(): void
    {
        Semester::query()->delete();

        $this->assertNull($this->service->current('2027-01-01'));

        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);

        // Before any semester starts
        $this->assertNull($this->service->current('2020-01-01'));
    }

    public function test_current_uses_school_settings_today_when_on_is_null(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);
        Semester::query()->where('academic_year_id', $year->id)->delete();
        [$ganjil] = $this->service->createFor($year);

        Carbon::setTestNow('2027-08-15 10:00:00');

        $current = $this->service->current();
        $this->assertNotNull($current);
        $this->assertSame($ganjil->id, $current->id);

        Carbon::setTestNow();
    }
}
