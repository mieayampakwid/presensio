# 02 — People & Classes (Admin CRUD)

Status: draft v1.4 (2026-09-18)

## Purpose

The master data entities everything else depends on: students, teachers, guardians, classes, and enrollment. 

## Core Principles & Decisions

- **Separation of Profile and Login**: The `users` table is maintained strictly for authentication and authorization. Each human actor (Student, Teacher, Guardian) has their own dedicated "profile" table to store domain-specific attributes (like a Guardian's address, or a Teacher's NIP). A profile can optionally be linked (1:1) to a `users` account if they require login capabilities.
- **Configurability over Hardcoding**: Business rules (like strict 1:1 teacher-to-class ratios) must be driven by application configurations rather than hardcoded logic or strict database constraints. This ensures the system remains scalable.
- **Classes**: A **class** has a name and a homeroom teacher (linked to `teachers`, not `users`). 
- **Enrollment**: A student is enrolled in **exactly one class** at a time (`class_id` on the student record). No academic-year history entity is tracked in v1 (one active roster).
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
- `class_id` (foreign, nullable) — Current active enrollment (replaces complex history pivot in v1).

### 5. `classes` Table
- `id` (primary)
- `name` (string)
- `teacher_id` (foreign, nullable) — Homeroom teacher. Links to `teachers` table. (DB allows duplicates to support soft-configuration constraints).

### 6. `guardian_student` Table (Pivot)
- `guardian_id` (foreign -> guardians)
- `student_id` (foreign -> students)

### 7. `rfid_cards` Table (Scanner Credentials)
- `id` (primary)
- `rfid_number` (string, unique) — The physical card identifier read by hardware scanners (Spec 03).
- `student_id` (foreign, nullable) — Current owner; `null` = unassigned (spare/returned). Multiple cards may point to one student (e.g., a replacement while the original is lost).

## Requirements

1. Admin-only CRUD screens for Teachers, Guardians, Students, and Classes.
2. Creating/editing a student allows assigning their class and linking guardian profiles.
3. Enrolling a student into a class replaces any previous enrollment (`class_id` is updated). Past attendance records strictly remain intact.
4. **Validation via Config**: Assigning a homeroom teacher checks a global application configuration. If the rule `allow_multiple_homerooms` is set to false, the application rejects the assignment of a teacher who already has one.
5. **Deletion Constraints**: 
   - Admin cannot delete a class if it still has students enrolled or historical attendance activity tied to it.
6. **RFID Card Management**: Admin registers cards and assigns/revokes card ownership to students. Spec 03's scanner resolves an `rfid_number` to its currently assigned `student_id`.
7. UI List screens support robust search across the dedicated tables, and pagination. (e.g. search students by `full_name` or `nickname`).
8. **Bulk Import (Students, CSV/XLSX)**:
   - Admin uploads a file; headers are detected and auto-mapped to target fields via a seeded Indonesian school alias dictionary (normalized + fuzzy matching: "TGL LHR " → `dob`). The mapping screen lets the admin override any guess via dropdown; required fields (`full_name`, `class`) must be mapped, unmapped optional columns are simply ignored.
   - The last successful mapping is remembered — a file with identical structure skips the mapping screen next time.
   - A dry-run validation previews the first rows and reports per-row errors (missing name, duplicate `student_number`, unparseable date) before anything commits. Valid rows import; erroneous rows are reported for correction — not all-or-nothing.
   - Class column values map to existing `classes`, with an option to auto-create missing classes (file naming rarely matches the system's).
   - Identifier and date columns are parsed defensively: always as strings (preserving leading zeros and 16-digit numbers like NIK), with Indonesian date formats (`dd/mm/yyyy`, `dd-mm-yyyy`, Excel serial dates) normalized.
   - Optional guardian columns (name + phone number) create and link a guardian profile, deduplicated by `phone_number` (siblings must not duplicate a guardian).
9. **Guardian Contact Self-Completion**: A logged-in guardian may edit their own profile's contact fields (`phone_number`, `address`, `work`) — the fields they are the source of truth for and where admin retyping is error-prone (one mistyped digit sends a child's absence notification to a stranger). `name` and guardian–student relations stay admin-managed (exception carved in spec 01, req 6).

## Out of scope

- Academic years, subjects, timetables, room assignments
- Bulk import for teachers / standalone guardians (students-first importer)
- RFID card event history (loss/reissue audit log)
