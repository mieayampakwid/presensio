<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActiveRoleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $target = UserRole::from($validated['role']);
        $user = $request->user();

        if (! $user->hasRole($target)) {
            abort(403, 'You do not hold this role.');
        }

        $request->session()->put('active_role', $target->value);

        return back();
    }
}
