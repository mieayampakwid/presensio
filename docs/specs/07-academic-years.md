# 07 — Academic Roll-over & Historical Attribution

Status: v1.1 — implemented 2026-09-21 (amended 2026-09-25)

## Problem

Schools operate on cyclical annual rhythms: cohorts promote simultaneously each July (e.g. Class 1A becomes 2A), and the senior cohort graduates as alumni. Naive administrative software typically executes roll-overs through destructive in-place updates (such as updating student class pointers or renaming existing class records). This pattern permanently corrupts historical auditability: querying historical attendance from two years prior either crashes or displays current student rosters instead of the children who were actually enrolled at that time. Furthermore, if graduated alumni are not formally decoupled from active operational routines, automated scanners and morning absence sweeps mistakenly flag alumni as truant, sending false alerts to families of former students.

## Goals

1. Provide an administrative roll-over workflow that transitions the school into a new academic year in a single atomic operation without mutating historical records.
2. Establish clean domain semantics for Alumni (graduated) and departed students: they retain full historical visibility in past reports, but are automatically excluded from all daily operational surfaces (scanners, sweeps, live boards, and today dashboards).
3. Ensure date-effective attribution across all queries: attendance records are permanently bound to the class corresponding to the student's enrollment active on that specific date (`classOn(date)`).
4. Provide a fail-safe user interface featuring mapping configuration, preview dry-runs, explicit confirmation modals, and idempotency protection against accidental repeat roll-overs.

## Non-goals

- Sub-semester divisions (tri-semesters or terms) within an academic year (formal report card periods are specified in spec 11).
- Free-form manual date manipulation of historical enrollment rows via raw editing tables.
- Retroactive re-attribution utilities ("move all my past records from Class A to Class B").
- Automatic grade retention calculations (students repeating a grade are mapped manually to a target class of the same grade level during roll-over review).
- Multi-school or multi-branch synchronized roll-overs.

## User Stories

- **As an administrator in July**, I want to set up the new "2027/2028" school year, map each existing class to its successor (1A -> 2A, 2A -> 3A, ..., 6A -> Graduated/Alumni), and apply the change in one click.
- **As an administrator**, I want graduated students to automatically become Alumni so that they no longer trigger morning absence notifications or clutter daily teacher attendance rosters.
- **As a principal**, I want to pull up the Class 5A attendance report from two years ago and see the exact roster and attendance percentages of that historical cohort, unchanged by subsequent promotions.
- **As a parent with an alumni child**, I want to view my child's past attendance archives without seeing active morning check-in prompts.

## Decisions

- **Enrollment History as Sole Source of Truth**: As established in spec 02, class membership is derived through date-effective `enrollments`. The system never stamps `class_id` onto `attendances` records and never stores a static `class_id` on `students`.
- **Atomic Roll-Over Workflow**: Roll-over is implemented as a structured application use case, not a batch SQL update:
  1. Admin creates the new `academic_years` row with start and end dates.
  2. System presents a mapping interface: each class from the currently active year is mapped to a target class in the new year (auto-suggesting same name or next grade level, with homeroom teacher assignment).
  3. Classes marked as "Graduating / None" indicate that students in those classes will not have a successor enrollment created.
  4. Upon administrative execution, the system transactionally closes active enrollments (`ended_on = roll_over_date - 1`) and opens new enrollments in the target classes (`started_on = roll_over_date`).
  5. The new academic year becomes `is_active = true`, and the previous year becomes `is_active = false`.
- **Alumni Semantics**: An "Alumni" or departed student is defined purely by domain state: a student who has **no open enrollment** (`ended_on IS NULL`) in the currently active academic year.
  - Alumni are excluded from the morning auto-absent sweep (spec 03).
  - Alumni are omitted from the Live Presence Board and Today's Attendance Dashboard (specs 06 and 08).
  - Alumni are excluded from active class dropdowns and pickers.
  - Historical reports for past years continue to display alumni with complete fidelity.
- **Idempotency and Confirmation Guard**: Re-executing a roll-over on an already promoted academic year is prevented. The UI requires explicit confirmation with summary statistics before committing changes.

## Requirements

1. **Roll-Over Preparation Screen (`GET /admin/academic-years/roll-over`)**:
   - Requires an upcoming, non-active academic year to be defined.
   - Lists all classes in the currently active academic year.
   - For each class, provides target mapping options:
     - Map to existing class in new year.
     - Auto-create new target class in new year (input class name + select homeroom teacher).
     - Mark as "Graduating / No Successor" (students become Alumni).
   - Allows excluding individual students from promotion (e.g. retained students mapped to a retained class).
2. **Roll-Over Execution (`POST /admin/academic-years/roll-over`)**:
   - Executes inside a single database transaction (`DB::transaction`).
   - Verifies target academic year is valid and has not previously been promoted into.
   - Closes open enrollments for all mapped students: `ended_on = target_year.starts_at - 1 day`.
   - Creates new open enrollments for promoted students: `started_on = target_year.starts_at`, `ended_on = null`.
   - Deactivates previous year (`is_active = false`) and activates new year (`is_active = true`).
   - Unmapped students simply have their open enrollment closed without a successor row.
3. **Date-Effective Query Contract**:
   - Resolving class roster on any date `D`:
     $$\text{Students in Class } C \text{ on Date } D = \{ s \mid \exists e \in \text{enrollments}: e.\text{student\_id} = s.\text{id} \land e.\text{class\_id} = C.\text{id} \land e.\text{started\_on} \le D \land (e.\text{ended\_on} \text{ is null} \lor e.\text{ended\_on} \ge D) \}$$
   - Any report generated for a historical date range queries enrollments matching that specific period.
4. **Alumni Scanner Protection**:
   - If an alumni student attempts to tap an uncollected RFID card at the gate, the scanner records `scan_events` with outcome `ignored_alumni` and creates no attendance record.

## Schema Reference

This specification utilizes the master data schema defined in spec 02:

```
academic_years (spec 02)
      │
      └───► classes (spec 02)
                 │
                 └───► enrollments (spec 02)
                             │
                             └───► students (spec 02)
```

**State Transition of Enrollments During Roll-over**:
```text
Before Roll-over (2026/2027 Active):
[Enrollment #101] Student: Ahmad | Class: 5A (2026/2027) | started_on: 2026-07-01 | ended_on: NULL

After Roll-over to 2027/2028:
[Enrollment #101] Student: Ahmad | Class: 5A (2026/2027) | started_on: 2026-07-01 | ended_on: 2027-06-30
[Enrollment #205] Student: Ahmad | Class: 6A (2027/2028) | started_on: 2027-07-01 | ended_on: NULL
```

## Acceptance Criteria

- **AC-07-01**: After executing roll-over from Year 1 to Year 2, running Class 5A's attendance report for Year 1 shows the exact Year 1 student roster and counts unchanged.
- **AC-07-02**: In Year 2, Class 6A displays the promoted cohort, while students who graduated in Year 1 appear in zero active rosters or today-views.
- **AC-07-03**: Given a student transferred mid-year from 5A to 5B on October 1st, reports for September credit their attendance to 5A, and reports for October credit their attendance to 5B.
- **AC-07-04**: An attempt to execute roll-over while leaving the database in an invalid state (e.g. overlapping enrollment) triggers a full transaction rollback with zero records altered.
- **AC-07-05**: When the daily auto-absent sweep runs on a school day in Year 2, alumni students without active enrollments are skipped entirely.
- **AC-07-06**: An alumni student tapping their RFID card at the gate does not generate an attendance record and logs `ignored_alumni`.

## Constraints & Assumptions

- Academic years run on contiguous date boundaries without calendar overlap.
- Single active academic year invariant: exactly one row in `academic_years` has `is_active = true`.

## Open Questions

- `[NEEDS DECISION: Mid-Year Promotion / Retention Exception]`: If a student is promoted mid-year, the system handles it via the transfer workflow (spec 02). No additional roll-over tooling is required for individual mid-year adjustments.
