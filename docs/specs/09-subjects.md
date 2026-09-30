# 09 — Subjects & Teaching Assignments (Mata Pelajaran & Pengajar)

Status: draft v1.0 (2026-09-25)

## Problem

Presensio currently functions exclusively as an attendance tracking system with homeroom awareness. It has no concept of academic subjects (Mata Pelajaran), curriculum catalogs, or subject-specific teaching assignments. In Indonesian primary and secondary schools, education revolves around distinct subjects (e.g. Matematika, Bahasa Indonesia, IPA, Pendidikan Agama) taught by specialized subject teachers (Guru Bidang Studi). Without a subject master catalog and teaching assignment model:
1. Academic evaluation, homework, and grading are impossible to record in the platform.
2. Semester report cards (Rapor) cannot be compiled digitally.
3. Class timetables (Jadwal Pelajaran) cannot be constructed.
4. Non-homeroom subject teachers have zero functional utility in the software, forcing them to maintain grades in offline spreadsheets.

## Goals

1. Establish a school-wide master catalog of academic subjects (`subjects`) with standardized naming and codes.
2. Enable administrators to assign specific teachers to instruct subjects within specific classes for the active academic year (`class_subjects`).
3. Define the Minimum Passing Criteria (Kriteria Ketuntasan Minimal / KKM) per subject per class.
4. Grant subject teachers authenticated, role-scoped workspace access to view student rosters and manage academic records for their assigned classes.
5. Provide homeroom teachers and administrators with visibility into all teaching assignments within a class.
6. Serve as the foundational domain entity for subsequent academic modules: Grading (spec 10), Report Cards (spec 11), and Timetables (spec 13).

## Non-goals

- Lesson plans, RPP (Rencana Pelaksanaan Pembelajaran), or syllabus digital file repository in v1.
- Team teaching (multiple co-teachers assigned simultaneously to one `class_subject` row; single primary instructor per assignment in v1).
- Higher education elective course registration (KRS / student course picking); all students enrolled in a class take all subjects assigned to that class.
- Period-by-period / per-mapel physical attendance scanning in v1 (daily gate/homeroom attendance remains standard; per-period attendance deferred to v1.x).
- Curricular tracking or competency base management (KD/CP).

## User Stories

- **As an administrator**, I want to manage the school's subject catalog (e.g. create "Matematika" with code "MAT"), so that subjects can be assigned to classes.
- **As an administrator**, I want to assign Ibu Siti as the Science (IPA) teacher for Class 7A in academic year 2026/2027 with a KKM of 75, so she can record student grades.
- **As a subject teacher**, I want to log in and see a list of classes I teach this semester, click on Class 7A Science, and view the enrolled student roster.
- **As a homeroom teacher**, I want to view my class dashboard and see the complete directory of subjects taught in my room alongside their assigned instructors.
- **As a parent**, I want to see which subjects and teachers my child has this semester.

## Decisions

- **Global Catalog vs Year-Scoped Assignment**:
  - The `subjects` table is a global, durable master catalog that persists across academic years (e.g. "Matematika" does not need to be recreated every year).
  - The `class_subjects` table establishes the operational assignment. Because `classes` are already strictly year-scoped instances (spec 02 v2.0), `class_subjects` inherits year-scoping naturally via its `class_id` foreign key.
- **Single Teacher Per Assignment in v1**:
  - Each `class_subject` record holds exactly one `teacher_id` foreign key. If a substitute teacher takes over temporarily, the admin updates the `teacher_id`. Team teaching is deferred to v1.x.
- **KKM Per Assignment**:
  - Different grade levels or curriculum tracks often impose different minimum passing grades for the same subject (e.g. Grade 1 Math KKM is 70, while Grade 6 Math KKM is 75). `kkm` is stored as a decimal column on `class_subjects`.
- **Expanded Teacher Authorization Scope**:
  - Updates the authorization rules established in spec 01: a teacher can access class academic views if:
    $$\text{User is Homeroom Teacher: } classes.teacher\_id = \text{auth()->teacher->id}$$
    $$\lor$$
    $$\text{User is Subject Teacher: } \exists cs \in class\_subjects: cs.class\_id = classes.id \land cs.teacher\_id = \text{auth()->teacher->id}$$

## Requirements

1. **Subject Catalog Management (`/admin/subjects`)**:
   - Admin CRUD operations for `subjects`.
   - Fields: `name` (required, unique, e.g. "Pendidikan Agama Islam"), `code` (required, unique, e.g. "PAI", uppercase), `description` (optional, string), `is_active` (boolean, default true).
   - Deletion guard: A subject referenced by any historical `class_subjects` record cannot be deleted (HTTP 422). Deactivation (`is_active = false`) is supported.
2. **Class Subject Assignment (`/admin/classes/{id}/subjects`)**:
   - Admin can view, assign, edit, and remove subjects assigned to a class in the active academic year.
   - Form fields: `subject_id` (required, dropdown of active subjects), `teacher_id` (required, dropdown of active teachers), `kkm` (decimal, default 75.0, range 0.0 to 100.0).
   - Unique constraint: A subject can only be assigned once per class (`UNIQUE(class_id, subject_id)`).
   - Removal guard: A `class_subject` that contains student grades (spec 10) cannot be deleted.
3. **Teacher My Courses Portal (`GET /teacher/courses`)**:
   - Lists all `class_subjects` assigned to the authenticated teacher in the active academic year.
   - Shows Class Name, Subject Name, Subject Code, KKM, and enrolled student count.
   - Clicking a course navigates to the course workspace (Roster view and Grading book; spec 10).
4. **Homeroom Class Directory View**:
   - Homeroom teachers can view a "Teachers & Subjects" tab on their class dashboard showing all subjects and instructors for their classroom.

## Schema

### 1. `subjects` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `code` | varchar(20) | not null, unique | Subject shorthand code (e.g. "MAT", "IPA") |
| `name` | varchar(255) | not null, unique | Official subject title |
| `description` | text | nullable | Curricular description or notes |
| `is_active` | boolean | not null, default: true | Status flag |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (code)`
- `UNIQUE (name)`

### 2. `class_subjects` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `class_id` | bigint | unsigned, not null | Foreign key -> `classes.id` |
| `subject_id` | bigint | unsigned, not null | Foreign key -> `subjects.id` |
| `teacher_id` | bigint | unsigned, not null | Foreign key -> `teachers.id` (Subject instructor) |
| `kkm` | decimal(5,2) | not null, default: 75.00 | Minimum passing score |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (class_id, subject_id)`
- `INDEX (teacher_id)`
- `INDEX (class_id)`

## Acceptance Criteria

- **AC-09-01**: Given an admin creates a subject with code "MAT" and name "Matematika", attempting to create another subject with the same code or name returns validation error HTTP 422.
- **AC-09-02**: Given Class 5A in active academic year 2026/2027, assigning "Matematika" with Teacher Budi and KKM 75 creates a `class_subjects` record successfully.
- **AC-09-03**: Attempting to assign "Matematika" a second time to Class 5A is rejected by the unique constraint with HTTP 422.
- **AC-09-04**: When Teacher Budi logs into `/teacher/courses`, Class 5A Matematika appears in their active teaching list.
- **AC-09-05**: Teacher Siti (who does not teach 5A Matematika and is not 5A's homeroom teacher) attempting to view 5A Matematika course data receives HTTP 403 Forbidden.
- **AC-09-06**: An admin attempting to delete a `subjects` row that is assigned to any `class_subjects` record receives HTTP 422 with a descriptive dependency error.

## Constraints & Assumptions

- Subjects master catalog is single-school scope.
- Teaching assignments are tied directly to existing `classes` and automatically adhere to the active academic year bounds.

## Open Questions

- `[NEEDS DECISION: Subject Category / Grouping]`: Indonesian Kurikulum Merdeka groups subjects into "Kelompok Umum" and "Kelompok Pilihan/Muatan Lokal". Should `subjects` include an enum `group` column (`general`, `local_content`, `elective`)? (Recommended for v1.1 to assist report card formatting).
