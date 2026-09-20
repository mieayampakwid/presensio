# 02 — People, Classes & Enrollment (Admin CRUD)

Status: draft v2.0 (2026-09-21)

## Purpose

The master data entities everything else depends on: academic years, classes, enrollments, students, teachers, guardians, and RFID cards.

## Core Principles & Decisions

- **Separation of Profile and Login**: The `users` table is maintained strictly for authentication and authorization. Each human actor (Student, Teacher, Guardian) has their own dedicated "profile" table to store domain-specific attributes (like a Guardian's address, or a Teacher's NIP). A profile can optionally be linked (1:1) to a `users` account if they require login capabilities.
- **Configurability over Hardcoding**: Business rules (like strict 1:1 teacher-to-class ratios) must be driven by application configurations rather than hardcoded logic or strict database constraints. This ensures the system remains scalable.
- **Academic years are structural** (v2.0, supersedes the v1 "one active roster" model — decision 2026-09-21): classes are instantiated per academic year; "5A 2026/2027" and "5A 2027/2028" are distinct rows, and once a year ends its classes become immutable history. Exactly one academic year is active at a time.
- **Enrollment is the source of truth** for class membership: a student's class on any date is *derived* from their enrollment history — never stored as a pointer on the student and never stamped onto attendance. A student has at most one open enrollment, enforced at the database level.
- **Bulk Import via Column Mapping, Not Rigid Templates**: Schools will not retype existing data into our template. The importer reads any CSV/XLSX, auto-guesses the column mapping (seeded Indonesian school aliases + fuzzy matching), and lets the admin confirm or adjust once — meeting the school's file where it is.

## Schema & Data Models

### 1. `users` Table (Authentication Only)
- `id` (primary)
- `username` / `identity_number` (string, unique) — The credential used for login (Email/NIK/NIP/NIS).
- `password` (string)
- `role` (enum: admin, teacher, parent, student)

### 2. `teachers` Table (Master Data)
- `id` (primary)
- `user_id` (foreign, nullable, unique) — Links down to login account.
- `name` (string)
- `teacher_number` (string, nullable) — NIP/NUPTK.
- `phone_number` (string, nullable)

### 3. `guardians` Table (Master Data)
- `id` (primary)
- `user_id` (foreign, nullable, unique) — Links down to login account.
- `name` (string)
- `phone_number` (string)
- `work` (string, nullable) — Occupation.
- `address` (text, nullable)

### 4. `students` Table (Master Data)
- `id` (primary)
- `user_id` (foreign, nullable, unique) — Links down to login account.
- `full_name` (string) — Nama lengkap sesuai dokumen resmi.
- `nickname` (string, nullable) — Nama panggilan sehari-hari.
- `dob` (date)
- `student_number` (string, unique, nullable) — NIS/NISN.
- *No class column* — current class is a derived relation from `enrollments` (`currentEnrollment()`, `classOn(date)` are the only sanctioned access paths; spec 08).

### 5. `academic_years` Table
- `id` (primary)
- `name` (string, unique) — e.g. "2026/2027".
- `starts_at`, `ends_at` (date) — Default bounds for enrollments and roll-over.
- `is_active` (boolean) — Exactly one active year.

### 6. `classes` Table
- `id` (primary)
- `academic_year_id` (foreign, constrained) — Classes belong to exactly one year; unique `(name, academic_year_id)`.
- `name` (string)
- `teacher_id` (foreign, nullable) — Homeroom teacher, per year. Links to `teachers` table. (DB allows duplicates across/within years to support soft-configuration constraints.)

### 7. `enrollments` Table (Class Membership History)
- `id` (primary)
- `student_id` (foreign, constrained)
- `class_id` (foreign → classes, constrained)
- `started_on`, `ended_on` (date; `ended_on` null = open enrollment)
- Partial unique: one open enrollment per student (`student_id where ended_on is null`); invariant: a student's enrollment periods never overlap (app-level overlap guard).

### 8. `guardian_student` Table (Pivot)
- `guardian_id` (foreign -> guardians)
- `student_id` (foreign -> students)

### 9. `rfid_cards` Table (Scanner Credentials)
- `id` (primary)
- `rfid_number` (string, unique) — The physical card identifier read by hardware scanners (Spec 03).
- `student_id` (foreign, nullable) — Current owner; `null` = unassigned (spare/returned). Multiple cards may point to one student (e.g., a replacement while the original is lost).

## Requirements

1. Admin CRUD screens for Teachers, Guardians, Students, Classes, and **Academic Years** (create/edit name and date bounds; set exactly one active year; a year with classes or enrollments cannot be deleted).
2. Creating/editing a student assigns their class **in the active year** — the write creates (or replaces) the open enrollment — and allows linking guardian profiles.
3. Moving a student mid-year ends the open enrollment and opens a new one **atomically** (single transaction); past attendance records stay intact and keep attributing to the class that was active on their dates (spec 08).
4. **Validation via Config**: Assigning a homeroom teacher checks a global application configuration. If the rule `allow_multiple_homerooms` is set to false, the application rejects the assignment of a teacher who already has one **in that academic year**.
5. **Deletion Constraints**:
   - A class cannot be deleted while any enrollment references it (ever).
   - A student with attendance or excuse history cannot be deleted (unchanged).
6. **RFID Card Management**: Admin registers cards and assigns/revokes card ownership to students. Spec 03's scanner resolves an `rfid_number` to its currently assigned `student_id`.
7. UI List screens support robust search across the dedicated tables, and pagination. (e.g. search students by `full_name` or `nickname`).
8. **Bulk Import (Students, CSV/XLSX)**:
   - Admin uploads a file; headers are detected and auto-mapped to target fields via a seeded Indonesian school alias dictionary (normalized + fuzzy matching: "TGL LHR " → `dob`). The mapping screen lets the admin override any guess via dropdown; required fields (`full_name`, `class`) must be mapped, unmapped optional columns are simply ignored.
   - The last successful mapping is remembered — a file with identical structure skips the mapping screen next time.
   - A dry-run validation previews the first rows and reports per-row errors (missing name, duplicate `student_number`, unparseable date) before anything commits. Valid rows import; erroneous rows are reported for correction — not all-or-nothing.
   - **Class column values map against the active year's classes only**, with an option to auto-create missing classes (created in the active year) — last year's same-named class is never reused.
   - Identifier and date columns are parsed defensively: always as strings (preserving leading zeros and 16-digit numbers like NIK), with Indonesian date formats (`dd/mm/yyyy`, `dd-mm-yyyy`, Excel serial dates) normalized.
   - Optional guardian columns (name + phone number) create and link a guardian profile, deduplicated by `phone_number` (siblings must not duplicate a guardian).
9. **Guardian Contact Self-Completion**: A logged-in guardian may edit their own profile's contact fields (`phone_number`, `address`, `work`) — the fields they are the source of truth for and where admin retyping is error-prone (one mistyped digit sends a child's absence notification to a stranger). `name` and guardian–student relations stay admin-managed (exception carved in spec 01, req 6).

## Out of scope

- Semester entities within a year; subjects; timetables; room assignments
- Roll-over/promotion, alumni semantics, and historical attribution rules — spec 08
- Direct editing of historical enrollment rows
- Bulk import for teachers / standalone guardians (students-first importer)
- RFID card event history (loss/reissue audit log)
