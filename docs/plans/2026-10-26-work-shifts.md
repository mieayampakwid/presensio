# Work Shifts Implementation Plan

> **For implementer agents:** Spec: `docs/specs/20-work-shifts.md` (v1.1 — decisions are final; ACs 20-01…20-16). Conventions: `.ai/rules/*` (workflow, data, controllers, inertia, navigation, tests); UI strings via `lang/{id,en}` files (spec 18); Node only through Sail; regenerate Wayfinder with `sail artisan wayfinder:generate --with-form` after any route change. Execute tasks in order; each task commits independently.

**Goal:** Named shift templates with cross-midnight support, employee default + roster assignment (incl. explicit day off), per-shift lateness/early-leave/absent-sweep computation, and the effective-shift display across staff attendance surfaces — fully backward compatible when no shift is defined.

**Architecture:** `Shift` is the time template; `ShiftResolver` turns (employee, date) into a tri-state `ShiftResolution` (shift / explicit day off / none) using `shift_roster` → `employees.shift_id` → global settings. Records snapshot `shift_id` at creation; **every closing computation** (check-out tap, absent-upgrade, manual override, missing-checkout display) uses the record's snapshot via `ShiftResolver::windowFor()`, never the tap date's effective shift. The sweep command gains `--shift={id}`; `routes/console.php` registers one run per distinct shift active today, following the existing settings-driven `dailyAt()` pattern.

**Prerequisites:** none beyond the shipped spec 16 code. No new packages, env vars, or settings keys. One spec 16 gap is closed en route: `staff_start_time` / `staff_end_time` / `staff_absent_sweep_time` currently have **no editing UI** (defaults only); Task 4 adds them to the attendance settings form, which spec 20 §Req 7 already assumes.

**Blocking questions:** none.

---

### Task 1: Schema, models, resolver

**Files:**

- Create: migrations `create_shifts_table`, `create_shift_roster_table`, `add_shift_id_to_employees_table`, `add_shift_id_to_employee_attendances_table` (spec §Schema); models `app/Models/Shift.php`, `app/Models/ShiftRoster.php`, factories; `app/Services/Attendance/ShiftResolution.php`, `app/Services/Attendance/ShiftResolver.php`
- Modify: `app/Models/Employee.php` (`shift()`, `rosters()`), `app/Models/EmployeeAttendance.php` (`shift()`)
- Test: `tests/Unit/Services/Attendance/ShiftResolverTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Services/Attendance/ShiftResolverTest.php`

- [ ] **Step 1:** Scaffold with `php artisan make:model Shift --factory --no-interaction`, `make:model ShiftRoster --factory --no-interaction`, and the four `make:migration` commands. Schema per spec: `shifts` (`name` varchar(100) unique, `start_time`/`end_time` time, `crosses_midnight` bool default false, `sweep_offset_minutes` smallint unsigned default 120); `shift_roster` (`employee_id` FK cascade, `date` date, `shift_id` **nullable** FK restrict, `UNIQUE(employee_id, date)`, `INDEX(date, shift_id)`); `employees.shift_id` nullable FK restrict; `employee_attendances.shift_id` nullable FK restrict. Casts: `crosses_midnight` bool, `start_time`/`end_time` `datetime:H:i`, roster `date` date.
- [ ] **Step 2:** `Shift` helpers (all in the school timezone, `SchoolSettings::timezone()`): `startOn(CarbonInterface|string $day)`, `endOn($day)` — **plus one day when `crosses_midnight`** — and `sweepCronTime(): string` returning `start_time + sweep_offset_minutes` as `H:i`. `ShiftResolution` is a promoted-constructor readonly DTO: `public function __construct(public readonly ?Shift $shift, public readonly bool $dayOff) {}`.
- [ ] **Step 3:** `ShiftResolver` (injects `SchoolSettings`):
  - `resolve(Employee $employee, string $date): ShiftResolution` — roster row for `(employee, date)` wins (its `shift_id`, or `dayOff: true` when null); else `$employee->shift`; else `(null, false)`.
  - `resolveMany(Collection $employees, string $date): Collection` — same result keyed by `employee_id`, in two queries (roster `whereIn employee_id`, shifts keyed by id). Used by overview/sweep to avoid N+1.
  - `windowFor(?Shift $shift, CarbonInterface|string $date): array{0: CarbonInterface, 1: CarbonInterface}` — `[start, end]` in school tz: shift's `startOn`/`endOn` on that date, or global `staffStartTimeOn`/`staffEndTimeOn` when null. This is the single definition of a record's working window.
- [ ] **Step 4:** Resolver tests: roster > default > none precedence; roster-with-null-shift → `dayOff: true`; `endOn` returns next calendar day for a 19:00–07:00 shift and same day otherwise; `windowFor(null, …)` mirrors the global settings; `resolveMany` matches per-employee `resolve` for a mixed set.

### Task 2: Tap resolution and manual override with shifts

**Files:**

- Modify: `app/Services/Attendance/EmployeeScanService.php`, `app/Http/Controllers/Staff/StaffAttendanceController.php` (`upsert`), `app/Http/Requests/Staff/UpsertStaffAttendanceRequest.php`
- Test: create `tests/Feature/Staff/ShiftScanTest.php`, `tests/Feature/Staff/ShiftManualOverrideTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/Staff/ShiftScanTest.php tests/Feature/Staff/ShiftManualOverrideTest.php`

- [ ] **Step 1:** Inject `ShiftResolver` into `EmployeeScanService`. Check-in paths (new record **and** `absent_upgraded`): resolve the effective shift on the scan date, compute late against `windowFor($shift, $date)[0]`, and write `shift_id` (the snapshot) into the record. Debounce is unchanged.
- [ ] **Step 2:** Cross-midnight close, before the today-record lookup: if no record exists for today, look for an **open** record dated yesterday whose `shift` snapshot `crosses_midnight` and whose `endOn(yesterday)` ≥ the tap; if found, treat this tap as its check-out (outcome `checked_out`, early-leave against that snapshot's end) and return — no new record. An orphan tap (window passed, or no open yesterday record) falls through to normal today logic and creates a check-in for the tap date.
- [ ] **Step 3:** All closing computations on an existing record (check-out overwrite, early-leave recompute, absent-upgrade lateness) use `windowFor($record->shift, $record->date)` — the snapshot — not the effective shift of the tap date.
- [ ] **Step 4:** `upsert()`: resolve the effective shift for the validated `date`; compute `late_minutes`/`early_leave_minutes` against `windowFor()`; include `shift_id` in the `updateOrCreate` attributes. When the resolved shift crosses midnight and `checked_out_at` < `checked_in_at`, parse the checkout as `$date + 1 day` (keep same-day parsing otherwise). Relax the request rule `after_or_equal:checked_in_at` to permit this case only (custom rule or `sometimes` + controller check); everything else unchanged.
- [ ] **Step 5:** `ShiftScanTest` (mirror `EmployeeScanTest` setup: scanner key, `Asia/Jakarta`, `Date::setTestNow`, `SchoolSettings::refresh()`): AC-20-02 (19:00–07:00 shift, tap Mon 19:05 → `late`, `late_minutes 5`, date Monday; tap Tue 06:50 → same record `checked_out_at`, `early_leave_minutes 10`); AC-20-10 (06:50 Tue closes yesterday's open record, no Tuesday record); AC-20-11 (second tap within the configured debounce → `ignored_debounce`; tap Tue 19:05 → new Tuesday record); AC-20-12 (change the employee's default shift after the record exists — record `shift_id` and closing math unchanged); AC-20-05 via a roster row (lateness vs the roster shift, not the default); orphan tap → today check-in; no-shift employee keeps spec 16 behavior.
- [ ] **Step 6:** `ShiftManualOverrideTest` (AC-20-14): admin upsert for a cross-midnight employee with check-in 19:05 / check-out 06:50 stores next-day checkout and `early_leave_minutes 10`; late minutes vs 19:00; a null-snapshot employee keeps global math.

### Task 3: Per-shift absent sweep

**Files:**

- Modify: `app/Console/Commands/MarkStaffAbsencesCommand.php`, `routes/console.php`
- Test: create `tests/Feature/Staff/StaffAbsenceSweepShiftTest.php`

**Dependencies:** Task 1 (Task 2 for upgraded-record snapshots, but independently testable) · **Verification:** `php artisan test --compact tests/Feature/Staff/StaffAbsenceSweepShiftTest.php tests/Feature/Staff/EmployeeScanTest.php`

- [ ] **Step 1:** Signature gains `{--shift= : Shift ID to sweep (default: employees with no shift)}`. `--shift` mode: candidates are active employees whose `resolveMany` result for the target date is exactly that shift (skip day-off rows); existing guards stay (school day, `EmployeeCalendar::isExpected`, no record); created `absent` records snapshot `shift_id`. No-flag mode now sweeps **only** employees with no effective shift that date (no roster row, no default) — with zero shifts configured this is byte-identical to current behavior; assert that in a regression case. Idempotency via the unique constraint, as today.
- [ ] **Step 2:** `routes/console.php`, after the existing staff schedule: collect distinct shift ids active today (defaults of active employees ∪ roster rows for `$settings->todayDate()` with non-null `shift_id`), then per shift `Schedule::command("staff-attendance:mark-absences --shift={$shift->id}")->dailyAt($shift->sweepCronTime())->timezone($settings->timezone())`. Wrap the queries in `try { … } catch (QueryException) {}` matching the file's pre-migration guard comment. Missed runs are not backfilled (existing property, documented in the spec).
- [ ] **Step 3:** Tests: AC-20-03 (`--shift=malam` marks only Malam employees without records); AC-20-04 (no-flag run touches only no-shift employees); AC-20-13 (day-off roster row suppresses the absent record; a tap that day still creates a record); absent records carry the `shift_id` snapshot; roster shift on a non-working weekday is not swept; second run is a no-op; `EmployeeScanTest` stays green (fallback behavior unchanged).

### Task 4: Shift management UI, staff fallback hours, employee assignment

**Files:**

- Create: `app/Http/Controllers/Settings/ShiftController.php` (store/update/destroy), `app/Http/Requests/Settings/SaveShiftRequest.php`
- Modify: `routes/settings.php` (admin group), `app/Http/Controllers/Settings/AttendanceSettingsController.php` (+ `app/Http/Requests/Settings/UpdateAttendanceSettingsRequest.php`), `resources/js/pages/settings/attendance.tsx`; `app/Http/Controllers/Employees/EmployeeController.php`, `resources/js/pages/employees/employee-form.tsx`, `resources/js/pages/employees/index.tsx` (edit listing); `lang/id/settings.php` + `lang/en/settings.php` (or a new `shifts.php` per the timetable lang-file pattern)
- Test: create `tests/Feature/Staff/ShiftManagementTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/Staff/ShiftManagementTest.php` + `vendor/bin/sail npm run types:check`

- [ ] **Step 1:** `SaveShiftRequest`: `name` required, unique (ignore-self on update), max 100; `start_time`/`end_time` required `date_format:H:i` and different; `sweep_offset_minutes` integer 30–480, default 120. Controller derives `crosses_midnight` (`end_time < start_time`) — never accepts it from the client. Reject `store` beyond 10 shifts with a 422 message. Delete guard (AC-20-08): `ValidationException::withMessages(['shift' => …])` when `employees()`/`rosters()`/`attendances()` `->exists()`. Routes: `settings/shifts` POST, `settings/shifts/{shift}` PUT/DELETE, names `shifts.store/update/destroy`, inside the existing admin group.
- [ ] **Step 2:** `AttendanceSettingsController::edit()` additionally passes `shifts` (id, name, times, crosses_midnight, sweep offset) and the staff fallback trio `staff_start_time` / `staff_end_time` / `staff_absent_sweep_time` (H:i, from `SchoolSettings`); `update()` accepts the trio (`date_format:H:i`) through the existing audit-diffing write path. Page gains two sections: "Jam Kerja Staf (Fallback)" inputs with the spec §Req 7 note, and "Shift Kerja" (table + create/edit dialog + delete, 422 errors surfaced inline). This closes the missing staff-hours editing UI.
- [ ] **Step 3:** Employee form: nullable Shift select (options from `EmployeeController` create/edit props, `exists:shifts,id`); employee listing shows the default shift name. Regenerate Wayfinder (`sail artisan wayfinder:generate --with-form`), run `sail npm run check` and `types:check`.
- [ ] **Step 4:** Tests: AC-20-01 (creating 19:00–07:00 stores `crosses_midnight: true`); name duplicate / `start_time == end_time` / offset out of range → 422; AC-20-08 (delete blocked 422 when referenced by an employee default, a roster row, or an attendance snapshot — three cases); 11th shift rejected; staff-hours update persists and audits; non-admin on every shift route and the staff-hours update → 403 (AC-20-16 part); admin employee form accepts `shift_id` and rejects an unknown id.

### Task 5: Roster page

**Files:**

- Create: `app/Http/Controllers/Staff/ShiftRosterController.php`, `resources/js/pages/staff/roster.tsx`
- Modify: `routes/staff.php`, `resources/js/pages/staff/attendance.tsx` (header action "Roster Shift" linking to the roster — contextual destination, no sidebar item per `.ai/rules/navigation.md`), lang files from Task 4
- Test: create `tests/Feature/Staff/ShiftRosterTest.php`

**Dependencies:** Tasks 1, 4 (shift options exist) · **Verification:** `php artisan test --compact tests/Feature/Staff/ShiftRosterTest.php` + `vendor/bin/sail npm run types:check`

- [ ] **Step 1:** Routes (flat, in `routes/staff.php`): `GET staff-attendance/roster` (`staff-roster.index`, admin+principal), `POST staff-attendance/roster` bulk (`staff-roster.bulk`, admin), `PUT staff-attendance/roster` single cell (`staff-roster.set`, admin), `DELETE staff-attendance/roster` clear (`staff-roster.clear`, admin). `index` takes `?week=Y-m-d` or `?month=Y-m-d` (default current week): rows = active employees (default shift included), columns = each date in the range with its `resolveMany` result (shift name/times, "Libur" for day-off, fallback label when none) and a `editable` flag (date ≥ school today). Bulk upsert: `employees[]`, `start_date`, `end_date` (≤ 62 days), `shift_id` nullable (= day off) → one roster row per date in range. Cell set: `employee_id`, `date`, `shift_id` nullable. Past dates → 422 on mutation.
- [ ] **Step 2:** Page: grid rows employees × dates, shifts color-coded consistently (day-off visually distinct from fallback), week/month toggle, bulk-assign dialog (employee multi-select, range, shift or day-off), per-cell override and clear; principal sees the grid read-only (mutation hidden client-side, 403 server-side).
- [ ] **Step 3:** Tests: grid payload reflects roster > default > fallback and flags past dates; bulk writes every date in range (bounded range rejection); cell set/clear (AC-20-06 — clear falls back to the default shift, observable through `ShiftResolver`); past-date mutation 422; principal `GET` ok, `POST`/`PUT`/`DELETE` 403 (AC-20-16); non-admin/non-principal GET 403; day-off cell stores a null-shift row and the grid shows "Libur".

### Task 6: Effective shift display and missing-checkout rule

**Files:**

- Modify: `app/Http/Controllers/Staff/StaffAttendanceController.php` (`index`), `app/Services/Staff/StaffRecapService.php`, `resources/js/pages/staff/attendance.tsx`
- Test: extend `tests/Feature/Staff/StaffAttendanceOverviewTest.php`, `tests/Feature/Staff/StaffRecapTest.php`

**Dependencies:** Task 1 (records with snapshots from Task 2 for fixtures) · **Verification:** `php artisan test --compact tests/Feature/Staff/StaffAttendanceOverviewTest.php tests/Feature/Staff/StaffRecapTest.php`

- [ ] **Step 1:** `index()`: resolve shifts once via `resolveMany($activeEmployees, $date)`; each row gains `shift_name` and `shift_times` (`"19:00 - 07:00"`) — null for fallback employees, whose rows keep the global window already passed as `staff_start_time`/`staff_end_time` props (AC-20-09). `no_checkout` becomes `$hasIn && ! $hasOut && windowFor($record->shift, $record->date)[1] < now` (school tz) instead of the `$isPastDate` heuristic.
- [ ] **Step 2:** `StaffRecapService`: eager-load `shift` on the records query; `tidak absen pulang` counts a present/late open record only when `windowFor(...)[1]` is in the past. Behavior for completed/past-day-shift records is unchanged; only same-day-before-end and cross-midnight-before-next-end records stop counting (AC-20-15). Recap structure is otherwise untouched (minutes are already stored per record).
- [ ] **Step 3:** Tests: overview row shows the effective shift name/times and the fallback label for unassigned employees; a Monday 19:00–07:00 record open at Tue 06:00 is **not** `no_checkout`/counted in the recap, and is once Tue 07:00 has passed; a day-shift record open today before `staff_end_time` is not counted.

### Task 7: Close-out

**Dependencies:** all · **Verification:** every row has real browser evidence or an explicit unavailable status.

- [ ] Gate: `vendor/bin/pint --test` (whole tree — CI gotcha), `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`, `composer test`, `vendor/bin/phpstan analyse --memory-limit=1G`. Delete `.playwright-mcp/` artifacts after browser QA.
- [ ] Door-to-door browser QA on disposable data: admin → Settings Kehadiran → fallback hours save + Shift CRUD (create Malam, edit, delete-guard error) → Employees → assign default shift → Staff Attendance → "Roster Shift" → bulk week assign, cell override, day-off, clear, past-date disabled → daily overview shows shift names, manual override with cross-midnight checkout → recap. Principal → roster read-only, no mutation controls; staff/teacher self-service unchanged. Delete `.playwright-mcp/` after.
- [ ] Update spec 20 Status → implemented (date, gate counts, QA result) and the README row; MOC + repo `CLAUDE.md` status via `/handoff`.
