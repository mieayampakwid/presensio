# Work Shifts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Repo conventions: `.ai/rules/*` (workflow, data, controllers, inertia, navigation, tests); UI strings via `lang/{id,en}` files (spec 18); Node only through Sail; regenerate Wayfinder with `sail artisan wayfinder:generate --with-form` after any route change.

**Goal:** Named shift templates with cross-midnight support, employee default + roster assignment (incl. explicit day off), per-shift lateness/early-leave/absent-sweep computation, and the effective-shift display across staff attendance surfaces — fully backward compatible when no shift is defined.

**Architecture:** `Shift` is the time template; `ShiftResolver` turns (employee, date) into a tri-state `ShiftResolution` (shift / explicit day off / none) using `shift_roster` → `employees.shift_id` → global settings. Records snapshot `shift_id` at creation; **every closing computation** (check-out tap, absent-upgrade, manual override, missing-checkout display) uses the record's snapshot via `ShiftResolver::windowFor()`, never the tap date's effective shift. The sweep command gains `--shift={id}`; `routes/console.php` registers one run per distinct shift active today, following the existing settings-driven `dailyAt()` pattern.

**Tech Stack:** Laravel 13 / PHP 8.5 (Octane), Inertia v3 + React 19 + Wayfinder, PostgreSQL via Sail (tests on sqlite), PHPUnit.

**Spec:** `docs/specs/20-work-shifts.md` (v1.1 — decisions final; ACs 20-01…20-16). The plan argues from the spec; executors read both.

## Global Constraints

- No new packages, no env vars, no new settings keys, no external integrations.
- All business times evaluate in the school timezone (`SchoolSettings::timezone()`); the app stores UTC.
- sqlite/PostgreSQL parity: `whereLike` for user-facing search; check column names against schema in eager-load selects.
- Never hand-edit `resources/js/{actions,routes,wayfinder}` — regenerate (`sail artisan wayfinder:generate --with-form`).
- UI strings ship through `lang/{id,en}`; user-entered data (shift names) stays untranslated.
- No request-specific state in singletons/statics (Octane).
- Pint runs via hook on edited PHP; `vendor/bin/pint --test` on the whole tree before push (CI gotcha).

## Review Focus

Spec-silent inputs most likely to bite, each pinned to its owning task's tests:

1. Tap far outside the shift window (night-shift employee taps 10:00 for a meeting, or 08:00 after the window closed) → still a normal check-in for the tap date against that date's effective start; never rejected → Task 2.
2. Consecutive cross-midnight shifts with no check-out between (Mon never closed, tap Tue 19:05) → Tuesday opens a new record; Monday stays open and later counts as *tidak absen pulang*, never steals Tuesday's tap → Task 2.
3. Manual override on an explicit day-off date succeeds — admin judgment beats the roster → Task 2.
4. Inactive employee with a default shift or roster rows is never swept and never late-computed → Task 3.
5. Editing shift template times after records exist never moves stored `late_minutes` / `early_leave_minutes` → Task 2.
6. Sweep offset pushing `start_time + sweep_offset_minutes` past 24:00 (23:00 start + 480) → save-time 422. Unvalidated, the sweep fires `dailyAt` on the *next* calendar day with target date = school today, marking tonight's not-yet-started shift absent as a false absence (and yesterday's actual shift never gets swept) → Task 4 validation, Task 3 note.

---

### Task 1: Schema, models, resolver

**Files:**

- Create: migrations `create_shifts_table`, `create_shift_roster_table`, `add_shift_id_to_employees_table`, `add_shift_id_to_employee_attendances_table` (spec §Schema); models `app/Models/Shift.php`, `app/Models/ShiftRoster.php`, factories; `app/Services/Attendance/ShiftResolution.php`, `app/Services/Attendance/ShiftResolver.php`
- Modify: `app/Models/Employee.php` (`shift()`, `rosters()`), `app/Models/EmployeeAttendance.php` (`shift()`)
- Test: `tests/Unit/Services/Attendance/ShiftResolverTest.php`

**Interfaces:**

- Consumes: `SchoolSettings` (`timezone()`, `staffStartTimeOn()`, `staffEndTimeOn()`).
- Produces: `Shift` with `startOn(CarbonInterface|string $day): CarbonInterface`, `endOn($day): CarbonInterface` (**+1 day when `crosses_midnight`**), `sweepCronTime(): string` (`H:i` = start + offset; Task 4's save-time validation keeps start + offset on the start date, so the value is always a same-day time and the `dailyAt` run sweeps the shift's own start date); readonly `ShiftResolution(public readonly ?Shift $shift, public readonly bool $dayOff)`; `ShiftResolver::resolve(Employee $employee, string $date): ShiftResolution`; `ShiftResolver::resolveMany(Collection $employees, string $date): Collection` (keyed by `employee_id`); `ShiftResolver::windowFor(?Shift $shift, CarbonInterface|string $date): array{0: CarbonInterface, 1: CarbonInterface}` (school tz; shift window on that date, global staff times when null).

- [ ] **Step 1: Write the failing resolver tests** — `ShiftResolverTest` cases: roster row > default shift > none precedence; roster row with null `shift_id` → `dayOff: true`; `endOn` returns next calendar day for a 19:00–07:00 shift, same day otherwise; `windowFor(null, …)` mirrors global settings; `resolveMany` matches per-employee `resolve` on a mixed set.

- [ ] **Step 2: Run to verify it fails** — Run: `php artisan test --compact tests/Unit/Services/Attendance/ShiftResolverTest.php` · Expected: FAIL (`Class "App\Services\Attendance\ShiftResolver" not found`).

- [ ] **Step 3: Schema and models** — Scaffold with `php artisan make:model Shift --factory --no-interaction`, `make:model ShiftRoster --factory --no-interaction`, and the four `make:migration` commands. `shifts`: `name` varchar(100) unique, `start_time`/`end_time` time, `crosses_midnight` bool default false, `sweep_offset_minutes` smallint unsigned default 120. `shift_roster`: `employee_id` FK cascade, `date` date, `shift_id` **nullable** FK restrict, `UNIQUE(employee_id, date)`, `INDEX(date, shift_id)`. `employees.shift_id` nullable FK restrict; `employee_attendances.shift_id` nullable FK restrict. Casts: `crosses_midnight` bool, `start_time`/`end_time` `datetime:H:i`, roster `date` date. Relations per Interfaces block.

- [ ] **Step 4: Implement the resolver** — `resolve`/`resolveMany` read one roster row per employee for the date (batch `whereIn`), else the employee's `shift`, else `(null, false)`. `windowFor` delegates to `Shift::startOn`/`endOn` or the global staff helpers.

- [ ] **Step 5: Run to verify it passes** — Run: `php artisan test --compact tests/Unit/Services/Attendance/ShiftResolverTest.php` · Expected: PASS.

- [ ] **Step 6: Commit** — `git add -A && git commit -m "feat(shifts): shift schema, models and effective-shift resolver"`

### Task 2: Tap resolution and manual override with shifts

**Files:**

- Modify: `app/Services/Attendance/EmployeeScanService.php`, `app/Http/Controllers/Staff/StaffAttendanceController.php` (`upsert`), `app/Http/Requests/Staff/UpsertStaffAttendanceRequest.php`
- Test: create `tests/Feature/Staff/ShiftScanTest.php`, `tests/Feature/Staff/ShiftManualOverrideTest.php`

**Interfaces:**

- Consumes: Task 1 (`ShiftResolver`, `windowFor`, `ShiftResolution`).
- Produces: `employee_attendances.shift_id` snapshots written on every record-creating path (scan, absent-upgrade, manual override, sweep in Task 3).

- [ ] **Step 1: Write the failing tests** — `ShiftScanTest` (mirror `EmployeeScanTest` setup: scanner key, `Asia/Jakarta`, `Date::setTestNow`, `SchoolSettings::refresh()`): AC-20-02 (19:00–07:00 shift; Mon 19:05 → `late`, 5 min, date Monday; Tue 06:50 → same record checkout, early 10); AC-20-10 (06:50 Tue closes yesterday's open record, no Tuesday record); AC-20-11 (tap within the configured debounce → `ignored_debounce`; Tue 19:05 → new Tuesday record); AC-20-12 (change default shift after creation — snapshot and closing math unchanged, pins Review Focus 5); AC-20-05 via a roster row (lateness vs roster shift); Review Focus 1 (10:00 tap on a 19:00-start day → `present`, late 0); Review Focus 2 (Mon never closed, Tue 19:05 → new record, Monday still open); no-shift employee keeps spec 16 behavior. `ShiftManualOverrideTest`: AC-20-14 (cross-midnight 19:05/06:50 → next-day checkout, early 10, late vs 19:00); Review Focus 3 (override succeeds on a day-off date); null-snapshot employee keeps global math.

- [ ] **Step 2: Run to verify they fail** — Run: `php artisan test --compact tests/Feature/Staff/ShiftScanTest.php tests/Feature/Staff/ShiftManualOverrideTest.php` · Expected: FAIL (no `shift_id` column / wrong minutes).

- [ ] **Step 3: Scan service** — Inject `ShiftResolver`. Check-in paths (new record **and** `absent_upgraded`): resolve the effective shift on the scan date, compute late against `windowFor($shift, $date)[0]`, write `shift_id` into the record. Debounce unchanged. Cross-midnight close, before the today-record lookup: no record today + an **open** record dated yesterday whose snapshot crosses midnight and whose `endOn(yesterday)` ≥ tap → debounce still applies against that record's last tap (Mon 23:58 check-in + Tue 00:02 tap within D → `ignored_debounce`); beyond debounce, this tap is that record's check-out (early leave vs its snapshot end), return without creating a record. All other closing computations on an existing record use `windowFor($record->shift, $record->date)` — the snapshot, never the tap date's effective shift.

- [ ] **Step 4: Manual override** — `upsert()`: resolve the effective shift for the validated date; compute minutes against `windowFor()`; include `shift_id` in `updateOrCreate`. When the resolved shift crosses midnight and `checked_out_at` < `checked_in_at`, parse checkout as `$date + 1 day`. Relax `after_or_equal:checked_in_at` to permit exactly this case.

- [ ] **Step 5: Run to verify they pass** — Run: `php artisan test --compact tests/Feature/Staff/ShiftScanTest.php tests/Feature/Staff/ShiftManualOverrideTest.php tests/Feature/Staff/EmployeeScanTest.php` · Expected: PASS (EmployeeScanTest proves fallback behavior unchanged).

- [ ] **Step 6: Commit** — `git add -A && git commit -m "feat(shifts): shift-aware taps and manual override with snapshot math"`

### Task 3: Per-shift absent sweep

**Files:**

- Modify: `app/Console/Commands/MarkStaffAbsencesCommand.php`, `routes/console.php`
- Test: create `tests/Feature/Staff/StaffAbsenceSweepShiftTest.php`

**Interfaces:**

- Consumes: Task 1 (`resolveMany`, `Shift::sweepCronTime`); snapshot semantics from Task 2.
- Produces: `staff-attendance:mark-absences --shift={id}`; absent records carry `shift_id`.

- [ ] **Step 1: Write the failing tests** — `StaffAbsenceSweepShiftTest` cases: AC-20-03 (`--shift` for Malam at 21:00 marks only Malam employees without records); AC-20-04 (no-flag run touches only no-shift employees); AC-20-13 (day-off roster row suppresses the absent record in **both** the `--shift` run and the no-flag run; a tap that day still records); absent records snapshot `shift_id`; roster shift on a non-working weekday is not swept; Review Focus 4 (deactivated employee with default shift and roster rows → no absent record); second run no-ops; zero-shifts-configured no-flag run behaves exactly as spec 16 (regression).

- [ ] **Step 2: Run to verify they fail** — Run: `php artisan test --compact tests/Feature/Staff/StaffAbsenceSweepShiftTest.php` · Expected: FAIL (unknown `--shift` option / wrong candidate set).

- [ ] **Step 3: Command** — Signature gains `{--shift= : Shift ID to sweep (default: employees with no shift)}`. `--shift` mode: candidates are active employees whose `resolveMany` result for the target date is exactly that shift (skip day-off rows); existing guards stay (school day, `EmployeeCalendar::isExpected`, no record); created records snapshot `shift_id`. No-flag mode sweeps **only** employees whose resolution is exactly `(shift: null, dayOff: false)` — day-off roster rows are excluded even though their shift is null — byte-identical to current behavior when no shifts exist. Unique-constraint idempotency, as today.

- [ ] **Step 4: Scheduler** — `routes/console.php`, after the existing staff schedule: distinct shift ids active today (defaults of active employees ∪ today's non-null roster `shift_id`s), then per shift `Schedule::command("staff-attendance:mark-absences --shift={$shift->id}")->dailyAt($shift->sweepCronTime())->timezone($settings->timezone())` (Task 4's save validation guarantees `sweepCronTime()` is a same-day time, so each run sweeps the date it fires on — never the next day's not-yet-started shift). Wrap the queries in `try { … } catch (QueryException) {}` matching the file's pre-migration guard comment. Missed runs are not backfilled (existing property, documented in the spec).

- [ ] **Step 5: Run to verify they pass** — Run: `php artisan test --compact tests/Feature/Staff/StaffAbsenceSweepShiftTest.php tests/Feature/Staff/EmployeeScanTest.php` · Expected: PASS.

- [ ] **Step 6: Commit** — `git add -A && git commit -m "feat(shifts): per-shift absent sweep with fallback-group compatibility"`

### Task 4: Shift management UI, staff fallback hours, employee assignment

**Files:**

- Create: `app/Http/Controllers/Settings/ShiftController.php` (store/update/destroy), `app/Http/Requests/Settings/SaveShiftRequest.php`
- Modify: `routes/settings.php` (admin group), `app/Http/Controllers/Settings/AttendanceSettingsController.php`, `app/Http/Requests/Settings/UpdateAttendanceSettingsRequest.php`, `resources/js/pages/settings/attendance.tsx`; `app/Http/Controllers/Employees/EmployeeController.php`, `resources/js/pages/employees/employee-form.tsx`, `resources/js/pages/employees/index.tsx`; `lang/id/shifts.php` + `lang/en/shifts.php` (new file, timetable lang-file pattern)
- Test: create `tests/Feature/Staff/ShiftManagementTest.php`

**Interfaces:**

- Consumes: Task 1 models.
- Produces: routes `shifts.store/update/destroy` (`settings/shifts…`); settings props `shifts` and the staff fallback trio; employee form prop `shifts`.

- [ ] **Step 1: Write the failing tests** — `ShiftManagementTest` cases: AC-20-01 (creating 19:00–07:00 stores `crosses_midnight: true`); duplicate name / `start_time == end_time` / offset out of range / `start_time + sweep_offset_minutes` past 24:00 (Review Focus 6) → 422; AC-20-08 (delete blocked 422 when referenced by an employee default, a roster row, or an attendance snapshot — three cases); 11th shift rejected; staff-hours update persists and audits; non-admin on every shift route and the staff-hours update → 403 (AC-20-16 part); employee form accepts `shift_id`, rejects an unknown id.

- [ ] **Step 2: Run to verify they fail** — Run: `php artisan test --compact tests/Feature/Staff/ShiftManagementTest.php` · Expected: FAIL (404s — routes absent).

- [ ] **Step 3: HTTP** — `SaveShiftRequest`: `name` required unique (ignore-self on update) max 100; `start_time`/`end_time` required `date_format:H:i` and different; `sweep_offset_minutes` integer 30–480 default 120, and reject `start_time + sweep_offset_minutes` ≥ 24:00 (422 — the sweep must fire on the shift's start date; Review Focus 6). Controller derives `crosses_midnight` — never trusts the client. Reject `store` beyond 10 shifts (422). Delete guard via `ValidationException::withMessages(['shift' => …])` when `employees()`/`rosters()`/`attendances()` `->exists()`. Routes in the existing admin group.

- [ ] **Step 4: Settings + employee UI** — `AttendanceSettingsController::edit()` passes `shifts` (id, name, times, crosses_midnight, offset) and `staff_start_time`/`staff_end_time`/`staff_absent_sweep_time` (H:i); `update()` accepts the trio through the existing audit-diffing write path — this closes the missing staff-hours editing UI. Page gains "Jam Kerja Staf (Fallback)" inputs with the spec §Req 7 note and a "Shift Kerja" section (table + create/edit dialog + delete, 422 errors inline). Employee form gets a nullable Shift select (`exists:shifts,id`); the listing shows the default shift name. Regenerate Wayfinder; run `sail npm run check` and `types:check`.

- [ ] **Step 5: Run to verify they pass** — Run: `php artisan test --compact tests/Feature/Staff/ShiftManagementTest.php` + `vendor/bin/sail npm run types:check` · Expected: PASS / clean.

- [ ] **Step 6: Commit** — `git add -A && git commit -m "feat(shifts): settings CRUD, staff fallback hours and employee assignment"`

### Task 5: Roster page

**Files:**

- Create: `app/Http/Controllers/Staff/ShiftRosterController.php`, `resources/js/pages/staff/roster.tsx`
- Modify: `routes/staff.php`, `resources/js/pages/staff/attendance.tsx` (header action "Roster Shift" — contextual destination, no sidebar item per `.ai/rules/navigation.md`), lang files from Task 4
- Test: create `tests/Feature/Staff/ShiftRosterTest.php`

**Interfaces:**

- Consumes: Task 1 `resolveMany`; Task 4 shift list prop pattern and lang file.
- Produces: routes `staff-roster.index` (GET `staff-attendance/roster`, admin+principal), `staff-roster.bulk` (POST `staff-attendance/roster/bulk`, admin), `staff-roster.set` (PUT `staff-attendance/roster`, admin), `staff-roster.clear` (DELETE `staff-attendance/roster`, admin) — flat-action paths like `staff-attendance`/`staff-leave`.

- [ ] **Step 1: Write the failing tests** — `ShiftRosterTest` cases: grid payload reflects roster > default > fallback and flags past dates; bulk writes every date in a range (bounded ≤ 62 days; Review Focus: range spanning weekends writes rows but changes nothing those days); cell set/clear (AC-20-06 — clear falls back to the default, observable via `ShiftResolver`); past-date mutation 422; principal GET ok, mutations 403 (AC-20-16); non-admin/non-principal GET 403; day-off cell stores a null-shift row rendered as "Libur".

- [ ] **Step 2: Run to verify they fail** — Run: `php artisan test --compact tests/Feature/Staff/ShiftRosterTest.php` · Expected: FAIL (404s — routes absent).

- [ ] **Step 3: Controller and routes** — `index` takes `?week=Y-m-d` or `?month=Y-m-d` (default current week): rows = active employees with default shift, columns = each date with its `resolveMany` result (shift name/times, "Libur" for day-off, fallback label when none) and `editable` (date ≥ school today). Bulk: `employees[]`, `start_date`, `end_date` (≤ 62 days), `shift_id` nullable (= day off) → one roster row per date in range. Cell set: `employee_id`, `date`, `shift_id` nullable. Past dates → 422 on mutation.

- [ ] **Step 4: Page** — Grid employees × dates, shifts color-coded consistently (day-off distinct from fallback), week/month toggle, bulk-assign dialog (multi-select, range, shift or day-off), per-cell override and clear; past dates disabled; principal sees the grid read-only. Regenerate Wayfinder; `sail npm run check` + `types:check`.

- [ ] **Step 5: Run to verify they pass** — Run: `php artisan test --compact tests/Feature/Staff/ShiftRosterTest.php` + `vendor/bin/sail npm run types:check` · Expected: PASS / clean.

- [ ] **Step 6: Commit** — `git add -A && git commit -m "feat(shifts): weekly roster grid with bulk assign and day-off"`

### Task 6: Effective shift display and missing-checkout rule

**Files:**

- Modify: `app/Http/Controllers/Staff/StaffAttendanceController.php` (`index`), `app/Services/Staff/StaffRecapService.php`, `resources/js/pages/staff/attendance.tsx`
- Test: extend `tests/Feature/Staff/StaffAttendanceOverviewTest.php`, `tests/Feature/Staff/StaffRecapTest.php`

**Interfaces:**

- Consumes: Task 1 (`resolveMany`, `windowFor`); snapshots written by Tasks 2–3.
- Produces: overview row fields `shift_name`, `shift_times`; effective-end `no_checkout` semantics.

- [ ] **Step 1: Write the failing tests** — Extend the owner files: overview rows show the effective shift name/times (AC-20-09) and the fallback label for unassigned employees; AC-20-15 (Monday 19:00–07:00 record open at Tue 06:00 is not `no_checkout` / not counted in the recap; counted once Tue 07:00 passed); a same-day day-shift record open before `staff_end_time` is not counted, and **is** counted once `staff_end_time` has passed (behavior change for today's view — `$isPastDate` previously never counted today); completed past-day records still count (regression).

- [ ] **Step 2: Run to verify they fail** — Run: `php artisan test --compact tests/Feature/Staff/StaffAttendanceOverviewTest.php tests/Feature/Staff/StaffRecapTest.php` · Expected: FAIL (no shift props / wrong no-checkout flag).

- [ ] **Step 3: Implement** — `index()`: resolve once via `resolveMany($activeEmployees, $date)`; add `with('shift')` to the records query (like `StaffRecapService` — `no_checkout` reads the snapshot per row, no N+1); rows gain `shift_name` + `shift_times` (`"19:00 - 07:00"`), null for fallback employees (who keep the global-window props). `no_checkout` becomes `$hasIn && ! $hasOut && windowFor($record->shift, $record->date)[1] < now` (school tz) instead of the `$isPastDate` heuristic. `StaffRecapService`: eager-load `shift`; `tidak absen pulang` counts only when `windowFor(...)[1]` is past. Recap structure otherwise untouched (minutes are stored per record).

- [ ] **Step 4: Run to verify they pass** — Run: `php artisan test --compact tests/Feature/Staff/StaffAttendanceOverviewTest.php tests/Feature/Staff/StaffRecapTest.php` · Expected: PASS.

- [ ] **Step 5: Commit** — `git add -A && git commit -m "feat(shifts): effective shift display and effective-end missing-checkout rule"`

### Task 7: Close-out

**Interfaces:**

- Consumes: all tasks.

- [ ] **Step 1: Gate** — `vendor/bin/pint --test` (whole tree — CI gotcha), `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`, `composer test`, `vendor/bin/phpstan analyse --memory-limit=1G`. Expected: all green.
- [ ] **Step 2: Door-to-door browser QA** (disposable data) — Admin: Settings Kehadiran → fallback hours save + Shift CRUD (create Malam, edit, delete-guard error) → Employees → assign default shift → Staff Attendance → "Roster Shift" → bulk week assign, cell override, day-off, clear, past-date disabled → daily overview shows shift names, manual override with cross-midnight checkout → recap. Principal: roster read-only, no mutation controls. Staff/teacher self-service unchanged. Delete `.playwright-mcp/` artifacts after.
- [ ] **Step 3: Status flip** — Spec 20 Status → implemented (date, gate counts, QA result); README row; MOC + repo `CLAUDE.md` via `/handoff`. Commit: `docs(shifts): mark spec 20 implemented`.
