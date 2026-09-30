# 05 — Absence Notifications (WhatsApp & Email Alerting)

Status: v1.1 — implemented 2026-09-20 (amended 2026-09-25)

## Problem

Unaccounted student absences present a grave child safety risk. When a child departs for school in the morning but fails to arrive at the gate, several critical hours may elapse before parents discover the absence. Manual phone calls by administrative staff are slow, expensive in staff hours, inconsistent, and often postponed until midday. Furthermore, without a centralized electronic delivery ledger, schools possess no auditable proof of notification should a safety emergency or truancy investigation occur.

## Goals

1. Automatically and asynchronously dispatch absence alerts to verified legal guardians the moment an enrolled student is marked `absent` by the morning sweep or manual entry.
2. Prioritize WhatsApp messaging as the primary communication medium to maximize immediate delivery and read rates in Indonesia, gracefully falling back to email when phone contact is unavailable.
3. Guarantee zero duplicate alerts through an idempotent notification ledger (`absence_notifications`), sending at most one alert per guardian per student per date.
4. Minimize notification fatigue, guardian anxiety, and third-party messaging API costs by strictly excluding `late`, `sick`, and `leave` statuses from automated notification pipelines.
5. Provide a resilient queue execution architecture featuring three exponential backoff retry attempts and detailed status logging (`pending`, `sent`, `failed`).

## Non-goals

- Retraction or "all-clear" follow-up messages when an absent record is later upgraded to late/present or excused (accepted in v1 to avoid conflicting message floods).
- SMS gateway integration (cellular SMS has low adoption and high character costs compared to WhatsApp).
- Interactive two-way WhatsApp bot conversations in v1 (messages are purely informational alerts with a link to the guardian web portal).
- Daily attendance summaries, weekly digests, or multi-day absence pattern warnings in v1.
- Direct notification to students (alerts target legal guardians exclusively).

## User Stories

- **As a working mother**, I want to receive an immediate WhatsApp alert at 08:35 WIB if my 10-year-old son has not checked in at the school gate, so I can immediately verify if he reached school safely.
- **As an administrative staff member**, I want the system to handle 30 absence notifications automatically in the background, so I do not have to spend my morning manually calling parents.
- **As a school principal**, I want a verifiable record of whether an absence notification was sent to a truant student's family, including delivery timestamps, in case of an official inquiry.
- **As a guardian with two children at the school**, I want absence notifications to clearly state which specific child is absent and their class name.

## Decisions

- **Strict Trigger Condition**: Notifications are dispatched **only upon the initial creation** of an `attendances` row with status `absent`.
  - Updating an existing record (e.g. from `late` to `absent`, or `absent` to `present`) does **not** trigger a notification.
  - Manual overrides by teachers/admins via the exception dashboard do **not** trigger notifications.
  - Pre-approved excuses injected as `sick` or `leave` do **not** trigger notifications.
- **Recipient Resolution via Domain Pivot**: When an absence record is created, the system queries the `guardian_student` pivot table and enqueues a notification job for every verified guardian linked to that student. If a student has no linked guardians, the task completes gracefully without error.
- **WhatsApp-First Delivery Chain**:
  - The notification worker first checks `guardians.phone_number`.
  - If a valid phone number exists, the message is dispatched via the configured WhatsApp driver (e.g., Fonnte, Twilio, or Waha).
  - If phone number is null or unreachable, and the guardian has a linked `users.email`, the system falls back to dispatching an HTML/plain email via Laravel Mail.
  - If neither phone nor email exists, the ledger records status `failed` with error message `No valid contact channel`.
- **Standardized Indonesian Message Template**:
  ```text
  Yth. Bapak/Ibu Wali dari {student_full_name},

  Kami menginformasikan bahwa ananda belum tercatat hadir di sekolah pada hari ini:
  📅 Hari, Tanggal: {day_name}, {formatted_date}
  🏫 Kelas: {class_name}
  ⏰ Status: Tidak Ada Keterangan (Alpa)

  Apabila ananda sedang berhalangan hadir karena sakit atau izin, mohon segera sampaikan permohonan izin melalui portal resmi Presensio:
  🔗 {portal_url}

  Terima kasih atas perhatian dan kerja samanya.
  — Pengelola {school_name}
  ```
- **Execution & Retry Policy**: Notifications run on Laravel's queued background workers (`absence-notifications` queue). Jobs retry up to 3 times with exponential backoff intervals of 1 minute, 5 minutes, and 15 minutes.
- **Persistent Delivery Ledger**: Every dispatch attempt is recorded in `absence_notifications`. Replays or duplicate jobs detect the existing ledger row and abort immediately (idempotency).

## Requirements

1. **Absence Event Listener**:
   - Listens to `AttendanceCreated` domain events where `status == 'absent'`.
   - Iterates through all linked guardians via `$student->guardians`.
   - For each guardian, checks if an `absence_notifications` row already exists for `(attendance_id, guardian_id)`. If yes, skips insertion.
   - Creates an `absence_notifications` record with `status = 'pending'`.
   - Dispatches `SendAbsenceNotificationJob` to the queue.
2. **Background Dispatcher (`SendAbsenceNotifications` Job)**:
   - Reads target guardian contact information.
   - Evaluates provider connection: sends payload to WAHA WhatsApp gateway API (`POST /api/sendText`, `chatId: {phone}@c.us`).
   - Upon HTTP 200 from gateway, updates ledger: `status = 'sent'`.
   - Upon network timeout or HTTP 5xx error, throws exception to trigger queue retry (`$backoff = [60, 300]`).
   - If WhatsApp retries are exhausted, the job's `failed()` handler falls back to dispatching email to the guardian's user email.
3. **Idempotency Guard**:
   - A compound unique constraint on `(attendance_id, guardian_id)` in the database prevents race conditions from creating duplicate notification records.
4. **Configuration Parameters**:
   - `services.waha.base_url` (WAHA API endpoint)
   - `services.waha.api_key`
   - `services.waha.session` (default: 'default')
   - `attendance.notifications.statuses` (default: 'absent')

## Schema

### `absence_notifications` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `attendance_id` | bigint | unsigned, not null | Foreign key -> `attendances.id` |
| `guardian_id` | bigint | unsigned, not null | Foreign key -> `guardians.id` |
| `channel` | varchar(20) | not null | Enum: `whatsapp`, `email` |
| `status` | varchar(20) | not null, default: 'pending' | Enum: `pending`, `sent`, `failed` |
| `recipient_contact` | varchar(100) | nullable | Phone or email address used at send time |
| `provider_message_id` | varchar(100) | nullable | Provider message ID (e.g. WAHA) |
| `sent_at` | timestamp | nullable | Timestamp of successful transmission |
| `error_message` | text | nullable | Error message on failure |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |
**Indexes:**
- `UNIQUE (attendance_id, guardian_id)`
- `INDEX (status, channel)`
- `INDEX (created_at)`

## Acceptance Criteria

- **AC-05-01**: When the auto-absent sweep creates an `absent` record for a student with two linked guardians, two queued jobs are dispatched, and two ledger records are created with `status = 'pending'`.
- **AC-05-02**: Given a student whose attendance record is created as `present` or `late`, zero notification jobs and zero ledger rows are generated.
- **AC-05-03**: Given a student with no linked guardians, when marked absent, the sweep completes without errors, and zero notifications are dispatched.
- **AC-05-04**: When an admin manually edits an attendance record from `late` to `absent`, no notification is dispatched.
- **AC-05-05**: If the WhatsApp gateway returns HTTP 500 on the first attempt, the job retries 1 minute later; on the second attempt, if successful, `status` becomes `sent` and `provider_message_id` is recorded.
- **AC-05-06**: If a guardian has no phone number but has an email, the channel is set to `email` and an HTML email is sent via Laravel Mail.
- **AC-05-07**: Attempting to insert a duplicate notification for the same `(attendance_id, guardian_id)` pair is rejected by the database unique constraint.

## Constraints & Assumptions

- Queues must be driven by a robust queue backend in production (Redis, database queue with Octane/Horizon).
- Third-party WhatsApp gateway must support standard HTTPS REST payloads.
- Cost control assumption: School administrators configure API credit monitoring directly on their WhatsApp gateway account.

## Open Questions

- `[NEEDS DECISION: WhatsApp Webhook for Read Receipts]`: Does Presensio listen to incoming webhooks to upgrade `sent` to `delivered` or `read`? (Supported by schema via `delivered` status; webhook endpoint deferred to v1.1).
