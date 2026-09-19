<?php

namespace App\Services\Attendance;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Class scoping for the exception dashboard (spec 03 §Requirements 4).
 * Admins work anywhere; teachers see the classes they homeroom. A teacher
 * without a linked teacher profile sees nothing — an empty dashboard, not
 * a 403.
 */
class ClassAccess
{
    /**
     * @return Collection<int, int>
     */
    public static function classIds(User $user): Collection
    {
        if ($user->role === UserRole::Admin) {
            return SchoolClass::query()->pluck('id');
        }

        return SchoolClass::query()->where('teacher_id', $user->teacher?->id)->pluck('id');
    }

    public static function canAccess(User $user, int $classId): bool
    {
        return self::classIds($user)->contains($classId);
    }
}
