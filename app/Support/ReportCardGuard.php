<?php

namespace App\Support;

use App\Models\ClassSubject;
use App\Models\Semester;

class ReportCardGuard
{
    /**
     * Determine if report cards for this course and semester have been published.
     * Stubbed to false until Plan 7 introduces digital report cards and publication.
     */
    public function isPublished(ClassSubject $classSubject, Semester $semester): bool
    {
        return false;
    }
}
