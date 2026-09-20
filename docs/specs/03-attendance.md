# 03 — Attendance Recording (Dual IoT & Anti-Fraud Scanner)

Status: draft v2.5 (2026-09-21) — v2.5 amends the sweep's target population to enrollment-active students (spec 02 v2.0 / spec 08).

## Purpose

The core engine: Students independently log their attendance via Hardware Scanners (RFID or Secure Dynamic QR). The system natively guards against fraud (Buddy Punching), aggregates absences, and handles check-in/check-out policies autonomously.

## Decisions

- **Unified Identity Modality (RFID + Android/Web)**: System provides two streams for check-in to maximize flexibility and combat "titip absen" (buddy punching fraud).
  - *Standard RFID*: Fast, reliable, but susceptible to card sharing.
  - *Dynamic QR (Time-Based)*: Requires a student to be logged in via their personal device, raising the fraud bar from "borrow a card" to "share live credentials + screen". Codes expire quickly (e.g., 30s) to reject screenshots shared via chat apps. Residual risk (relaying a live screen over a video call) is accepted in v1.
- **Server-Issued QR Tokens**: The QR content is a token signed with a server-side signing secret and issued by an authenticated, student-only endpoint. The client only requests and renders it — the secret never reaches any client. The UI refreshes the code every ~25s so a valid code is always on display.
- **Scanner Authentication**: Hardware scanners authenticate with a shared device API key (config) on the scan endpoint. Per-device keys and a device registry are deferred (v1.x).
- **Time Tracking & Debounce**: Accurately records `checked_in_at` and `checked_out_at`. A configurable debounce window (`scan_debounce_minutes`, default 1) turns taps following a check-in into no-ops — a hardware double-tap must never become an instant check-out.
- **Device Scan Timestamps**: Scanners send their own scan clock in the payload. Within `scan_drift_tolerance_minutes` of server time, the device clock is authoritative for `checked_in_at`/`checked_out_at`/`scanned_at` — accurate under server lag and future-proof for offline buffering (v1.x). Beyond tolerance, server time wins.
- **Application Configurability**: Key business logic anchors on settings (all evaluated in the school's timezone):
  - `school_start_time` (Time to determine `late` / keterlambatan)
  - `require_checkout` (Boolean to toggle Check-out tap logic)
  - `auto_absent_cron_time` (Sweep time)
  - `scan_debounce_minutes` (Double-tap shield)
  - `scan_drift_tolerance_minutes` (Device-clock trust window, default 2)
- **Automatic Absent Generation (Cron)**: A background scheduler sweeps for students missing a record and generates `absent` statuses automatically. This triggers Spec 05 Absence Notifications reliably.
- **Non-School Day Calendar**: The sweep must never fire on a holiday. A `non_school_days` table is the single source of truth, combining auto-synced national holidays + cuti bersama (from a holiday feed — e.g., Google Calendar's public Indonesian holiday calendar or a public holidays API) with admin-managed school dates (semester breaks, exams, school events). Sync is one-way, scheduled, idempotent (upsert by date), imports only future dates, and never touches manual rows; admins may delete an imported day (e.g., school holds a session on a national holiday).
- **Manual Override as Safety Net**: Teachers and admins can correct any day (IoT outage, damaged card); every edit is attributable and never re-sends notifications (Spec 05).
- **Raw Scan Event Log (Append-Only)**: Every scan attempt is recorded with its outcome. The mutable `attendances` record stays the projection; the immutable event log is the history — for dispute resolution ("I did tap"), fraud analytics (a tap by a `sick`/`leave` student signals a lent card), and hardware debugging. Recorded from day one: event history cannot be retrofitted.

## Requirements

1. **Unified Scanner API Endpoint (`POST /api/attendance/scan`)**:
   - Authenticated via the scanner device key; rate-limited. Parses incoming JSON payload containing credential type, token/string, and the device's scan timestamp.
   - **QR Path (Anti-Fraud)**: Validates the token's HMAC signature and freshness against the server-side secret. If `|now − issued_at| > 30 seconds`, rejects the payload with `Expired Token` (blocks screenshot fraud). A valid token decodes to the bound `student_id`. Replays inside the window are harmless: they collapse into the same daily record via debounce.
   - **RFID Path**: Looks up `rfid_number` in the `rfid_cards` registry (Spec 02) for its assigned `student_id`. Unknown or unassigned credentials return an error for the scanner display.
   - **Tap Resolution (ordered)**:
     1. No record today → create it: set `checked_in_at`; status `late` if past `school_start_time`, else `present`.
     2. Record is `absent` (auto-sweep) without a check-in → set `checked_in_at` and re-status to `late`/`present`. The already-sent absence notification is not retracted (Spec 05).
     3. Checked-in without check-out → within the debounce window: no-op; otherwise set `checked_out_at` if `require_checkout`, else no-op.
     4. Already checked out → no-op.
     5. Record is `sick`/`leave` (excuse-injected, Spec 04) → no-op.
   - The `(student_id, date)` compound unique makes simultaneous taps on two scanners safe (create-once, update-thereafter).
   - Responds with the outcome (`checked_in`, `checked_out`, `ignored`, or error) plus the student's `full_name` for the scanner display.
2. **Student Portal — Dynamic QR Generator**:
   - A student-only authenticated endpoint returns the short-lived signed token; the UI renders it as a QR and auto-refreshes (~25s), bound to the requester's `student_id`.
3. **Auto Sweep (Cron Task)**:
   - At `auto_absent_cron_time`, creates a record with status `absent` for every student **with an enrollment active on that date** (open enrollment, spec 02 v2.0 — alumni/left students are never swept, spec 08) and no record today (weekends and `non_school_days` skipped). Approved excuses need no special case — Spec 04 pre-creates their `sick`/`leave` records, which already count as "having a record". Sweep-created records carry `scan_method = null`.
4. **Teacher & Admin Exception Dashboard**:
   - Teachers may correct records for their own class(es) on any date; admins anywhere. An edit sets the status and optionally `checked_in_at`/`checked_out_at` (e.g., reconstructing a day the scanner was offline).
   - Every manual edit stamps `override_by_user_id` and `scan_method = manual_override`, with an optional `notes` context (e.g., "Card damaged"). Edits never trigger notifications (Spec 05).
5. **Bulk Class Marking**:
   - A teacher picks a class + date and marks all students *still without a record* as `present` in one action (scanner-outage day). Existing records — including `sick`/`leave` — are never overwritten. Bulk-created records count as manual overrides; since only `absent` creations notify (Spec 05), this sends no notifications.
6. **Data Overlap Shield (Spec 04 Synergy)**:
   - An approved Excuse pre-creates `sick`/`leave` records; the Scanner API treats taps by those students as no-ops (see Tap Resolution), so overlapping tap anomalies never corrupt an approved excuse.
7. **Holiday Calendar & Sync**:
   - A scheduled one-way sync from the configured holiday feed auto-applies national holidays and cuti bersama (dates ≥ today only) into `non_school_days` with `source = sync` and the holiday name. Idempotent upsert by date; manual rows are never overwritten or deleted by the sync. No write-back to the feed.
   - Admin CRUD adds school-specific dates (`source = manual`) and may delete synced rows.
8. **Scan Event Logging**:
   - The scan endpoint persists one `scan_events` row per attempt, regardless of outcome: resolved `student_id` (null on unknown credential), `scan_method`, the raw `rfid_number` for RFID attempts (QR tokens are never stored — they expire by design), device `scanned_at`, and the outcome (`check_in`, `check_out`, `absent_upgraded`, `ignored_debounce`, `ignored_complete`, `ignored_excused`, `error_expired_token`, `error_unknown_credential`).
   - The log is append-only; the Exception Dashboard edits attendance records, never events.
9. **Student Portal — My Attendance Page**:
   - A student-only, read-only view of their own records: today's record highlighted in a card (status + check-in/out times), followed by paginated history (15/page, newest first; today excluded — it lives in the today card). Times render in the school timezone. A student without a linked profile sees an empty state, mirroring the QR page.
   - Privacy carve-out: `notes` and `override_by_user_id` are teacher-facing and never exposed to students — override rationale may contain internal commentary. Rows expose date, status, times, and scan method only.
   - This page is the student's self-serve feedback loop ("did my scan register?") — in v1 nobody notifies the *student* of an absence (Spec 05 notifies guardians only), and the scanner display is the only other feedback surface.

## Schema Expected (attendances)

- `id`
- `student_id`
- `date` (date, compound unique w/ student_id)
- `status` (enum: present, absent, late, sick, leave)
- `checked_in_at` (timestamp, nullable)
- `checked_out_at` (timestamp, nullable)
- `scan_method` (string/enum, nullable) — Values: `rfid`, `dynamic_qr`, `manual_override`; `null` = system-generated (cron sweep, excuse injection). (Ensures analytics on method adoption).
- `override_by_user_id` (foreign, nullable)
- `notes` (string, nullable)

## Schema Expected (scan_events)

- `id`
- `student_id` (foreign, nullable — null when the credential resolves to nobody)
- `scan_method` (enum: `rfid`, `dynamic_qr`)
- `identifier` (string, nullable) — Raw `rfid_number` on RFID attempts; empty for QR (tokens expire by design, never stored).
- `scanned_at` (timestamp — device clock when available)
- `outcome` (string) — What the tap did, or why it was ignored/rejected.

## Schema Expected (non_school_days)

- `id`
- `date` (date, unique)
- `name` (string) — e.g., "Idul Fitri", "Cuti Bersama", "Libur Semester Ganjil".
- `source` (enum: `sync`, `manual`)

## Out of scope

- Per-period attendance (mapel-level entry).
- Timetables; per-class/per-level calendar overrides (one school-wide calendar in v1).
- Two-way calendar sync (writing school dates back to the holiday feed).
- Per-device scanner keys / device registry.
- Offline scanner buffering (store-and-forward) — manual override is the v1 mitigation for outages.
