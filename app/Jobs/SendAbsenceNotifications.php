<?php

namespace App\Jobs;

use App\Enums\AttendanceStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationType;
use App\Mail\AbsenceAlertMail;
use App\Models\AbsenceNotification;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Student;
use App\Notifications\AppNotification;
use App\Services\Notifications\WhatsAppClient;
use App\Services\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Sends the guardian alert for one freshly created attendance record
 * whose status is enabled in config (default: absent only — spec 05):
 * WhatsApp via WAHA first, email when the guardian has no phone on file.
 * A send failure after the queue retries is escalated to failed(), which
 * delivers the strict email fallback for WhatsApp rows.
 */
class SendAbsenceNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly Attendance $attendance) {}

    public function handle(WhatsAppClient $whatsapp): void
    {
        // Fresh state, not the serialized snapshot: the record may have
        // been corrected between dispatch and delivery — a correction
        // suppresses the alert (spec 05: no retraction, no stale sends).
        $attendance = Attendance::query()->findOrFail($this->attendance->id);

        if (! in_array($attendance->status->value, $this->statuses(), true)) {
            return;
        }

        $attendance->load('student.currentEnrollment.schoolClass', 'student.guardians');
        $student = $attendance->student;

        // No guardians → nothing to send, no error (spec 05 §Requirements 5).
        if ($student->guardians->isEmpty()) {
            return;
        }

        // Additional in-app copy for linked guardians with accounts (spec 17 §Decisions)
        foreach ($student->guardians as $guardian) {
            $guardian->loadMissing('user');
            if ($guardian->user !== null) {
                $dedupeKey = "absence_alert:{$attendance->id}:{$guardian->user->id}";
                try {
                    $guardian->user->notify(new AppNotification(
                        type: NotificationType::AbsenceAlert,
                        title: 'Pemberitahuan Ketidakhadiran',
                        body: "Siswa {$student->full_name} tercatat {$this->statusLabel($attendance->status)} pada tanggal {$attendance->date->toDateString()}.",
                        url: '/my-attendance',
                        key: $dedupeKey,
                    ));
                } catch (UniqueConstraintViolationException) {
                    // Already written
                } catch (QueryException $e) {
                    if ($e->getCode() !== '23505') {
                        throw $e;
                    }
                }
            }
        }

        $text = $this->message($attendance, $student);
        $failed = false;

        foreach ($student->guardians as $guardian) {
            $target = $this->targetFor($guardian);

            if ($target === null) {
                continue;
            }

            [$channel, $recipient] = $target;

            $row = $this->claim($attendance->id, $guardian->id, $channel);

            if ($row === null) {
                continue;
            }

            try {
                if ($channel === NotificationChannel::WhatsApp) {
                    $messageId = $whatsapp->send($recipient, $text);
                    $row->update([
                        'status' => NotificationDeliveryStatus::Sent->value,
                        'recipient_contact' => $recipient,
                        'provider_message_id' => $messageId,
                        'sent_at' => now(),
                    ]);
                } else {
                    Mail::to($recipient)->send(new AbsenceAlertMail(
                        guardianName: $guardian->name,
                        studentName: $student->full_name,
                        className: $student->currentEnrollment?->schoolClass?->name,
                        statusLabel: $this->statusLabel($attendance->status),
                        dateText: $this->dateText($attendance->date->toDateString()),
                        dateShort: $attendance->date->toDateString(),
                    ));
                    $row->update([
                        'status' => NotificationDeliveryStatus::Sent->value,
                        'recipient_contact' => $recipient,
                        'sent_at' => now(),
                    ]);
                }
            } catch (Throwable $exception) {
                $row->update([
                    'status' => NotificationDeliveryStatus::Failed->value,
                    'recipient_contact' => $recipient,
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                Log::warning('absence-notification-failed', [
                    'attendance_id' => $attendance->id,
                    'guardian_id' => $guardian->id,
                    'channel' => $channel->value,
                    'error' => $exception->getMessage(),
                ]);
                $failed = true;
            }
        }

        if ($failed) {
            throw new RuntimeException("Absence notifications for attendance {$attendance->id} partly failed — the queue will retry.");
        }
    }

    /**
     * Queue exhausted the retries: fall back to email for every still-
     * failed WhatsApp row (spec 05 §Decisions, "missing or unreachable").
     */
    public function failed(Throwable $exception): void
    {
        $attendance = Attendance::query()->find($this->attendance->id);

        if ($attendance === null) {
            return;
        }

        $rows = AbsenceNotification::query()
            ->where('attendance_id', $attendance->id)
            ->where('channel', NotificationChannel::WhatsApp->value)
            ->where('status', NotificationDeliveryStatus::Failed->value)
            ->with('guardian.user')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $attendance->load('student.currentEnrollment.schoolClass');
        $text = $this->message($attendance, $attendance->student);

        foreach ($rows as $row) {
            $email = $row->guardian->user?->email;

            if ($email === null) {
                continue;
            }

            try {
                Mail::to($email)->send(new AbsenceAlertMail(
                    guardianName: $row->guardian->name,
                    studentName: $attendance->student->full_name,
                    className: $attendance->student->currentEnrollment?->schoolClass?->name,
                    statusLabel: $this->statusLabel($attendance->status),
                    dateText: $this->dateText($attendance->date->toDateString()),
                    dateShort: $attendance->date->toDateString(),
                ));
                $row->update([
                    'channel' => NotificationChannel::Email->value,
                    'status' => NotificationDeliveryStatus::Sent->value,
                    'recipient_contact' => $email,
                    'sent_at' => now(),
                ]);
            } catch (Throwable $fallbackException) {
                $row->update([
                    'error_message' => mb_substr($fallbackException->getMessage(), 0, 2000),
                ]);
                Log::warning('absence-notification-fallback-failed', [
                    'attendance_id' => $attendance->id,
                    'guardian_id' => $row->guardian_id,
                    'error' => $fallbackException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Channel + recipient for one guardian: phone → WhatsApp, else the
     * linked user's email, else null (no reachable medium — silent skip).
     *
     * @return array{NotificationChannel, string}|null
     */
    private function targetFor(Guardian $guardian): ?array
    {
        if ($guardian->phone_number !== '') {
            return [NotificationChannel::WhatsApp, $guardian->phone_number];
        }

        $email = $guardian->user?->email;

        if ($email !== null && $email !== '') {
            return [NotificationChannel::Email, $email];
        }

        return null;
    }

    /**
     * Claim this guardian's slot for the record: create the pending row,
     * or re-issue the existing row for a retry. Returns null when the
     * notification was already sent (or claimed by a concurrent worker).
     */
    private function claim(int $attendanceId, int $guardianId, NotificationChannel $channel): ?AbsenceNotification
    {
        $existing = AbsenceNotification::query()
            ->where('attendance_id', $attendanceId)
            ->where('guardian_id', $guardianId)
            ->first();

        if ($existing !== null) {
            return $existing->status === NotificationDeliveryStatus::Sent
                ? null
                : $existing;
        }

        try {
            return AbsenceNotification::create([
                'attendance_id' => $attendanceId,
                'guardian_id' => $guardianId,
                'channel' => $channel,
                'status' => NotificationDeliveryStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent worker won the claim (create-once rule).
            return null;
        }
    }

    /**
     * The Indonesian alert body (spec 05 §Decisions content), worded for
     * the record's status — the school may notify on more than absences.
     */
    private function message(Attendance $attendance, Student $student): string
    {
        $class = $student->currentEnrollment?->schoolClass?->name;

        $schoolName = app(SchoolSettings::class)->schoolName();

        return sprintf(
            '[%s] Anak Anda, %s%s, tercatat %s pada %s. Silakan hubungi wali kelas atau masuk ke %s untuk melihat catatan kehadiran.',
            $schoolName,
            $student->full_name,
            $class !== null ? " ({$class})" : '',
            $this->statusPhrase($attendance->status),
            $this->dateText($attendance->date->toDateString()),
            rtrim((string) config('app.url'), '/'),
        );
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        $statuses = config('attendance.notifications.statuses');

        return is_array($statuses) ? array_values($statuses) : [];
    }

    private function statusPhrase(AttendanceStatus $status): string
    {
        return match ($status) {
            AttendanceStatus::Present => 'HADIR',
            AttendanceStatus::Late => 'TERLAMBAT',
            AttendanceStatus::Absent => 'TIDAK HADIR',
            AttendanceStatus::Sick => 'SAKIT',
            AttendanceStatus::Leave => 'IZIN',
        };
    }

    private function statusLabel(AttendanceStatus $status): string
    {
        return mb_strtolower($this->statusPhrase($status));
    }

    /**
     * School-day date in Indonesian. Parsed from the Y-m-d string — the
     * date cast carries UTC midnight, which drifts a day in the school
     * timezone (spec 03 gotcha).
     */
    private function dateText(string $date): string
    {
        $day = CarbonImmutable::parse($date);
        $day->locale('id');

        return $day->translatedFormat('l, j F Y');
    }
}
