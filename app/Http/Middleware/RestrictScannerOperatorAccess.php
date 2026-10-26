<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictScannerOperatorAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole(UserRole::ScannerOperator)) {
            abort_unless($request->routeIs([
                'scanner.index', 'scanner.scan', 'dashboard', 'logout',
                'login', 'login.store', 'two-factor.login', 'two-factor.login.store',
                'passkey.login-options', 'passkey.login', 'home',
                'password.request', 'password.email', 'password.reset', 'password.update',
                'well-known.passkeys',
            ]), 403);
        }

        return $next($request);
    }
}
