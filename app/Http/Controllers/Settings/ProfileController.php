<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page (read-only — username and role are
     * admin-managed per spec 01 §6). Guardians additionally get their own
     * contact section (spec 02 §9).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'guardian' => $request->user()?->guardian,
        ]);
    }
}
