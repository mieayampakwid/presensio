# Spec 08 — Dashboard (Role Landing)

## Context

Implements `docs/specs/08-dashboard.md` — the app's front door. Every role lands on `dashboard` after login; it's still the starter-kit placeholder. It becomes a controller-composed, **read-only** role summary reusing existing queries: admin/teacher today-counts from `AttendanceReportService::board()` (already `ClassAccess`-scoped, enrollment-model-aware post-spec-07), parent per-child today status + latest excuse, student today card mirroring `my-attendance`. No writes, no polling, no deferred props, no new tables, no changes to any service — pure composition.

**Locked decisions (spec):**
- One role-shaped props payload from a controller; widgets carry numbers/summary fields only — no student names for admin per-class counts (board owns drill-down), never `notes` / `override_by_user_id` / `review_note` / ledger data.
- Today = `SchoolSettings::todayDate()`; weekend/non-school-day → muted banner via `is_school_day` prop (same as Presence Board); widgets still render honest zeros.
- Scoping is inherited, never new: `ClassAccess` for staff, `guardian_student` for parents, self for students.
- Students get no excuse widgets (spec 04); route name `dashboard` + post-login redirect unchanged; welcome page untouched.

**Ambiguities resolved by interpretation (minor, flag if you disagree):**
1. Unlinked-teacher empty state reuses Presence Board's "No classes assigned to you." (`presence-board.tsx:211-215`) — `attendance/index.tsx` has no dedicated zero-class state.
2. Parent: `children === null` (no guardian profile) vs `[]` (profile, zero children) — same `UserRoundX` Card shape, adapted copy.
3. "Latest excuse they submitted" = latest excuse for the child (`excuses` has no submitter column; guardians are the only submitters — identical semantics to `GuardianExcuseController`).
4. Admin "reports" quick link = Student Report + Class Report links (no reports hub exists).
5. `as_of` dropped from the payload (exists for the board's polling; dashboard doesn't poll).

## Props contract (exact keys)

```
is_school_day: boolean                                   // all roles
board: {                                                 // admin + teacher; null for parent/student
    totals: { enrolled, in_building, checked_out, not_checked_in } | null,   // admin only (board() nulls it for teachers)
    classes: Array<{ id, name, enrolled, in_building, checked_out, not_checked_in }>  // counts only — not_in stripped
} | null
pending_excuses: number | null                           // admin + teacher; null otherwise
children: Array<{                                        // parent; null = no guardian profile; [] = zero children
    id, full_name, class_name: string | null,
    today_status: string | null,                         // null = "No record yet" (never "absent")
    latest_excuse: { type, status, start_date, end_date } | null
}> | null
student: { today: { status, checked_in_at, checked_out_at, scan_method } | null } | null
                                                         // student; outer null = no profile; today null = no record yet
```

## Task order

### 0. Docs
Copy this plan to `docs/plans/2026-09-21-dashboard.md`.

### 1. Controller — new `app/Http/Controllers/Dashboard/DashboardController.php`

Constructor DI `AttendanceReportService $reports` + `SchoolSettings $settings`. `index(Request)` renders `'dashboard'` with the contract above; staff = admin‖teacher gets `boardSummary()` + `pendingCount()`, parent gets `children()`, student gets `studentToday()`. Full array-shape docblocks (phpstan level 7). Private composers:

- **`boardSummary(User)`** — `$this->reports->board(ClassAccess::classIds($user, AcademicYear::active()?->id), $user)`, then map `classes` to `{id, name, enrolled, in_building, checked_out, not_checked_in}` — drop `not_in` + `as_of`. Service untouched.
- **`pendingCount(User)`** — `Excuse::where('status', Pending)->when($user->role !== Admin, whereHas('student.enrollments', whereNull ended_on + whereIn class_id ClassAccess::classIds($user, active year)))->count()` — mirrors `ExcuseReviewController:54-57` verbatim, no refactor.
- **`children(User)`** — null when `$user->guardian === null`; else guardian's students eager `currentEnrollment.schoolClass:id,name` ordered by full_name; one `Attendance` whereIn student_id + whereDate today (`get(['student_id','status'])->keyBy`), one `Excuse` whereIn ordered desc `created_at,id` grouped by student_id (first per group = latest). Row: `{id, full_name, class_name, today_status (value|null), latest_excuse {type,status,start_date->toDateString(),end_date->toDateString()}|null}`.
- **`studentToday(User)`** — null when `$user->student === null`; else `['today' => todayRow($user->student->attendances()->whereDate('date', todayDate())->first(...))]`.
- **`todayRow(?Attendance)`** — ~6-line mirror of `StudentAttendanceController::row()` (`status->value`, `?->tz($tz)->format('H:i')` times, `scan_method?->value`; null in → null out). Not shared — spec asks shape mirror only.

### 2. Route — edit `routes/web.php:22-24`

Replace `Route::inertia('dashboard', 'dashboard')` with `Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')` inside the existing `['auth']` group. No new route file (single route); Wayfinder regenerates on build; `dashboard()` signature unchanged.

### 3. Frontend — rewrite `resources/js/pages/dashboard.tsx`

House skeleton: `<Head title="Dashboard"/>` + `div.space-y-6.p-4` + `Heading` + banner + role branch on `usePage<{auth: Auth}>().props.auth.user.role`; local `type Props` + local section components in-file (precedent: attendance/index.tsx local dialogs); remove `PlaceholderPattern`; keep `Dashboard.layout` breadcrumbs.

- **Banner (all roles)**: `!is_school_day &&` the muted bordered `<p>` from `presence-board.tsx:73-77`.
- **Admin/Teacher** (`board !== null`): admin totals grid wrapped in `Link` to `PresenceBoardController.index().url` (cards from `presence-board.tsx:79-122`); per-class compact table — name (linked to board) + enrolled/in-building/checked-out/not-checked-in numbers; `board.classes.length === 0` → "No classes assigned to you."; pending-excuses card → `ExcuseReviewController.index().url`; quick-link Buttons — Attendance (`AttendanceController.index().url`), Student + Class report; teacher additionally Presence Board.
- **Parent** (`children !== null`): Card per child — name + class, `STATUS_BADGES` chip on `today_status` or muted "No record yet", latest-excuse line (`EXCUSE_TYPE_LABELS` + `EXCUSE_STATUS_BADGES` + date range), links "My Excuses" (`my().url`) + child report (`StudentReportController.index({ query: { student_id } }).url`). `children === null` → `UserRoundX` Card "No guardian profile linked" (`my-excuses.tsx:79-92`); `[]` → same shape, "No children linked".
- **Student** (`student !== null`): today card from `my-attendance.tsx:75-111` (chip, In/Out `H:i`, `METHOD_LABELS`), whole card links to `myAttendance().url`; `today === null` → "No record yet — check in at the scanner."; `student === null` → "No student profile linked" Card verbatim. No excuse widgets.

### 4. Tests — extend `tests/Feature/DashboardTest.php`

Adopt PresenceBoardTest scaffolding: `Date::setTestNow('2026-09-20 18:30:00', 'UTC')` setUp (school-tz Mon 2026-09-21), reset tearDown; `assertInertia` + `component('dashboard')`. Keep both existing tests (factory default role = Teacher ⇒ unlinked-teacher path, still 200). Add:

- `test_admin_sees_presence_board_numbers_and_per_class_counts` — totals + class counts match board() source.
- `test_admin_dashboard_carries_no_student_names` — `missing('board.classes.0.not_in')`.
- `test_admin_pending_excuse_count_equals_all_pending` (2 pending + 1 approved ⇒ 2).
- `test_teacher_sees_only_homeroom_class_counts` (has classes 1; `board.totals` null).
- `test_teacher_pending_count_is_scoped_to_their_classes`.
- `test_teacher_without_a_linked_profile_gets_an_empty_board` (classes [], pending 0).
- `test_parent_sees_one_card_per_linked_child_across_classes`.
- `test_parent_child_without_a_record_shows_no_record_status` (`today_status` null, never 'absent').
- `test_parent_sees_latest_excuse_per_child` (later created_at wins; fields only).
- `test_parent_never_sees_other_guardians_children`.
- `test_parent_without_a_guardian_profile_sees_the_empty_state` (children null).
- `test_student_sees_their_today_status_card` (H:i school-tz formatted).
- `test_student_without_a_record_sees_no_record_yet` / `test_student_without_a_profile_sees_the_empty_state`.
- `test_students_and_parents_get_no_cross_role_widgets` (student: board/pending null; parent: board null).
- `test_weekend_shows_the_not_a_school_day_flag_for_every_role` (pin `2026-09-19 18:30:00` UTC ⇒ school-tz Sunday; all four roles).
- `test_a_seeded_non_school_day_shows_the_banner_flag`.
- `test_loading_the_dashboard_writes_nothing` — Attendance/Excuse/AbsenceNotification counts unchanged across GETs as all four roles (settings row firstOrCreate is legitimately excluded).

### 5. Gates

`vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → `sail npm run build` + `sail npm run types:check` → `php artisan test --compact` (narrow DashboardTest, then full suite).

### 6. Commit + wrap-up

`feat(dashboard): role-scoped landing page summarizing today` (+ Co-Authored-By trailer). Update `docs/specs/README.md` (08 → Implemented 2026-09-21; build order → "01→08 shipped · next: deploy/pilot") + MOC Active work. No migration/seed changes — dev DB untouched.

## Gotchas

- `preventLazyLoading` is on — eager `currentEnrollment.schoolClass` in the parent composer.
- Inertia serializes enum casts to values; props use `->value` explicitly anyway (match existing controllers).
- `whereDate('date', $today)` with pinned `Date::setTestNow` — same idiom as board()/PresenceBoardTest.
- Pint strips "unused" imports — keep trait/use lines in the test.
- Zero-teacher-class path must render 200, not 403 (ClassAccess returns empty collection by design).

## Verification

- Full suite green (417 existing + ~18 new DashboardTest cases); pint/phpstan/tsc/build clean.
- Manual smoke on seeded DB (school day): admin `/dashboard` totals == `/presence-board` for the same moment; teacher1 sees 5A only; parent1 (Slamet) sees Ahmad (5A) + Dimas (5B) with today statuses; student1 card == `/my-attendance` today card; weekend pin/holiday shows the banner.
