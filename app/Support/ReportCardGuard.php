<?php

namespace App\Support;

use App\Models\ClassSubject;
use App\Models\ReportCardPublication;
use App\Models\Semester;

class ReportCardGuard
{
    /**
     * Determine if report cards for this course's class and semester have a
     * current (published, not retracted) version.
     */
    public function isPublished(ClassSubject $classSubject, Semester $semester): bool
    {
        return ReportCardPublication::query()
            ->where('class_id', $classSubject->class_id)
            ->where('semester_id', $semester->id)
            ->current()
            ->exists();
    }
}
