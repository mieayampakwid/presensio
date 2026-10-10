<?php

namespace App\Support\Finance;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Turns a finance validation rejection into the response its caller expects.
 *
 * The SPA gets an error toast and stays on the page it posted from; every other
 * caller keeps the documented HTTP 422, so the ledger's rejection contract is
 * unchanged for API clients and tests.
 */
final class RejectionResponse
{
    public static function make(Request $request, string $message): RedirectResponse
    {
        if ($request->header('X-Inertia') === 'true') {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back();
        }

        abort(422, $message);
    }
}
