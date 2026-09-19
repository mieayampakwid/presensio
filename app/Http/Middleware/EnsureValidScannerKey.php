<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared device-key auth for the scanner endpoint (spec 03 §Decisions
 * "Scanner Authentication"). Any configured key is accepted; an unset
 * config fails closed.
 */
class EnsureValidScannerKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Scanner-Key');

        foreach ((array) config('attendance.scanner_keys', []) as $key) {
            if ($key !== '' && hash_equals((string) $key, $provided)) {
                return $next($request);
            }
        }

        abort(401, 'Invalid scanner key.');
    }
}
