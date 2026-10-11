<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Services\Attendance\ClassAccess;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssessmentReleaseController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Release assessment scores to students and guardians (idempotent).
     */
    public function release(Request $request, ClassSubject $classSubject, Assessment $assessment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ClassAccess::canWriteCourse($user, $classSubject), 403);
        abort_if($assessment->class_subject_id !== $classSubject->id, 404);

        if ($assessment->released_at === null) {
            $now = now();
            $assessment->update([
                'released_at' => $now,
            ]);

            $this->auditLogger->record(
                $assessment,
                'released',
                null,
                ['released_at' => $now->toIso8601String()]
            );
        }

        return back()->with('success', 'Nilai asesmen berhasil dirilis.');
    }
}
