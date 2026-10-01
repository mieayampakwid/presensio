# 08 — Dashboard (Role-Tailored Operational Landing)

Status: v1.0 — implemented 2026-09-21 (amended 2026-09-25; audit amendment 2026-10-01 — see AUDIT-2026-10-01 D-09)

## Problem

When school community members log into traditional school administrative software, landing on a generic starter-kit page or empty menu forces them to navigate multiple sub-pages to answer immediate morning questions:
- Administrators need to know: *"How many students are in the building right now? Are there unreviewed sick notes waiting for approval?"*
- Homeroom teachers need to know: *"Who is missing from my classroom this morning? Did any parents submit excuses?"*
- Guardians need to know: *"Did my daughter tap into school safely this morning? Has our travel leave notice been approved?"*
- Students need to know: *"Did my gate card scan register properly, or am I marked late?"*

Generic dashboard implementations frequently suffer from security leaks by inadvertently exposing school-wide counts to unauthorized roles or imposing heavy client-side polling loops that strain server memory.

## Goals

1. Deliver a role-tailored landing page upon successful login that provides immediate operational clarity for the current school business day.
2. Provide Administrators with a bird's-eye view of school-wide presence metrics, per-class attendance tallies, and pending excuse approval queues.
3. Provide Teachers with homeroom-scoped today presence totals, unrecorded student counts, and relevant class excuse queues.
4. Provide Guardians with live today attendance status badges per linked child, latest excuse application statuses, and direct links to full reports.
5. Provide Students with an immediate scan feedback card confirming today's recorded arrival time and check-in method.
6. Enforce a strict zero-mutation guarantee: loading the dashboard executes read-only aggregations, performs no database writes, and dispatches zero notifications.

## Non-goals

- In-place mutation controls on dashboard cards (the dashboard summarizes and deep-links; mutations occur on dedicated operational pages like the exception dashboard or excuse review queue).
- High-frequency WebSocket or client polling on the main dashboard (the dedicated Live Presence Board in spec 06 handles kiosk polling).
- Historical trend line charts, multi-month attendance bar graphs, or analytics mining in v1.
- Custom drag-and-drop end-user widget customization in v1.
- Direct student interaction with excuse management (excuse submission and review are strictly guardian-admin workflows).

## User Stories

- **As a school administrator arriving at 08:00 WIB**, I want to glance at my dashboard and instantly see total students in-building (145), students not yet checked in (15), and a badge indicating 3 pending absence excuses awaiting my review.
- **As a homeroom teacher**, I want to open my dashboard during morning homeroom and see that 28 of my 30 students are present, 1 has an approved doctor's note, and 1 is unrecorded, with a direct link to take class roll.
- **As a working guardian checking my phone at 07:15 WIB**, I want to open Presensio and see a green "Hadir (06:52 WIB)" badge for my son, confirming he reached school safely.
- **As a student**, I want to view my dashboard after entering through the gate to confirm that my card tap registered as "Hadir" and not "Terlambat".
- **As any user logging in on Sunday or a national holiday**, I want to see an informational banner indicating that today is a non-school day, avoiding confusion over zero-attendance tallies.

## Decisions

- **Controller-Backed Single Composite Query**: The `/dashboard` route executes `DashboardController@index`, assembling a clean, role-tailored Inertia props payload in a single server pass. No deferred props or heavy API waterfalls.
- **School Business Date Authority**: "Today" is strictly determined in the school's configured timezone via `SchoolSettings::todayDate()`. On weekends and calendar dates recorded in `non_school_days`, an informational banner displays *"Hari ini bukan hari sekolah efektif"* while widgets render transparent zero/empty states.
- **Admin Dashboard: Numbers, Not Names**: To keep the landing page fast and clean, administrators see aggregated per-class numbers (enrolled / present / unrecorded). Clicking any count navigates directly to the Live Presence Board or Attendance Dashboard with pre-filtered class scopes.
- **Inherited Scoping Enforcement**: Dashboard queries reuse identical authorization constraints established in source specs:
  - Admin: Unrestricted across all classes and queues.
  - Teacher: Filtered strictly via `ClassAccess` to homeroom classes in the active academic year.
  - Parent: Filtered strictly to students linked via `guardian_student`.
  - Student: Filtered strictly to their own linked `student_id`.
- **Zero Side-Effects Invariant**: Loading the dashboard performs no state mutations, creates no records, and fires no background events or notifications.

## Requirements

1. **Administrator Dashboard Payload**:
   - School-wide today totals: Enrolled Count, In-Building Count, Checked-In Count, Not-Yet-Arrived Count, Checked-Out Count.
   - Per-class summary table: Class Name, Enrolled, In-Building, Not-In-Building (each row links to class presence view).
   - Pending Excuses Badge: Total count of excuses where `status = 'pending'`, linking directly to the excuse review queue (`GET /excuses`, spec 04).
   - Quick navigational shortcuts to Attendance Dashboard, Live Presence Board, and Academic Reports.
2. **Teacher Dashboard Payload**:
   - Homeroom class summary: Presence totals for teacher's assigned classes in the active academic year.
   - Unrecorded count for homeroom class with quick link to bulk class check-in.
   - Pending excuses count for students in their assigned homeroom classes (linking to read-only excuse view).
   - Empty state displayed if teacher account has no linked profile or no assigned homerooms in the active year.
3. **Guardian Dashboard Payload**:
   - Iterates all linked children from `guardian_student`.
   - Per child: Full Name, Class Name, Today's Status Badge (`present`, `late`, `sick`, `leave`, `absent`, or "Belum Ada Catatan"), Check-in Time, and Check-out Time.
   - Latest submitted excuse per child: Type, Date Range, Status Badge (`pending`, `approved`, `rejected`), linking to My Excuses.
   - Direct link to individual child attendance report.
   - Empty state displayed if guardian account has no linked student profiles.
4. **Student Dashboard Payload**:
   - Today's Presence Card: Status Badge, Check-in Timestamp, Check-out Timestamp, and Method badge (RFID / QR).
   - Prominent link to My Attendance full historical log.
   - No excuse cards or administrative metrics are exposed to student sessions.
5. **Non-School Day Presentation**:
   - Evaluates if today's business date is Saturday, Sunday, or exists in `non_school_days`.
   - If true, prepends a prominent non-school day alert banner across all role dashboards.

## Schema

### Pure Composite Presentation Layer (No Dedicated Tables)

The dashboard introduces no dedicated database tables. It composes a read-only projection:

```
DashboardController
       ├──► SchoolSettings::todayDate()
       ├──► AttendanceReportService::board() (spec 06)
       ├──► Excuses::whereStatus('pending') (spec 04)
       └──► Attendances::whereDate('date', today) (spec 03)
```

**Props Contract (Example Guardian Payload)**:
```json
{
  "business_date": "2026-09-25",
  "is_school_day": true,
  "children": [
    {
      "student_id": 14,
      "full_name": "Fatimah Az-Zahra",
      "class_name": "5A",
      "today_attendance": {
        "status": "present",
        "checked_in_at": "06:48 WIB",
        "checked_out_at": null,
        "method": "rfid"
      },
      "latest_excuse": {
        "type": "sick",
        "status": "approved",
        "dates": "2026-09-20 - 2026-09-21"
      }
    }
  ]
}
```

## Acceptance Criteria

- **AC-08-01**: When an admin loads `/dashboard`, the displayed "In-Building" total matches the In-Building metric on the Live Presence Board at that same second.
- **AC-08-02**: When a teacher loads `/dashboard`, they see presence metrics strictly for their assigned homeroom classes; out-of-scope classes are absent from props.
- **AC-08-03**: When a guardian with two linked children loads `/dashboard`, two child summary cards render; a child who has not yet tapped displays status "Belum Ada Catatan" (not "absent").
- **AC-08-04**: When a student loads `/dashboard`, today's check-in card renders with their scan time; administrative notes or reviewer comments are absent from the payload.
- **AC-08-05**: On a date recorded in `non_school_days`, the non-school day banner displays across all roles, and absence counts remain at zero.
- **AC-08-06**: Loading `/dashboard` generates zero database insert or update queries and dispatches zero queued notification jobs.

## Constraints & Assumptions

- All timestamps are pre-formatted on the server in the school's configured timezone before serialization to Inertia props.
- Standard Inertia page component: `resources/js/pages/Dashboard.tsx`.

## Open Questions

- `[NEEDS DECISION: Dashboard Refresh Frequency]`: The dashboard does not poll automatically. A manual "Refresh" button or 5-minute background re-validation via Inertia router reload may be considered for v1.1.
