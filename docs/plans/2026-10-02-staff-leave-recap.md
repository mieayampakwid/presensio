# Staff Attendance B — Leave Requests, Monthly Recap, Self-Service & Dashboard Implementation Plan

> **For implementer agents:** Spec: `docs/specs/16-staff-attendance.md` Leave requests decision, Requirements 4–7, ACs 16-07…10. Pattern reference: spec 04 excuses (`app/Services/Attendance/ExcuseApprovalService.php`, `app/Http/Controllers/Excuses/*`). Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Employee leave requests with principal/admin review, monthly recap with CSV, self-service pages, and dashboard widgets.

**Architecture:** `LeaveApprovalService` mirrors `ExcuseApprovalService`: approval upserts `employee_attendances` for _expected_ days only. Recap is a read service (`StaffRecapService`) over `employee_attendances` + `EmployeeCalendar`, reusing the CSV writer conventions of spec 06 (`openspout`, UTF-8 BOM).

**Prerequisites:** Staff Attendance A merged. Notifications (types `leave_request_submitted/reviewed`, `staff_absence_digest`) are wired in the last task and need plan 4.

**Blocking questions:** none.

---

### Task 1: Leave schema + submission + cancellation

**Files:**

- Create: migration `create_employee_leave_requests_table` (spec §Schema 4), `app/Models/EmployeeLeaveRequest.php`, factory, `app/Enums/LeaveType.php`, `app/Enums/LeaveRequestStatus.php`; `app/Http/Controllers/Staff/MyLeaveController.php` (`index`, `store`, `destroy`), `app/Http/Requests/Staff/StoreLeaveRequest.php`, `resources/js/pages/staff/my-leave.tsx`
- Modify: `routes/staff.php` (`GET/POST my-leave`, `DELETE my-leave/{id}`)
- Test: `tests/Feature/Staff/MyLeaveTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Feature/Staff/MyLeaveTest.php`

- [ ] **Step 1:** Request: type enum, `end_date ≥ start_date`, `reason` required, optional attachment (PDF/JPG/PNG, ≤5 MB, private disk like `Excuses/ExcuseAttachmentController`). Overlap with another `pending` or `approved` request of the same employee → 422 (AC-16-08).
- [ ] **Step 2:** Cancel only while `pending` (`cancelled`); approved → 422. Available to any user linked to an employee (any role, per spec 15 note); non-employee → 403.

### Task 2: Review and approval writes

**Files:**

- Create: `app/Services/Attendance/LeaveApprovalService.php`, `app/Http/Controllers/Staff/LeaveReviewController.php` (`index`, `approve`, `reject`), `app/Http/Requests/Staff/{Approve,Reject}LeaveRequest.php`, `resources/js/pages/staff/leave-review.tsx`
- Modify: `routes/staff.php` (`GET staff-leave`, `PUT …/{id}/approve|reject`)
- Test: `tests/Feature/Staff/LeaveReviewTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/Staff/LeaveReviewTest.php`

- [ ] **Step 1:** Reviewers: admin and principal. `reject` requires note. `approve` in one transaction: for every date in range that is _expected_ (`EmployeeCalendar::isExpected`), upsert `employee_attendances` with status = leave type (`sick`/`leave`/`annual_leave`/`official_duty`); an existing `present/late` record on that day is left alone and reported. AC-16-07: 3-day annual leave over a weekend writes only expected days, and a tap on those days returns `ignored_excused`.
- [ ] **Step 2:** AC-16-10: `staff`-role user approving → 403; principal approving → 200. Audit decision (`approved`/`rejected`).

### Task 3: Monthly recap + CSV

**Files:**

- Create: `app/Services/Staff/StaffRecapService.php`, `app/Http/Controllers/Staff/StaffRecapController.php` (`index`, `export`), `resources/js/pages/staff/recap.tsx`
- Modify: `routes/staff.php` (`GET staff-attendance/recap?month=`, `…/export`)
- Test: `tests/Unit/Services/Staff/StaffRecapServiceTest.php`, `tests/Feature/Staff/StaffRecapTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Unit/Services/Staff tests/Feature/Staff/StaffRecapTest.php`

- [ ] **Step 1:** Metrics per employee: expected days, extra days (taps on non-expected days, not added to expected), hadir (present+late), terlambat count+minutes, pulang cepat count+minutes, tidak absen pulang, sakit, izin, cuti, dinas luar, alpa; rate = (hadir + dinas luar) / expected. AC-16-09 exact fixture: 20 expected, 17 present (2 late, 25 min), 1 dinas luar, 1 sakit, 1 alpa → 90%.
- [ ] **Step 2:** Admin and principal only. CSV: UTF-8 BOM, same column order as the screen (look at `app/Services/Reports` exporter for the existing writer and reuse it rather than writing a new one).

### Task 4: Self-service page and dashboard widgets

**Files:**

- Create: `app/Http/Controllers/Staff/MyAttendanceController.php` (`GET my-attendance` for employees; today card + monthly history), `resources/js/pages/staff/my-attendance.tsx`
- Modify: `app/Http/Controllers/Dashboard/DashboardController.php` (admin/principal: staff totals + not-yet-arrived **teachers** with first period today only when plan 11 timetable exists — `class_exists(TimetableSlot::class)` is not allowed; implement the first-period lookup behind a `FirstPeriodLookup` interface bound to a null implementation now, replaced in plan 11 Task 6; employee: own today card), `resources/js/pages/dashboard.tsx`
- Test: `tests/Feature/DashboardTest.php`, `tests/Feature/Staff/MyAttendanceTest.php`

**Dependencies:** Task 2, Staff A · **Verification:** `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/Staff`

- [ ] **Step 1:** Privacy: an employee sees only own rows; teacher requesting another employee's → 403 (AC-16-10). Widget numbers: expected, arrived, late, not yet arrived, on leave.

### Task 5: Notifications wiring (needs plan 4)

**Files:**

- Modify: `MyLeaveController::store` (`leave_request_submitted` → principals + admins), `LeaveReviewController` (`leave_request_reviewed` → employee), `app/Console/Commands/MarkStaffAbsencesCommand.php` (`staff_absence_digest` → principals after the sweep, one per day: dedupe `staff_absence_digest:{date}:{user}`)
- Test: `tests/Feature/Notifications/StaffNotificationTest.php`

**Dependencies:** Tasks 1–2, Plan 4 · **Verification:** `php artisan test --compact tests/Feature/Notifications/StaffNotificationTest.php`

- [ ] **Step 1:** Payloads carry no reason text (AC-17-10).

### Task 6: Close out

- [ ] `composer ci:check`. Propose spec 16 → implemented.
