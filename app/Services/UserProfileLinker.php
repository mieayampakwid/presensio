<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Sole writer of user↔profile link state: every attach/detach between users
 * and their teacher/guardian/student profile goes through this service.
 */
class UserProfileLinker
{
    /**
     * Role-to-profile-model map; admins have no profile.
     *
     * @var array<string, class-string<Teacher|Guardian|Student>>
     */
    private const PROFILE_MODELS = [
        UserRole::Teacher->value => Teacher::class,
        UserRole::Parent->value => Guardian::class,
        UserRole::Student->value => Student::class,
    ];

    /**
     * Detach any profile rows pointing at the user, then attach the selected
     * profile for the given role. Passing a null profile id detaches only,
     * so changing role always clears the old link.
     */
    public function sync(User $user, UserRole $role, ?int $profileId): void
    {
        DB::transaction(function () use ($user, $role, $profileId): void {
            foreach (self::PROFILE_MODELS as $model) {
                $model::where('user_id', $user->id)->update(['user_id' => null]);
            }

            $model = self::PROFILE_MODELS[$role->value] ?? null;

            if ($profileId !== null && $model !== null) {
                $model::whereKey($profileId)->update(['user_id' => $user->id]);
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

        /** @var Teacher|Guardian|Student|null $profile */
        $profile = self::PROFILE_MODELS[$role->value]::query()->find($profileId);

        if ($profile === null) {
            $validator->errors()->add('profile_id', "The selected profile does not match the {$role->value} role.");

            return;
        }

        if ($profile->user_id !== null && $profile->user_id !== $target?->id) {
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
        $labels = [
            Teacher::class => fn (Teacher $teacher): string => $teacher->name,
            Guardian::class => fn (Guardian $guardian): string => $guardian->name,
            Student::class => fn (Student $student): string => $student->full_name,
        ];

        $options = [];

        foreach (self::PROFILE_MODELS as $roleValue => $model) {
            $options[$roleValue] = [];

            $profiles = $model::query()
                ->whereNull('user_id')
                ->orderBy($model === Student::class ? 'full_name' : 'name')
                ->get();

            foreach ($profiles as $profile) {
                $options[$roleValue][] = [
                    'id' => $profile->id,
                    'label' => $labels[$model]($profile),
                ];
            }
        }

        if ($forUser !== null) {
            foreach (self::PROFILE_MODELS as $roleValue => $model) {
                /** @var Teacher|Guardian|Student|null $linked */
                $linked = $model::query()->where('user_id', $forUser->id)->first();

                if ($linked !== null) {
                    $options[$roleValue][] = [
                        'id' => $linked->id,
                        'label' => $labels[$model]($linked),
                    ];
                }
            }
        }

        return $options;
    }
}
