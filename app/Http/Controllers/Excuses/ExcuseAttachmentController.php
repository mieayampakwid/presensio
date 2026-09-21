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
 * Authorized excuse-attachment download — the only file-serving route in
 * the app. Attachments live on the private local disk under opaque token
 * names; access mirrors who may see the excuse itself: admins, the
 * child's guardians, and the child's homeroom teacher.
 */
class ExcuseAttachmentController extends Controller
{
    public function show(Request $request, Excuse $excuse): StreamedResponse
    {
        $user = $request->user();
        $path = $excuse->attachment_path;

        // Authorization first — a missing file must not leak to a probe
        // from someone who could not view the excuse anyway.
        abort_unless($this->canView($user, $excuse), 403);
        abort_if($path === null, 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download((string) $path, "excuse-{$excuse->id}.{$extension}");
    }

    private function canView(User $user, Excuse $excuse): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Parent) {
            return $user->guardian?->students()->whereKey($excuse->student_id)->exists() ?? false;
        }

        if ($user->role === UserRole::Teacher) {
            // The class the student was enrolled in when the excuse started
            // (spec 07 — date-effective, so a mid-year move keeps the old
            // homeroom's teacher able to see the old excuse).
            $class = $excuse->student->classOn($excuse->start_date->toDateString());

            return $class !== null && ClassAccess::canAccess($user, $class->id);
        }

        return false;
    }
}
