<?php

namespace App\Services\Grading;

use App\Models\AcademicYear;
use App\Models\LearningObjective;
use Illuminate\Support\Facades\DB;

class CopyLearningObjectivesAction
{
    /**
     * Copy learning objectives from the previous academic year for the given target year, subject, and grade level.
     *
     * Returns null if no previous year was found, or the count of newly copied objectives.
     */
    public function execute(int $academicYearId, int $subjectId, int $gradeLevel): ?int
    {
        /** @var AcademicYear $targetYear */
        $targetYear = AcademicYear::findOrFail($academicYearId);

        $prevYear = AcademicYear::query()
            ->where('id', '!=', $targetYear->id)
            ->where('starts_at', '<', $targetYear->starts_at->toDateString())
            ->orderByDesc('starts_at')
            ->first();

        if ($prevYear === null) {
            return null;
        }

        return DB::transaction(function () use ($targetYear, $prevYear, $subjectId, $gradeLevel): int {
            $previousObjectives = LearningObjective::query()
                ->where('academic_year_id', $prevYear->id)
                ->where('subject_id', $subjectId)
                ->where('grade_level', $gradeLevel)
                ->get();

            $existingCodes = LearningObjective::query()
                ->where('academic_year_id', $targetYear->id)
                ->where('subject_id', $subjectId)
                ->where('grade_level', $gradeLevel)
                ->pluck('code')
                ->all();

            $copiedCount = 0;
            foreach ($previousObjectives as $prev) {
                if (in_array($prev->code, $existingCodes, true)) {
                    continue;
                }

                LearningObjective::create([
                    'academic_year_id' => $targetYear->id,
                    'subject_id' => $subjectId,
                    'grade_level' => $gradeLevel,
                    'code' => $prev->code,
                    'description' => $prev->description,
                    'sort_order' => $prev->sort_order,
                ]);

                $copiedCount++;
            }

            return $copiedCount;
        });
    }
}
