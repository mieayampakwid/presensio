# 03 — Attendance Recording (Dual IoT & Anti-Fraud Scanner)

Status: draft v2.1 (2026-09-18)

## Purpose

The core engine: Students independently log their attendance via Hardware Scanners (RFID or Secure Dynamic QR). The system natively guards against fraud (Buddy Punching), aggregates absences, and handles check-in/check-out policies autonomously.

## Decisions

- **Unified Identity Modality (RFID + Android/Web)**: System provides two streams for check-in to maximize flexibility and combat "titip absen" (buddy punching fraud).
  - *Standard RFID*: Fast, reliable, but susceptible to card sharing.
  - *Dynamic QR (Time-Based)*: Requires a student to be logged in via their personal device, raising the fraud bar from "borrow a card" to "share live credentials + screen". Codes expire quickly (e.g., 30s) to reject screenshots shared via chat apps. Residual risk (relaying a live screen over a video call) is accepted in v1.
- **Server-Issued QR Tokens**: The QR content is a token signed with a server-side signing secret and issued by an authenticated, student-only endpoint. The client only requests and renders it — the secret never reaches any client. The UI refreshes the code every ~25s so a valid code is always on display.
- **Scanner Authentication**: Hardware scanners authenticate with a shared device API key (config) on the scan endpoint. Per-device keys and a device registry are deferred (v1.x).
- **Time Tracking & Debounce**: Accurately records `check_in_time` and `check_out_time`. A configurable debounce window (`scan_debounce_minutes`, default 1) turns taps following a check-in into no-ops — a hardware double-tap must never become an instant check-out.
- **Application Configurability**: Key business logic anchors on settings (all evaluated in the school's timezone):
  - `school_start_time` (Time to determine `late` / keterlambatan)
  - `require_checkout` (Boolean to toggle Check-out tap logic)
  - `auto_absent_cron_time` (Sweep time)
  - `scan_debounce_minutes` (Double-tap shield)
- **Automatic Absent Generation (Cron)**: A background scheduler sweeps for students missing a record and generates `absent` statuses automatically. This triggers Spec 05 Absence Notifications reliably.
- **Manual Override as Safety Net**: Teachers and admins can correct any day (IoT outage, damaged card); every edit is attributable and never re-sends notifications (Spec 05).

## Requirements

1. **Unified Scanner API Endpoint (`POST /api/attendance/scan`)**:
   - Authenticated via the scanner device key; rate-limited. Parses incoming JSON payload containing credential type and token/string.
   - **QR Path (Anti-Fraud)**: Validates the token's HMAC signature and freshness against the server-side secret. If `|now − issued_at| > 30 seconds`, rejects the payload with `Expired Token` (blocks screenshot fraud). A valid token decodes to the bound `student_id`. Replays inside the window are harmless: they collapse into the same daily record via debounce.
   - **RFID Path**: Looks up `rfid_number` in the `rfid_cards` registry (Spec 02) for its assigned `student_id`. Unknown or unassigned credentials return an error for the scanner display.
   - **Tap Resolution (ordered)**:
     1. No record today → create it: set `check_in_time`; status `late` if past `school_start_time`, else `present`.
     2. Record is `absent` (auto-sweep) without a check-in → set `check_in_time` and re-status to `late`/`present`. The already-sent absence notification is not retracted (Spec 05).
     3. Checked-in without check-out → within the debounce window: no-op; otherwise set `check_out_time` if `require_checkout`, else no-op.
     4. Already checked out → no-op.
     5. Record is `sick`/`leave` (excuse-injected, Spec 04) → no-op.
   - The `(student_id, date)` compound unique makes simultaneous taps on two scanners safe (create-once, update-thereafter).
   - Responds with the outcome (`checked_in`, `checked_out`, `ignored`, or error) plus the student's `full_name` for the scanner display.
2. **Student Portal — Dynamic QR Generator**:
   - A student-only authenticated endpoint returns the short-lived signed token; the UI renders it as a QR and auto-refreshes (~25s), bound to the requester's `student_id`.
3. **Auto Sweep (Cron Task)**:
   - At `auto_absent_cron_time`, creates a record with status `absent` for every student in master data with no record today (weekends skipped). Approved excuses need no special case — Spec 04 pre-creates their `sick`/`leave` records, which already count as "having a record". Sweep-created records carry `scan_method = null`.
4. **Teacher & Admin Exception Dashboard**:
   - Teachers may correct records for their own class(es) on any date; admins anywhere. An edit sets the status and optionally `check_in_time`/`check_out_time` (e.g., reconstructing a day the scanner was offline).
   - Every manual edit stamps `override_by_user_id` and `scan_method = manual_override`, with an optional `notes` context (e.g., "Card damaged"). Edits never trigger notifications (Spec 05).
5. **Bulk Class Marking**:
   - A teacher picks a class + date and marks all students *still without a record* as `present` in one action (scanner-outage day). Existing records — including `sick`/`leave` — are never overwritten. Bulk-created records count as manual overrides; since only `absent` creations notify (Spec 05), this sends no notifications.
6. **Data Overlap Shield (Spec 04 Synergy)**:
   - An approved Excuse pre-creates `sick`/`leave` records; the Scanner API treats taps by those students as no-ops (see Tap Resolution), so overlapping tap anomalies never corrupt an approved excuse.

## Schema Expected (attendances)

- `id`
- `student_id`
- `date` (date, compound unique w/ student_id)
- `status` (enum: present, absent, late, sick, leave)
- `check_in_time` (timestamp, nullable)
- `check_out_time` (timestamp, nullable)
- `scan_method` (string/enum, nullable) — Values: `rfid`, `dynamic_qr`, `manual_override`; `null` = system-generated (cron sweep, excuse injection). (Ensures analytics on method adoption).
- `override_by_user_id` (foreign, nullable)
- `notes` (string, nullable)

## Out of scope

- Per-period attendance (mapel-level entry).
- Timetables or Dynamic holiday calendars (handled centrally in v2).
- Per-device scanner keys / device registry.
- Offline scanner buffering (store-and-forward) — manual override is the v1 mitigation for outages.
