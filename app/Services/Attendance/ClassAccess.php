<?php

namespace App\Services\Attendance;

use App\Enums\UserRole;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Class scoping (spec 01 §Data Scoping): admins, principals, and counselors work
 * anywhere; teachers see the classes they homeroom. A teacher without a linked
 * teacher profile sees nothing — an empty dashboard, not a 403. Today-surfaces
 * pass the active year; historical surfaces (reports, the exception dashboard)
 * omit it so past-year classes stay reachable.
 */
class ClassAccess
{
    /**
     * @return Collection<int, int>
     */
    public static function classIds(User $user, ?int $yearId = null): Collection
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Principal, UserRole::Counselor)) {
            return SchoolClass::query()
                ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
                ->pluck('id');
        }

        if ($user->hasRole(UserRole::Teacher)) {
            return SchoolClass::query()
                ->where('teacher_id', $user->teacher?->id)
                ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
                ->pluck('id');
        }

        return collect();
    }

    /**
     * Classes accessible for academic views (homeroom ∪ subject assignments).
     *
     * @return Collection<int, int>
     */
    public static function academicClassIds(User $user, ?int $yearId = null): Collection
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Principal, UserRole::Counselor)) {
            return SchoolClass::query()
                ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
                ->pluck('id');
        }

        if ($user->hasRole(UserRole::Teacher)) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                return collect();
            }

            return SchoolClass::query()
                ->where(function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId)
                        ->orWhereHas('classSubjects', fn ($q) => $q->where('teacher_id', $teacherId));
                })
                ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
                ->pluck('id');
        }

        return collect();
    }

    /**
     * Determine if the user can write grades or configure this course.
     */
    public static function canWriteCourse(User $user, ClassSubject $classSubject): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Teacher)) {
            return $user->teacher !== null && $classSubject->teacher_id === $user->teacher->id;
        }

        return false;
    }

    public static function canAccess(User $user, int $classId): bool
    {
        return self::classIds($user)->contains($classId);
    }

    public static function canWrite(User $user, int $classId): bool
    {
        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Teacher)) {
            return self::classIds($user)->contains($classId);
        }

        return false;
    }
}
