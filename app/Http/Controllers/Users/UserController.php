<?php

namespace App\Http\Controllers\Users;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserPasswordUpdateRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\UserProfileLinker;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->with('roleGrants')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'roles' => $user->roles()->map(fn ($r) => $r->value)->values()->all(),
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(UserProfileLinker $linker): Response
    {
        return Inertia::render('users/create', [
            'profiles' => $linker->profileOptions(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request, UserProfileLinker $linker): RedirectResponse
    {
        DB::transaction(function () use ($request, $linker): void {
            $roles = (array) $request->input('roles', []);
            $parsedRoles = array_filter(
                array_map(fn ($r) => is_string($r) ? UserRole::tryFrom($r) : $r, $roles),
                fn ($r) => $r instanceof UserRole
            );

            $enumOrder = array_flip(array_map(fn (UserRole $case) => $case->value, UserRole::cases()));
            usort($parsedRoles, fn (UserRole $a, UserRole $b) => $enumOrder[$a->value] <=> $enumOrder[$b->value]);

            $primaryRole = $parsedRoles[0] ?? UserRole::Teacher;

            $data = collect($request->safe()->except(['profile_id', 'roles', 'role']))->all();

            $user = User::create($data);

            $user->roleGrants()->delete();
            foreach ($parsedRoles as $role) {
                $user->roleGrants()->create([
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }

            $newRoles = collect($parsedRoles)->map(fn ($r) => $r->value)->values()->all();
            app(AuditLogger::class)->record(
                $user,
                'created',
                null,
                ['roles' => $newRoles]
            );
            $user->unsetRelation('roleGrants');
            $linker->sync($user, $primaryRole, $request->validated('profile_id'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('users.toast.created')]);

        return to_route('users.index');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user, UserProfileLinker $linker): Response
    {
        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'roles' => $user->roles()->map(fn ($r) => $r->value)->values()->all(),
                'is_active' => $user->is_active,
            ],
            'profiles' => $linker->profileOptions($user),
            'current_profile_ids' => [
                'teacher' => $user->teacher()->value('id'),
                'parent' => $user->guardian()->value('id'),
                'student' => $user->student()->value('id'),
            ],
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user, UserProfileLinker $linker): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $linker): void {
            $oldRoles = $user->roles()->map(fn ($r) => $r->value)->values()->all();
            $roles = (array) $request->input('roles', []);
            $parsedRoles = array_filter(
                array_map(fn ($r) => is_string($r) ? UserRole::tryFrom($r) : $r, $roles),
                fn ($r) => $r instanceof UserRole
            );

            $enumOrder = array_flip(array_map(fn (UserRole $case) => $case->value, UserRole::cases()));
            usort($parsedRoles, fn (UserRole $a, UserRole $b) => $enumOrder[$a->value] <=> $enumOrder[$b->value]);

            $primaryRole = $parsedRoles[0] ?? UserRole::Teacher;

            $data = collect($request->safe()->except(['profile_id', 'roles', 'role']))->all();

            $user->update($data);

            $user->roleGrants()->delete();
            foreach ($parsedRoles as $role) {
                $user->roleGrants()->create([
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }

            $newRoles = collect($parsedRoles)->map(fn ($r) => $r->value)->values()->all();
            if ($oldRoles !== $newRoles) {
                app(AuditLogger::class)->record(
                    $user,
                    'roles_updated',
                    ['roles' => $oldRoles],
                    ['roles' => $newRoles]
                );
            }
            $user->unsetRelation('roleGrants');
            $linker->sync($user, $primaryRole, $request->validated('profile_id'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('users.toast.updated')]);

        return to_route('users.edit', $user);
    }

    /**
     * Reset the specified user's password.
     */
    public function updatePassword(UserPasswordUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->string('password')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('users.toast.password_updated')]);

        return to_route('users.edit', $user);
    }
}
