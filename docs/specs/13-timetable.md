# 13 — Class Timetables & Schedules (Jadwal Pelajaran Mingguan)

Status: draft v1.0 (2026-09-25)

## Problem

In Indonesian elementary and secondary schools, daily academic routines depend on a clear weekly timetable (Jadwal Pelajaran). Currently, schedules are distributed informally as photos of whiteboard sketches shared in WhatsApp groups or handwritten slips glued into student notebooks. This manual distribution causes recurring operational problems:
1. Parents and students frequently misplace schedules, leading to students arriving with the wrong textbooks, missing art supplies, or wearing the incorrect uniform on physical education (PJOK) days.
2. Subject teachers who instruct across multiple classrooms (e.g. teaching Math across 5A, 5B, 6A, 6B) struggle to keep track of their daily room and period allocations without a unified digital schedule.
3. Without a formal timetable data model connecting classes, subjects, teachers, and specific time slots, future educational platform expansions (such as period-by-period attendance tracking or substitute teacher re-assignments) are architecturally impossible to implement.

## Goals

1. Define an intuitive weekly timetable data structure (`timetable_slots`) mapping days of the week, time windows, subjects, instructors, and physical classroom locations to school classes.
2. Enable school administrators to construct and update weekly schedules per class in the active academic year.
3. Accommodate non-academic school routines (such as "Upacara Bendera", "Istirahat", "Sholat Berjamaah", "Literasi Pagi") alongside standard curriculum subjects.
4. Present students and guardians with a "Today's Schedule" widget on their portal dashboard (spec 08) and an interactive weekly timetable grid view.
5. Provide teachers with a consolidated "My Weekly Teaching Schedule" displaying their personal instructional commitments across all assigned classrooms.
6. Establish the structural foundation for v1.x period-by-period attendance recording.

## Non-goals

- Algorithmic timetable generation, AI automated scheduling, or genetic scheduling optimization algorithms in v1 (timetables are configured manually by school administrators).
- Campus facility, laboratory, or sports hall booking and reservation engines in v1.
- Substitute teacher dispatching or temporary class coverage workflows in v1 (handled via manual assignment updates; spec 09).
- Automated electronic bell chime or campus public address (PA) hardware integration.
- Alternating multi-week schedules (e.g. Week A / Week B rotations; single repeating weekly schedules are standard in v1).

## User Stories

- **As an administrator**, I want to set up the weekly schedule for Class 5A (e.g. Monday 07:00–07:45: Upacara Bendera, 07:45–09:15: Matematika with Pak Budi in Room 5A), so that the class schedule is published online.
- **As a student packing my backpack on Sunday evening**, I want to open the Presensio app and check tomorrow's schedule to see which textbooks and notebooks I need for Monday.
- **As a parent**, I want to glance at my child's daily schedule on my dashboard so I know whether they have Physical Education (PJOK) today and need to wear sports attire.
- **As a subject teacher**, I want to view my personalized schedule for the day, seeing that I teach Class 7A at 08:00 and Class 8B at 10:00, with room numbers specified.
- **As a homeroom teacher**, I want to view my classroom's complete weekly timetable grid to know which teachers are in my room throughout the week.

## Decisions

- **Direct Binding to `class_subjects`**:
  - Academic periods bind directly to `class_subject_id` (spec 09).
  - This automatically guarantees that the subject name, subject code, instructor, and class are resolved dynamically without duplicate data entry.
- **Support for Non-Academic Activities**:
  - School routines contain non-subject events (Flag Ceremonies, Recesses, Prayers).
  - To support these, `class_subject_id` is nullable. When null, the slot requires a descriptive string in `activity_name` (e.g. "Upacara Bendera", "Istirahat").
- **Day of Week Storage**:
  - Stored as a tiny integer from 1 (Monday / Senin) to 7 (Sunday / Minggu), adhering to ISO-8601 day-of-week numbering (`Carbon::dayOfWeekIso`).
- **Soft Conflict Validation in v1**:
  - During administrative schedule input, the system checks for teacher and room overlapping double-bookings.
  - If Teacher Budi is already scheduled in Class 5A on Monday at 08:00–09:30, assigning him to Class 5B during that same slot triggers a visible warning modal: *"Peringatan: Guru bersangkutan telah dijadwalkan di Kelas 5A pada jam tersebut."*
  - The administrator can choose to override or adjust, preventing rigid software lockouts during real-world school scheduling adjustments.
- **Time Representation**:
  - Stored as SQL `time` columns (`start_time`, `end_time`, `HH:MM`). Evaluated in the school's configured timezone.

## Requirements

1. **Class Timetable Editor (`GET /admin/classes/{id}/timetable`)**:
   - Interactive weekly matrix: Days (Monday through Friday/Saturday) $\times$ Time Slots.
   - Admin can add, edit, move, and delete slots.
   - Modal form:
     - Type selector: "Mata Pelajaran" vs "Kegiatan Non-Akademik".
     - If Subject: select from `class_subjects` assigned to this class.
     - If Non-Academic: input `activity_name` (e.g. "Istirahat").
     - Day of Week dropdown (Senin - Sabtu).
     - `start_time` and `end_time` (validates `start_time < end_time`).
     - `room` (optional string, e.g. "Lab IPA", "Ruang 5A").
   - Validates that time slots within the same class do not overlap.
2. **Class Weekly Timetable View (`GET /classes/{id}/timetable`)**:
   - Accessible by Admin, Teachers, and Enrolled Students / Guardians.
   - Renders a clean weekly grid displaying day columns and ordered time cards.
   - Cards display Start-End time, Subject Name (or Activity), Teacher Name, and Room.
   - Printable view option for physical classroom pinboards.
3. **Teacher Personal Schedule (`GET /teacher/schedule`)**:
   - Aggregates all `timetable_slots` where `class_subjects.teacher_id = auth()->teacher->id`.
   - Displays weekly schedule grouped by day, highlighting current active or upcoming class today.
4. **Dashboard Integration ("Jadwal Hari Ini")**:
   - `/dashboard` injects today's schedule for the authenticated student/parent:
     $$\text{Today's Slots} = \{ s \in \text{timetable\_slots} \mid s.\text{class\_id} = \text{student.class\_id} \land s.\text{day\_of\_week} = \text{today.dayOfWeekIso} \}$$
   - Sorted chronologically by `start_time`.
   - Highlight current or next upcoming class period on the dashboard.

## Schema

### `timetable_slots` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `class_id` | bigint | unsigned, not null | Foreign key -> `classes.id` |
| `class_subject_id` | bigint | unsigned, nullable | Foreign key -> `class_subjects.id` (null for non-academic) |
| `activity_name` | varchar(100) | nullable | Title for non-subject slots (e.g. "Upacara", "Istirahat") |
| `day_of_week` | tinyint | unsigned, not null | 1 = Senin, 2 = Selasa, ..., 7 = Minggu |
| `start_time` | time | not null | Start time of slot (HH:MM:00) |
| `end_time` | time | not null | End time of slot (HH:MM:00) |
| `room` | varchar(50) | nullable | Physical room or location name |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `INDEX (class_id, day_of_week, start_time)`
- `INDEX (class_subject_id)`

## Acceptance Criteria

- **AC-13-01**: Given an attempt to save a timetable slot where `start_time = 09:00` and `end_time = 08:30`, validation rejects the request with HTTP 422.
- **AC-13-02**: Given two overlapping slots for the same class on the same day (e.g. 08:00–09:30 and 09:00–10:00), the editor rejects the collision with validation error HTTP 422.
- **AC-13-03**: Given a slot created as non-academic with `activity_name = "Istirahat 1"` and `class_subject_id = null`, the slot renders on the timetable grid with "Istirahat 1" and no teacher name.
- **AC-13-04**: When Teacher Budi views `/teacher/schedule`, slots across Class 5A and Class 6A where he is the subject teacher render in his personal teaching agenda.
- **AC-13-05**: When a student enrolled in Class 5A views `/dashboard` on Wednesday, the "Jadwal Hari Ini" card renders Wednesday's timetable slots for Class 5A in chronological order.
- **AC-13-06**: When today is Sunday or a school holiday, the dashboard "Jadwal Hari Ini" card displays an empty state: "Tidak ada jadwal pelajaran hari ini."

## Constraints & Assumptions

- Weekly schedule repeats uniformly across the academic year term.
- Classrooms operate on a standard 5-day (Monday–Friday) or 6-day (Monday–Saturday) school week depending on `school_operational_days` config.

## Open Questions

- `[NEEDS DECISION: Period-by-Period Attendance Linking (v1.x)]`: When per-period attendance is introduced in v1.x, each period attendance record will link to `timetable_slots.id`. The current schema design is fully forward-compatible with this enhancement.
