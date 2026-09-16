# 03 — Attendance Recording (Dual IoT & Anti-Fraud Scanner)

Status: draft v2.0 (2026-09-16)

## Purpose

The core engine: Students independently log their attendance via Hardware Scanners (RFID or Secure Dynamic QR). The system natively guards against fraud (Buddy Punching), aggregates absences, and handles check-in/check-out policies autonomously.

## Decisions

- **Unified Identity Modality (RFID + Android/Web)**: System provides two streams for check-in to maximize flexibility and combat "titip absen" (buddy punching fraud).
  - *Standard RFID*: Fast, reliable, but susceptible to card sharing.
  - *Dynamic QR (Time-Based)*: Requires a student to be logged in via their personal device. Generates a cryptographic QR that expires quickly (e.g., 30s) to reject screenshots shared via chat apps.
- **Time Tracking & Debounce**: Accurately records `check_in_time` and `check_out_time`. Implements debounce minimum intervals to prevent accidental hardware double-taps.
- **Application Configurability**: Key business logic anchors on settings:
  - `school_start_time` (Time to determine `late` / keterlambatan)
  - `require_checkout` (Boolean to toggle Check-out tap logic)
  - `auto_absent_cron_time` (Sweep time)
- **Automatic Absent Generation (Cron)**: A background scheduler sweeps for missing students and generates `absent` statuses automatically. This triggers Spec 05 Absence Notifications reliably.

## Requirements

1. **Unified Scanner API Endpoint (`POST /api/attendance/scan`)**:
   - Secure and rate-limited. Parses incoming JSON payload containing credential type and token/string.
   - **QR Path (Anti-Fraud)**: Validates digital signature and Timestamp via server secret (`app_key`). If `(CurrentTime - TokenTimestamp)` > `30 seconds`, rejects payload with `Expired Token` (blocks screenshot fraud). Decodes valid token to extract `student_id`.
   - **RFID Path**: Look up `rfid_number` to get `student_id`.
   - **Logging Logic**: If no record exists today, sets `check_in_time`. If timestamp > `school_start_time`, marks status as `late`. Otherwise, marks `present`. On subsequent tap (if `require_checkout`), sets `check_out_time`.
2. **Student Portal - Dynamic QR Generator**:
   - A dedicated UI for logged-in `student` roles displaying an auto-refreshing QR code bound to their `student_id` and the rolling time clock.
3. **Auto Sweep (Cron Task)**:
   - At `auto_absent_cron_time`, queries active students missing a record today.
   - Creates a record with status `absent` (excluding weekends or pre-approved `excused` dates).
4. **Teacher & Admin Exception Dashboard**:
   - Authorized users can manually override/append an attendance status (e.g. if the IoT internet went down).
   - Any manual edit requires an optional `notes` context (e.g., "Card damaged") and rigidly logs the `override_by_user_id`.
5. **Data Overlap Shield (Spec 04 Synergy)**:
   - If an Administrator pre-approved an Excuse (Leave), an `excused` record is pre-created by the system. The Scanner API safely handles/ignores overlapping tap anomalies tied to excused students.

## Schema Expected (attendances)

- `id`
- `student_id`
- `date` (date, compound unique w/ student_id)
- `status` (enum: present, absent, late, excused)
- `check_in_time` (timestamp, nullable)
- `check_out_time` (timestamp, nullable)
- `scan_method` (string/enum, nullable) — Values: `rfid`, `dynamic_qr`, `manual_override`. (Ensures analytics on method adoption).
- `override_by_user_id` (foreign, nullable) 
- `notes` (string, nullable)

## Out of scope

- Per-period attendance (mapel-level entry).
- Timetables or Dynamic holiday calendars (handled centrally in v2).