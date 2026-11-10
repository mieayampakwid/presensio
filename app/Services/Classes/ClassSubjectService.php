<?php

namespace App\Services\Classes;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ClassSubjectService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Assign a subject to a class.
     *
     * @param  array<string, mixed>  $data
     */
    public function assignSubject(SchoolClass $schoolClass, array $data): ClassSubject
    {
        $data['class_id'] = $schoolClass->id;

        $classSubject = DB::transaction(fn (): ClassSubject => ClassSubject::create($data));

        $this->auditLogger->record(
            $classSubject,
            'created',
            null,
            $classSubject->only(['class_id', 'subject_id', 'teacher_id', 'passing_threshold'])
        );

        return $classSubject;
    }

    /**
     * Update an assigned class subject.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSubject(ClassSubject $classSubject, array $data): ClassSubject
    {
        DB::transaction(function () use ($classSubject, $data): void {
            $old = $classSubject->only(['teacher_id', 'passing_threshold']);

            $classSubject->update($data);

            if ($classSubject->wasChanged(['teacher_id', 'passing_threshold'])) {
                $this->auditLogger->record(
                    $classSubject,
                    'updated',
                    $old,
                    $classSubject->only(['teacher_id', 'passing_threshold'])
                );
            }
        });

        return $classSubject;
    }

    /**
     * Remove an assigned class subject.
     */
    public function removeSubject(ClassSubject $classSubject): void
    {
        $old = $classSubject->only(['class_id', 'subject_id', 'teacher_id', 'passing_threshold']);

        DB::transaction(function () use ($classSubject): void {
            $classSubject->delete();
        });

        $this->auditLogger->record(
            $classSubject,
            'deleted',
            $old,
            null
        );
    }
}
