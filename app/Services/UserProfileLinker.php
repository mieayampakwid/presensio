<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Sole writer of user↔profile link state: every attach/detach between users
 * and their teacher/staff/guardian/student profile goes through this service.
 */
class UserProfileLinker
{
    /**
     * Role-to-profile-model map; admins have no profile.
     *
     * @var array<string, class-string<Teacher|Employee|Guardian|Student>>
     */
    private const PROFILE_MODELS = [
        UserRole::Teacher->value => Teacher::class,
        UserRole::Staff->value => Employee::class,
        UserRole::Parent->value => Guardian::class,
        UserRole::Student->value => Student::class,
    ];

    /**
     * Detach any profile rows pointing at the user for roles the user no longer holds,
     * then attach/detach the selected profile for the given role.
     */
    public function sync(User $user, UserRole $role, ?int $profileId): void
    {
        DB::transaction(function () use ($user, $role, $profileId): void {
            $userRoles = $user->roles();

            if (! $userRoles->contains(UserRole::Teacher) && ! $userRoles->contains(UserRole::Staff)) {
                $user->employee?->update(['user_id' => null]);
            }
            if (! $userRoles->contains(UserRole::Parent)) {
                Guardian::where('user_id', $user->id)->update(['user_id' => null]);
            }
            if (! $userRoles->contains(UserRole::Student)) {
                Student::where('user_id', $user->id)->update(['user_id' => null]);
            }

            if ($role === UserRole::Teacher) {
                $user->employee?->update(['user_id' => null]);

                if ($profileId !== null) {
                    $teacher = Teacher::with('employee')->find($profileId);
                    $teacher?->employee?->update(['user_id' => $user->id]);
                }
            } elseif ($role === UserRole::Staff) {
                $user->employee?->update(['user_id' => null]);

                if ($profileId !== null) {
                    Employee::whereKey($profileId)->update(['user_id' => $user->id]);
                }
            } elseif ($role === UserRole::Parent) {
                Guardian::where('user_id', $user->id)->update(['user_id' => null]);

                if ($profileId !== null) {
                    Guardian::whereKey($profileId)->update(['user_id' => $user->id]);
                }
            } elseif ($role === UserRole::Student) {
                Student::where('user_id', $user->id)->update(['user_id' => null]);

                if ($profileId !== null) {
                    Student::whereKey($profileId)->update(['user_id' => $user->id]);
                }
            }
        });
    }

    /**
     * Request-side guard for a submitted profile_id: it must match the
     * selected role's table, be unlinked (or already the target user's), and
     * never attach to an admin. On store, pass null as the target user.
     */
    public function validateSelection(Validator $validator, ?string $roleValue, ?int $profileId, ?User $target): void
    {
        if ($profileId === null) {
            return;
        }

        $role = $roleValue === null ? null : UserRole::tryFrom($roleValue);

        if ($role === null || ! isset(self::PROFILE_MODELS[$role->value])) {
            $validator->errors()->add('profile_id', 'Admin accounts cannot be linked to a profile.');

            return;
        }

        /** @var Teacher|Employee|Guardian|Student|null $profile */
        $profile = self::PROFILE_MODELS[$role->value]::query()->find($profileId);

        if ($profile === null) {
            $validator->errors()->add('profile_id', "The selected profile does not match the {$role->value} role.");

            return;
        }

        $linkedUserId = match (true) {
            $profile instanceof Teacher => $profile->employee?->user_id,
            $profile instanceof Employee => $profile->user_id,
            $profile instanceof Guardian => $profile->user_id,
            $profile instanceof Student => $profile->user_id,
            default => null,
        };

        if ($linkedUserId !== null && $linkedUserId !== $target?->id) {
            $validator->errors()->add('profile_id', 'The selected profile is already linked to another user.');
        }
    }

    /**
     * Options for the users forms: per role, the unlinked profiles, with the
     * given user's currently linked profile injected into its role's list.
     *
     * @return array<string, list<array{id: int, label: string}>>
     */
    public function profileOptions(?User $forUser = null): array
    {
        $options = [];

        // Teacher
        $options[UserRole::Teacher->value] = [];
        $unlinkedTeachers = Teacher::with('employee')
            ->whereHas('employee', fn ($q) => $q->whereNull('user_id'))
            ->get()
            ->sortBy(fn (Teacher $t) => $t->employee?->name ?? '')
            ->values();

        foreach ($unlinkedTeachers as $teacher) {
            $options[UserRole::Teacher->value][] = [
                'id' => $teacher->id,
                'label' => $teacher->employee?->name ?? '',
            ];
        }

        if ($forUser !== null && $forUser->teacher !== null) {
            $options[UserRole::Teacher->value][] = [
                'id' => $forUser->teacher->id,
                'label' => $forUser->teacher->employee?->name ?? '',
            ];
        }

        // Staff
        $options[UserRole::Staff->value] = [];
        $unlinkedStaff = Employee::whereNull('user_id')
            ->whereDoesntHave('teacher')
            ->orderBy('name')
            ->get();

        foreach ($unlinkedStaff as $staff) {
            $options[UserRole::Staff->value][] = [
                'id' => $staff->id,
                'label' => $staff->name,
            ];
        }

        if ($forUser !== null && $forUser->employee !== null && $forUser->teacher === null) {
            $options[UserRole::Staff->value][] = [
                'id' => $forUser->employee->id,
                'label' => $forUser->employee->name,
            ];
        }

        // Parent
        $options[UserRole::Parent->value] = [];
        $unlinkedParents = Guardian::whereNull('user_id')->orderBy('name')->get();
        foreach ($unlinkedParents as $parent) {
            $options[UserRole::Parent->value][] = [
                'id' => $parent->id,
                'label' => $parent->name,
            ];
        }
        if ($forUser !== null && $forUser->guardian !== null) {
            $options[UserRole::Parent->value][] = [
                'id' => $forUser->guardian->id,
                'label' => $forUser->guardian->name,
            ];
        }

        // Student
        $options[UserRole::Student->value] = [];
        $unlinkedStudents = Student::whereNull('user_id')->orderBy('full_name')->get();
        foreach ($unlinkedStudents as $student) {
            $options[UserRole::Student->value][] = [
                'id' => $student->id,
                'label' => $student->full_name,
            ];
        }
        if ($forUser !== null && $forUser->student !== null) {
            $options[UserRole::Student->value][] = [
                'id' => $forUser->student->id,
                'label' => $forUser->student->full_name,
            ];
        }

        return $options;
    }
}
