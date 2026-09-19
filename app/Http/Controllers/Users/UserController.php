<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserPasswordUpdateRequest;
use App\Models\User;
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
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

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
            $user = User::create(collect($request->safe()->except(['profile_id']))->all());
            $linker->sync($user, $user->role, $request->validated('profile_id'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User created.']);

        return to_route('users.index');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user, UserProfileLinker $linker): Response
    {
        return Inertia::render('users/edit', [
            'user' => $user,
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
            $user->update(collect($request->safe()->except(['profile_id']))->all());
            $linker->sync($user, $user->role, $request->validated('profile_id'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return to_route('users.edit', $user);
    }

    /**
     * Reset the specified user's password.
     */
    public function updatePassword(UserPasswordUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->string('password')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password updated.']);

        return to_route('users.edit', $user);
    }
}
