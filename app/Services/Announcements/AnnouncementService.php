<?php

namespace App\Services\Announcements;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AnnouncementReceipt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnnouncementService
{
    public function __construct(private readonly AnnouncementAudience $audience, private readonly AuditLogger $audit, private readonly SchoolSettings $settings) {}

    /** @param array<string,mixed> $input */
    public function save(User $user, array $input, ?Announcement $item = null, ?UploadedFile $attachment = null): Announcement
    {
        $path = null;
        try {
            if ($attachment !== null) {
                $path = $attachment->store('announcements', 'local');
                if ($path === false) {
                    $path = null;
                    throw ValidationException::withMessages(['attachment' => 'Penyimpanan lampiran gagal.']);
                }
            }
            $saved = DB::transaction(function () use ($user, $input, $item, $attachment, $path): Announcement {
                $record = $item === null ? new Announcement : Announcement::whereKey($item->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->audience->canManage($user, $item, isset($input['class_id']) ? (int) $input['class_id'] : null), 403);
                $fields = collect($input)->only(['title', 'body', 'audience_type', 'audience_role', 'audience_grade_level', 'class_id', 'is_pinned', 'requires_acknowledgement', 'notify_urgent'])->all();
                foreach (['audience_role', 'audience_grade_level', 'class_id'] as $key) {
                    $fields[$key] = $input[$key] ?? null;
                }
                $fields['published_at'] = empty($input['published_at']) ? null : Carbon::parse($input['published_at'], $this->settings->timezone())->utc();
                $fields['academic_year_id'] = in_array($input['audience_type'], ['class', 'grade_level'], true)
                    ? ($record->exists && $record->isLive() ? $record->academic_year_id : AcademicYear::active()?->id)
                    : null;
                if (in_array($input['audience_type'], ['class', 'grade_level'], true) && $fields['academic_year_id'] === null) {
                    throw ValidationException::withMessages(['audience_type' => 'Atur tahun ajaran aktif terlebih dahulu.']);
                }
                if ($record->exists && $record->isLive()) {
                    foreach (['audience_type', 'audience_role', 'audience_grade_level', 'class_id', 'requires_acknowledgement', 'notify_urgent'] as $key) {
                        $original = $record->getRawOriginal($key);
                        if (in_array($key, ['requires_acknowledgement', 'notify_urgent'], true)) {
                            $original = (bool) $original;
                            $fields[$key] = (bool) $fields[$key];
                        }
                        if ((string) $original !== (string) ($fields[$key] ?? null)) {
                            throw ValidationException::withMessages([$key => 'Target dan aturan pengumuman terbit tidak dapat diubah.']);
                        }
                    }
                    if ($fields['published_at'] === null || ! $record->published_at?->equalTo($fields['published_at'])) {
                        throw ValidationException::withMessages(['published_at' => 'Waktu pengumuman terbit tidak dapat diubah.']);
                    }
                    $fields['academic_year_id'] = $record->academic_year_id;
                }
                $old = $record->exists ? $record->getAttributes() : null;
                if (! $record->exists) {
                    $fields['created_by_user_id'] = $user->id;
                }
                $oldPath = $record->attachment_path;
                if ($path !== null) {
                    $fields['attachment_path'] = $path;
                    $fields['attachment_name'] = $attachment->getClientOriginalName();
                } elseif (! empty($input['remove_attachment'])) {
                    $fields['attachment_path'] = null;
                    $fields['attachment_name'] = null;
                }
                $record->fill($fields)->save();
                $this->audit->record($record, $old === null ? 'announcement_created' : 'announcement_updated', $old, $record->getAttributes());
                if ($oldPath !== null && $oldPath !== $record->attachment_path) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }

                return $record;
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
        try {
            app(AnnouncementPublisher::class)->publishDue($saved);
        } catch (Throwable $e) {
            report($e);
        }

        return $saved;
    }

    public function delete(User $user, Announcement $item): void
    {
        DB::transaction(function () use ($user, $item): void {
            $item = Announcement::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->audience->canManage($user, $item), 403);
            $this->audit->record($item, 'announcement_deleted', $item->getAttributes());
            $path = $item->attachment_path;
            $item->delete();
            if ($path !== null) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($path));
            }
        });
    }

    public function read(User $user, Announcement $item, bool $acknowledge = false): void
    {
        abort_unless($this->audience->visibleTo($user, false)->whereKey($item->id)->exists(), 403);
        if ($acknowledge) {
            abort_unless($item->isLive(), 403);
            abort_unless($item->requires_acknowledgement, 422);
        }
        DB::transaction(function () use ($user, $item, $acknowledge): void {
            AnnouncementReceipt::insertOrIgnore(['announcement_id' => $item->id, 'user_id' => $user->id, 'read_at' => now(), 'acknowledged_at' => null]);
            if ($acknowledge) {
                AnnouncementReceipt::where('announcement_id', $item->id)->where('user_id', $user->id)->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);
            }
        });
    }
}
