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
| **Curricular subjects** | `subjects` → `class_subjects` | Master subject catalog and year-scoped teacher teaching assignments with KKM (spec 09) |
| **Assessments & grading** | `grade_categories` → `grades` | Course-level gradebook with weighted scoring and student progress tracking (spec 10) |
| **Digital report cards** | `report_periods` → `report_card_publications` | Semester report synthesis (grades + attendance + notes) with PDF generation and publication gating (spec 11) |
| **School announcements** | `announcements` | Role-targeted digital bulletin board with Markdown formatting and attachments (spec 12) |
| **Weekly timetable** | `timetable_slots` | Weekly class schedules mapping time slots to subjects, teachers, and rooms (spec 13) |
| **School fees & tuition** | `fee_types` → `bills` → `payments` | Fee catalog, automated monthly SPP/billing, cashier cash entry, bank transfer verification, receipts (spec 14) |

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
| 09 | [Subjects & Teaching Assignments](09-subjects.md) | Draft v1.0 (2026-09-25) — Ready for planning | Academic Foundation |
| 10 | [Student Assessment & Grading](10-grading.md) | Draft v1.0 (2026-09-25) — Ready for planning | Academic Evaluation |
| 11 | [Digital Report Cards](11-report-cards.md) | Draft v1.0 (2026-09-25) — Ready for planning | Academic Reporting |
| 12 | [School Announcements](12-announcements.md) | Draft v1.0 (2026-09-25) — Ready for planning | Campus Communication |
| 13 | [Class Timetables & Schedules](13-timetable.md) | Draft v1.0 (2026-09-25) — Ready for planning | Timetable & Schedule |
| 14 | [School Fees & Tuition](14-school-fees.md) | Draft v1.0 (2026-09-25) — Ready for planning | Student Billing & Cashier |

## Build Order & Roadmap

### Wave 1: Core Attendance System (Shipped ✅)
`01 (Auth) → 02 (Master Data & Enrollments) → 03 (Attendance Engine) → 04 (Excuses) → 05 (Notifications) → 06 (Reports) → 07 (Roll-over) → 08 (Dashboard)`

### Wave 2: Academic Teaching & Evaluation (Next Build Target 🎯)
`09 (Subjects & Assignments) → 10 (Grading & Gradebook) → 11 (Digital Report Cards & PDF)`

### Wave 3: Campus Communication, Timetables & Tuition (Supporting & Operations 💳)
`12 (Announcements & Bulletin Board) ─── 13 (Weekly Timetables) ─── 14 (School Fees & SPP Tuition)`

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
