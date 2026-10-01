# 09 — Subjects & Teaching Assignments (Mata Pelajaran & Pengajar)

Status: draft v1.1 (2026-10-01) — revised against spec 15 (AUDIT-2026-10-01 P09-01…03, S-01). Supersedes draft v1.0 (2026-09-25): KKM column renamed to curriculum-neutral `passing_threshold`; subject `group` + `sort_order` added; assignment copy at roll-over added.

## Problem

Presensio currently functions exclusively as an attendance tracking system with homeroom awareness. It has no concept of academic subjects (Mata Pelajaran), curriculum catalogs, or subject-specific teaching assignments. In Indonesian primary and secondary schools, education revolves around distinct subjects (e.g. Matematika, Bahasa Indonesia, IPAS, Pendidikan Agama) taught by specialized subject teachers (Guru Mata Pelajaran). Without a subject master catalog and teaching assignment model:
1. Academic evaluation, homework, and grading are impossible to record in the platform.
2. Semester report cards (Rapor) cannot be compiled digitally.
3. Class timetables (Jadwal Pelajaran) cannot be constructed.
4. Non-homeroom subject teachers have zero functional utility in the software, forcing them to maintain grades in offline spreadsheets.

## Goals

1. Establish a school-wide master catalog of academic subjects (`subjects`) with standardized naming, codes, and report-card grouping.
2. Enable administrators to assign specific teachers to subjects within specific classes for an academic year (`class_subjects`).
3. Define a passing threshold per subject per class (KKTP under Kurikulum Merdeka; the column is curriculum-neutral).
4. Grant subject teachers role-scoped workspace access to view student rosters and manage academic records for their assigned classes.
5. Provide homeroom teachers and administrators with visibility into all teaching assignments within a class.
6. Avoid yearly re-entry: roll-over (07) can copy assignments into the new year's classes.
7. Serve as the foundational domain entity for Grading (10), Report Cards (11), and Timetables (13).

## Non-goals

- Lesson plans, modul ajar, or syllabus file repository in v1.
- Team teaching (one primary instructor per `class_subjects` row in v1).
- Student elective course registration; all students enrolled in a class take all subjects assigned to that class. (SMA "mata pelajaran pilihan" per student is a v1.x candidate.)
- Per-semester assignment changes as separate rows: `class_subjects` is year-scoped; a teacher change mid-year updates the row and is captured in the audit log (15).
- Period-by-period attendance (deferred; see 13).
- Learning objectives (TP) — owned by spec 10, since they exist to structure assessment.

## User Stories

- **As an administrator**, I want to manage the subject catalog (e.g. "Matematika", code "MAT", group "Umum"), so that subjects can be assigned to classes and appear in the right report-card section.
- **As an administrator**, I want to assign Ibu Siti as the IPAS teacher for Class 5A in 2026/2027 with a passing threshold of 75, so she can record student grades.
- **As an administrator during roll-over**, I want to copy last year's subject assignments into the new classes and only adjust the changes, instead of re-entering 9 subjects × 12 classes.
- **As a subject teacher**, I want to see the classes I teach this year, open Class 5A IPAS, and view the enrolled roster.
- **As a homeroom teacher**, I want to see every subject taught in my class alongside its teacher.
- **As a parent**, I want to see which subjects and teachers my child has this year.

## Decisions

- **Global Catalog vs Year-Scoped Assignment**:
  - `subjects` is a durable catalog persisting across years.
  - `class_subjects` is the operational assignment; it inherits year scope from `classes` (spec 02 v2.0).
- **Subject Grouping**: `subjects.group` (`general` = Mata Pelajaran Umum, `local_content` = Muatan Lokal, `elective` = Pilihan) and `sort_order` determine report-card section and row order (11). (Resolves the v1.0 open question.)
- **Single Teacher Per Assignment in v1**: Substitutions update `teacher_id`; the change is audited (15). Scores keep their own `graded_by_user_id` (10), so history of who graded is preserved.
- **Curriculum-Neutral Passing Threshold**: `class_subjects.passing_threshold` (decimal, 0–100). Under Kurikulum Merdeka it is presented as **KKTP**. Changing it is allowed and audited; published report cards are unaffected because they are snapshots (11).
- **Teacher Authorization Scope** (permission matrix in 15): a teacher can access a class's academic views if they are its homeroom teacher **or** hold any `class_subjects` row for it. Gradebook *write* access requires holding that specific `class_subjects` row.
- **Assignment Copy at Roll-over**: For each class mapped in roll-over (07), the admin may tick "copy subject assignments". Rows are copied with the same `subject_id`, `teacher_id`, and `passing_threshold`; inactive subjects and inactive teachers are skipped and listed for review.

## Requirements

1. **Subject Catalog Management (`/admin/subjects`)**:
   - Admin CRUD. Fields: `name` (required, unique), `code` (required, unique, uppercase), `group` (required enum), `sort_order` (integer, default 0), `description` (optional), `is_active` (default true).
   - Deletion guard: a subject referenced by any `class_subjects` row cannot be deleted (HTTP 422); deactivation is supported.
2. **Class Subject Assignment (`/admin/classes/{id}/subjects`)**:
   - Admin can view, assign, edit, and remove assignments for a class.
   - Fields: `subject_id` (active subjects), `teacher_id` (active teachers), `passing_threshold` (decimal 0.00–100.00, default from school setting `default_passing_threshold`, initially 75.00).
   - `UNIQUE(class_id, subject_id)`.
   - Removal guard: an assignment with any assessment (10) or timetable slot (13) cannot be deleted (HTTP 422).
   - Classes of a past (non-active) academic year are read-only.
3. **Roll-over Copy (extends spec 07 roll-over)**:
   - Per mapped class, optional "copy subject assignments" checkbox (default on).
   - Executed inside the roll-over transaction; result summary lists copied rows and skipped rows with reason.
4. **Teacher My Courses Portal (`GET /teacher/courses`)**:
   - Lists the authenticated teacher's `class_subjects` in the active academic year: class, subject, code, passing threshold, enrolled student count (as of today via `classOn`).
   - Each course links to the course workspace (roster + gradebook; spec 10).
5. **Class Directory View**:
   - Homeroom teachers, admins and principals see a "Mata Pelajaran & Guru" tab per class.
   - Parents and students see the same list (subject, teacher name) for the child's/own current class.

## Schema

### 1. `subjects` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `code` | varchar(20) | not null, unique | e.g. "MAT", "IPAS" |
| `name` | varchar(255) | not null, unique | Official subject title |
| `group` | varchar(20) | not null, default: 'general' | Enum: `general`, `local_content`, `elective` |
| `sort_order` | integer | not null, default: 0 | Report-card row order within group |
| `description` | text | nullable | |
| `is_active` | boolean | not null, default: true | |
| `created_at` / `updated_at` | timestamp | nullable | |

### 2. `class_subjects` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `class_id` | bigint | unsigned, not null | FK -> `classes.id` |
| `subject_id` | bigint | unsigned, not null | FK -> `subjects.id` |
| `teacher_id` | bigint | unsigned, not null | FK -> `teachers.id` |
| `passing_threshold` | decimal(5,2) | not null, default: 75.00 | KKTP (Merdeka) / passing score |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (class_id, subject_id)`, `INDEX (teacher_id)`

### 3. `settings` — added column

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `default_passing_threshold` | decimal(5,2) | not null, default: 75.00 | Pre-fill for new assignments |

## Acceptance Criteria

- **AC-09-01**: Creating a second subject with an existing `code` or `name` returns HTTP 422.
- **AC-09-02**: Assigning "Matematika" to Class 5A (active year) with Teacher Budi and threshold 75 creates a `class_subjects` row.
- **AC-09-03**: Assigning "Matematika" a second time to Class 5A returns HTTP 422.
- **AC-09-04**: Teacher Budi sees Class 5A Matematika at `/teacher/courses`.
- **AC-09-05**: Teacher Siti (neither 5A's homeroom teacher nor holding any 5A assignment) requesting 5A course data receives HTTP 403.
- **AC-09-06**: Deleting a subject referenced by any `class_subjects` row returns HTTP 422.
- **AC-09-07**: Roll-over with "copy subject assignments" on maps 5A (2026/2027, 9 assignments) to 6A (2027/2028) and creates 9 assignments on 6A; an assignment whose teacher is inactive is skipped and reported.
- **AC-09-08**: Changing the teacher of an assignment writes an audit log row with old and new `teacher_id` (15).
- **AC-09-09**: Attempting to edit assignments of a class in a non-active academic year returns HTTP 422.

## Constraints & Assumptions

- Single-school catalog.
- Requires spec 15 (roles/permissions, audit log, `grade_level`).

## Open Questions

- `[NEEDS DECISION: Per-student electives (SMA)]`: Kurikulum Merdeka SMA phase F lets students choose elective subjects. v1 assumes every student in a class takes every assigned subject. Needed only if an SMA adopts Presensio.
