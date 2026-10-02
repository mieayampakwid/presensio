<?php

namespace App\Http\Controllers\Excuses;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Excuse;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a submitted excuse note attachment. Access mirrors the excuse
 * review queue / student report: admins anywhere, homeroom teachers for
 * their students, parents for their own children.
 */
class ExcuseAttachmentController extends Controller
{
    public function show(Request $request, Excuse $excuse): StreamedResponse
    {
        $user = $request->user();

        abort_unless($this->canView($user, $excuse), 403);

        $path = $excuse->attachment_path;

        abort_if($path === null, 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download((string) $path, "excuse-{$excuse->id}.{$extension}");
    }

    private function canView(User $user, Excuse $excuse): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Parent) && ($user->guardian?->students()->whereKey($excuse->student_id)->exists() ?? false)) {
            return true;
        }

        if ($user->hasAnyRole(UserRole::Teacher, UserRole::Principal, UserRole::Counselor)) {
            // The class the student was enrolled in when the excuse started
            // (spec 07 — date-effective, so a mid-year move keeps the old
            // homeroom's teacher able to see the old excuse).
            $class = $excuse->student->classOn($excuse->start_date->toDateString());

            return $class !== null && ClassAccess::canAccess($user, $class->id);
        }

        return false;
    }
}
