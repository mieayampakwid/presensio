<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use App\Models\SchoolSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\WhatsAppQuota;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
        private readonly WhatsAppQuota $whatsAppQuota,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function edit(): Response
    {
        $setting = $this->schoolSettings->row();
        $channels = $this->schoolSettings->notificationChannels();

        $catalog = collect(NotificationType::cases())->map(fn (NotificationType $t) => [
            'key' => $t->value,
            'label' => $this->labelFor($t),
            'in_app' => $t->inApp(),
            'default_whatsapp' => $t->defaultWhatsapp(),
            'default_email' => $t->defaultEmail(),
            'whatsapp_allowed' => $t->whatsappAllowed(),
            'email_allowed' => $t->emailAllowed(),
            'opt_out_allowed' => $t->optOutAllowed(),
            'quota_bound' => $t->quotaBound(),
            'quiet_hours_bound' => $t->quietHoursBound(),
        ])->all();

        return Inertia::render('settings/notifications', [
            'channels' => $channels,
            'catalog' => $catalog,
            'whatsapp_daily_quota' => $setting->whatsapp_daily_quota,
            'today_whatsapp_usage' => $this->whatsAppQuota->todayUsage(),
            'quiet_hours_start' => substr((string) ($setting->quiet_hours_start ?? '21:00:00'), 0, 5),
            'quiet_hours_end' => substr((string) ($setting->quiet_hours_end ?? '06:00:00'), 0, 5),
            'bill_reminder_days_before' => (int) ($setting->bill_reminder_days_before ?? 3),
        ]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $setting = SchoolSetting::query()->firstOrFail();
        $validated = $request->validated();

        $inputChannels = (array) ($validated['notification_channels'] ?? []);
        $clampedChannels = [];

        foreach (NotificationType::cases() as $type) {
            $typeInput = (array) ($inputChannels[$type->value] ?? []);

            $clampedChannels[$type->value] = [
                'whatsapp' => $type->whatsappAllowed() && (bool) ($typeInput['whatsapp'] ?? $type->defaultWhatsapp()),
                'email' => $type->emailAllowed() && (bool) ($typeInput['email'] ?? $type->defaultEmail()),
            ];
        }

        $validated['notification_channels'] = $clampedChannels;
        $validated['quiet_hours_start'] = strlen((string) $validated['quiet_hours_start']) === 5
            ? $validated['quiet_hours_start'].':00'
            : $validated['quiet_hours_start'];
        $validated['quiet_hours_end'] = strlen((string) $validated['quiet_hours_end']) === 5
            ? $validated['quiet_hours_end'].':00'
            : $validated['quiet_hours_end'];

        DB::transaction(function () use ($setting, $validated) {
            $oldValues = $setting->only(array_keys($validated));
            $setting->update($validated);
            $newValues = $setting->only(array_keys($validated));

            $this->auditLogger->record($setting, 'updated', $oldValues, $newValues);
        });

        $this->schoolSettings->refresh();

        return redirect()->route('notification-settings.edit');
    }

    private function labelFor(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::AbsenceAlert => 'Peringatan Ketidakhadiran (Absen)',
            NotificationType::ExcuseSubmitted => 'Pengajuan Izin Masuk',
            NotificationType::ExcuseReviewed => 'Keputusan Izin / Dispensasi',
            NotificationType::ReportCardPublished => 'Penerbitan Rapor Siswa',
            NotificationType::ReportCardRetracted => 'Penarikan Kembali Rapor',
            NotificationType::PaymentVerified => 'Verifikasi Pembayaran / SPP Berhasil',
            NotificationType::PaymentRejected => 'Penolakan Pembayaran / Bukti Transfer',
            NotificationType::BillDueReminder => 'Pengingat Jatuh Tempo Tagihan',
            NotificationType::AnnouncementUrgent => 'Pengumuman Penting / Mendesak',
            NotificationType::AnnouncementAckRequired => 'Pengumuman Butuh Konfirmasi',
            NotificationType::LeaveRequestSubmitted => 'Pengajuan Cuti Staf / Guru',
            NotificationType::LeaveRequestReviewed => 'Keputusan Pengajuan Cuti Staf',
            NotificationType::StaffAbsenceDigest => 'Ringkasan Rekap Kehadiran Staf',
        };
    }
}
