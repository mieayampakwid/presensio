<?php

namespace App\ReportCards;

use App\Models\ReportCardEntry;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ReportCardSnapshotBuilder
{
    /**
     * Students enrolled in the class as of min(today, semester.ends_at);
     * an enrollment ended exactly on the as-of date is included.
     *
     * @return Collection<int, Student>
     */
    public function roster(SchoolClass $class, Semester $semester): Collection;

    /**
     * Build the immutable report card payload for one student from live data.
     * Every value is copied at build time: later profile, logo or result
     * changes never alter the returned array.
     *
     * @return array<string, mixed>
     */
    public function build(
        SchoolClass $class,
        Semester $semester,
        Student $student,
        int $version,
        CarbonInterface $publishedAt,
        ?ReportCardEntry $entry = null,
    ): array;
}
