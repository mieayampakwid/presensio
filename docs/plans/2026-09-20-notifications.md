# Spec 05 — Absence Notifications (WhatsApp via WAHA + email fallback)

## Context

Implements `docs/specs/05-notifications.md` — fifth spec in the build order (01–04 shipped). When an attendance record is **created** with status `absent`, every linked guardian gets notified same-day: WhatsApp first through **WAHA** (self-hosted WhatsApp HTTP API the user runs themselves), email as fallback. Sent asynchronously on the existing **database queue** (already the configured default; `jobs`/`failed_jobs` tables already migrated; no Redis in Sail). No routes, no UI, no frontend work.

**Absent-creating paths (verified — trigger needs zero engine changes):** `MarkAbsencesCommand` (sweep) and `AttendanceController::updateRecord` (manual upsert). Scan service creates present/late only; excuse approval writes sick/leave only. A model-event hook on `Attendance::created` catches both.

**Locked user decisions (2026-09-20):**
1. **WAHA gateway** — `POST {base}/api/sendText` with `{session, chatId, text}`; chatId = digits (no `+`) + `@c.us`; optional `X-Api-Key` header when the WAHA server sets `WAHA_API_KEY`. Verified against waha.devlike.pro docs.
2. **Strict fallback** — no phone → email. WhatsApp fails after queue retries (3 tries, 60s/300s backoff) → job's `failed()` hook sends the email fallback.
3. **Indonesian message text** (school context: Jakarta tz, Sakit/Izin UI labels).

**Spec constraints honored:** `late` never notifies; edits never notify and never re-tract; at most one notification per guardian per record (idempotency table); no guardians → silent no-op.

## Task order

### 0. Save execution doc
Copy this plan to `docs/plans/2026-09-20-notifications.md` (repo convention).

### 1. Config — `config/services.php` + `.env.example`
```php
'waha' => [
    'base_url' => env('WAHA_BASE_URL'),
    'api_key' => env('WAHA_API_KEY'),
    'session' => env('WAHA_SESSION', 'default'),
],
```
`.env.example`: `WAHA_BASE_URL=` (empty; e.g. `http://waha:3000` when containerized), `WAHA_API_KEY=`, `WAHA_SESSION=default`. Blank base_url → client throws = loud misconfig surfaced via failed jobs.

### 2. Enums — `app/Enums/`
`NotificationChannel` (`WhatsApp = 'whatsapp'`, `Email = 'email'`), `NotificationDeliveryStatus` (`Pending/Sent/Failed`) — string-backed, TitleCase, house style.

### 3. Idempotency table — `absence_notifications`
Migration `2026_09_20_000006_create_absence_notifications_table.php`: `id`; `foreignId('attendance_id')->index()->constrained()` (RESTRICT — attendance rows have no delete path; mirrors the other deletion guards); `foreignId('guardian_id')->index()->constrained()` (RESTRICT); `string('channel')`; `string('status')`; timestamps; **`unique(['attendance_id', 'guardian_id'])` = the spec Req-3 idempotency key**. Model `AbsenceNotification` with `#[Fillable]` + enum casts. No factory (tests create rows directly).

Delivery semantics: row written **before** send (status `Pending`), updated to `Sent`/`Failed` after. Retried jobs skip `Sent` rows and re-attempt `Failed`/`Pending` — satisfies "at most one per guardian per record" and "failures retried". Duplicate window exists only if a send succeeded but the status update didn't persist (process crash) — documented in the model docblock.

### 4. Client — `app/Services/Notifications/WhatsAppClient.php`
`send(string $phone, string $text): void` — normalize to chatId: strip non-digits; leading `0` → `62` (ID local numbers); append `@c.us`. Then `Http::baseUrl(config('services.waha.base_url'))` (+ `->when(api_key, X-Api-Key header)`) `->connectTimeout(5)->timeout(15)->retry(1, 1000, connection-or-5xx)` `->post('/api/sendText', ['session' => …, 'chatId' => …, 'text' => …])->throw()` — exactly the `HolidaySyncService.php:38-49` house pattern. RuntimeException on blank base_url.

### 5. Job — `app/Jobs/SendAbsenceNotifications.php`
- `__construct(public Attendance $attendance)`; `$tries = 3`; `$backoff = [60, 300]`.
- `handle(WhatsAppClient $whatsapp)`:
  1. **Stale guard:** serialized model re-fetches fresh at handle time — `if ($attendance->status !== AttendanceStatus::Absent) return;` (record corrected between dispatch and send → suppressed; dispatch still honored creation-time trigger).
  2. Load `student.schoolClass` + `student.guardians`. No guardians → return (Req 5, no error).
  3. Per guardian: channel = `phone_number` → whatsapp; else `user?->email` → email; else skip silently.
  4. Idempotency: `AbsenceNotification::firstOrNew(attendance_id, guardian_id)`; exists && `Sent` → skip; else save `[channel, Pending]` catching `UniqueConstraintViolationException` → skip (house create-once pattern).
  5. Send: whatsapp → `$whatsapp->send($phone, $text)`; email → `Mail::to($email)->send(new AbsenceAlertMail(...))`. Per-guardian try/catch → `Log::warning('absence-notification-failed', …)` + row → `Failed` + collect.
  6. Any failures → throw `RuntimeException` (queue retries; `Sent` rows now skipped).
- `failed(Throwable $e)` — **strict fallback**: rows for this attendance where `status = Failed && channel = whatsapp` → guardian's `user?->email` → best-effort mail inside try/catch + log; on success row → `channel email, status Sent`.
- Message (Indonesian), composed in the job:
  `[{app.name}] Anak Anda, {full_name} ({class_name}), tercatat TIDAK HADIR pada {tanggal}. Silakan hubungi wali kelas atau masuk ke {app.url} untuk melihat catatan kehadiran.`
  Date via `CarbonImmutable::parse($attendance->date->toDateString())->locale('id')->translatedFormat('l, j F Y')` — **parse from the Y-m-d string, never the UTC-midnight date cast** (known tz trap). School name: `config('app.name')` (no `school_name` column exists).

### 6. Mailable — `app/Mail/AbsenceAlertMail.php` + `resources/views/mail/absence-alert.blade.php`
Envelope subject `Absensi: {full_name} tidak hadir {Y-m-d}`; markdown Content (greeting `Yth. {guardian name},`, the same Indonesian lines, button `Buka Presensio` → `config('app.url')`, salutation app name). Constructor receives the built strings — dumb envelope.

### 7. Observer — `app/Observers/AttendanceObserver.php`
`created(Attendance $attendance): void` → `if ($attendance->status === AttendanceStatus::Absent) { SendAbsenceNotifications::dispatch($attendance); }`. Register with `#[ObservedBy(AttendanceObserver::class)]` on the Attendance model (matches `#[Fillable]` attribute house style).

### 8. Stale comment updates (in-scope — they describe this feature)
`MarkAbsencesCommand` docblock "nothing fires today" → dispatches via observer; `AttendanceController` docblock same; `app/Models/User.php:84` + `app/Notifications/ResetPassword.php:26` "when spec 05 lands" → WhatsApp gateway now exists, reset delivery stays email-only (reset-over-WhatsApp remains out of scope). Leave `AttendanceScanService:90-92` (no retraction — still true).

### 9. `phpunit.xml`
`QUEUE_CONNECTION=sync` → **`database`** — absent-creating tests (sweep, bulk, factories, excuses suite) then just enqueue rows into the sqlite `jobs` table, never processed, no HTTP. Dispatch assertions use `Queue::fake()`. Run the full suite after to confirm nothing depended on sync.

### 10. Tests
- **`tests/Feature/Notifications/AbsenceNotificationTest.php`** (RefreshDatabase, `Date::setTestNow` pin + reset):
  - sweep → `Queue::fake` + `artisan attendance:mark-absences` → `assertPushed(SendAbsenceNotifications)`
  - manual override `attendance.record.update` creating absent → pushed; updating an existing record → not pushed again
  - scan tap (present/late), bulk-present, excuse approval → nothing pushed
  - `handle()` called directly with `Http::fake`: two guardians with phones → two `/api/sendText` posts with correct chatIds, rows `Sent`
  - guardian without phone + linked user email → `Mail::fake`, mailable sent, row `Sent`/email
  - no phone and no linked user → nothing sent, no row
  - student without guardians → no error, no sends
  - idempotency: pre-seeded `Sent` row → no HTTP call for that guardian
  - WAHA 500 → row `Failed` + `RuntimeException` thrown (job will retry)
  - `failed()` fallback: `Failed` whatsapp rows + guardian email → mail sent, row updated to email/`Sent`
  - stale guard: record flipped to `sick` before run → handle no-ops
- **`tests/Unit/Services/Notifications/WhatsAppClientTest.php`** (mirror `HolidaySyncServiceTest`): chatId normalization (`+62812…` → `62812…@c.us`, `0812…` → `62812…@c.us`), request shape (session/chatId/text + `X-Api-Key` when configured), retry on 5xx then success, throw on 4xx, throw on blank base_url.

### 11. Gates
No routes → no Wayfinder regen; no JS → no npm gates. `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → `php artisan test --compact` (narrow new files first, then full).

### 12. Commit + wrap-up
`feat(notifications): guardian absence alerts via WhatsApp (WAHA) with email fallback` (`--no-verify`, Co-Authored-By trailer). Update MOC. Ops notes for the user (no code): dev needs `sail artisan queue:work` running; WAHA container + session QR pairing are deployment-side; failed jobs visible via `queue:failed`.

## Gotchas carried into execution
- Date-only columns: parse Y-m-d strings for display formatting — never the UTC-midnight cast.
- Serialized model in job = fresh re-fetch at handle time (free stale check, but don't carry stale relation state — reload inside handle).
- `QUEUE_CONNECTION=database` in phpunit changes when notifications execute — full-suite run is the regression check.
- WAHA chatId: digits only, no `+`, `@c.us`; leading `0` → `62`.
- Email fallback target is `guardian->user?->email` (nullable) — guardians without user accounts have no email fallback.
- `Guardian` model needs no `Notifiable` trait — the job addresses channels directly.

## Verification
- New tests + full suite green (342 existing + ~16 new); phpstan/pint clean.
- Optional manual smoke (needs a running WAHA container): set `WAHA_*` env, run `sail artisan queue:work`, trigger `attendance:mark-absences`, observe the WhatsApp message and the `absence_notifications` rows.
