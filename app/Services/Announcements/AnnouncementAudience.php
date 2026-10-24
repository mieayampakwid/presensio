<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementAudienceType;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnnouncementAudience
{
    public function __construct(private readonly SchoolSettings $settings) {}

    public function manager(User $user): bool
    {
        return $user->hasAnyRole(UserRole::Admin, UserRole::Principal);
    }

    /** @return Collection<int,int> */
    public function teachingClassIds(User $user): Collection
    {
        if (! $user->hasRole(UserRole::Teacher) || $user->teacher === null || ! $user->teacher->employee->is_active) {
            return collect();
        }
        $teacherId = $user->teacher->id;

        return SchoolClass::whereHas('academicYear', fn (Builder $q) => $q->where('is_active', true))->where(fn (Builder $q) => $q->where('teacher_id', $teacherId)->orWhereHas('classSubjects', fn (Builder $s) => $s->where('teacher_id', $teacherId)))->pluck('id');
    }

    /** @return Collection<int,int> */
    private function classIds(User $user): Collection
    {
        $ids = Enrollment::activeOn($this->settings->todayDate())->whereHas('student', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id)->orWhereHas('guardians', fn (Builder $g) => $g->where('user_id', $user->id));
        })->pluck('class_id');

        return $ids->merge($this->teachingClassIds($user))->unique()->values();
    }

    /** @return Builder<Announcement> */
    public function visibleTo(User $user, bool $liveOnly = true): Builder
    {
        $query = Announcement::query();
        if ($liveOnly) {
            $query->where('published_at', '<=', now());
        }
        if ($this->manager($user)) {
            return $query;
        }
        $classes = $this->classIds($user);

        return $query->where(function (Builder $q) use ($user, $classes): void {
            $q->where('created_by_user_id', $user->id)->orWhere(function (Builder $live) use ($user, $classes): void {
                $live->where('published_at', '<=', now())->where(function (Builder $audience) use ($user, $classes): void {
                    $audience->where('audience_type', 'all')
                        ->orWhere(fn (Builder $role) => $role->where('audience_type', 'role')->whereIn('audience_role', $user->roles()->map(fn (UserRole $r) => $r->value)))
                        ->orWhere(fn (Builder $class) => $class->where('audience_type', 'class')->whereIn('class_id', $classes))
                        ->orWhere(function (Builder $grade) use ($classes): void {
                            $grade->where('audience_type', 'grade_level')->where(function (Builder $targets) use ($classes): void {
                                $targets->whereRaw('1 = 0');
                                foreach (SchoolClass::whereIn('id', $classes)->get(['academic_year_id', 'grade_level']) as $class) {
                                    $targets->orWhere(fn (Builder $match) => $match->where('academic_year_id', $class->academic_year_id)->where('audience_grade_level', $class->grade_level));
                                }
                            });
                        });
                });
            });
        });
    }

    public function canManage(User $user, ?Announcement $item = null, ?int $classId = null): bool
    {
        if ($this->manager($user)) {
            return true;
        }
        if ($item !== null && $item->created_by_user_id !== $user->id) {
            return false;
        }
        $classId ??= $item?->class_id;

        return $classId !== null && $this->teachingClassIds($user)->contains($classId);
    }

    /** @return Builder<SchoolClass> */
    public function targetClasses(Announcement $item): Builder
    {
        return SchoolClass::where('academic_year_id', $item->academic_year_id)->when($item->audience_type === AnnouncementAudienceType::Classroom, fn (Builder $q) => $q->whereKey($item->class_id), fn (Builder $q) => $q->where('grade_level', $item->audience_grade_level));
    }

    /** @return Builder<User> */
    public function recipientUsers(Announcement $item): Builder
    {
        $users = User::where('is_active', true);
        if ($item->audience_type === AnnouncementAudienceType::All) {
            return $users;
        }
        if ($item->audience_type === AnnouncementAudienceType::Role) {
            return $users->whereHas('roleGrants', fn (Builder $q) => $q->where('role', $item->audience_role));
        }
        $classes = $this->targetClasses($item)->pluck('id');
        $date = $this->settings->todayDate();
        $studentIds = Enrollment::activeOn($date)->whereIn('class_id', $classes)->pluck('student_id');
        $activeClasses = SchoolClass::whereIn('id', $classes)->whereHas('academicYear', fn (Builder $q) => $q->where('is_active', true))->pluck('id');
        $teacherIds = SchoolClass::whereIn('id', $activeClasses)->whereNotNull('teacher_id')->pluck('teacher_id')->merge(ClassSubject::whereIn('class_id', $activeClasses)->pluck('teacher_id'))->unique();

        return $users->where(function (Builder $q) use ($studentIds, $teacherIds): void {
            $q->whereHas('student', fn (Builder $s) => $s->whereIn('id', $studentIds))
                ->orWhereHas('guardian.students', fn (Builder $s) => $s->whereIn('students.id', $studentIds))
                ->orWhere(function (Builder $t) use ($teacherIds): void {
                    $t->whereHas('roleGrants', fn (Builder $r) => $r->where('role', 'teacher'))->whereHas('teacher', fn (Builder $teacher) => $teacher->whereIn('teachers.id', $teacherIds)->whereHas('employee', fn (Builder $e) => $e->where('is_active', true)));
                });
        });
    }

    /** @return Builder<Guardian> */
    public function accountlessGuardians(Announcement $item): Builder
    {
        $query = Guardian::whereNull('user_id');
        if ($item->audience_type === AnnouncementAudienceType::All || ($item->audience_type === AnnouncementAudienceType::Role && $item->audience_role === 'parent')) {
            return $query;
        }
        if ($item->audience_type === AnnouncementAudienceType::Role) {
            return $query->whereRaw('1 = 0');
        }
        $classes = $this->targetClasses($item)->pluck('id');

        return $query->whereHas('students.enrollments', fn (Builder $q) => $q->activeOn($this->settings->todayDate())->whereIn('class_id', $classes));
    }
}
