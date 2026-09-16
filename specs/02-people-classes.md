# 02 — People & Classes (Admin CRUD)

Status: draft v1.1 (2026-09-16)

## Purpose

The master data entities everything else depends on: students, teachers, guardians, classes, and enrollment. 

## Core Principles & Decisions

- **Separation of Profile and Login**: The `users` table is maintained strictly for authentication and authorization. Each human actor (Student, Teacher, Guardian) has their own dedicated "profile" table to store domain-specific attributes (like a Guardian's address, or a Teacher's NIP). A profile can optionally be linked (1:1) to a `users` account if they require login capabilities.
- **Configurability over Hardcoding**: Business rules (like strict 1:1 teacher-to-class ratios) must be driven by application configurations rather than hardcoded logic or strict database constraints. This ensures the system remains scalable.
- **Classes**: A **class** has a name and a homeroom teacher (linked to `teachers`, not `users`). 
- **Enrollment**: A student is enrolled in **exactly one class** at a time (`class_id` on the student record). No academic-year history entity is tracked in v1 (one active roster).

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

## Requirements

1. Admin-only CRUD screens for Teachers, Guardians, Students, and Classes.
2. Creating/editing a student allows assigning their class and linking guardian profiles.
3. Enrolling a student into a class replaces any previous enrollment (`class_id` is updated). Past attendance records strictly remain intact.
4. **Validation via Config**: Assigning a homeroom teacher checks a global application configuration. If the rule `allow_multiple_homerooms` is set to false, the application rejects the assignment of a teacher who already has one.
5. **Deletion Constraints**: 
   - Admin cannot delete a class if it still has students enrolled or historical attendance activity tied to it.
6. UI List screens support robust search across the dedicated tables, and pagination. (e.g. search students by `full_name` or `nickname`).

## Out of scope

- Academic years, subjects, timetables, room assignments
- Bulk import (CSV) of master data — candidate for v1.x
