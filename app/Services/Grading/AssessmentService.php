<?php

namespace App\Services\Grading;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Semester;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class AssessmentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Store a newly created assessment and sync linked learning objectives.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(ClassSubject $classSubject, array $data): Assessment
    {
        /** @var list<int> $objectiveIds */
        $objectiveIds = $data['objective_ids'] ?? [];
        unset($data['objective_ids']);

        /** @var Semester $semester */
        $semester = Semester::findOrFail($data['semester_id']);
        $courseSemester = CourseSemester::forCourse($classSubject, $semester);
        $courseSemester->assertOpen();

        $data['class_subject_id'] = $classSubject->id;

        return DB::transaction(function () use ($data, $objectiveIds): Assessment {
            /** @var Assessment $assessment */
            $assessment = Assessment::create($data);

            if (! empty($objectiveIds)) {
                $assessment->learningObjectives()->sync($objectiveIds);
            }

            $this->auditLogger->record(
                $assessment,
                'created',
                null,
                [
                    'name' => $assessment->name,
                    'kind' => $assessment->kind->value,
                    'assessed_on' => $assessment->assessed_on->toDateString(),
                    'max_score' => $assessment->max_score,
                    'objective_ids' => $objectiveIds,
                ]
            );

            return $assessment;
        });
    }

    /**
     * Update an assessment and sync linked learning objectives.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ClassSubject $classSubject, Assessment $assessment, array $data): Assessment
    {
        $courseSemester = CourseSemester::forCourse($classSubject, $assessment->semester);
        $courseSemester->assertOpen();

        /** @var list<int> $objectiveIds */
        $objectiveIds = $data['objective_ids'] ?? [];
        unset($data['objective_ids']);

        $oldValues = [
            'name' => $assessment->name,
            'kind' => $assessment->kind->value,
            'assessed_on' => $assessment->assessed_on->toDateString(),
            'max_score' => $assessment->max_score,
            'sort_order' => $assessment->sort_order,
            'objective_ids' => $assessment->learningObjectives()->pluck('id')->all(),
        ];

        return DB::transaction(function () use ($assessment, $data, $objectiveIds, $oldValues): Assessment {
            $assessment->update($data);
            $assessment->learningObjectives()->sync($objectiveIds);

            $this->auditLogger->record(
                $assessment,
                'updated',
                $oldValues,
                [
                    'name' => $assessment->name,
                    'kind' => $assessment->kind->value,
                    'assessed_on' => $assessment->assessed_on->toDateString(),
                    'max_score' => $assessment->max_score,
                    'sort_order' => $assessment->sort_order,
                    'objective_ids' => $objectiveIds,
                ]
            );

            return $assessment;
        });
    }
}
