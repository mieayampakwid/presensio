<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Middleware parameters arrive as strings (userland functions do not
     * coerce strings to enums), so match against the enum's backed value.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $parsedRoles = array_filter(
            array_map(fn (string $role) => UserRole::tryFrom($role), $roles),
            fn (?UserRole $role) => $role !== null
        );

        if (empty($parsedRoles) || ! $user->hasAnyRole(...$parsedRoles)) {
            abort(403);
        }

        return $next($request);
    }
}
