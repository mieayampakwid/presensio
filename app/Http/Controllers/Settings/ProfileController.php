<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
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
        $user = $request->user();
        $guardian = $user?->guardian;
        $preferences = null;

        if ($guardian !== null && $user !== null) {
            $existing = NotificationPreference::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('type_key');

            $preferences = collect(NotificationType::cases())
                ->filter(fn (NotificationType $t) => $t->optOutAllowed())
                ->map(fn (NotificationType $t) => [
                    'key' => $t->value,
                    'label' => match ($t) {
                        NotificationType::ExcuseReviewed => 'Keputusan Izin / Dispensasi',
                        NotificationType::ReportCardPublished => 'Penerbitan Rapor Siswa',
                        NotificationType::PaymentVerified => 'Verifikasi Pembayaran',
                        NotificationType::BillDueReminder => 'Pengingat Jatuh Tempo Tagihan',
                        NotificationType::AnnouncementUrgent => 'Pengumuman Penting',
                        default => $t->value,
                    },
                    'whatsapp_enabled' => isset($existing[$t->value])
                        ? (bool) $existing[$t->value]->whatsapp_enabled
                        : $t->defaultWhatsapp(),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('settings/profile', [
            'guardian' => $guardian,
            'notification_preferences' => $preferences,
        ]);
    }
}
