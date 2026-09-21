<?php

namespace App\Services\Attendance;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Class scoping (spec 01 §Data Scoping): admins work anywhere; teachers
 * see the classes they homeroom. A teacher without a linked teacher
 * profile sees nothing — an empty dashboard, not a 403. Today-surfaces
 * pass the active year; historical surfaces (reports, the exception
 * dashboard) omit it so past-year classes stay reachable.
 */
class ClassAccess
{
    /**
     * @return Collection<int, int>
     */
    public static function classIds(User $user, ?int $yearId = null): Collection
    {
        if ($user->role === UserRole::Admin) {
            return SchoolClass::query()
                ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
                ->pluck('id');
        }

        return SchoolClass::query()
            ->where('teacher_id', $user->teacher?->id)
            ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
            ->pluck('id');
    }

    public static function canAccess(User $user, int $classId): bool
    {
        return self::classIds($user)->contains($classId);
    }
}
