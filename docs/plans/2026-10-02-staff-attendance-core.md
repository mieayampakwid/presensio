# Staff Attendance A — Employee Master, Scanner, Sweep & Daily Overview Implementation Plan

> **For implementer agents:** Spec: `docs/specs/16-staff-attendance.md` Decisions (employee master, working days, scanner, tap resolution, sweep, manual override), Requirements 1–3, ACs 16-01…06, 10 (scanner/override parts), 11. Leave/recap/self-service/dashboard are Plan "Staff Attendance B". Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** `employees` master (teachers become an extension), scanner support for employee cards/QR with the tap-resolution rules, the absent sweep, and the admin daily overview with manual override.

**Architecture:** One-shot migration of teacher profile fields to `employees` (teachers keep their `id`; no accessor shim — every reader/writer is rewritten in the same commit). A parallel `EmployeeScanService` mirrors `AttendanceScanService`'s structure (credential resolution shared via a small `CredentialResolver`), so student scanning stays untouched. The sweep is a new command, scheduled from `routes/console.php` like `attendance:mark-absences`.

**Prerequisites:** Plans 1 and 2 (role `staff`, audit log, `school_operational_days`).

**Blocking questions:** none. Tasks 1 and 2 must land in **one commit/branch state**: after Task 1's migration the app is red until Task 2's rewrite is done.

---

### Task 1: `employees` table + one-shot data migration

**Files:**

- Create: migrations `create_employees_table`; `move_teacher_profile_to_employees` (single migration: insert one employee per teacher copying `name`, `teacher_number`→`employee_number`, `phone_number`, `user_id`, `employment_type='permanent'`, `is_active=true`; add `teachers.employee_id` not-null unique FK; drop `teachers.name`, `teacher_number`, `phone_number`, `user_id`; `down()` restores the columns from the employee rows); `add_staff_settings_to_settings_table` (`staff_start_time` 07:00, `staff_end_time` 14:00, `staff_absent_sweep_time` 09:00); `app/Models/Employee.php`, `app/Enums/EmploymentType.php`, `database/factories/EmployeeFactory.php`
- Modify: `app/Models/Teacher.php` (`employee()` belongsTo; no delegating accessors), `database/factories/TeacherFactory.php` (creates its employee), `app/Models/User.php` (`employee()` hasOne; `teacher()` hasOneThrough employee → teacher)
- Test: `tests/Feature/Staff/EmployeeMigrationTest.php`, `tests/Unit/Models/{EmployeeTest,TeacherTest}.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Feature/Staff tests/Unit/Models`

- [ ] **Step 1:** AC-16-01: put the move logic in a static method on the migration class and test it directly: every teacher ends with exactly one employee with the same name/number/phone/user link and `classes.teacher_id` still resolves. Include a rollback test (`down()` restores values).
- [ ] **Step 2:** Do not run the whole suite yet; continue to Task 2.

### Task 2: Rewrite every teacher-profile reader and writer

**Files:**

- Modify: all usages found with `grep -rnE "teacher->(name|phone_number|teacher_number|user)|teachers\.(name|phone_number|teacher_number|user_id)|'phone_number'|'teacher_number'" app resources/js database tests` — at minimum `app/Http/Controllers/Teachers/TeacherController.php`, `app/Http/Requests/Teachers/{Store,Update}TeacherRequest.php`, `app/Services/UserProfileLinker.php`, `app/Services/Reports/*`, `app/Http/Controllers/Classes/SchoolClassController.php` (teacher select), `resources/js/pages/teachers/*`, `resources/js/pages/classes/class-form.tsx`, `database/seeders/*`, and their tests
- Test: existing `tests/Feature/teachers/*`, `tests/Feature/users/UserProfileLinkingTest.php`, `tests/Feature/classes/*`

**Dependencies:** Task 1 · **Verification:** full `php artisan test --compact` green, then `composer ci:check`

- [ ] **Step 1:** Teacher create/update writes the employee row (name, number, phone, user link) and the teacher row in one transaction. Teacher lists/selects read `employee.name` via eager load (`with('employee')`) to avoid N+1. Role `staff` links through `employees.user_id`.
- [ ] **Step 2:** Re-run the grep; zero hits left. Run the full suite and fix remaining failures before committing Tasks 1–2 together.

---

### Task 3: Employee management UI

**Files:**

- Create: `app/Http/Controllers/Employees/EmployeeController.php`, `app/Http/Requests/Employees/{Store,Update}EmployeeRequest.php`, `resources/js/pages/employees/{index,create,edit,employee-form}.tsx`, `routes/employees.php`
- Test: `tests/Feature/employees/EmployeeManagementTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/employees`

- [ ] **Step 1:** Fields per spec Req 1. `working_days` nullable array of ISO weekdays ⊆ operational days. "Is a teacher" toggle creates/removes the `teachers` row; removal 422 while referenced by `classes.teacher_id` or `class_subjects.teacher_id`. Linked user gets role `staff` when not a teacher (and cannot be a `student`).
- [ ] **Step 2:** The Teachers admin page now lists/edits through the same employee form partial; one source of truth.

### Task 4: RFID & QR for employees; scan_events/outcomes

**Files:**

- Create: migrations `add_employee_id_to_rfid_cards_table` (nullable FK + DB check or app-level rule that at most one of student/employee), `add_employee_id_to_scan_events_table`; `app/Services/Attendance/CredentialResolver.php`
- Modify: `app/Enums/ScanOutcome.php` (add `IgnoredInactive`, `CheckIn`, `CheckOut`, `AbsentUpgraded`, `IgnoredExcused` only if not already present — check the enum first), `app/Models/RfidCard.php`, `app/Http/Controllers/RfidCards/RfidCardController.php` + requests (assign to employee; AC-16-04 → 422 if card already on a student), `app/Services/Attendance/QrTokenService.php` (subject type claim), `app/Http/Controllers/Attendance/StudentQrController.php` → add `EmployeeQrController` (`GET /my-qr` for employee users; existing student `my-qr` URL stays a single route that branches on the authenticated subject)
- Test: `tests/Feature/rfid-cards/EmployeeCardTest.php`, `tests/Unit/Services/Attendance/QrTokenServiceTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/rfid-cards tests/Unit/Services/Attendance`

- [ ] **Step 1:** Token claim backwards compatible: tokens without a subject-type claim keep resolving as students (firmware/old QR pages unaffected).

### Task 5: Employee attendance table + tap resolution

**Files:**

- Create: migration `create_employee_attendances_table` (spec §Schema 3), `app/Models/EmployeeAttendance.php`, factory, `app/Enums/EmployeeAttendanceStatus.php`, `app/Services/Attendance/EmployeeScanService.php`, `app/Services/Attendance/EmployeeCalendar.php` (`isExpected(Employee, date)` = `isSchoolDay` ∧ working-day)
- Modify: `app/Http/Controllers/Api/AttendanceScanController.php` (branch on resolved subject; response shape unchanged, add additive `subject_type`), `app/Services/Attendance/ScanResult.php`
- Test: `tests/Feature/Staff/EmployeeScanTest.php`, `tests/Unit/Services/Attendance/EmployeeCalendarTest.php`

**Dependencies:** Tasks 1, 4 · **Verification:** `php artisan test --compact tests/Feature/Staff tests/Feature/Api` + existing student scan tests untouched

- [ ] **Step 1:** Implement the five-step resolution in this order (spec): inactive → `ignored_inactive` (AC-16-11, no record); excused statuses (`sick`,`leave`,`annual_leave`,`official_duty`) → `ignored_excused`; none/`absent` → check-in with `present` if `time ≤ staff_start_time` else `late` + `late_minutes` (AC-16-02: 06:50 present, 07:20 late 20) and outcome `check_in`/`absent_upgraded` (AC-16-06); within debounce → `ignored_debounce`; else set `checked_out_at` (latest tap wins) and `early_leave_minutes = max(0, staff_end_time - tap)` (AC-16-03: 13:30 → 30, 14:10 → 0).
- [ ] **Step 2:** Taps on non-expected days create a normal record (recap counts as extra later). Every tap writes a `scan_events` row with `employee_id`.
- [ ] **Step 3:** Student-scan regression tests remain unchanged and green. Note: AUDIT D-01 (alumni scan guard) is a student-path defect and is **not** fixed here.

### Task 6: Absent sweep command

**Files:**

- Create: `app/Console/Commands/MarkStaffAbsencesCommand.php` (`staff-attendance:mark-absences`), tests
- Modify: `routes/console.php` (daily at `staff_absent_sweep_time`, school timezone — same reading pattern as the student sweep)
- Test: `tests/Feature/Console/MarkStaffAbsencesCommandTest.php`

**Dependencies:** Task 5 · **Verification:** `php artisan test --compact tests/Feature/Console/MarkStaffAbsencesCommandTest.php`

- [ ] **Step 1:** AC-16-05: marks absent expected active employees with no record; skips part-timer whose `working_days` excludes the weekday; skips `non_school_days`; idempotent on re-run. `scan_method = null`, no `scan_events`.

### Task 7: Daily overview + manual override

**Files:**

- Create: `app/Http/Controllers/Staff/StaffAttendanceController.php` (`index`, `upsert`), `app/Http/Requests/Staff/UpsertStaffAttendanceRequest.php`, `resources/js/pages/staff/attendance.tsx`, `routes/staff.php`
- Test: `tests/Feature/Staff/StaffAttendanceOverviewTest.php`

**Dependencies:** Task 5 · **Verification:** `php artisan test --compact tests/Feature/Staff/StaffAttendanceOverviewTest.php`

- [ ] **Step 1:** `GET staff-attendance?date=` for admin and principal: expected employees with status, in/out, late/early minutes; filters not-yet-arrived / late / absent / on leave. "Tidak absen pulang" is derived (in without out on a past date).
- [ ] **Step 2:** `PUT` upsert for admin only: stamps `override_by_user_id`, `overridden_at`, `scan_method = manual_override`, audits (`created`/`updated`). Principal write → 403; teacher/staff reading others → 403 (AC-16-10 partial).

### Task 8: Close out

- [ ] `composer ci:check`. Propose spec 16 "partially shipped (A)". Record in the PR that `rfid_cards`/`scan_events` amendments to spec 03 are now implemented.
