# 06 — Reports & Exports

Status: draft v1.2 (2026-09-18)

## Purpose

Attendance summaries over a date range, per student and per class, with CSV export.

## Decisions

- **Attendance rate** = (`present` + `late`) / total records × 100. `sick and leave` is shown as its own count and does not count against the rate. (If the school's official definition differs, change the formula here before building.)
- Date ranges are capped at 366 days; defaults to the current month.
- Access follows spec 01 scoping: admin → all classes, teacher → own class, parent/student → own records.

## Requirements

1. **Student report**: pick student (search by `full_name` or `student_number`) + range → per-status counts, attendance rate, and the day-by-day list of records.
2. **Class report**: pick class + range → per-status counts and rate per student, plus a students × dates grid showing each day's status.
3. Both reports export to CSV matching what the screen shows (grid and summary).
4. Weekends and non-school days (Spec 03 calendar) appear greyed/no-cell in the grid; days without a record show as blank (not counted in rates).
5. Reports are read-only views; no caching layer in v1 — plain queries are fine at single-school scale.

## Out of scope

- PDF export, printable letters
- Custom report builder, saved reports
- School-wide aggregated dashboard/analytics