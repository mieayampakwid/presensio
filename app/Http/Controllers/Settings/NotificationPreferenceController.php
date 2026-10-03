<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;

class NotificationPreferenceController extends Controller
{
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $preferences = (array) $request->validated('preferences');

        $allowedTypes = collect(NotificationType::cases())
            ->filter(fn (NotificationType $t) => $t->optOutAllowed())
            ->map(fn (NotificationType $t) => $t->value)
            ->all();

        foreach ($preferences as $typeKey => $enabled) {
            if (! in_array($typeKey, $allowedTypes, true)) {
                continue;
            }

            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type_key' => $typeKey,
                ],
                [
                    'whatsapp_enabled' => (bool) $enabled,
                ]
            );
        }

        return redirect()->route('profile.edit');
    }
}
