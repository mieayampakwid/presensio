<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGuardianContactRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GuardianContactController extends Controller
{
    /**
     * Update the authenticated guardian's own contact fields. Extra posted
     * fields (name, user_id, …) are ignored — the FormRequest only validates
     * the three contact fields and only they are persisted.
     */
    public function update(UpdateGuardianContactRequest $request): RedirectResponse
    {
        $request->user()->guardian->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contact details updated.']);

        return to_route('profile.edit');
    }
}
