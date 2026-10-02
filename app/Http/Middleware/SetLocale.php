<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the request locale: the user's saved preference, then the locale
     * cookie, then the configured default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $saved = $request->user()?->locale;
        $cookie = $request->cookie('locale');

        $locale = ($saved !== null ? Locale::tryFrom($saved) : null)
            ?? (is_string($cookie) ? Locale::tryFrom($cookie) : null);

        if ($locale !== null) {
            App::setLocale($locale->value);
        }

        return $next($request);
    }
}
