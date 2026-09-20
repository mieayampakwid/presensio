# 08 — Academic Roll-over & Historical Attribution

Status: draft v1.1 (2026-09-21) — consolidated: the foundation schema (academic_years, year-scoped classes, enrollments, removal of `students.class_id`) now lives in spec 02 v2.0; this spec owns the annual lifecycle and how the rest of the system reads it.

## Purpose

The academic year is the heartbeat of a school: rosters form each July, whole cohorts promote, students graduate. This spec defines the one admin operation that moves the school into a new year, the semantics of alumni, and the attribution rules that keep every historical report true — so last year's class report forever shows last year's children.

## Decisions

- **Enrollment is the source of truth** (user-locked 2026-09-21, Clean Architecture rationale — chosen over write-time class stamping for single-source-of-truth cleanliness): a student's class on any date is *derived* from their enrollment history via `classOn(date)`. No denormalized `class_id` on `attendances`; attribution is a domain query, not stored state.
- **Roll-over is a use case, not a bulk UPDATE**: one admin screen — create the new year, map each existing class to a target class in the new year (created on the fly with homeroom teacher; default = same name), review, apply transactionally. Students left unmapped end their enrollment with no successor (alumni/left) but keep all history. Re-applying an already-promoted year is a no-op.
- **Today-surfaces only see enrolled students**: the auto-absent sweep, presence board, attendance dashboard, and every active picker operate on open enrollments (active year). Alumni stop appearing everywhere "today", yet remain fully visible in the years they attended.
- **Build order**: spec 07 (Dashboard, drafted) implements **after** this spec — its per-class counts read the enrollment model.

## Requirements

1. **Roll-over (admin)**: create next year → per-source-class target mapping (create-with-name + homeroom teacher inline) → transactional apply: open enrollments end on the roll-over date, new enrollments open in the mapped target classes; unmapped students simply end with no successor.
2. **Alumni/left students**: no open enrollment; excluded from sweep, board, active pickers and today-views; still selectable in admin lists (filter) and fully present in historical reports. Deletion guards unchanged.
3. **Date-effective attribution**: the exception dashboard resolves the roster *as of the requested date* (not just today); reports attribute each record to the class of the student's enrollment active on the record's date — students not enrolled in that class on that date never appear in its report.
4. **Class report UI (spec 06)**: gains an academic-year picker; the classes list is per selected year. Presence board and attendance dashboard stay active-year-only.
5. **Auto-absent sweep (spec 03)**: marks only students with an enrollment active on the swept date; weekend/non-school-day skip unchanged.
6. **Class name rendering (specs 04/05)**: excuse views and notification bodies render the class from the student's relevant enrollment (today's record → open enrollment); no behavior change otherwise.
7. **Bootstrap**: the migration backfills one open enrollment per pre-existing student (with a `class_id`) into a bootstrap "2026/2027" year — the system never ships with students that have no enrollment row.

## Acceptance criteria

- After a simulated roll-over (year 1 promoted to year 2): the year-1 class report shows year-1's roster and counts unchanged; the same class name in year 2 shows the new roster; alumni appear in neither today-view but appear in year-1 reports.
- A student moved mid-year (5A → 5B in October) attributes September records to 5A and November records to 5B in the respective class reports.
- Creating a second open enrollment for one student fails at the DB level (partial unique) and at validation (overlap guard message).
- The sweep on a school day creates absent records only for students with an enrollment active that date; an alumni student's card tap still scans but never resurrects them into today-views.
- Import into the active year maps/creates classes with the year's `academic_year_id`; last year's same-named class is never reused.
- Every role-scoped surface keeps its spec 01 guarantees (teacher sees own homeroom classes of the relevant year; parent/student unchanged — they are class-agnostic).

## Constraints & assumptions

- **Pre-production window**: no real attendance data exists yet — the schema reshaping (dropping `students.class_id`) is cheap now and impossible once data lands; this spec ships before go-live.
- Tests run on sqlite; partial unique indexes work on both sqlite and PostgreSQL (dev).
- Overlap/transaction patterns reuse existing house solutions (excuses overlap guard, transactional services, `lockForUpdate` re-fetch).

## Out of scope

- Semester entities within a year
- Direct editing of historical enrollment rows (moves and roll-over create correct rows; date surgery is not a UI)
- Retroactive re-attribution tools ("reassign these old records to another class")
- Timetables, subjects, per-period attendance (unchanged deferrals)
- Multi-school / multi-branch years
