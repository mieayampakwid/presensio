# 03 — Attendance Recording

Status: draft v1 (2026-09-15)

## Purpose

The core feature: teachers and admins mark daily attendance for a class.

## Decisions

- Statuses: `present`, `absent`, `late`, `excused`. `excused` is normally set by excuse approval (spec 04); admins may also set it directly.
- One record per student per day — unique constraint on (`student_id`, `date`).
- Teachers may create/edit records for **their own class for any date**; admins for any class. Corrections are made in place; no audit trail in v1 (`updated_at` only).
- Marking is done per class per date in one screen and one submit; the submit requires **every enrolled student to be marked** (no half-taken attendance).
- Absences on non-school days (weekends) cannot be recorded — the marking screen rejects weekend dates. A holiday calendar is deferred.

## Requirements

1. Teacher dashboard shows their class with a date picker (defaults to today) and the marking form: one status control per enrolled student.
2. Submitting stores/updates one record per student for that date; a second submit for the same date updates instead of duplicating.
3. A student enrolled mid-range only gets records from their enrollment onward; enrollment is effective-date-less (current roster only) in v1.
4. Admin dashboard lists all classes with today's marking completion (done/pending) and links into each marking screen.
5. Admin marking screen is identical to the teacher's, plus class selection.
6. Students and parents have read-only access to attendance records per spec 01 scoping.

## Out of scope

- Per-period attendance, timetables
- Holiday/inset-day calendar
- Audit log of edits
- Check-in kiosks, geolocation, QR self check-in
