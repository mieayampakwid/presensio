# 07 — Dashboard (Role Landing)

Status: draft v1 (2026-09-21)

## Purpose

Every signed-in user lands on `dashboard` after login, but the page is still the starter-kit placeholder — empty cards, no data. The app's front door should answer "what's happening today?" at a glance, per role, using data that already exists: today's presence state, pending excuses, and each person's own attendance. Everything actionable stays in its own page; the dashboard only summarizes and deep-links.

## Decisions

- **Controller-backed, read-only**: the `dashboard` route switches from `Route::inertia` to a controller that composes one role-shaped props payload. No mutations, no polling, no deferred props, no new tables — plain queries at single-school scale.
- **Today = school-tz business date** via `SchoolSettings::todayDate()`. On a weekend or `non_school_days` date, every role sees a muted "not a school day" banner above their widgets; widgets still render (zeros/no-records are honest).
- **Reuse over new code**: admin/teacher today-aggregates come from `AttendanceReportService::board()` (already scoped through `ClassAccess`, totals folded in); student today-card mirrors the `my-attendance` row shape; status colors reuse `STATUS_BADGES`.
- **Admin gets numbers, not names** (locked 2026-09-21): students without a record appear as per-class counts with links to the Presence Board / attendance dashboard — the board owns the drill-down; the dashboard stays a summary.
- **Excuse visibility follows spec 04 exactly**: pending count for admin (approver) and teacher (class read-only); parents see their own submissions' latest state; students never see excuses.
- **Scoping is inherited, never new**: every query runs through the same scoping as its source page (`ClassAccess` for class data, `guardian_student` for parents, self for students). The dashboard exposes nothing the source pages don't already show.
- UI language: English with Indonesian parentheticals, matching the rest of the app.

## Requirements

1. **Admin** sees:
   - Today's school-wide totals: in-building (evacuation headcount), not-yet-checked-in, checked-out, enrolled — same numbers as the Presence Board, same link.
   - Per-class compact counts (enrolled / in-building / not-in) for all classes — numbers only, no name lists; each row links to the board.
   - Pending excuses count linking to the review queue.
   - Quick links: attendance dashboard, reports.
2. **Teacher** sees:
   - The same per-class today-counts, but only their homeroom class(es) (teacher without a linked profile sees the empty state, same as the attendance dashboard).
   - Pending excuses count for their class(es), linking to the review queue (read-only, per spec 04).
   - Quick links: attendance dashboard, Presence Board, reports.
3. **Parent** sees, per linked child:
   - Today's status (`STATUS_BADGES` chip; "No record yet" when absent of a record) and the child's class name.
   - The latest excuse they submitted for that child (type + status + date range), linking to My Excuses.
   - Link to the child report.
   - No linked children → the empty state used elsewhere in their portal.
4. **Student** sees:
   - Today's status card: status, check-in/check-out times, method (same fields as the `my-attendance` today card, pre-formatted school-tz times), linking to My Attendance.
   - No excuse widgets (spec 04 grants no student excuse visibility).
   - No linked student profile → the existing "No student profile linked" empty state.
5. All widgets carry only summary fields (status, counts, times); `notes` / `override_by_user_id` / notification ledger data are never exposed.
6. Loading the dashboard performs no writes (no records created, no notifications triggered).

## Acceptance criteria

- Admin visiting `/dashboard` sees totals matching the Presence Board payload for the same moment, and a pending-excuse count equal to `excuses where status = pending`.
- Teacher sees counts only for their homeroom classes; an out-of-scope class never appears; teacher without a linked profile gets the empty state.
- Parent (e.g. the seeded dual-guardian) sees one row per linked child across classes, each with today's status; a child with no record today shows "No record yet", not "absent".
- Student sees their own today-status card; a student without a linked profile sees the empty state.
- On a weekend or seeded `non_school_days` date, every role sees the "not a school day" banner.
- Feature tests cover each role's scoping (out-of-scope data absent from props), the pending-count math, empty states, and no-write behavior.

## Constraints & assumptions

- Single-school scale; plain eager-loaded queries, no caching layer (same stance as spec 06).
- Route name `dashboard` and the post-login redirect stay unchanged.
- The welcome page (`/`) is untouched.
- **Sequenced after spec 08** — per-class counts read the enrollment model (open enrollments, active year), not the removed `students.class_id`.
- Implementation plans follow the repo convention (`docs/plans/YYYY-MM-DD-*.md`); after this spec the roadmap shifts to deploy/pilot work, not more features.

## Out of scope

- Any mutation from the dashboard (approving, editing, marking) — links only
- Charts, trends, analytics dashboards (carried over from spec 06 out-of-scope)
- Polling / auto-refresh (the Presence Board is the live surface)
- Student access to excuses; guardian self-registration (spec 01 v1.x)
- Notification history / delivery ledger UI (spec 05 has no admin UI by design)
- Per-role widget preferences or customization
