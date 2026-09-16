# 02 — People & Classes (Admin CRUD)

Status: draft v1 (2026-09-15)

## Purpose

The admin-managed entities everything else depends on: students, guardians, classes, and enrollment.

## Decisions

- A **student** has: first/last name, date of birth, optional student ID number. A student is NOT a login account — students with logins are `users` linked 1:1 to a student profile (optional link).
- **Guardians** are `users` with role `parent`, linked to students many-to-many (a guardian may have several children; a child may have several guardians).
- A **class** has: name and a homeroom teacher (a `user` with role `teacher`). A teacher homerooms at most one class.
- A student is enrolled in **exactly one class** at a time. Daily attendance is taken per class, so this stays 1:1.
- No academic-year entity in v1: one active roster, rollover handled manually next year (deferred, see Out of scope).

## Requirements

1. Admin-only CRUD screens for students, classes, and guardian↔student links.
2. Creating/editing a student allows assigning their class and linking guardian users.
3. Enrolling a student into a class replaces any previous enrollment (old enrollment is not kept as history in v1).
4. Deleting a student or class that has attendance records is rejected with a clear error (no soft deletes, no orphaned attendance).
5. Deleting a guardian user unlinks them; their children's records are untouched.
6. List screens support search by name and pagination.
7. All writes are validated (required fields, valid date of birth, unique email for users).

## Out of scope

- Academic years, subjects, timetables, room assignments
- Student transfer/enrollment history
- Bulk import (CSV) of students — candidate for v1.x
