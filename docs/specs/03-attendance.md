# 03 — Attendance Recording (Dual IoT & Anti-Fraud Scanner)

Status: v2.5 — implemented 2026-09-19 (sweep population amendment 2026-09-21, amended 2026-09-25; audit amendment 2026-10-01 — see AUDIT-2026-10-01 D-01/03/04/05/08)

## Problem

Manual morning roll call in classrooms consumes 15 to 20 minutes of instructional time per class each day, produces error-prone paper logs, and delays absence discovery until midday or later. While standalone card-based RFID systems automate gate check-ins, they suffer extensively from "titip absen" (buddy punching fraud), where one student carries several peers' cards through the turnstile. Furthermore, automated check-in systems frequently break down during power/network outages or trigger mass false alarms by marking students absent on unrecorded national holidays.

## Goals

1. Enable autonomous, sub-second attendance recording through two complementary input streams: physical RFID cards and authenticated Dynamic QR codes displayed on mobile/web.
2. Defeat buddy punching and screenshot relay fraud through short-lived (30-second expiry, 25-second UI auto-refresh) HMAC-signed dynamic tokens issued exclusively by authenticated student sessions.
3. Automatically detect missing students and generate `absent` records at a configurable cutoff time on valid school days, triggering immediate guardian alerts (spec 05).
4. Integrate a resilient school calendar combining automated Indonesian national holiday/cuti bersama feeds with administrative school event overrides to suppress automated sweeps on non-school dates.
5. Provide teachers and administrators with manual override tools (individual correction and bulk class check-in) as an operational safety net during hardware or connectivity failures.
6. Maintain an immutable, append-only scan event log (`scan_events`) recording every raw tap and its exact system resolution for auditability, hardware diagnostics, and dispute settlement.

## Non-goals

- Subject-level or period-by-period attendance tracking in v1 (handled at the daily school level; per-period attendance deferred to v1.x; see spec 09 and spec 13).
- Facial recognition or biometric scanning hardware integration.
- Hardware-side offline store-and-forward buffering (v1 relies on teacher bulk manual override as the outage fallback; device buffering deferred to v1.x).
- Unique asymmetric cryptographic keys per physical scanner device (v1 uses a shared device API bearer key).
- Student notification dispatch (absence alerts notify guardians only; students verify status via self-serve portal).

## User Stories

- **As a student**, I want to tap my RFID card at the gate scanner and see my name and "Hadir" on the screen within 1 second, so I know my arrival is logged without delay.
- **As a student who forgot my card**, I want to open the Presensio app on my smartphone, display my dynamic QR code to the scanner, and check in securely.
- **As a school administrator**, I want the system to automatically sweep at 08:30 WIB and mark any enrolled student without a record as `absent`, so that their parents are notified promptly.
- **As a teacher during a scanner power failure**, I want to select my class and mark all unrecorded students as present with a single confirmation, so instructional time is not wasted taking roll on paper.
- **As an administrator**, I want national holidays like Idul Fitri to automatically populate the calendar, so the system never marks the entire student body absent while school is closed.
- **As a student**, I want to log in to my dashboard and view my attendance records for the month, so I can ensure my taps were accurately recorded.

## Decisions

- **Dual Modality (RFID + Dynamic QR)**:
  - *Standard RFID*: Fast gate throughput; resolves card UID to `student_id`.
  - *Dynamic QR*: High anti-fraud guarantee; short-lived HMAC-signed token generated server-side. Codes expire in 30s to prevent screenshot sharing via WhatsApp/Telegram.
- **Ordered Tap Resolution**:
  0. Student has no open enrollment in the active academic year (alumni/withdrawn; spec 07) -> no record is created (`ignored_alumni`).
  1. No record today -> create record: status `present` if `now <= school_start_time`, else `late`.
  2. Record is `absent` (auto-sweep already ran) -> upgrade status to `late` or `present` based on clock; record `checked_in_at`. (Prior notification to guardian is not retracted).
  3. Record is `present` or `late` without check-out:
     - If elapsed time since check-in is within `scan_debounce_minutes` (default 1 min) -> ignore as duplicate double-tap (`ignored_debounce`).
     - If beyond debounce window and `require_checkout` is true -> record `checked_out_at` (`check_out`).
     - If `require_checkout` is false -> ignore (`ignored_complete`).
  4. Record already checked out -> ignore (`ignored_complete`).
  5. Record is `sick` or `leave` (pre-injected by approved excuse; spec 04) -> ignore tap (`ignored_excused`) to prevent accidental excuse corruption.
- **Device Clock Authority with Tolerance**: Scanners transmit their local clock timestamp. If within `scan_drift_tolerance_minutes` (default 2 minutes) of server time, the device timestamp is authoritative. Beyond tolerance, the server clock is used.
- **Active Enrolled Population Sweep**: The automated sweep targets only students with an open enrollment (`ended_on IS NULL`) in the active academic year on the swept date. Alumni, withdrawn students, and future enrollments are never swept.
- **Calendar Single Source of Truth**: The `non_school_days` table combines auto-synced national holidays with admin-managed manual entries. Auto-sync is idempotent, one-way, and never modifies or deletes manual entries.
- **Raw Event Immutability**: `scan_events` rows are append-only. Manual overrides update the mutable `attendances` table and stamp `override_by_user_id` and `overridden_at`, leaving raw scan events untouched.

## Requirements

1. **Scanner API Endpoint (`POST /api/attendance/scan`)**:
   - Authenticated via `X-Scanner-Key` header against `config('attendance.scanner_keys')` (supports comma-separated keys for zero-downtime rotation).
   - Throttled to 60 requests/minute per device IP (`RateLimiter::for('scanner')`).
   - Payload accepts `{ "method": "rfid"|"dynamic_qr", "identifier": string, "scanned_at": ISO8601 }`.
   - Rejects expired QR tokens (`|now - issued_at| > 30s`) with HTTP 422 (`Expired Token`).
   - Resolves RFID card to active `student_id`; rejects unregistered cards with HTTP 404 (`Unknown Credential`).
   - Executes tap resolution in an atomic database transaction.
   - Returns JSON response `{ "status": "success", "outcome": string, "student_name": string, "time": string }`.
2. **Dynamic QR Token Generation & Presentation (`GET /my-qr`)**:
   - Authenticated student portal route rendered via Inertia (`StudentQrController`).
   - Generates an HMAC-SHA256 token containing `student_id`, `issued_at`, and signature using a server-side secret (`ATTENDANCE_QR_SIGNING_KEY` falling back to `APP_KEY`).
   - The Inertia view auto-refreshes every 25 seconds via Inertia partial reload to rotate the QR SVG.
3. **Auto-Absent Sweep (Scheduled Console Task)**:
   - Executes daily at configured `auto_absent_cron_time` (e.g. 08:30 WIB).
   - Aborts immediately if the current date is not an operational weekday (`school_operational_days`) or exists in `non_school_days`.
   - Identifies all students with active enrollment in the active academic year who have **no record** in `attendances` for today.
   - Bulk-inserts attendance records with `status = 'absent'`, `date = today`, `scan_method = null`.
   - Each created `absent` record triggers the asynchronous absence notification pipeline (spec 05).
4. **Manual Exception Dashboard**:
   - Authorized teachers (scoped to their assigned classes) and admins (all classes) can create or edit the record for any `(student_id, date)` in scope. The write is an upsert keyed on `(student_id, date)`, so days lost to a scanner outage can be reconstructed, not only existing records repaired.
   - Supports setting status (`present`, `late`, `absent`, `sick`, `leave`), `checked_in_at`, `checked_out_at`, and an explanatory `notes` field.
   - Stamped with `override_by_user_id`, `scan_method = 'manual_override'`, and `overridden_at = now()`.
   - Manual edits **never** trigger absence notifications (spec 05).
5. **Bulk Class Marking**:
   - Allows a teacher/admin to mark all unrecorded students in a class for a specific date as `present`.
   - Does not overwrite existing records (`sick`, `leave`, or existing scans are preserved).
6. **National Holiday Sync**:
   - Scheduled Artisan command (`php artisan attendance:sync-holidays`) fetches Indonesian national holidays from Nager.Date API (`https://date.nager.at/api/v3/PublicHolidays/{year}/ID`).
   - Idempotently upserts dates >= today with `source = 'sync'`.
   - Preserves manual rows (`source = 'manual'`).
7. **Student Self-Serve Attendance View**:
   - Dedicated portal page displaying today's attendance card (status badge, check-in time, check-out time).
   - Paginated historical table (15 records per page, newest first).
   - Conceals administrative `notes` and `override_by_user_id` from student view.

## Schema

### 1. `attendances` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` |
| `date` | date | not null | School date of attendance record |
| `status` | varchar(20) | not null | Enum: `present`, `absent`, `late`, `sick`, `leave` |
| `checked_in_at` | timestamp | nullable | Timestamp of initial check-in |
| `checked_out_at` | timestamp | nullable | Timestamp of check-out |
| `scan_method` | varchar(30) | nullable | `rfid`, `dynamic_qr`, `manual_override`, null (cron) |
| `override_by_user_id` | bigint | unsigned, nullable | Foreign key -> `users.id` |
| `notes` | varchar(255) | nullable | Teacher/admin explanatory notes |
| `created_at` | timestamp | nullable | |
| `overridden_at` | timestamp | nullable | Timestamp of the last manual override (`updated_at` also moves on scanner check-out, so it cannot serve this purpose) |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (student_id, date)`
- `INDEX (date, status)`

### 2. `scan_events` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `student_id` | bigint | unsigned, nullable | Resolved student ID (null if unrecognized) |
| `scan_method` | varchar(20) | not null | `rfid` or `dynamic_qr` |
| `identifier` | varchar(100) | nullable | RFID serial number (null for QR tokens) |
| `scanned_at` | timestamp | not null | Device or server timestamp of scan attempt |
| `outcome` | varchar(50) | not null | Result code: `check_in`, `check_out`, `absent_upgraded`, `ignored_debounce`, `ignored_complete`, `ignored_excused`, `ignored_alumni`, `error_expired_token`, `error_unknown_credential` |
| `created_at` | timestamp | nullable | Insertion timestamp |

**Indexes:**
- `INDEX (student_id, scanned_at)`
- `INDEX (scanned_at)`

### 3. `non_school_days` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `date` | date | not null, unique | Holiday date |
| `name` | varchar(255) | not null | Name of holiday / break |
| `source` | varchar(20) | not null | Enum: `sync`, `manual` |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |


### 4. `settings` Table (School Configuration)

Single-row configuration table (`id = 1`) managed via `SchoolSetting` model:

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Always 1 |
| `school_timezone` | varchar(255) | not null, default: 'Asia/Jakarta' | Application timezone |
| `school_start_time` | time | not null, default: '07:30:00' | Late threshold |
| `require_checkout` | boolean | not null, default: false | Check-out tap required flag |
| `auto_absent_cron_time` | time | not null, default: '08:30:00' | Daily auto-sweep time (must run in the morning — absence alerts are a safety signal, spec 05) |
| `school_operational_days` | json | not null, default: `[1,2,3,4,5]` | ISO weekdays (1 = Senin … 7 = Minggu) on which school runs; `[1,2,3,4,5,6]` for 6-day schools. Drives `isSchoolDay()` for the sweep (03), excuse approval (04), dashboard banner (08) and timetable days (13) |
| `scan_debounce_minutes` | smallint | unsigned, default: 1 | Double-tap shield window |
| `scan_drift_tolerance_minutes` | smallint | unsigned, default: 2 | Scanner clock trust window |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |
## Acceptance Criteria

- **AC-03-01**: Given an unregistered RFID card UID, when tapped on the scanner endpoint, the system logs an event with outcome `error_unknown_credential` and returns HTTP 404 with student name "Kartu Tidak Dikenal".
- **AC-03-02**: Given a registered student tapping at 06:45 WIB before `school_start_time` (07:30 WIB), the system creates an attendance record with status `present` and logs outcome `check_in`.
- **AC-03-03**: Given a student tapping at 07:45 WIB after `school_start_time`, the system creates an attendance record with status `late`.
- **AC-03-04**: Given a student who tapped in 30 seconds ago, when they tap again within the 60-second debounce window, the second tap is ignored with outcome `ignored_debounce` and no record is modified.
- **AC-03-05**: Given an expired Dynamic QR code (>30 seconds old), the scanner rejects the scan with outcome `error_expired_token` and HTTP 422.
- **AC-03-06**: When the auto-absent sweep runs on a day listed in `non_school_days`, zero attendance records are generated.
- **AC-03-07**: Given a student marked `absent` by the 08:30 sweep, when they tap in at 08:45, their record is updated to `late`, `checked_in_at` is set, and outcome is `absent_upgraded`.
- **AC-03-08**: A teacher executing bulk class check-in marks unrecorded students as `present` with `scan_method = 'manual_override'`; existing `sick` records for that class are untouched.

## Constraints & Assumptions

- Hardware scanners communicate via standard HTTP/HTTPS JSON payloads.
- Time tracking is evaluated in the school's configured timezone (`app.timezone` or `school_timezone` setting, e.g. `Asia/Jakarta`).
- Single-school scale: daily sweep processes <= 2,000 students in a single lightweight background query.

## Open Questions

- `[NEEDS DECISION: Event Log Retention Policy]`: `scan_events` grows at ~80,000 rows/year for a 200-student school. In v1, logs are retained indefinitely. A pruning policy (retain 365 days) is recommended for v1.1.
- `[NEEDS DECISION: Per-Device Scanner Authentication]`: Shared API key in v1. Migrating to individual device tokens with hardware registration table planned for v1.x when deploying multiple physical turnstiles.
