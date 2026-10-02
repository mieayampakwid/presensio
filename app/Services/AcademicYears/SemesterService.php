<?php

namespace App\Services\AcademicYears;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class SemesterService
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * Create Ganjil and Genap semesters for an academic year using the standard 1 Jan split.
     *
     * @return array{0: Semester, 1: Semester}
     */
    public function createFor(AcademicYear $year): array
    {
        $start = Carbon::parse($year->starts_at);
        $splitYear = $start->year;

        $december31 = Carbon::create($splitYear, 12, 31)->toDateString();
        $january1 = Carbon::create($splitYear + 1, 1, 1)->toDateString();

        $ganjil = Semester::create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semester Ganjil',
            'starts_at' => $year->starts_at instanceof CarbonInterface ? $year->starts_at->toDateString() : (string) $year->starts_at,
            'ends_at' => $december31,
        ]);

        $genap = Semester::create([
            'academic_year_id' => $year->id,
            'number' => 2,
            'name' => 'Semester Genap',
            'starts_at' => $january1,
            'ends_at' => $year->ends_at instanceof CarbonInterface ? $year->ends_at->toDateString() : (string) $year->ends_at,
        ]);

        return [$ganjil, $genap];
    }

    /**
     * Resolves the current semester.
     *
     * When $on is null, resolves for today in the school timezone.
     * Query: starts_at <= date <= ends_at, else latest starts_at <= date.
     * If no semester has started by $on, or no semesters exist, returns null.
     */
    public function current(CarbonInterface|string|null $on = null): ?Semester
    {
        $date = match (true) {
            $on === null => $this->settings->todayDate(),
            $on instanceof CarbonInterface => $on->toDateString(),
            default => (string) $on,
        };

        // Check if there is an active year first, or search across semesters
        // Spec 15 §Semesters: "Current semester is derived, not flagged: the semester whose range contains
        // the school-timezone date. During a gap (none by default) the most recently started semester is current."
        $exact = Semester::query()
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        // Gap rule: most recently started semester
        return Semester::query()
            ->whereDate('starts_at', '<=', $date)
            ->orderByDesc('starts_at')
            ->first();
    }

    /**
     * Update boundaries for the two semesters of an academic year.
     *
     * @param  array<int, array{number: int, starts_at: string, ends_at: string}>  $semesters
     */
    public function updateBoundaries(AcademicYear $year, array $semesters): void
    {
        DB::transaction(function () use ($year, $semesters) {
            foreach ($semesters as $data) {
                Semester::query()
                    ->where('academic_year_id', $year->id)
                    ->where('number', $data['number'])
                    ->update([
                        'starts_at' => $data['starts_at'],
                        'ends_at' => $data['ends_at'],
                    ]);
            }
        });
    }
}
