<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Collection;

class RecipientResolver
{
    public function forUser(User $user, ?string $customKey = null): Recipient
    {
        return Recipient::fromUser($user, $customKey);
    }

    /**
     * @param  iterable<User>  $users
     * @return Collection<int, Recipient>
     */
    public function forUsers(iterable $users): Collection
    {
        return collect($users)->map(fn (User $u) => $this->forUser($u));
    }

    public function forGuardian(Guardian $guardian, ?string $customKey = null): Recipient
    {
        return Recipient::fromGuardian($guardian, $customKey);
    }

    /**
     * @param  iterable<Guardian>  $guardians
     * @return Collection<int, Recipient>
     */
    public function forGuardians(iterable $guardians): Collection
    {
        return collect($guardians)->map(fn (Guardian $g) => $this->forGuardian($g));
    }

    public function forEmployee(Teacher|Employee $employee, ?string $customKey = null): Recipient
    {
        return Recipient::fromEmployee($employee, $customKey);
    }

    public function forHomeroomTeacher(SchoolClass $class): ?Recipient
    {
        $class->loadMissing('teacher.employee.user');

        if (! $class->teacher) {
            return null;
        }

        return $this->forEmployee($class->teacher);
    }

    /**
     * @return Collection<int, Recipient>
     */
    public function forAdmins(): Collection
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roleGrants', fn ($q) => $q->where('role', UserRole::Admin->value))
            ->get();

        return $users->map(fn (User $u) => $this->forUser($u));
    }

    /**
     * @return Collection<int, Recipient>
     */
    public function forPrincipals(): Collection
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roleGrants', fn ($q) => $q->where('role', UserRole::Principal->value))
            ->get();

        return $users->map(fn (User $u) => $this->forUser($u));
    }

    /**
     * Resolve all active students and/or linked guardians of a class.
     *
     * @return Collection<int, Recipient>
     */
    public function forClass(SchoolClass $class, bool $includeStudents = true, bool $includeGuardians = true): Collection
    {
        $enrollments = $class->enrollments()
            ->whereNull('ended_on')
            ->with(['student.user', 'student.guardians.user'])
            ->get();

        $recipients = collect();

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if (! $student) {
                continue;
            }

            if ($includeStudents && $student->user) {
                $recipients->push($this->forUser($student->user));
            }

            if ($includeGuardians) {
                foreach ($student->guardians as $guardian) {
                    $recipients->push($this->forGuardian($guardian));
                }
            }
        }

        return $recipients->unique(fn (Recipient $r) => $r->key())->values();
    }
}
