# Class Timetables Implementation Plan

> **For implementer agents:** Spec: `docs/specs/13-timetable.md` (ACs 13-01…10). Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Bell schedules with day variants, per-semester class timetables bound to periods with conflict handling, weekly views, teacher schedule, and "Jadwal Hari Ini" on the dashboard.

**Architecture:** `BellSchedule` → `LessonPeriod` is the only source of times. `TimetableValidator` is a pure-ish service (overlap = hard error, teacher/room clash = warning list) used by the editor. A `TodaySchedule` service resolves who sees which slots through enrollments and the current semester.

**Prerequisites:** Plan 2 (semesters, `school_operational_days`), Plan 3 (`class_subjects`).

**Blocking questions:** none.

---

### Task 1: Bell schedule + periods

**Files:**

- Create: migrations `create_bell_schedules_table`, `create_lesson_periods_table` (spec §Schema 1–2); models `BellSchedule`, `LessonPeriod`, factories; `app/Enums/PeriodKind.php`; `app/Http/Controllers/Timetable/BellScheduleController.php`, `app/Http/Requests/Timetable/SaveBellScheduleRequest.php`, `resources/js/pages/timetable/bell-schedules.tsx`, `routes/timetable.php`
- Modify: `app/Models/LessonPeriod.php` (`isInUse()` via slots; later), `routes/web.php`
- Test: `tests/Feature/timetable/BellScheduleTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Feature/timetable/BellScheduleTest.php`

- [ ] **Step 1:** Validation: across all schedules every operational weekday (`SchoolSettings::operationalWeekdays()`) is covered exactly once (AC-13-01: uncovered or double → 422); within a schedule periods are ordered, non-overlapping, `start < end`, lessons have `number`, breaks have `name`.
- [ ] **Step 2:** Period delete guard 422 when used by slots (stubbed to false until Task 2; Task 2 updates). Admin only.
- [ ] **Step 3:** AC-13-06 is satisfied structurally (times live only here); test it in Task 4.

### Task 2: Slots + validator

**Files:**

- Create: migration `create_timetable_slots_table` (spec §Schema 3); `app/Models/TimetableSlot.php`, factory; `app/Services/Timetable/TimetableValidator.php`; `app/Http/Controllers/Timetable/ClassTimetableController.php` (editor + CRUD), `app/Http/Requests/Timetable/SaveSlotRequest.php`
- Modify: `app/Models/ClassSubject.php::isInUse()` (add `timetableSlots()->exists()`), `LessonPeriod::isInUse()`
- Test: `tests/Unit/Services/Timetable/TimetableValidatorTest.php`, `tests/Feature/timetable/SlotTest.php`

**Dependencies:** Task 1, Plan 3 · **Verification:** `php artisan test --compact tests/Unit/Services/Timetable tests/Feature/timetable/SlotTest.php`

- [ ] **Step 1:** Validator tests from ACs: AC-13-02 overlap in same class+semester+day (considering `period_count`) → error; AC-13-03 span crossing a break → error; a day that is not operational → error; AC-13-04 same teacher (via `class_subjects.teacher_id`) or same non-empty `room` in another class at overlapping periods → warning (not error); `confirm_conflicts = true` saves.
- [ ] **Step 2:** Request: exactly one of `class_subject_id` / `activity_name`; the `class_subject_id` must belong to the same class; starting period of kind `lesson` and the span's lesson periods all exist in that day's bell schedule. Warning response is HTTP 409 with `conflicts: [{type, message}]` and the Indonesian text from the spec; UI shows a confirm modal and resubmits with `confirm_conflicts`.
- [ ] **Step 3:** Activity slot has no teacher (AC-13-05, assert in the view model test later).

### Task 3: Editor grid + copy from previous semester

**Files:**

- Create: `app/Services/Timetable/TimetableCopier.php`, `resources/js/pages/timetable/editor.tsx`
- Modify: `ClassTimetableController` (`copy`), `routes/timetable.php` (`GET classes/{school_class}/timetable/edit?semester=`, `POST …/copy`)
- Test: `tests/Feature/timetable/TimetableCopyTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/timetable/TimetableCopyTest.php`

- [ ] **Step 1:** Copy skips slots whose `class_subjects` row no longer exists and returns the skipped list (AC-13-07); never overwrites an existing slot at the same position (reports as skipped). Grid: operational days × that day's periods, breaks shaded, drag-free (form modal per cell) to keep scope small.
- [ ] **Step 2:** Classes of a past year are read-only (consistent with plan 3).

### Task 4: Weekly view + teacher schedule

**Files:**

- Create: `app/Services/Timetable/TimetableView.php` (derives times from periods), `app/Http/Controllers/Timetable/ClassTimetableViewController.php` (`GET /classes/{school_class}/timetable?semester=`), `app/Http/Controllers/Teacher/ScheduleController.php`, `resources/js/pages/timetable/week.tsx` (print CSS), `resources/js/pages/teacher/schedule.tsx`
- Test: `tests/Feature/timetable/TimetableViewTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/timetable/TimetableViewTest.php`

- [ ] **Step 1:** Access: admin/principal all; teacher classes in scope (`academicClassIds`); student own, guardian child's current class; others 403. AC-13-06: change Jam ke-1 to 07:15–07:50 → every class's Monday–Thursday view shows it.
- [ ] **Step 2:** `schedule`: current-semester slots of the teacher's `class_subjects` grouped by day, current/next highlighted (AC-13-09: Budi's 5A and 6A).

### Task 5: Today's schedule + dashboard block

**Files:**

- Create: `app/Services/Timetable/TodaySchedule.php`
- Modify: `DashboardController` + `resources/js/pages/dashboard.tsx` (student/guardian per-child blocks, current/next highlight)
- Test: `tests/Unit/Services/Timetable/TodayScheduleTest.php`, `tests/Feature/DashboardTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Unit/Services/Timetable tests/Feature/DashboardTest.php`

- [ ] **Step 1:** Student → `classOn(today)`; guardian → each child's; current semester via `SemesterService::current()`; not a school day (`isSchoolDay`, includes `non_school_days` and non-operational weekday) → empty with "Tidak ada jadwal pelajaran hari ini." (AC-13-10). AC-13-08: student moved 5A → 5B yesterday sees 5B.

### Task 6: Staff dashboard first-period hook

**Files:**

- Modify: bind the real `FirstPeriodLookup` (introduced in plan 9 Task 4 as a null object) in `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Staff/DashboardFirstPeriodTest.php`

**Dependencies:** Task 4; Plan 9 · **Verification:** `php artisan test --compact tests/Feature/Staff`

- [ ] **Step 1:** Skip this task if plan 9 has not landed; it is one binding plus one test.

### Task 7: Close out

- [ ] `composer ci:check`; propose spec 13 → implemented. Note: slots are never hard-deleted once per-period attendance exists (v1.x) — no change needed now.
