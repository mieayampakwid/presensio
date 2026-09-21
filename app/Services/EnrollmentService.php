<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sole writer of enrollment history (spec 02 v2.0 / spec 07) — the same
 * single-writer pattern as UserProfileLinker. Assigning the same class a
 * student already attends is a no-op, so form re-saves never create
 * history noise.
 */
class EnrollmentService
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * Enroll the student into the class, ending any open enrollment the
     * day before. Returns the open enrollment (pre-existing when the
     * class is unchanged).
     */
    public function assign(Student $student, SchoolClass $class, ?string $startedOn = null): Enrollment
    {
        $startedOn ??= $this->settings->todayDate();

        return DB::transaction(function () use ($student, $class, $startedOn): Enrollment {
            /** @var Enrollment|null $open */
            $open = $student->enrollments()->whereNull('ended_on')->lockForUpdate()->first();

            if ($open !== null && $open->class_id === $class->id) {
                return $open;
            }

            $endedOn = $this->dayBefore($startedOn);

            if ($open !== null) {
                // A same-day class switch may not end the old enrollment
                // before it began — clamp to keep the record valid.
                $open->forceFill(['ended_on' => max($endedOn, $open->started_on->toDateString())])->save();
            }

            $openId = $open !== null ? $open->id : 0;

            $conflict = $student->enrollments()
                ->whereKeyNot($openId)
                ->where(fn ($query) => $query->whereNull('ended_on')->orWhere('ended_on', '>=', $startedOn))
                ->first();

            if ($conflict !== null) {
                throw new InvalidArgumentException(
                    "Enrollment period overlaps an existing enrollment ({$conflict->started_on->toDateString()} – "
                    .($conflict->ended_on?->toDateString() ?? 'open').').',
                );
            }

            return $student->enrollments()->create([
                'class_id' => $class->id,
                'started_on' => $startedOn,
                'ended_on' => null,
            ]);
        });
    }

    /**
     * End the student's open enrollment (alumni/left). Idempotent.
     */
    public function release(Student $student, ?string $endedOn = null): void
    {
        DB::transaction(function () use ($student, $endedOn): void {
            /** @var Enrollment|null $open */
            $open = $student->enrollments()->whereNull('ended_on')->lockForUpdate()->first();

            if ($open === null) {
                return;
            }

            $open->forceFill([
                'ended_on' => max($endedOn ?? $this->settings->todayDate(), $open->started_on->toDateString()),
            ])->save();
        });
    }

    private function dayBefore(string $date): string
    {
        return Date::parse($date)->subDay()->toDateString();
    }
}
