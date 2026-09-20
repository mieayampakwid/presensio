# 06 — Reports & Exports

Status: draft v1.4 (2026-09-20)

## Purpose

Attendance summaries over a date range, per student and per class, with CSV export — plus a live presence board for the current day.

## Decisions

- **Attendance rate** = (`present` + `late`) / (`present` + `late` + `absent`) × 100 — sick/leave are excluded from the denominator by default (locked 2026-09-20: "does not count against the rate" wins over the literal "total records"; rate shows `—` when the denominator is 0). Reports carry an **include-excused switch** (default off): when on, sick + leave enter the denominator. Counts always show regardless; CSV mirrors the switch state.
- Date ranges are capped at 366 days; defaults to the current month.
- Access follows spec 01 scoping: admin → all classes, teacher → own class, parent/student → own records.
- **Live Presence Board**: the school's shop-window screen (piket room / headmaster) showing the current day as it happens — built for management decisions in minutes, not month-end. Plain polling (30–60s auto-refresh), no websockets; it is a view over scan-engine data (Spec 03), so near-zero build cost. Its "in-building" count doubles as the evacuation headcount.

## Requirements

1. **Student report**: pick student (search by `full_name` or `student_number`) + range → per-status counts, attendance rate, and the day-by-day list of records.
2. **Class report**: pick class + range → per-status counts and rate per student, plus a students × dates grid showing each day's status.
3. Both reports export to CSV matching what the screen shows (grid and summary).
4. Weekends and non-school days (Spec 03 calendar) appear greyed/no-cell in the grid; days without a record show as blank (not counted in rates).
5. Reports are read-only views; no caching layer in v1 — plain queries are fine at single-school scale.
6. **Live Presence Board (today)**:
   - Auto-refreshes every 30–60s over today's records: per class, counts of checked-in / not-yet-checked-in / checked-out, with school-wide totals for admins.
   - Drill-down per class lists students not yet checked in (with today's status, including `sick`/`leave`); *in-building* = checked-in minus checked-out — the evacuation headcount.
   - Read-only; triggers no notifications. Access: admin → all classes, teacher → own class(es).

## Out of scope

- PDF export, printable letters
- Custom report builder, saved reports
- Trend/analytics dashboards (charts, absence-pattern mining — beyond per-range reports and the live board)