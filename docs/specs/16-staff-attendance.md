# 16 — Employee Master & Staff Attendance (Pegawai & Presensi Guru/Tendik)

Status: draft v1.0 (2026-10-01) — resolves AUDIT-2026-10-01 F-01 (decided for v1: teachers **and** non-teaching staff; scanner only, no GPS check-in). Depends on spec 15.

## Problem

Schools must track the attendance of teachers (guru) and education staff (tenaga kependidikan / tendik: TU, librarian, lab assistant, security, cleaning). Today this is a paper sign-in book (buku daftar hadir), which causes several problems:
1. The principal cannot see in the morning which teachers are absent, so classes are left without a teacher and substitutes are arranged late.
2. Monthly recaps used for performance evaluation, honorarium of part-time teachers (guru honorer) and allowance (tunjangan) eligibility are tallied by hand, slowly and inaccurately.
3. Paper books are easy to backfill.

Presensio already has gate scanners, a calendar, and a sweep engine for students (03). However, it has no master record for non-teaching staff: `teachers` only covers teaching personnel, and holds the profile fields directly.

## Goals

1. Introduce an `employees` master covering all personnel; `teachers` becomes the teaching extension of an employee.
2. Record employee arrival and departure via the existing RFID / dynamic QR scanners.
3. Derive lateness, early departure and missing check-out against configurable working hours and per-employee working days.
4. Auto-mark absent employees on working days after a morning cutoff, so the principal knows early.
5. Let employees request sick leave, permission (izin), annual leave (cuti) and official duty (dinas luar). Approved requests pre-fill attendance.
6. Provide a monthly recap per employee and a daily overview, exportable to CSV.

## Non-goals

- GPS / selfie mobile check-in (decided: scanner only — fake-GPS risk).
- Payroll, honorarium or allowance calculation (the recap is the input; calculation is out of scope — README v2 candidate).
- Shift scheduling (e.g. security night shifts). v1 assumes one daily working window, with per-employee working days.
- Leave balance accounting (cuti quota). v1 records approved cuti days but does not enforce a quota.
- WhatsApp alerts about employee absence (the principal gets the dashboard list plus an in-app `staff_absence_digest`, spec 17).
- Per-period teaching attendance (jurnal mengajar) — v1.x, together with per-period student attendance (13).

## User Stories

- **As a teacher**, I want to tap my card at the gate when I arrive and when I leave.
- **As the principal at 07:45**, I want to see which teachers have not arrived, so I can arrange substitutes for their first periods.
- **As a part-time teacher who works only Monday and Wednesday**, I want not to be marked absent on other days.
- **As a TU staff member**, I want to submit a cuti request for three days and see whether it was approved.
- **As an admin**, I want a monthly recap per employee: working days, present, late minutes, early-leave minutes, sick, izin, cuti, dinas luar, alpa.
- **As an admin during a scanner outage**, I want to record employees' arrival times manually.

## Decisions

- **Employee master**:
  - `employees` holds identity for all personnel: name, employee number (NIP/NUPTK/NIY), phone, employment type, position, active flag, optional `user_id`, and working days.
  - `teachers` keeps its `id` (so `classes.teacher_id` and `class_subjects.teacher_id` stay valid) and gains `employee_id` (unique). `teachers.name`, `teacher_number`, `phone_number` and `user_id` move to `employees`.
  - Data migration: one employee per existing teacher.
- **Login & role**: an employee with a login links through `employees.user_id`. New role `staff` (added to spec 15's enum) gives employee self-service: own attendance and leave requests. Teachers already get the same self-service through `teacher`. The authenticated teacher is resolved as user → employee → teacher.
- **Working days**: `employees.working_days` (ISO weekdays, nullable). Null = all operational days (`school_operational_days`, 03). An employee is *expected* on a date when that date is a school day (03 `isSchoolDay`) **and** a working day for them.
- **Working hours** (settings): `staff_start_time` (default 07:00), `staff_end_time` (default 14:00), `staff_absent_sweep_time` (default 09:00).
- **Scanner integration** (amends 03):
  - `rfid_cards` gains `employee_id`; a card belongs to exactly one student **or** one employee, or is unassigned.
  - Dynamic QR tokens carry a subject type (`student` / `employee`); the employee QR page is `GET /my-qr` for employee users.
  - `scan_events` gains `employee_id`.
  - The endpoint, keys, throttle, drift tolerance and debounce are shared with students.
- **Employee tap resolution**:
  1. Employee inactive → `ignored_inactive`, no record.
  2. Record is `sick`, `leave`, `annual_leave` or `official_duty` → `ignored_excused`.
  3. No record, or an `absent` record from the sweep → check-in. Status `present` if at or before `staff_start_time`, else `late` (`late_minutes` stored). Outcome `check_in` or `absent_upgraded`.
  4. Already checked in, within debounce → `ignored_debounce`.
  5. Already checked in, beyond debounce → set `checked_out_at` to this tap (the latest tap wins) and recompute `early_leave_minutes` against `staff_end_time`. Outcome `check_out`.
- **Taps on non-expected days** (e.g. a weekend event) create a normal record. Recaps count them as *extra days* and do not add them to expected days.
- **Absent sweep**:
  - At `staff_absent_sweep_time` on school days, the sweep creates `absent` records for active employees expected that day who have no record.
  - Separate scheduled command `staff-attendance:mark-absences`; idempotent.
- **Missing check-out**: a record with `checked_in_at` and no `checked_out_at` at day end is shown as "tidak absen pulang". Nothing is written; it is derived in reports.
- **Manual override**: admins create or edit any `(employee_id, date)` record (upsert, like 03). The override is stamped with `override_by_user_id` and `overridden_at`, and audited (15).
- **Leave requests** (pattern from spec 04):
  - Types: `sick`, `leave` (izin), `annual_leave` (cuti), `official_duty` (dinas luar), with optional attachment (surat dokter / surat tugas).
  - Reviewed by `principal` or `admin`.
  - On approval, the expected days in the range are upserted with the matching status. Non-expected days are skipped.
  - The employee can cancel while the request is `pending`.
  - Overlap with a `pending` or `approved` request → HTTP 422.
- **Privacy**: employees see only their own records. Admins and principals see all. Teachers do not see colleagues' attendance.

## Requirements

1. **Employee Management (`/admin/employees`)**:
   - CRUD: name, `employee_number`, phone, `employment_type` (`pns`, `pppk`, `permanent` = GTY/PTY, `contract` = GTT/PTT, `honorary`), `position` (free text, e.g. "Guru Kelas", "Staf TU", "Satpam"), `working_days`, `is_active`, linked user.
   - "Is a teacher" toggle creates/removes the linked `teachers` row. Removal is blocked while the teacher is referenced by classes or assignments.
   - RFID card assignment for employees (extends spec 02 RFID management).
2. **Scanner** (`POST /api/attendance/scan`, spec 03): resolves the credential to a student or an employee; employee taps follow the resolution above. The response contract (03) is unchanged: `student_name` carries the employee's name for employee taps. Firmware compatibility rules out renaming it; an additive `subject_type` (`student` / `employee`) field may be added.
3. **Daily Overview (`GET /admin/staff-attendance?date=`)**:
   - Expected employees with status, check-in, check-out, late minutes and early-leave minutes. Filters: not yet arrived / late / absent / on leave.
   - Manual create/edit per row.
4. **Leave Requests**:
   - Employee: `GET /my-leave`, `POST /my-leave`, `DELETE /my-leave/{id}` (pending only).
   - Reviewer: `GET /admin/staff-leave`, `PUT …/{id}/approve` (optional note), `PUT …/{id}/reject` (required note).
5. **Monthly Recap (`GET /admin/staff-attendance/recap?month=`)**:
   - Per employee:
     - Expected days and extra days.
     - Hadir (present + late), terlambat (count, total minutes), pulang cepat (count, total minutes), tidak absen pulang.
     - Sakit, izin, cuti, dinas luar, alpa.
     - Attendance rate = (hadir + dinas luar) / expected days.
   - CSV export (UTF-8 BOM, spec 06 conventions).
6. **Self-Service (`GET /my-attendance` for employees)**: today's card and monthly history.
7. **Dashboard (08)**:
   - Admin/principal: today's staff totals (expected, arrived, late, not yet arrived, on leave) and a list of not-yet-arrived **teachers** with their first period today (when timetable 13 exists).
   - Employee: own today card.

## Schema

### 1. `employees` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `user_id` | bigint | unsigned, nullable, unique | FK -> `users.id` |
| `name` | varchar(255) | not null | Full name with titles |
| `employee_number` | varchar(50) | nullable, unique | NIP / NUPTK / NIY |
| `phone_number` | varchar(30) | nullable | |
| `employment_type` | varchar(20) | not null | Enum: `pns`, `pppk`, `permanent`, `contract`, `honorary` |
| `position` | varchar(100) | nullable | |
| `working_days` | json | nullable | ISO weekdays; null = all operational days |
| `is_active` | boolean | not null, default: true | |
| `created_at` / `updated_at` | timestamp | nullable | |

### 2. `teachers` — changed

Adds `employee_id` (bigint, unsigned, not null, unique, FK -> `employees.id`). Removes `name`, `teacher_number`, `phone_number`, `user_id` after the data migration.

### 3. `employee_attendances` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `employee_id` | bigint | unsigned, not null | FK -> `employees.id` |
| `date` | date | not null | |
| `status` | varchar(20) | not null | Enum: `present`, `late`, `absent`, `sick`, `leave`, `annual_leave`, `official_duty` |
| `checked_in_at` | timestamp | nullable | |
| `checked_out_at` | timestamp | nullable | Latest check-out tap |
| `late_minutes` | smallint | unsigned, not null, default: 0 | |
| `early_leave_minutes` | smallint | unsigned, not null, default: 0 | |
| `scan_method` | varchar(30) | nullable | `rfid`, `dynamic_qr`, `manual_override`, null (sweep / leave) |
| `override_by_user_id` | bigint | unsigned, nullable | FK -> `users.id` |
| `overridden_at` | timestamp | nullable | |
| `notes` | varchar(255) | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (employee_id, date)`, `INDEX (date, status)`

### 4. `employee_leave_requests` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `employee_id` | bigint | unsigned, not null | FK -> `employees.id` |
| `type` | varchar(20) | not null | Enum: `sick`, `leave`, `annual_leave`, `official_duty` |
| `start_date` | date | not null | |
| `end_date` | date | not null | ≥ `start_date` |
| `reason` | text | not null | |
| `attachment_path` | varchar(255) | nullable | Private storage |
| `status` | varchar(20) | not null, default: 'pending' | Enum: `pending`, `approved`, `rejected`, `cancelled` |
| `review_note` | text | nullable | |
| `reviewed_by_user_id` | bigint | unsigned, nullable | FK -> `users.id` |
| `reviewed_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `INDEX (employee_id, start_date, end_date)`, `INDEX (status)`

### 5. Changes to existing tables

- `rfid_cards`: add `employee_id` (nullable, FK); check that at most one of `student_id` / `employee_id` is set.
- `scan_events`: add `employee_id` (nullable); add outcome `ignored_inactive`.
- `settings`: add `staff_start_time` (time, default `07:00`), `staff_end_time` (time, default `14:00`), `staff_absent_sweep_time` (time, default `09:00`).

## Acceptance Criteria

- **AC-16-01**: After migration, every existing teacher has exactly one employee with the same name, number, phone and user link, and all `classes.teacher_id` values still resolve.
- **AC-16-02**: An employee tapping at 06:50 (start 07:00) gets `present`; at 07:20 gets `late` with `late_minutes = 20`.
- **AC-16-03**: A second tap at 13:30 (end 14:00) sets `checked_out_at` and `early_leave_minutes = 30`; a later tap at 14:10 overwrites `checked_out_at` and resets `early_leave_minutes` to 0.
- **AC-16-04**: An RFID card cannot be assigned to an employee while it is assigned to a student (HTTP 422).
- **AC-16-05**: The sweep on a school day marks absent an expected employee without a record. It skips a part-time employee whose `working_days` excludes that weekday, and skips everyone on a `non_school_days` date.
- **AC-16-06**: An employee marked absent by the sweep who taps at 09:30 is upgraded to `late` with outcome `absent_upgraded`.
- **AC-16-07**: Approving a 3-day `annual_leave` request covering a weekend writes records only on expected days; taps on those days are `ignored_excused`.
- **AC-16-08**: Submitting a leave request overlapping an approved one returns HTTP 422; cancelling a pending request sets `cancelled`; cancelling an approved one returns HTTP 422.
- **AC-16-09**: The monthly recap for an employee with 20 expected days, 17 present (2 late, 25 minutes total), 1 dinas luar, 1 sakit, 1 alpa shows rate (17 + 1) / 20 = 90%.
- **AC-16-10**: A teacher requesting another employee's attendance receives HTTP 403; the principal can view the daily overview and approve leave; a `staff` user cannot approve leave (HTTP 403).
- **AC-16-11**: An inactive employee's tap logs `ignored_inactive` and creates no record.

## Constraints & Assumptions

- Single daily working window for all employees in v1.
- Requires spec 15 (roles incl. `staff`, audit log) and amends 02 (`teachers` profile fields move to `employees`) and 03 (scanner credential resolution, `rfid_cards`, `scan_events`).

## Open Questions

- `[NEEDS DECISION: Per-employee working hours]`: Some staff (security, cleaning) work different hours. Per-employee or per-position hours are a v1.x candidate.
- `[NEEDS DECISION: Cuti quota]`: Track annual leave entitlement and balance per employee? (v1.x.)
