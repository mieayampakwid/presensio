# Presensio — Spec Index

Single-school information & attendance system. Each numbered spec is a decision record: the current document describes the system **as it should be now**; superseded decisions stay visible in the per-spec version history (see each file's `Status:` line). Implementation status lives in the table below and is updated as specs ship.

## Domain model (who owns the truth)

| Concept | Owner | Notes |
|---|---|---|
| Login & roles | `users` | Auth only — canonical schema in spec 01; no domain profile data |
| People | `teachers`, `guardians`, `students` | Master profiles, optionally linked 1:1 to a user account (spec 02) |
| **Class membership** | **`enrollments`** | The only source of truth. A student's class on any date is derived (`classOn(date)`); one open enrollment per student. No class pointer on students, no class stamps on attendance (spec 02 v2.0 / 08) |
| Academic structure | `academic_years` → `classes` | Classes are year-scoped instances; past years are immutable history (spec 02 v2.0 / 07) |
| Daily attendance | `attendances` | Mutable per-day projection, keyed `(student_id, date)`; class attribution derived from enrollment as-of the record's date (spec 03 / 08) |
| Scan audit | `scan_events` | Append-only, never updated or deleted (spec 03) |
| School calendar | `non_school_days`, settings row | Feed-synced national holidays + manual layer (spec 03) |
| Excuses | `excuses` | Guardian-submitted, admin-approved; approval writes `sick`/`leave` records (spec 04) |
| Notifications | `absence_notifications` | Idempotent ledger for guardian absence alerts (spec 05) |
| Reports | — | Read-only views over attendances and enrollments; CSV mirrors the screen (spec 06) |
| Role landing | — | Composite read-only landing payload per role (spec 08) |
| **Roles & permissions** | `user_roles` | Multiple roles per user, code-defined permission matrix (spec 15) |
| **Terms & class levels** | `semesters`, `classes.grade_level` / `curriculum` | Two semesters per academic year; curriculum chosen per class for staged transitions (spec 15) |
| **School profile & audit** | `settings`, `audit_logs` | School identity for documents; append-only change history (spec 15) |
| **Curricular subjects** | `subjects` → `class_subjects` | Subject catalog and year-scoped teaching assignments with passing threshold / KKTP (spec 09) |
| **Assessments & grading** | `learning_objectives`, `assessments` → `scores` → `subject_results` | Semester gradebook, Kurikulum Merdeka assessment kinds, TP-based descriptions, finalization (spec 10) |
| **Digital report cards** | `report_card_publications` → `report_cards` | Versioned immutable semester snapshots with PDF and publication gating (spec 11) |
| **School announcements** | `announcements` → `announcement_receipts` | Bulletin board targeted by role, grade level or class (resolved via enrollments), with read/acknowledgement tracking (spec 12) |
| **Weekly timetable** | `bell_schedules` → `lesson_periods`, `timetable_slots` | School bell schedule with day variants; per-semester class timetables bound to periods (spec 13) |
| **School fees & tuition** | `fee_types` → `bills` ← `payment_allocations` → `payments` | Fee catalog, discounts, bulk billing, cash and verified transfers, derived bill status, void-only corrections, receipts (spec 14) |
| **General ledger** | `accounts` → `journal_entries` → `journal_lines`, `accounting_periods` | Double-entry, accrual basis; every fee event posts a balanced journal; manual journals, trial balance, receivables reconciliation, period closing (spec 14) |
| **Notification center** | `notifications`, `notification_deliveries`, `notification_preferences` | In-app inbox plus WhatsApp/email per type, idempotent ledger, quota, quiet hours, opt-out; absence alerts stay on spec 05 (spec 17) |
| **Employees & staff attendance** | `employees` ← `teachers`; `employee_attendances`, `employee_leave_requests` | Teacher and tendik master; scanner check-in/out, lateness, early leave, leave requests, monthly recap (spec 16) |
| **Interface language** | `users.locale`, `lang/{en,id}` | Per-user locale (id default), strings in Laravel lang files shared to React; user-entered data and stored enum values stay untranslated (spec 18) |
| **In-app QR scanner** | `scanner_operator` role, `POST /scanner/scan` | Browser webcam scanner for Dynamic QR; dedicated operator role, reuses scan pipeline (spec 19) |
| **Work shifts** | `shifts`, `shift_roster` | Named shift templates, per-employee default and daily roster, cross-midnight, per-shift sweep (spec 20) |

## Specs

| # | Spec | Status | Scope |
|---|---|---|---|
| 01 | [Authentication & Roles](01-auth.md) | Implemented 2026-09-19 (amended 2026-09-25) | Core Auth |
| 02 | [People, Classes & Enrollment](02-people-classes.md) | Implemented 2026-09-19 (v2.0 enrollment 2026-09-21, amended 2026-09-25) | Master Data |
| 03 | [Attendance Recording](03-attendance.md) | Implemented 2026-09-19 (v2.5 sweep 2026-09-21, amended 2026-09-25) | IoT & Core Engine |
| 04 | [Absence Excuses](04-excuses.md) | Implemented 2026-09-20 (amended 2026-09-25) | Guardian Portal |
| 05 | [Absence Notifications](05-notifications.md) | Implemented 2026-09-20 (amended 2026-09-25) | Queue & Alerts |
| 06 | [Reports & Exports](06-reports.md) | Implemented 2026-09-20 (v1.5 year picker 2026-09-21, amended 2026-09-25) | Reporting |
| 07 | [Academic Roll-over & Historical Attribution](07-academic-years.md) | Implemented 2026-09-21 (amended 2026-09-25) | Academic Lifecycle |
| 08 | [Dashboard (Role Landing)](08-dashboard.md) | Implemented 2026-09-21 (amended 2026-09-25) | Portal Landing |
| 09 | [Subjects & Teaching Assignments](09-subjects.md) | Draft v1.1 (2026-10-01) — Ready for planning after 15 | Academic Foundation |
| 10 | [Student Assessment & Grading](10-grading.md) | Draft v1.1 (2026-10-01) — Ready for planning after 15 | Academic Evaluation |
| 11 | [Digital Report Cards](11-report-cards.md) | Draft v1.1 (2026-10-01) — Ready for planning after 15 | Academic Reporting |
| 12 | [School Announcements](12-announcements.md) | Draft v1.1 (2026-10-01) — Ready for planning after 15 | Campus Communication |
| 13 | [Class Timetables & Schedules](13-timetable.md) | Draft v1.1 (2026-10-01) — Ready for planning after 15 | Timetable & Schedule |
| 14 | [School Fees, Receivables & General Ledger](14-school-fees.md) | Draft v2.0 (2026-10-01) — Ready for planning after 15 | Billing, Cashier & Accounting |
| 15 | [Academic Foundation](15-foundation.md) | Draft v1.0 (2026-10-01) — Ready for planning | Roles, Semesters, Class Levels, School Profile, Audit |
| 16 | [Employee Master & Staff Attendance](16-staff-attendance.md) | Draft v1.0 (2026-10-01) — Ready for planning after 15 | Teacher & Tendik Attendance |
| 17 | [Notification Center](17-notifications-center.md) | Draft v1.0 (2026-10-01) — Ready for planning after 15 | In-App, WhatsApp & Email Notifications |
| 18 | [Internationalization (id/en)](18-i18n.md) | Draft v1.0 (2026-10-02) — infrastructure + users pages implemented | UI language, per-user locale |
| 19 | [In-App QR Scanner](19-in-app-scanner.md) | Draft v1.0 (2026-10-05) — Ready for planning after 15 | Webcam Scanner & Operator Role |
| 20 | [Work Shifts](20-work-shifts.md) | Draft v1.0 (2026-10-05) — Ready for planning after 16 | Shift Scheduling & Per-Shift Sweep |

> **Open audit:** [AUDIT-2026-10-01](AUDIT-2026-10-01.md) — drift in 03/04/05/07 resolved in the specs (code follow-ups listed there); 09–14 revised against spec 15.

## Build Order & Roadmap

### Wave 1: Core Attendance System (Shipped ✅)
`01 (Auth) → 02 (Master Data & Enrollments) → 03 (Attendance Engine) → 04 (Excuses) → 05 (Notifications) → 06 (Reports) → 07 (Roll-over) → 08 (Dashboard)`

### Wave 2: Academic Teaching & Evaluation (Next Build Target 🎯)
`15 (Foundation) → 09 (Subjects & Assignments) → 10 (Grading & Gradebook) → 11 (Digital Report Cards & PDF)`

Spec 15 is numbered after 14 to avoid renumbering, but is built first. Spec 16 (staff attendance) depends only on 15 and can run in parallel with 09–11. Spec 17 (notification center) depends on 15 and should land before 11/12/14 ship, since they dispatch through it.

Spec 19 (in-app scanner) depends on 15 (new role) and can run in parallel with 16. Spec 20 (work shifts) depends on 16 (employee attendance engine).

### Wave 3: Campus Communication, Timetables & Tuition (Supporting & Operations 💳)
`12 (Announcements & Bulletin Board) ─── 13 (Weekly Timetables) ─── 14 (School Fees, Receivables & General Ledger)`

---

## Candidates for v2 (Out of Scope for v1)

The following capabilities are recognized as valuable school operations features but are explicitly deferred to Presensio v2.x to preserve focus on core attendance, academic essentials, and fee collections:

1. **Automated Payment Gateway (Midtrans/Xendit/QRIS callback)**: Direct API integration for instant online settlement without manual slip verification.
2. **Per-Period / Subject-Level Attendance**: Attendance taken at each class period by the subject teacher (will bind `attendances` to `timetable_slots.id` from spec 13).
3. **Kemendikbud e-Rapor & Dapodik Integration**: Bi-directional sync with the Ministry of Education's centralized data systems.
4. **Computer-Based Testing (CBT)**: Online exam taking, timed quizzes, and automatic grading.
5. **Extracurricular Activities (Ekskul)**: Activity registration, attendance, and evaluation.
6. **Counseling & Behavioral Tracking (Guru BK)**: Student violation points, counseling meeting logs, and confidential notes.
7. **Expense & Payroll Management**: Operational cash disbursements and teacher salary/honorarium calculations.
8. **Alumni Portal & Tracer Study**: Graduate directory, university admission tracking, and alumni community.
9. **Claim-Based Guardian Registration**: Parents onboarding via student-specific claim invite codes and WhatsApp OTP instead of manual admin account creation.
