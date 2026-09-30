# 02 — People, Classes & Enrollment (Master Data & Rostering)

Status: v2.0 — implemented 2026-09-19 (enrollment model 2026-09-21, amended 2026-09-25)

## Problem

School administrations face dynamic student rosters: cohorts promote annually, students transfer between classes mid-year, and siblings share parental guardians. Traditional educational software that stores a static `class_id` foreign key on the `students` table fundamentally breaks historical reporting: changing a student's class corrupts past attendance records or makes historical class rosters impossible to reconstruct. Furthermore, initial onboarding is often delayed because school administrative staff cannot conform to rigid spreadsheet templates, having already formatted their student rosters in varied Indonesian spreadsheet formats (e.g. "TGL LAHIR", "Tgl. Lahir", "DOB").

## Goals

1. Decouple student identity from class assignment by establishing `enrollments` as the sole historical source of truth for class membership.
2. Provide full administrative CRUD operations for Teachers, Guardians, Students, Classes, Academic Years, and RFID scanner cards.
3. Deliver an intelligent, resilient bulk spreadsheet importer (CSV/XLSX) capable of auto-detecting Indonesian school column aliases, remembering custom mappings, running dry-run previews, and handling partial imports.
4. Expand student and guardian domain data to reflect Indonesian school records (`gender`, `birth_place`, `religion`, `address`, and guardian relationship type).
5. Empower guardians to maintain their own contact data directly to ensure high delivery rates for emergency and absence notifications.

## Non-goals

- Direct manual editing of raw historical enrollment date ranges via generic CRUD screens (changes happen via atomic transfer or roll-over workflows; spec 07).
- Bulk import for teachers and standalone guardians in v1 (student-driven import auto-provisions linked guardians).
- Multi-campus or multi-branch educational institutions (single school instance).
- Subjects, timetables, and period-level teaching assignments (specified in spec 09 and spec 13).
- Student disciplinary, health, or psychological counseling records in v1.

## User Stories

- **As an admin**, I want to upload an Excel file from the administrative office with varying headers ("NAMA SISWA", "TGL LHR", "KELAS", "HP WALI") and have the system auto-suggest the column mapping, so I can import 500 students in under two minutes without retyping.
- **As an admin**, I want to transfer a student from Class 5A to Class 5B in the middle of October, so that September attendance is permanently credited to 5A while October onward attributes to 5B.
- **As an admin**, I want to register a physical RFID card and bind it to a student, so that the student can tap to check in at the school gate scanner.
- **As a teacher**, I want to see an up-to-date roster of students currently enrolled in my homeroom class, including contact details of their primary guardian.
- **As a guardian**, I want to update my phone number and occupation in my profile, so that the school always has accurate contact information without administrative delays.

## Decisions

- **Separation of Profile and Login**: As established in spec 01, authentication credentials live strictly in the `users` table. `teachers`, `students`, and `guardians` store real-world domain master data. A profile links 1:1 to a `users` row via nullable `user_id`.
- **Enrollment as Single Source of Truth**: A student's class on any given date is strictly derived via `classOn(date)` from the `enrollments` table. The `students` table has **no** `class_id` column, and the `attendances` table has **no** `class_id` column.
- **Academic Years are Structural**: Classes belong to an academic year (`academic_year_id`). Class "5A 2026/2027" and "5A 2027/2028" are distinct rows. Once an academic year ends, its classes become immutable historical records. Exactly one academic year is active (`is_active = true`) at any time.
- **Single Open Enrollment Constraint**: A student may have at most one active enrollment (`ended_on IS NULL`). This invariant is enforced by a partial unique index at the database level and verified by application-level overlap guards.
- **Indonesian Profile Fields**: School records in Indonesia require gender (`L`/`P`), birthplace, religion, and student identification numbers (NIS/NISN). Sibling relationships link to shared guardian records, differentiated by `relationship_type` (`father`, `mother`, `guardian`).
- **Flexible Column Mapping with Memory**: The bulk importer matches uploaded headers against an alias dictionary. Custom mapping selections are persisted per file fingerprint in `student_import_mappings` to bypass mapping confirmation on re-imports.

## Requirements

1. **Master CRUD Operations**:
   - Admin CRUD for Teachers, Guardians, Students, Classes, and Academic Years.
   - Deletion of an academic year with existing classes or enrollments is prohibited (HTTP 422).
   - Deletion of a class with any historical enrollment is prohibited (HTTP 422).
   - Deletion of a student with historical attendance, excuses, or enrollments is prohibited (HTTP 422).
2. **Student Enrollment Lifecycle**:
   - Creating a new student assigns them to a class in the active academic year by automatically inserting an open `enrollments` record (`started_on = today`, `ended_on = null`).
   - Transferring a student mid-year closes the current enrollment (`ended_on = transfer_date - 1 day`) and opens a new enrollment in the target class in an atomic database transaction.
   - The application enforces that a student's enrollment date ranges never overlap.
3. **Homeroom Teacher Assignment**:
   - Assigning a teacher to a class in an academic year evaluates the `allow_multiple_homerooms` configuration. When false, assigning a teacher already holding a homeroom in that same year is rejected.
4. **RFID Card Management**:
   - Admin can register RFID card serial numbers (`rfid_number`).
   - Cards can be assigned to, unassigned from, or reassigned between students. One card belongs to at most one student at a time; a student may hold replacement cards.
5. **Bulk Student Importer**:
   - Accepts CSV, XLS, and XLSX files up to 10MB.
   - Uses fuzzy string matching and an Indonesian alias dictionary (e.g. `['tgl lahir', 'tanggal lahir', 'dob'] -> 'dob'`).
   - Admin can review and override detected mappings via a dropdown interface.
   - Remembers mapping configurations in `student_import_mappings`.
   - Executes a non-destructive dry-run preview reporting valid row counts and specific per-row errors (e.g. invalid date format, missing required full name).
   - Imports valid rows transactionally or in batches, reporting row-level failure logs for unprocessable rows.
   - Parses dates defensively, accommodating formats (`DD/MM/YYYY`, `DD-MM-YYYY`, `YYYY-MM-DD`, and Excel numerical date serials).
   - Deduplicates incoming guardian rows by `phone_number`: if a guardian with the given phone number already exists, links the existing guardian to the student rather than creating a duplicate.
6. **Guardian Self-Service Updates**:
   - Authenticated guardians can edit their own `phone_number`, `work`, and `address`.
   - Guardians cannot edit their `name`, nor can they modify student-guardian relationship linkages.

## Schema

### 1. `teachers` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `user_id` | bigint | unsigned, nullable, unique | Link to `users.id` |
| `name` | varchar(255) | not null | Full teacher name with academic titles |
| `teacher_number` | varchar(50) | nullable, unique | NIP / NUPTK |
| `phone_number` | varchar(30) | nullable | WhatsApp / contact number |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

### 2. `guardians` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `user_id` | bigint | unsigned, nullable, unique | Link to `users.id` |
| `name` | varchar(255) | not null | Full name of guardian |
| `phone_number` | varchar(30) | not null | Primary contact / WhatsApp |
| `work` | varchar(255) | nullable | Occupation / job title |
| `address` | text | nullable | Residential address |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

### 3. `students` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `user_id` | bigint | unsigned, nullable, unique | Link to `users.id` |
| `full_name` | varchar(255) | not null | Legal name per birth certificate / KK |
| `nickname` | varchar(100) | nullable | Preferred daily call name |
| `dob` | date | not null | Date of birth |
| `student_number` | varchar(50) | nullable, unique | NIS / NISN |
| `gender` | varchar(1) | not null, default: 'L' | Gender (`L`/`P`) |
| `birth_place` | varchar(100) | nullable | City / regency of birth |
| `religion` | varchar(50) | nullable | Religion (e.g. Islam, Kristen, Katolik, Hindu, Buddha, Konghucu) |
| `address` | text | nullable | Residential address |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |
### 4. `academic_years` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `name` | varchar(50) | not null, unique | e.g. "2026/2027" |
| `starts_at` | date | not null | Start of school year |
| `ends_at` | date | not null | End of school year |
| `is_active` | boolean | not null, default: false | Exactly one active year flag |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

### 5. `classes` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `academic_year_id` | bigint | unsigned, not null | Foreign key -> `academic_years.id` |
| `name` | varchar(50) | not null | Class name (e.g. "5A", "7B") |
| `teacher_id` | bigint | unsigned, nullable | Foreign key -> `teachers.id` (Homeroom teacher) |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (academic_year_id, name)`
- `INDEX (teacher_id)`

### 6. `enrollments` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` |
| `class_id` | bigint | unsigned, not null | Foreign key -> `classes.id` |
| `started_on` | date | not null | Effective date of class entry |
| `ended_on` | date | nullable | Effective date of class exit (null = active) |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- Partial Unique Index: `CREATE UNIQUE INDEX unique_open_enrollment ON enrollments (student_id) WHERE ended_on IS NULL;`
- `INDEX (class_id, started_on, ended_on)`

### 7. `guardian_student` Table (Pivot)

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `guardian_id` | bigint | unsigned, not null | Foreign key -> `guardians.id` (cascade delete) |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` (cascade delete) |
| `relationship_type` | varchar(20) | not null, default: 'guardian' | Relationship (`father`, `mother`, `guardian`) |
**Primary Key & Indexes:**
- `PRIMARY KEY (guardian_id, student_id)`

### 8. `rfid_cards` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `rfid_number` | varchar(100) | not null, unique | Physical card hexadecimal/decimal UID |
| `student_id` | bigint | unsigned, nullable | Foreign key -> `students.id` (null = unassigned) |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

### 9. `student_import_mappings` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `header_fingerprint` | varchar(64) | not null, unique | SHA-256 hash of normalized file header signature |
| `mapping` | json | not null | Persisted column mapping dictionary |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

## Acceptance Criteria

- **AC-02-01**: Given a student with an open enrollment in 5A from 2026-07-01, when querying `classOn('2026-08-15')`, the system returns Class 5A.
- **AC-02-02**: Given a student with open enrollment in 5A, when transferred to 5B on 2026-10-01, the 5A enrollment is updated with `ended_on = '2026-09-30'` and a new enrollment in 5B is created with `started_on = '2026-10-01'`.
- **AC-02-03**: An attempt to insert a second open enrollment (`ended_on IS NULL`) for a single student violates the unique constraint and is rejected.
- **AC-02-04**: Uploading an Excel file with headers "Nama Lengkap", "Tgl Lahir", "Kelas", "Nomor HP Wali" correctly auto-suggests mapping to `full_name`, `dob`, `class`, and `guardian_phone`.
- **AC-02-05**: Importing two student rows sharing the exact same `guardian_phone` creates two students linked to a single deduplicated guardian record.
- **AC-02-06**: An attempt to delete a class that has existing historical enrollments returns HTTP 422 with a clear explanatory error.
- **AC-02-07**: A guardian submitting an update to their own `phone_number` updates `guardians.phone_number` successfully, while attempting to change `student_id` links via that endpoint is rejected.

## Constraints & Assumptions

- Database supports partial unique indexes (SQLite, PostgreSQL, MySQL 8.0+ functional index).
- Import file parsing uses OpenSpout / Laravel Excel for memory-efficient streaming of large spreadsheets.
- Exactly one academic year is marked `is_active = true` at all times.

## Open Questions

- `[NEEDS DECISION: Dual Guardian Import Rows]`: When a spreadsheet provides separate columns for "Ayah" and "Ibu", should the importer create two guardian records linked as `father` and `mother` respectively? (Supported in manual CRUD; import parser v1 reads primary guardian column; dual-column mapping planned for v1.1).
