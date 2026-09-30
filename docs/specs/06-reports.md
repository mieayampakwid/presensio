# 06 — Reports & Exports (Summaries, CSV Export & Live Presence Board)

Status: v1.5 — implemented 2026-09-20 (academic-year picker amendment 2026-09-21, amended 2026-09-25)

## Problem

School administrations, homeroom teachers, and counseling staff (Guru BK) require periodic attendance summaries over months and semesters for official regulatory compliance (Dinas Pendidikan), parent conferences, and early identification of at-risk students. Manually tallying attendance tallies from physical roll books or disjointed spreadsheets takes days of labor and frequently produces math discrepancies. Furthermore, during urgent campus situations (such as fire drills, natural disasters, or student truancy investigations), school principals cannot determine the real-time headcount of students physically present on campus.

## Goals

1. Deliver on-demand attendance summary analytics per student and per class across customizable date ranges (capped at 366 days, defaulting to the current calendar month).
2. Support historical accuracy across academic years: class reports feature an academic year selector and attribute records dynamically through date-effective enrollments (`classOn(date)`).
3. Compute standardized attendance rates according to Indonesian educational standards, with a configurable toggle to include or exclude excused absences (`sick` and `leave`) from the percentage denominator.
4. Export high-fidelity CSV files that mirror the on-screen tabular data, student details, daily status grids, and statistical rates.
5. Provide a real-time, auto-refreshing Live Presence Board designed for reception or administrative kiosks, computing live in-building headcounts (`checked_in - checked_out`) for evacuation safety.

## Non-goals

- Drag-and-drop custom visual report designers or ad-hoc query builders in v1.
- Native server-side PDF generation in v1 (CSV export and standard browser print stylesheets serve export needs; official PDF report cards are specified in spec 11).
- Long-term machine learning predictive models for truancy forecasting.
- Persistent caching layer for report queries (direct indexed database queries execute in milliseconds at single-school scale).
- Writing attendance modifications directly from report views (reports are strictly read-only).

## User Stories

- **As a homeroom teacher**, I want to view my class attendance grid for the past month, seeing each student's daily presence and their attendance percentage, so I can prepare for parent-teacher meetings.
- **As a school administrator**, I want to export the monthly class attendance summary to a CSV file formatted for import into regional education databases.
- **As a school counselor**, I want to view an individual student's annual attendance history across all months to evaluate a chronic absence pattern.
- **As a parent**, I want to view my child's attendance rate and total counts of present, absent, sick, and late days for the active semester.
- **As a principal during an emergency evacuation**, I want to check the Live Presence Board on a mobile device to see the exact number of students currently inside the school building.

## Decisions

- **Mathematical Attendance Rate Formulation**:
  - *Default Formula (Excused Excluded)*:
    $$\text{Rate} = \frac{\text{Present} + \text{Late}}{\text{Present} + \text{Late} + \text{Absent}} \times 100$$
    When the denominator is zero (e.g. all days were holidays or sick), the rate displays as `—` (dash) to prevent division by zero or misleading 0% ratings.
  - *Include-Excused Switch (Active)*:
    $$\text{Rate} = \frac{\text{Present} + \text{Late}}{\text{Present} + \text{Late} + \text{Absent} + \text{Sick} + \text{Leave}} \times 100$$
  - CSV exports dynamically follow the on-screen state of the include-excused toggle.
- **Date-Effective Enrollment Attribution**: Attendance records are attributed to classes based on the student's enrollment that was active on the date of the attendance record. If a student transferred from 5A to 5B on October 1st, their September records appear in 5A's report, and their October records appear in 5B's report.
- **Live Presence Board Architecture**:
  - Auto-refreshes every 30 to 60 seconds via lightweight client polling over today's attendance records.
  - *In-Building Headcount* = Total students with `checked_in_at IS NOT NULL` and `checked_out_at IS NULL`.
- **Pure Derived Query Views**: No dedicated report tables are created. Reports are computed via efficient SQL aggregations over `attendances`, `enrollments`, `classes`, and `students`.
- **Strict Role-Based Scoping**:
  - `admin`: Full access to all classes, all academic years, and all students.
  - `teacher`: Scoped to homeroom classes assigned to them in the selected academic year.
  - `parent`: Scoped strictly to their linked children.
  - `student`: Scoped strictly to their own personal records.

## Requirements

1. **Student Individual Report (`GET /reports/student/{id}`)**:
   - Filter by date range: `start_date` and `end_date` (max 366 days).
   - Summary statistics card: Total Present, Total Late, Total Sick, Total Leave, Total Absent, and Computed Attendance Rate.
   - Chronological table of daily records showing Date, Day of Week, Status Badge, Check-in Time, Check-out Time, and Method.
   - Weekends and dates in `non_school_days` without records are visually distinct or omitted from calculation.
2. **Class Group Report (`GET /reports/class/{id}`)**:
   - Academic Year picker; lists classes belonging to the selected year.
   - Date range selector (defaults to current month).
   - Matrix Grid view: rows represent enrolled students; columns represent dates in range.
   - Cells render status indicators: `H` (Hadir), `T` (Terlambat), `S` (Sakit), `I` (Izin), `A` (Alpa).
   - Non-school days and weekends render greyed-out columns.
   - Summary columns on the right: per-status counts and individual attendance rate.
3. **CSV Export Service**:
   - Endpoints: `GET /reports/student/{id}/export` and `GET /reports/class/{id}/export`.
   - Generates RFC 4180 compliant CSV files with UTF-8 BOM encoding for seamless Microsoft Excel compatibility.
   - Filename convention: `presensio-report-{class_or_student}-{start_date}-to-{end_date}.csv`.
4. **Live Presence Board (`GET /presence-board`)**:
   - Accessible by Admin and Teachers (homeroom scoped for teachers; route name `presence-board.index`).
   - Top banner metrics (`totals`): Total Enrolled, Currently In-Building, Total Checked-In, Not Yet Arrived, Total Checked-Out (computed and displayed for Admins; teachers see their assigned class summaries).
   - Per-class breakdown table: Class Name, Enrolled Count, In-Building Count, Absent/Unrecorded Count (`not_checked_in`).
   - Interactive modal / drawer drill-down: clicking an unrecorded count reveals the names of students who have not yet checked in today.
   - Auto-refreshes data every 30 seconds via partial page reload.

## Schema

### Pure Derived Query Engine (No New Tables)

This specification creates no new database tables. It executes read-only aggregations over the existing schema:

```
attendances (spec 03)
     │
     └───► enrollments (spec 02) ───► classes (spec 02) ───► academic_years (spec 02)
     │
     └───► students (spec 02)
```

**Query Contract (Class Report Aggregation Shape)**:
```json
{
  "academic_year": { "id": 1, "name": "2026/2027" },
  "class": { "id": 4, "name": "5A" },
  "date_range": { "start": "2026-09-01", "end": "2026-09-30" },
  "include_excused": false,
  "students": [
    {
      "id": 12,
      "full_name": "Ahmad Dahlan",
      "student_number": "2026001",
      "counts": { "present": 18, "late": 2, "sick": 1, "leave": 0, "absent": 1 },
      "attendance_rate": 95.2,
      "daily_records": { "2026-09-01": "present", "2026-09-02": "late" }
    }
  ]
}
```

## Acceptance Criteria

- **AC-06-01**: Given a student with 18 present, 2 late, 1 absent, 1 sick, and 0 leave records, when the include-excused toggle is off, the attendance rate is $(18 + 2) / (18 + 2 + 1) \times 100 = 95.2\%$.
- **AC-06-02**: Given the same student, when the include-excused toggle is on, the rate is $(18 + 2) / (18 + 2 + 1 + 1 + 0) \times 100 = 90.9\%$.
- **AC-06-03**: Given a student who transferred from Class 5A to Class 5B on September 15, generating Class 5A's report for September 1 to September 30 attributes the student's records up to September 14 to Class 5A and omits subsequent dates.
- **AC-06-04**: Downloading the CSV export produces a valid UTF-8 file with BOM that opens directly in Microsoft Excel with Indonesian characters intact.
- **AC-06-05**: When viewing the Live Presence Board, if 200 students are enrolled, 150 tapped in, and 10 tapped out, the In-Building Headcount displays exactly 140.
- **AC-06-06**: A teacher attempting to view the class report of a class they do not manage receives HTTP 403 Forbidden.

## Constraints & Assumptions

- Date ranges requested by clients are strictly capped at 366 calendar days to prevent database memory exhaustion.
- Single-school scale: aggregated queries run over <= 1,000,000 attendance records with indexed queries completing in < 100ms.

## Open Questions

- `[NEEDS DECISION: Kiosk Display Dedicated Account]`: Should the school reception TV running the Live Presence Board authenticate with a dedicated read-only `display` role user account to prevent session timeout? (Recommended for v1.1).
