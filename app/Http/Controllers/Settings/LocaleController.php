<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Save the interface language: on the account when signed in, and always
     * in a cookie so guests (login pages) get it too.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        $request->user()?->update(['locale' => $locale]);

        return back()->withCookie(Cookie::make('locale', $locale, 60 * 24 * 365));
    }
}
