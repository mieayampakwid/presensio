# 13 — Class Timetables & Schedules (Jadwal Pelajaran)

Status: draft v1.1 (2026-10-01) — revised against spec 15 (AUDIT-2026-10-01 S-05, P13-01…03). Supersedes draft v1.0 (2026-09-25): free-form slot times replaced by a bell schedule (jam pelajaran) master with day variants; slots scoped per semester; today's class resolved through enrollments; operational days taken from `school_operational_days`.

## Problem

Weekly timetables (Jadwal Pelajaran) are distributed as photos of whiteboards or handwritten slips. This causes recurring problems:
1. Students bring the wrong books, or forget sports attire on PJOK days.
2. Teachers teaching across several classes lose track of their periods and rooms.
3. Times are inconsistent between classes because each schedule is typed by hand, even though the whole school runs on one bell schedule ("Jam ke-1 07:00–07:35"), usually with a shorter Friday.
4. Without a timetable model, later features (per-period attendance, substitute coverage) cannot be built.

## Goals

1. Define the school's bell schedule once: numbered lesson periods and breaks, with day variants (e.g. Friday).
2. Let admins build each class's weekly timetable per semester by placing subjects (09) or non-academic activities onto periods.
3. Prevent overlaps within a class; warn (not block) on teacher and room double-booking.
4. Show "Jadwal Hari Ini" on the dashboard (08) for students and guardians, and a personal weekly schedule for teachers.
5. Provide a printable weekly grid per class.
6. Be forward-compatible with per-period attendance (v1.x).

## Non-goals

- Automatic timetable generation or optimization.
- Room/facility booking.
- Substitute-teacher dispatch (handled by updating the `class_subjects` teacher; spec 09).
- Bell/PA hardware integration.
- Rotating multi-week schedules (Week A/B).
- Historical bell schedules: changing a period's time applies to all timetables that use it.

## User Stories

- **As an admin**, I want to define "Jam ke-1 07:00–07:35 … Jam ke-8" for Monday–Thursday and a shorter set for Friday, so every class timetable uses the same times.
- **As an admin**, I want to place Upacara on Monday Jam ke-1 for all classes, and Matematika with Pak Budi on Jam ke-2 and 3 in Room 5A.
- **As an admin at the start of semester Genap**, I want to copy each class's Ganjil timetable and adjust only what changed.
- **As a student packing my bag on Sunday evening**, I want to see Monday's subjects.
- **As a parent**, I want to see whether my child has PJOK today.
- **As a subject teacher**, I want to see today's classes, periods and rooms across all classes I teach.

## Decisions

- **Bell schedule**:
  - A `bell_schedules` row (e.g. "Reguler", "Jumat") applies to a set of ISO weekdays.
  - Every operational day (`school_operational_days`, spec 03) maps to exactly one bell schedule.
  - Each bell schedule has ordered `lesson_periods` of kind `lesson` (numbered "Jam ke-n") or `break` (Istirahat). Breaks show in every class grid automatically and cannot hold slots.
- **Slots bind to periods, not free times**: a slot = class + semester + weekday + starting lesson period + `period_count` (span for double periods).
  - Content is either a `class_subjects` row (09) or an `activity_name` (Upacara, Literasi, Sholat Berjamaah).
  - Times are always derived from the bell schedule.
  - A slot's span cannot cross a break.
- **Semester scope**: slots belong to a semester (15). "Copy from previous semester" duplicates a class's slots, skipping slots whose `class_subjects` row no longer exists.
- **Validation**:
  - Overlap within the same class, semester and day → hard error (HTTP 422).
  - Same teacher, or same non-empty room, at overlapping periods on the same day and semester in another class → warning modal: *"Peringatan: Guru bersangkutan telah dijadwalkan di Kelas 5A pada jam tersebut."* The admin may confirm and save anyway (`confirm_conflicts = true`).
  - The day must be an operational day.
- **Today resolution** (S-05):
  - Student: the class from `classOn(today)`.
  - Guardian: `classOn(today)` for each linked child.
  - Teacher: their slots via `class_subjects.teacher_id`.
  - All use the current semester (15). There is no schedule when today is not a school day (`isSchoolDay`, spec 03).
- **Forward compatibility**: per-period attendance will reference `timetable_slots.id`. Slots are therefore never hard-deleted while referenced (no references in v1).

## Requirements

1. **Bell Schedule Editor (`/admin/bell-schedules`)**:
   - CRUD bell schedules with weekday assignment. Validation: every operational day is covered exactly once.
   - Per schedule, ordered periods: `kind`, `number` (lesson only), `start_time`, `end_time`. Periods are contiguous or gapped, never overlapping.
   - Deleting a period used by slots → HTTP 422.
2. **Class Timetable Editor (`GET /admin/classes/{id}/timetable?semester=`)**:
   - Grid: operational days × that day's periods (breaks shaded).
   - Add, edit, move and delete slots. Form fields:
     - type (Mata Pelajaran / Kegiatan)
     - `class_subject_id` or `activity_name`
     - day, starting period, `period_count`
     - `room` (optional)
   - Conflict warnings as above.
   - "Salin dari semester sebelumnya" action.
3. **Class Weekly View (`GET /classes/{id}/timetable?semester=`)**:
   - Admin and principal: all classes. Teachers: classes in scope (09). Students and guardians: own / child's current class.
   - Cards show period numbers, derived times, subject or activity, teacher, and room. Printable layout.
4. **Teacher Schedule (`GET /teacher/schedule`)**:
   - Current-semester slots of the teacher's `class_subjects`, grouped by day; today's current/next slot highlighted.
5. **Dashboard "Jadwal Hari Ini" (08)**:
   - Students and guardians (one block per child): today's slots in period order, current/next highlighted.
   - Non-school day or no slots: "Tidak ada jadwal pelajaran hari ini."

## Schema

### 1. `bell_schedules` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `name` | varchar(50) | not null, unique | e.g. "Reguler", "Jumat" |
| `weekdays` | json | not null | ISO weekdays this schedule applies to, e.g. `[1,2,3,4]` |
| `created_at` / `updated_at` | timestamp | nullable | |

### 2. `lesson_periods` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `bell_schedule_id` | bigint | unsigned, not null | FK -> `bell_schedules.id` |
| `kind` | varchar(10) | not null | Enum: `lesson`, `break` |
| `number` | tinyint | unsigned, nullable | "Jam ke-n" for lessons; null for breaks |
| `name` | varchar(50) | nullable | Label for breaks, e.g. "Istirahat 1" |
| `start_time` | time | not null | |
| `end_time` | time | not null | |
| `sort_order` | tinyint | unsigned, not null | Order within the schedule |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (bell_schedule_id, sort_order)`

### 3. `timetable_slots` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `semester_id` | bigint | unsigned, not null | FK -> `semesters.id` |
| `class_id` | bigint | unsigned, not null | FK -> `classes.id` |
| `day_of_week` | tinyint | unsigned, not null | ISO 1 = Senin … 7 = Minggu |
| `lesson_period_id` | bigint | unsigned, not null | FK -> `lesson_periods.id` (starting period, kind `lesson`) |
| `period_count` | tinyint | unsigned, not null, default: 1 | Number of consecutive lesson periods |
| `class_subject_id` | bigint | unsigned, nullable | FK -> `class_subjects.id`; null for activities |
| `activity_name` | varchar(100) | nullable | Required when `class_subject_id` is null |
| `room` | varchar(50) | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `INDEX (semester_id, class_id, day_of_week)`, `INDEX (class_subject_id)`

## Acceptance Criteria

- **AC-13-01**: Saving bell schedules that leave an operational day uncovered, or cover a day twice, returns HTTP 422.
- **AC-13-02**: Placing Matematika on Monday Jam ke-2 with `period_count = 2` in 5A, when 5A already has a slot at Jam ke-3 Monday in the same semester, returns HTTP 422.
- **AC-13-03**: A slot spanning across "Istirahat 1" returns HTTP 422.
- **AC-13-04**: Assigning Pak Budi to 5B Monday Jam ke-2 while he teaches 5A then returns a conflict warning; resubmitting with `confirm_conflicts = true` saves it.
- **AC-13-05**: An activity slot "Upacara Bendera" renders with no teacher name.
- **AC-13-06**: When 07:00–07:35 for Jam ke-1 in "Reguler" is changed to 07:15–07:50, every class's Monday–Thursday Jam ke-1 shows the new time.
- **AC-13-07**: "Salin dari semester sebelumnya" copies 5A's Ganjil slots into Genap and reports slots skipped because their `class_subjects` row was removed.
- **AC-13-08**: A student who moved from 5A to 5B yesterday sees 5B's timetable in "Jadwal Hari Ini".
- **AC-13-09**: Pak Budi's `/teacher/schedule` lists his slots in 5A and 6A for the current semester.
- **AC-13-10**: On a date in `non_school_days` or a non-operational weekday, "Jadwal Hari Ini" shows "Tidak ada jadwal pelajaran hari ini."

## Constraints & Assumptions

- Times evaluated in the school timezone (03).
- Requires specs 09 and 15, and `school_operational_days` (03).

## Open Questions

- `[NEEDS DECISION: Per-period attendance (v1.x)]`: Per-period attendance will reference `timetable_slots.id`; slot deletion must then become a soft-delete or be blocked when referenced.
