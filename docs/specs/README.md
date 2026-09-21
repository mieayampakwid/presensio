# Presensio — Spec Index

Single-school attendance system. Each numbered spec is a decision record: the current document describes the system **as it should be now**; superseded decisions stay visible in the per-spec version history (see each file's `Status:` line). Implementation status lives in the table below and is updated as specs ship.

## Domain model (who owns the truth)

| Concept | Owner | Notes |
|---|---|---|
| Login & roles | `users` | Auth only — no profile data (spec 01) |
| People | `teachers`, `guardians`, `students` | Master profiles, optionally linked 1:1 to a user (spec 02) |
| **Class membership** | **`enrollments`** | The only source of truth. A student's class on any date is derived (`classOn(date)`); one open enrollment per student. No class pointer on students, no class stamps on attendance (spec 02 v2.0 / 08) |
| Academic structure | `academic_years` → `classes` | Classes are year-scoped instances; past years are immutable history (spec 02 v2.0) |
| Daily attendance | `attendances` | Mutable per-day projection, keyed `(student_id, date)`; class attribution derived from enrollment as-of the record's date (spec 03 / 08) |
| Scan audit | `scan_events` | Append-only, never updated or deleted (spec 03) |
| School calendar | `non_school_days`, settings row | Feed-synced + manual layer (spec 03) |
| Excuses | `excuses` | Guardian-submitted, admin-approved; approval writes `sick`/`leave` records (spec 04) |
| Notifications | `absence_notifications` | Ledger for guardian absence alerts (spec 05) |
| Reports | — | Read-only views over the above; CSV mirrors the screen (spec 06) |

## Specs

| # | Spec | Status |
|---|---|---|
| 01 | [Authentication & Roles](01-auth.md) | Implemented 2026-09-19 |
| 02 | [People, Classes & Enrollment](02-people-classes.md) | Implemented 2026-09-19; **v2.0 (enrollment model) implemented 2026-09-21** |
| 03 | [Attendance Recording](03-attendance.md) | Implemented 2026-09-19; v2.5 amendment (sweep population) implemented 2026-09-21 |
| 04 | [Absence Excuses](04-excuses.md) | Implemented 2026-09-20 |
| 05 | [Absence Notifications](05-notifications.md) | Implemented 2026-09-20 |
| 06 | [Reports & Exports](06-reports.md) | Implemented 2026-09-20; v1.5 amendment (year picker) implemented 2026-09-21 |
| 07 | [Academic Roll-over & Historical Attribution](07-academic-years.md) | Implemented 2026-09-21 |
| 08 | [Dashboard (Role Landing)](08-dashboard.md) | Implemented 2026-09-21 |

## Build order

`01 → 02 → 03 → 04 → 05 → 06 → 07 → 08` shipped · next: `deploy/pilot`
