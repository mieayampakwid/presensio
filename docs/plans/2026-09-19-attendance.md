# Spec 03 — Attendance Recording: Implementation Plan

## Context

Spec 02 (people/classes/importer) shipped 2026-09-19 from `docs/plans/2026-09-19-people-classes.md`. Spec 03 (`docs/specs/03-attendance.md`) is next in build order: the scan engine (RFID + dynamic QR), settings-anchored business logic evaluated in the school timezone, auto-absent sweep, holiday calendar + feed sync, teacher/admin exception dashboard, and the append-only scan event log. Nothing attendance-related exists yet — no `settings`/`attendances`/`scan_events`/`non_school_days` tables, no `routes/api.php`, no scheduled tasks.

**Locked user decisions** (settled during planning — do not revisit):
- **Settings**: single-row `settings` table seeded by migration + `SchoolSettings` service + admin UI (Settings → Attendance). App timezone stays **UTC**; all business logic evaluates in the school timezone (default `Asia/Jakarta`).
- **Holiday feed**: Nager.Date (`https://date.nager.at/api/v3`), keyless, weekly one-way idempotent sync, fail-soft.
- **QR**: server-side SVG via `bacon/bacon-qr-code`, promoted to a direct composer dep (`^3.0`, already in lock via Fortify — no version churn). Zero new JS packages; student page refreshes the QR via Inertia partial reload.

**Execution convention**: Task 0 copies this plan to `docs/plans/2026-09-19-attendance.md` (repo convention). Dev DB is pgsql via Sail; tests run host-side on sqlite `:memory:` (`php artisan test`). Every task that adds a controller ends with `php artisan wayfinder:generate --with-form` (spec 01 lesson: without `--with-form`, `.form()` consumers crash). Frontend commands via `sail npm ...`. Per-task gates: `vendor/bin/pint --dirty --format agent`, `composer types:check`, narrow `php artisan test --compact <path>`.

## Decisions baked into this plan

1. **Enums follow `UserRole`** (string DB column + backed enum cast): `AttendanceStatus` (present/absent/late/sick/leave), `ScanMethod` (rfid/dynamic_qr/manual_override), `NonSchoolDaySource` (sync/manual), `ScanOutcome` (check_in/check_out/absent_upgraded/ignored_debounce/ignored_complete/ignored_excused/error_expired_token/error_unknown_credential).
2. **Scanner API** = first `routes/api.php`, registered `withRouting(api: ...)` in `bootstrap/app.php`. Framework `api` group has **no default throttle** → named limiter `scanner` (60/min per IP, registered in `AppServiceProvider`). Auth = `X-Scanner-Key` header checked by middleware against `config('attendance.scanner_keys')` — `ATTENDANCE_SCANNER_KEYS` accepts a comma list so key rotation is zero-downtime; unset config ⇒ always 401 (fail closed). Missing/invalid key logs **no** scan event (unauthenticated source).
3. **QR token**: `{student_id}.{issued_at_unix}.{hmac}` with `hmac = hash_hmac('sha256', "{id}.{ts}", secret)`, secret = `ATTENDANCE_QR_SIGNING_KEY` ?: `APP_KEY`, TTL 30s (`|now − issued| > ttl` → expired; abs() also rejects future-issued). Verified with `hash_equals`. Tokens are **never persisted**.
4. **Scan resolution is a service** (`AttendanceScanService`): fetch today's record → apply spec §1 tap-resolution order (create only in case 1) → append exactly one `scan_events` row per attempt **including both error outcomes**. Effective time = device `scanned_at` iff `|device − server| ≤ scan_drift_tolerance_minutes`, else server now; business `date` = school-tz date of the effective instant.
5. **Race safety**: unique `(student_id, date)` + case-1 create wrapped in try / catch `UniqueConstraintViolationException` / re-fetch (Laravel unifies this across pgsql+sqlite); cases 2–5 update the fetched row inside `DB::transaction`.
6. **Scheduling**: `Schedule` facade in `routes/console.php`; schedule re-evaluates on every `schedule:run` (each minute), so reading settings at definition time is safe — changed cron time applies within a minute. Both events get `->timezone(schoolTz)`. `SchoolSettings` guards against pre-migration console boots (catch `QueryException` → defaults) so `artisan migrate:fresh` never explodes.
7. **Exception dashboard edit is an upsert** keyed on `(student_id, date)` — the spec's "reconstruct a day the scanner was offline" means record-less students must be editable, not just existing records.
8. **Spec 05 boundary**: no notification code now. Trigger point documented as a comment on the sweep (`Attendance` created with status `absent`) and on the dashboard (edits never notify). No speculative events/listeners.
9. **Deletion guards**: `StudentController::deletionBlockers()` gains the attendance-history check (pre-planned hook). `SchoolClassController`'s hook resolves to an explanatory comment — `attendances` carries no `class_id` (spec schema), so the enrollment blocker already subsumes it; do **not** invent a column.
10. **Non-school-days CRUD**: index + create (`source=manual`) + delete (any row) only — no editing of synced names (Decision from spec §7: sync owns synced rows; admins delete, never rewrite).
11. Frontend rules carry over verbatim: native `<select>`/checkbox/time/number inputs only inside Inertia `<Form>` (uncontrolled, `defaultValue`), hand-rolled tables, `Badge` states, Dialog confirms, `use-flash-toast`, breadcrumbs via `PageName.layout`, Wayfinder `@/actions` + `@/routes` imports.

---

## Task 0 — Plan doc

Create `docs/plans/2026-09-19-attendance.md` (verbatim copy of this plan). Commit `docs: plan spec 03 attendance implementation`.

## Task 1 — Enums, migrations, models, factories

**Create**: `app/Enums/{AttendanceStatus,ScanMethod,ScanOutcome,NonSchoolDaySource}.php`; migrations `2026_09_20_000001..000004_*` (settings, non_school_days, attendances, scan_events); `app/Models/{SchoolSetting,NonSchoolDay,Attendance,ScanEvent}.php`; `database/factories/{AttendanceFactory,NonSchoolDayFactory}.php`. **Modify**: `app/Models/Student.php` (+`attendances()` hasMany). **Tests**: `tests/Unit/Models/{AttendanceTest,ScanEventTest,NonSchoolDayTest,SchoolSettingTest}.php`.

Anonymous-class migrations, `foreignId()` without `->constrained()`, comments citing spec § (match spec 02 style):

- **settings** (single row, migration seeds it): `school_timezone` string default `'Asia/Jakarta'`, `school_start_time` time default `'07:30'`, `require_checkout` bool default false, `auto_absent_cron_time` time default `'15:30'`, `scan_debounce_minutes` unsignedSmallInteger default 1, `scan_drift_tolerance_minutes` unsignedSmallInteger default 2. Seed `DB::table('settings')->insert(['id' => 1, ...defaults, timestamps])`.
- **non_school_days**: `date` date **unique**, `name` string, `source` string. Single source of truth (national sync + manual school dates).
- **attendances**: `student_id` foreignId index, `date` date, `status` string, `checked_in_at`/`checked_out_at` nullable timestamps, `scan_method` **nullable** string (null = system-generated: sweep/excuse injection), `override_by_user_id` nullable foreignId, `notes` nullable string(255). **`$table->unique(['student_id', 'date'])`** + `$table->index('date')`.
- **scan_events**: `student_id` nullable foreignId index, `scan_method` string (rfid|dynamic_qr), `identifier` nullable string (raw RFID only; **always null for QR**), `scanned_at` timestamp, `outcome` string. Comment: append-only — no code ever updates an event row.

Models mirror `Student.php` (`#[Fillable]`, `@property` docblocks incl. enum types, `casts()` method, `HasFactory`): `Attendance` casts `status`/`scan_method` to enums (nullable scan_method stays null), `date => date:Y-m-d`, `checked_*_at => datetime`; relations `student()`, `overrideBy() BelongsTo<User>`. `ScanEvent` casts `scan_method`/`outcome` to enums. `NonSchoolDay` casts `source`. `SchoolSetting` (`$table = 'settings'`) casts `require_checkout => bool`, minutes => int; `school_start_time`/`auto_absent_cron_time` kept as strings (`setTimeFromTimeString` handles `"07:30:00"` on both drivers).

Factories: `AttendanceFactory` (default present/today/rfid; states `late() absent() sick() leave() checkedOut() withoutMethod()`), `NonSchoolDayFactory` (state `synced()`).

**Tests**: enum values serialize to spec strings; duplicate `(student_id, date)` insert throws (proves the constraint); casts behave; settings seeded exactly one row with spec defaults.

**Verify**: `sail artisan migrate:fresh --no-interaction` → `php artisan test --compact tests/Unit/Models` → pint + phpstan.

## Task 2 — `config/attendance.php` + `SchoolSettings` service

**Create**: `config/attendance.php`, `app/Services/SchoolSettings.php`. **Modify**: `app/Providers/AppServiceProvider.php` (register `SchoolSettings` as **singleton** — memoization is per-process), `.env.example` (`ATTENDANCE_SCANNER_KEY=`, `# ATTENDANCE_SCANNER_KEYS=k1,k2`, `# ATTENDANCE_QR_SIGNING_KEY=`, `# ATTENDANCE_QR_TTL=30`, `# HOLIDAY_FEED_BASE_URL=https://date.nager.at/api/v3`). **Test**: `tests/Unit/Services/SchoolSettingsTest.php`.

```php
// config/attendance.php
'scanner_keys' => array_filter(explode(',', (string) env('ATTENDANCE_SCANNER_KEYS', env('ATTENDANCE_SCANNER_KEY', '')))),
'qr' => ['ttl_seconds' => (int) env('ATTENDANCE_QR_TTL', 30), 'signing_key' => env('ATTENDANCE_QR_SIGNING_KEY')],
'holiday_feed' => ['base_url' => env('HOLIDAY_FEED_BASE_URL', 'https://date.nager.at/api/v3'), 'country_code' => 'ID'],
```

`SchoolSettings`: `row(): SchoolSetting` (memoized `firstOrCreate([], defaults)`, catch `QueryException` → unpersisted defaults for pre-migration boots); `timezone(): string`; `now(): CarbonImmutable` (school tz); `todayDate(): string` (Y-m-d in school tz — **the** business date); `startTimeOn(CarbonImmutable $day)` (date + `school_start_time` in school tz — per-day derivation keeps DST gap/overlap sane); `isLate(CarbonImmutable $at): bool` (strictly after start — 07:30 on the dot is on time); `requireCheckout()`, `debounceMinutes()`, `driftToleranceMinutes()`, `autoAbsentCronTime(): string`; `isSchoolDay($date): bool` (!Sat/Sun && no `non_school_days` row).

**Tests**: defaults; isLate boundary (07:29/07:30/07:31 with start 07:30); `todayDate()` flips at school midnight — `17:00 UTC` → Jakarta `00:00` **next day** while UTC says same date; `isSchoolDay` false Sat / false with a row / true otherwise; pre-table guard returns defaults (drop table in-test).

**Verify**: `php artisan test --compact tests/Unit/Services/SchoolSettingsTest.php` + pint + phpstan.

## Task 3 — QR token service + SVG renderer

**Modify**: `composer.json` — `composer require bacon/bacon-qr-code:^3.0` (promote transitive; user-approved). **Create**: `app/Services/Attendance/QrTokenService.php`, `app/Services/Attendance/QrCodeRenderer.php`. **Test**: `tests/Unit/Services/Attendance/QrTokenServiceTest.php`.

- `QrTokenService::issue(Student): QrToken` (readonly DTO: `token`, `issuedAt`, `expiresAt`) / `verify(string): QrTokenVerification` (readonly: `?Student`, `?QrTokenError` enum `invalid_format|expired|unknown_student`). HMAC recompute + `hash_equals`; freshness = `abs(now − issued) > ttl`; non-digit/unknown-id/malformed → `invalid_format`/`unknown_student` (both collapse to `error_unknown_credential` at the scan layer; `expired` maps to `error_expired_token`).
- `QrCodeRenderer::svg(string $token, int $size = 240): string` — the Fortify 2FA pattern (`Writer` + `ImageRenderer` + `RendererStyle` + `SvgImageBackEnd`, XML prolog trimmed) so the page embeds it with `dangerouslySetInnerHTML` exactly like the existing 2FA QR.

**Tests**: issue→verify round-trip; travel +31s → expired; travel −31s (future-issued) → expired (abs guard); tampered hmac → not verified; deleted student id → unknown_student; custom signing key isolation; SVG starts with `<svg`, no `<?xml`.

**Verify**: `php artisan test --compact tests/Unit/Services/Attendance` + pint + phpstan.

## Task 4 — Scanner API (route file, key middleware, limiter, scan service, controller)

**Create**: `routes/api.php`; `app/Http/Middleware/EnsureValidScannerKey.php`; `app/Services/Attendance/AttendanceScanService.php`; `app/Services/Attendance/ScanResult.php` (readonly: `ScanOutcome`, `?Student`, `?Attendance`); `app/Http/Controllers/Api/AttendanceScanController.php` (invokable); `app/Http/Requests/Api/AttendanceScanRequest.php`. **Modify**: `bootstrap/app.php` (`withRouting(api: __DIR__.'/../routes/api.php', ...)`), `app/Providers/AppServiceProvider.php` (`RateLimiter::for('scanner', 60/min by ip)`). **Test**: `tests/Feature/Attendance/AttendanceScanApiTest.php`.

```php
Route::middleware([EnsureValidScannerKey::class, 'throttle:scanner'])
    ->post('/attendance/scan', AttendanceScanController::class)->name('attendance.scan');
```

- `EnsureValidScannerKey`: `hash_equals` over each configured key; failure → `abort(401, 'Invalid scanner key.')` (renders JSON via existing `shouldRenderJsonWhen`).
- `AttendanceScanRequest`: `authorize(): true` (auth is the device key); rules `credential_type in:rfid,dynamic_qr`, `credential required string max:255`, `scanned_at nullable date`; helpers `method(): ScanMethod`, `deviceScannedAt(): ?CarbonImmutable`.
- `AttendanceScanService::scan(ScanMethod, string $credential, ?CarbonImmutable $deviceScannedAt): ScanResult` — pipeline, **one `ScanEvent` appended per attempt**:
  1. Resolve credential → `?Student`: RFID via `rfid_cards.rfid_number` (unknown number **or spare card** → `error_unknown_credential`); QR via `QrTokenService::verify` (`expired` → `error_expired_token`; anything else → `error_unknown_credential`). Event `identifier` = raw rfid_number for RFID, **null for QR**.
  2. Effective time: device clock trusted iff `|device − server| ≤ driftToleranceMinutes`; event `scanned_at` = trusted device instant, else server now.
  3. Business date = school-tz date of effective instant.
  4. Fetch today's record; then spec §1 order:
     - **no record** → try-create (catch `UniqueConstraintViolationException` → re-fetch): `checked_in_at = effective`, status `isLate ? late : present`, `scan_method = $method` → `check_in`.
     - **absent without check-in** (sweep record) → set `checked_in_at`, re-status late/present, `scan_method = $method` → `absent_upgraded`.
     - **checked-in, no checkout** → within `debounceMinutes` of `checked_in_at` → `ignored_debounce`; beyond → `requireCheckout` ? set `checked_out_at` → `check_out` : `ignored_complete` (only available outcome bucket).
     - **checked out** → `ignored_complete`.
     - **sick/leave** → `ignored_excused` (Data Overlap Shield; record untouched).
     Cases 2–5 run in `DB::transaction` against the fetched row.
- Controller JSON: success/ignored → 200 `{outcome: checked_in|checked_out|ignored, detail: ScanOutcome->value, student_name}`; credential errors → 422 `{outcome: 'error', detail: ...}` (event still logged); 401 bad key; 429 throttle (framework).

**Tests** (`postJson(route('attendance.scan'), payload, ['X-Scanner-Key' => ...])`): 401 missing/wrong key; 422 validation; RFID first tap checked_in+present with `checked_in_at` + event identifier stored; late via `travelTo` past school start; second tap within debounce → `ignored_debounce` (no checkout, second event row); beyond debounce with `require_checkout=false` → `ignored_complete`; with true → `check_out` + third tap `ignored_complete`; sweep-style absent record + tap → `absent_upgraded` re-statused; sick & leave → `ignored_excused` untouched; QR: issued token → `checked_in`, +31s → `error_expired_token`, tampered → `error_unknown_credential`, QR events `identifier === null`; unknown RFID + spare card → 422 with `student_id === null` event; device clock within tolerance → `checked_in_at` = device instant, beyond → server now; midnight boundary (17:05 UTC → Jakarta next-day date on record); double scan collapses to one record; 61st request → 429.

**Verify**: `php artisan test --compact tests/Feature/Attendance/AttendanceScanApiTest.php` + `php artisan route:list --path=api` + pint + phpstan.

## Task 5 — Student portal QR page

**Create**: `app/Http/Controllers/Attendance/StudentQrController.php`; `routes/attendance.php` (require in `routes/web.php`); `resources/js/pages/attendance/my-qr.tsx`; `resources/js/types/attendance.ts`. **Modify**: `resources/js/components/app-sidebar.tsx` (student-only "My QR", `QrCode` icon). **Test**: `tests/Feature/Attendance/StudentQrPageTest.php`.

- Route `GET my-qr` → `attendance.my-qr`, `['auth', 'role:student']`.
- `show()`: `$request->user()->student` — none → render with `qr => null` (friendly "no linked profile" empty state); else `qr => {token, svg, expires_at (ISO8601), school_timezone}`. Plain prop (cheap, always returned, refetchable via partial reload).
- `my-qr.tsx`: card with `dangerouslySetInnerHTML` SVG (2FA-QR precedent), countdown to `expires_at`; `useEffect` interval every 25s → `router.reload({ only: ['qr'] })` (partial reload — no remount/scroll reset) + force reload when countdown ≤ 0, so a valid code is always displayed. Breadcrumbs.
- Sidebar: `...(auth.user.role === 'student' ? [{ title: 'My QR', href: attendance.my-qr(), icon: QrCode }] : [])`.

**Tests**: student gets token+svg props (`assertOk`, non-empty `svg`, future `expires_at`, token verifies round-trip); other roles 403 (DataProvider); guest redirect; student without linked profile → `qr === null`.

**Verify**: wayfinder `--with-form` → test file → `sail npm run types:check && sail npm run build` + pint + phpstan.

## Task 6 — Teacher/Admin exception dashboard (index + upsert edit + bulk present)

**Modify**: `routes/attendance.php` (add block `['auth', 'role:teacher,admin']`). **Create**: `app/Http/Controllers/Attendance/AttendanceController.php`; `app/Http/Requests/Attendance/{UpsertAttendanceRequest,StoreBulkAttendanceRequest}.php`; `resources/js/pages/attendance/index.tsx` (+ inline edit dialog). **Modify**: `app-sidebar.tsx` (teacher+admin "Attendance", `CalendarCheck` icon). **Test**: `tests/Feature/Attendance/ExceptionDashboardTest.php`.

Routes:
```php
Route::get('attendance', ...)->name('attendance.index');
Route::put('attendance/record', ...)->name('attendance.record.update');   // upsert (Decision 7)
Route::post('attendance/bulk-present', ...)->name('attendance.bulk-present');
```

- **Scoping helper** shared by controller + requests (small `app/Services/Attendance/ClassAccess.php` static): admin → all; teacher → `SchoolClass::where('teacher_id', $user->teacher?->id)->pluck('id')` (no linked teacher profile → empty = sees nothing, empty-state not 403).
- `index(Request)`: `class_id` (default first authorized) + `date` (default school-tz today). Props: `classes` (scoped), `filters`, `rows` = class students with their record for that date (`with(['attendances' => where date])` → map to `{student_id, full_name, student_number, attendance: {id?, status, checked_in_at: 'H:i'|null (school tz), checked_out_at, scan_method, notes} | null}`). Times pre-formatted server-side.
- `UpsertAttendanceRequest`: rules `student_id required exists`, `date required date`, `status required enum`, `checked_in_at/checked_out_at nullable date_format:H:i` (`after()`: checkout ≥ checkin when both); `authorize()`: admin OR student's **current** `class_id` ∈ teacher's classes (v1 rule — attendance rows carry no class FK). Controller: `updateOrCreate(['student_id','date'], [...])` in a transaction, stamping `scan_method => ManualOverride`, `override_by_user_id`, `notes`; times combined with the record's `date` in school tz → stored as UTC instants. Comment: edits never notify; creations of `absent` will be the spec 05 trigger. Toast + redirect with filters.
- `StoreBulkAttendanceRequest`: `class_id required exists` (+ teacher-owns), `date required date`. Controller: class students `whereDoesntHave('attendances', date)` → create `present` + `ManualOverride` + `override_by_user_id` + `notes => 'Bulk marked present'`. Flash "Marked N students present." Existing records (incl. sick/leave) never overwritten.
- Page: GET `<form>` with native class `<select>` + `<input type="date">` (auto-submit on change); table: name/NIS, status `Badge` (muted "No record" otherwise), times, scan method, notes; **every** row gets an Edit button (dialog works for record-less rows — reconstruction) with `<Form {...AttendanceController.record.update.form()}>`: status select, two `type="time"` inputs, notes textarea, hidden student_id/date; header "Mark remaining present" `<Form>` + Dialog confirm with hidden class_id/date.

**Tests**: teacher sees only own classes/rows; admin all; teacher editing other-teacher's student → 403; bulk on another teacher's class → 403; upsert creates record-less row then updates it (manual_override + override_by both stamped, date preserved); checkout-before-checkin rejected; bulk skips students with any record; default date = school-tz today; parent/student 403 (DataProvider).

**Verify**: wayfinder `--with-form` → test → `sail npm run types:check && sail npm run build` + pint + phpstan.

## Task 7 — Settings → Attendance admin page

**Create**: `app/Http/Controllers/Settings/AttendanceSettingsController.php`; `app/Http/Requests/Settings/UpdateAttendanceSettingsRequest.php`; `resources/js/pages/settings/attendance.tsx`. **Modify**: `routes/settings.php` (`GET/PUT settings/attendance` → `attendance-settings.edit/update`, `role:admin`); `resources/js/layouts/settings/layout.tsx` (move `sidebarNavItems` into component, append admin-only "Attendance" via generated route fn). **Test**: `tests/Feature/Settings/AttendanceSettingsTest.php`.

Rules: `school_timezone required in:` curated list (Asia/Jakarta, Asia/Makassar, Asia/Jayapura, Asia/Singapore, Asia/Tokyo, UTC — native select), `school_start_time/auto_absent_cron_time required date_format:H:i`, `require_checkout required boolean`, `scan_debounce_minutes integer 0–120`, `scan_drift_tolerance_minutes integer 0–60`. Controller: `edit()` via `SchoolSettings::row()`; `update()` saves row 1 + success flash. Docblock: schedule + scan logic read these live.

Page: `<Form {...AttendanceSettingsController.update.form()}>` — timezone select, two `type="time"`, native checkbox `require_checkout` (spec-02 hidden-0 trick), two `type="number"`. Breadcrumbs.

**Tests**: admin loads/updates each field (next resolve reflects change); invalid tz/time rejected; non-admin 403 GET+PUT (DataProvider); guest redirect.

**Verify**: wayfinder `--with-form` → test → `sail npm run types:check && sail npm run build` + pint + phpstan.

## Task 8 — Auto-absent sweep + holiday sync (commands + schedule)

**Create**: `app/Console/Commands/MarkAbsencesCommand.php` (`attendance:mark-absences`); `app/Console/Commands/SyncHolidaysCommand.php` (`attendance:sync-holidays`); `app/Services/Calendar/HolidaySyncService.php`. **Modify**: `routes/console.php`. **Tests**: `tests/Feature/Console/MarkAbsencesCommandTest.php`, `tests/Feature/Calendar/HolidaySyncCommandTest.php`, `tests/Unit/Services/Calendar/HolidaySyncServiceTest.php`.

- **Sweep** (`handle(SchoolSettings $settings)`): today = school-tz date; `!isSchoolDay` → info "Non-school day — skipped." + SUCCESS; else `Student::whereDoesntHave('attendances', where date = today)` → per-student model create `status: absent, scan_method: null, date` (model creates, not bulk insert — spec 05 will hook `Attendance::created`; extension-point comment only). Output "Marked N students absent."
- **HolidaySyncService::sync(): array{imported, updated, failed?}`** — years `[schoolNow->year, +1]`; `Http::baseUrl(config(...))->get("/PublicHolidays/{$year}/{$countryCode}")` per year, `->throw()` in try/catch (`ConnectionException|RequestException`); **keep only `global === true` items** (national — drops Nager's provincial rows); skip `date < today` (future-only); `name => localName ?: name`; upsert by date: missing → create `source=sync`; existing `sync` row → update name if changed; existing **manual row → never touched**; no deletions (feed removals don't propagate — admins delete). Fail-soft: log `holiday-sync-failed` warning, command still SUCCESS (weekly retry).
- `routes/console.php`:
```php
$settings = app(SchoolSettings::class); // pre-migration-safe via its guard
Schedule::command('attendance:mark-absences')->dailyAt($settings->autoAbsentCronTime())->timezone($settings->timezone());
Schedule::command('attendance:sync-holidays')->weeklyOn(0, '03:00')->timezone($settings->timezone());
```

**Tests**: sweep creates absent rows (null scan_method) only for record-less students (present/sick/leave/absent untouched); Saturday + `non_school_days` row → no-op; 17:00-UTC boundary (school date ≠ UTC date); sync via `Http::fake`: future global items imported with localName, provincial (`global:false`) **not** imported, past skipped, idempotent re-run, manual row untouched, name updated on feed change, 500 → warning + nothing written + command SUCCESS.

**Verify**: `php artisan test --compact tests/Feature/Console tests/Feature/Calendar tests/Unit/Services/Calendar` + `php artisan schedule:list` (both events, school tz) + pint + phpstan.

## Task 9 — Non-school days admin CRUD

**Create**: `app/Http/Controllers/Calendar/NonSchoolDayController.php`; `app/Http/Requests/Calendar/StoreNonSchoolDayRequest.php`; `routes/non-school-days.php` (require in `routes/web.php`); `resources/js/pages/non-school-days/{index,create}.tsx`. **Modify**: `app-sidebar.tsx` (admin "Non-School Days", `CalendarOff` icon). **Test**: `tests/Feature/Calendar/NonSchoolDayManagementTest.php`.

Routes mirror `rfid-cards.php` minus edit/update: index/create/store/destroy, `['auth','role:admin']`, `->missing(to index)`. Rules: `date required date unique:non_school_days,date` (one row per date — sync/manual collide by design), `name required string max:255`. Index: ordered by date, paginate 15, `Badge` Sync/Manual. `destroy()` deletes any row — page notes that deleting a synced future date is temporary until the feed stops listing it. Toasts per convention.

**Tests**: CRUD happy path; duplicate date rejected; past dates allowed; delete synced row allowed; non-admin 403 (DataProvider); guest redirect.

**Verify**: wayfinder `--with-form` → test → `sail npm run types:check && sail npm run build` + pint + phpstan.

## Task 10 — Deletion guards + final gates

**Modify**: `app/Http/Controllers/Students/StudentController.php` (`deletionBlockers()`: `if ($student->attendances()->exists())` → "Cannot delete: student has attendance history."); `app/Http/Controllers/Classes/SchoolClassController.php` (hook comment → explanation per Decision 9). **Extend**: destroy tests in `StudentManagementTest` (blocked with attendance row).

Final gates:
```bash
sail artisan migrate:fresh --no-interaction
vendor/bin/pint --dirty --format agent
composer types:check
php artisan test
php artisan wayfinder:generate --with-form
sail npm run types:check && sail npm run check && sail npm run build
```

Manual smoke (Sail app, admin): set Settings → Attendance (07:30 start, require_checkout on); scanner key in `.env`; `curl -X POST http://localhost/api/attendance/scan -H "X-Scanner-Key: …" -d '{"credential_type":"rfid","credential":"<card>","scanned_at":"2026-09-21T07:31:00+07:00"}'` → checked_in/late + name; re-tap → ignored_debounce; student My QR renders + auto-refreshes ~25s; scan token → checked_in; stale token (31s) → error_expired_token; wrong key → 401; `attendance:mark-absences` → absent rows; `attendance:sync-holidays` → 2026 Indonesian national holidays; teacher corrects a record (incl. a record-less row); delete a synced holiday; student-with-attendance delete blocked.

## Spec coverage checklist

| Spec 03 requirement | Task(s) |
| --- | --- |
| §1 Scanner API (key auth, rate limit, QR HMAC+30s, RFID path, tap resolution 1–5, unique safety, outcome + full_name) | 4, 1, 3 |
| §2 Student portal dynamic QR (~25s refresh, requester-bound) | 3, 5 |
| §3 Auto sweep (weekends + non_school_days skipped, scan_method null) | 8, 7 |
| §4 Exception dashboard (own classes / anywhere, edit + optional times, override stamp, never notifies) | 6 |
| §5 Bulk class marking (record-less only, present, manual override) | 6 |
| §6 Data overlap shield (sick/leave taps no-op) | 4 |
| §7 Holiday calendar + one-way sync + admin CRUD | 8, 9 |
| §8 Scan event logging (append-only, all outcomes, identifier rules) | 4, 1 |
| Schemas attendances / scan_events / non_school_days | 1 |
| Settings anchors + school timezone | 1, 2, 7 |
| Spec 02 deletion-guard hooks | 10 |
| Spec 05 boundary (documented, not built) | 6, 8 |

## Risks

- **Timezone correctness** is the top risk: business dates always from `SchoolSettings::todayDate()` / the effective instant in school tz — never `now()` UTC. 17:00 UTC = Jakarta next-day 00:00. Boundary tests pin scan path + sweep.
- **Scan races**: try-create/catch-unique/refetch works on pgsql + sqlite; residual double-checkout beyond debounce is `whereNull`-guarded/idempotent.
- **CarbonImmutable**: `Date::use(CarbonImmutable)` — mutations return new instances; forgetting to reassign is a silent bug.
- **API group** has no session/CSRF and **no default throttle** — the named limiter is mandatory, not optional.
- **Deleting synced future holidays** re-imports weekly while the feed lists them — surfaced as a UI note, not code.
- **Nager.Date** is external/keyless — fail-soft keeps the schedule healthy; weekly cadence is gentle; provincial rows filtered by `global === true`.
- **Spec 05 temptation**: trigger point documented in comments only; no events/listeners now.

## Ambiguity resolutions

1. Tap 3 no-op when `require_checkout=false` → logged `ignored_complete` (only fitting outcome bucket).
2. Business date of a scan → school-tz date of the **effective** (device-when-trusted) instant.
3. Class deletion hook → comment only; no class_id on attendances (spec schema) — enrollment blocker subsumes.
4. `identifier` for QR → `null` (never any token material).
5. Response shape → coarse `outcome` + granular `detail` + `student_name`; errors HTTP 422; both error outcomes still logged as events.
6. Sync year range → current + next school-tz year, still filtered ≥ today.
7. Resolution order is strict: absent-upgrade (2) precedes excused-ignore (5) — sick/leave never upgrade.
8. Teacher without linked profile → empty dashboard, not 403; student-role user without student profile → `qr => null` empty state.
