# 04 — Absence Excuses (Sakit & Izin)

Status: v2.0 — implemented 2026-09-20 (amended 2026-09-25)

## Problem

In Indonesian school operations, guardians typically notify teachers of a child's illness or absence through informal, fragmented channels: WhatsApp direct messages, verbal messages via siblings, or crumpled paper letters delivered days later. These informal messages are easily misplaced by homeroom teachers, are disconnected from gate scanners, and fail to prevent automated morning absence sweeps from dispatching distressing false-alarm absence alerts to parents. Furthermore, medical certificates (Surat Keterangan Dokter) and family leave letters lack a centralized audit repository for accreditation and regulatory reporting.

## Goals

1. Provide an authenticated self-service portal for legal guardians to submit absence notices for their linked children across single or multi-day date ranges.
2. Standardize categorization strictly into `sick` (Sakit) and `leave` (Izin) to maintain compliance with Indonesian Ministry of Education (Kemendikbud) reporting conventions.
3. Enable advance leave submissions to pre-populate attendance schedules, preventing false absence alerts during scheduled family leaves or hospital stays.
4. Support secure upload, storage, and preview of supporting verification documents (doctor's notes, formal letters) in JPG, PNG, or PDF formats up to 5MB.
5. Provide administrators with a review queue to evaluate, approve, or reject submissions with explanatory feedback notes.
6. Synchronize approvals atomically with the attendance engine by injecting or updating `sick`/`leave` records for all covered weekdays.
7. Shield approved excused dates from accidental corruption by hardware scanner taps.

## Non-goals

- Teacher-level approval authority in v1 (homeroom teachers have read-only visibility into their class excuses; review authority is reserved for administrators).
- Multi-student batch submissions in a single form (each student requires a distinct excuse submission to ensure supporting attachments are legally bound to one child).
- Optical Character Recognition (OCR) or automated medical certificate authenticity verification.
- In-app PDF generation of formal leave request letters.
- Partial-day excuses (e.g. leaving early at 11:00 AM; handled via manual teacher attendance overrides; spec 03).

## User Stories

- **As a guardian**, I want to submit a sick leave for my daughter from Monday to Wednesday and attach a photo of the doctor's note from my phone, so the school knows she is resting at home.
- **As a guardian**, I want to submit an advance leave for family travel next Friday, so the gate scanner sweep doesn't send me an alarming absence alert while we are traveling.
- **As an admin**, I want to review pending excuses on my dashboard, inspect the attached doctor's note, and approve the request with an optional note ("Semoga lekas sembuh").
- **As a homeroom teacher**, I want to view approved sick and leave records for my class today, so I understand why certain desks are empty.
- **As a guardian**, I want to check my submissions history to verify whether my request was approved or rejected by the school office.

## Decisions

- **Two Distinct Categories**: Replaces generic "excused" status with Indonesian standards:
  - `sick` (Sakit): Medical condition, illness, hospitalization.
  - `leave` (Izin): Family matters, official competitions, bereavement, religious pilgrimage.
- **Advance Submission Pre-Population**: Future date ranges are fully supported. When an advance excuse is approved, attendance records for all weekdays in that range are pre-created with status `sick` or `leave`. The daily auto-absent cron job (spec 03) detects an existing record and bypasses the student.
- **Guardian Attribution**: The schema explicitly stores `submitted_by_guardian_id` (in addition to `student_id`), providing a verifiable audit trail of which legal guardian submitted the notice in multi-guardian households.
- **Overlapping Range Guard**: Submitting an excuse whose date range overlaps with an existing `pending` or `approved` excuse for the same student is rejected with validation error (HTTP 422). If an illness extends, parents submit a subsequent contiguous date range.
- **Atomic Attendance Resolution**:
  - *On Approval*: System iterates all weekdays (Monday through Friday) in `[start_date, end_date]`. For each date, it upserts the `attendances` table with status `sick` or `leave`, setting `scan_method = null`. Weekend dates are skipped.
  - *On Rejection*: Existing attendance records remain untouched. The status changes to `rejected`, and the guardian views the `review_note`.
  - *Idempotency*: Re-approving an approved excuse is a no-op.
- **Scanner Immunity**: The scanner API (spec 03 tap resolution rule 5) ignores taps for students with an existing `sick` or `leave` record, logging `ignored_excused`.
- **Attachment Storage**: Stored in private application storage (`storage/app/excuses/`). File paths are obscured. Access requires authentication through a temporary signed URL or controller stream with authorization checks.

## Requirements

1. **Submission Form (`POST /excuses`)**:
   - Authenticated guardian endpoint; verifies guardian is linked to `student_id` via `guardian_student` pivot.
   - Fields: `student_id` (required), `type` (required: `sick`|`leave`), `start_date` (required, date), `end_date` (required, date >= `start_date`), `reason` (required, string, max 1000 chars), `attachment` (optional file).
   - Validates attachment: mime types `image/jpeg`, `image/png`, `application/pdf`; max size 5120 KB (5MB).
   - Rejects overlapping ranges for the same student (HTTP 422).
   - Stores attachment with hashed filename and creates `excuses` record with `status = 'pending'`.
2. **Guardian Review Portal (`GET /my-excuses`)**:
   - Lists all excuses submitted for children linked to the logged-in guardian.
   - Displays child name, type, dates, status badge (`pending`, `approved`, `rejected`), submission date, and administrative `review_note`.
   - Securely views/downloads the uploaded attachment via `GET /excuses/{excuse}/attachment`.
3. **Admin Review Queue (`GET /excuses`)**:
   - Accessible to admin and teachers (scoped to homeroom).
   - Shows student name, class, dates, type, reason, attachment link, and current attendance state.
4. **Approval Action (`PUT /excuses/{excuse}/approve`)**:
   - Admin-only endpoint.
   - Accepts optional `review_note` (max 500 chars).
   - Atomically updates excuse record: `status = 'approved'`, `reviewed_by_user_id = auth()->id()`.
   - Injects/updates `attendances` rows for all weekdays in range to `sick` or `leave`.
5. **Rejection Action (`PUT /excuses/{excuse}/reject`)**:
   - Admin-only endpoint.
   - Requires `review_note` explaining rejection reason.
   - Updates excuse record: `status = 'rejected'`, `reviewed_by_user_id = auth()->id()`.
   - Leaves attendance records untouched.
6. **Teacher Scoped Visibility**:
   - Homeroom teachers can view excuses for students enrolled in their homeroom classes.
   - Teacher interface is strictly read-only (approve/reject buttons hidden and unauthorized; HTTP 403 on mutation).

## Schema

### `excuses` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` |
| `type` | varchar(20) | not null | Enum: `sick`, `leave` |
| `start_date` | date | not null | Start of absence period |
| `end_date` | date | not null | End of absence period (inclusive) |
| `reason` | text | not null | Detailed justification from guardian |
| `attachment_path` | varchar(255) | nullable | Path in private storage under `excuses/` |
| `status` | varchar(20) | not null, default: 'pending' | Enum: `pending`, `approved`, `rejected` |
| `review_note` | text | nullable | Feedback / reason from administrator |
| `reviewed_by_user_id` | bigint | unsigned, nullable | Foreign key -> `users.id` (Admin reviewer) |
| `submitted_by_guardian_id` | bigint | unsigned, nullable | Foreign key -> `guardians.id` (Guardian submitter) |
| `reviewed_at` | timestamp | nullable | Resolution timestamp |
| `created_at` | timestamp | nullable | Submission timestamp |
| `updated_at` | timestamp | nullable | Last update timestamp |
**Indexes:**
- `INDEX (student_id, start_date, end_date)`
- `INDEX (status)`
- `INDEX (submitted_by_guardian_id)`

## Acceptance Criteria

- **AC-04-01**: Given an unlinked guardian, attempting to submit an excuse for student X returns HTTP 403 Forbidden.
- **AC-04-02**: Given an existing approved excuse for a student from 2026-10-05 to 2026-10-07, submitting a new excuse for 2026-10-06 to 2026-10-08 returns HTTP 422 with an overlap validation message.
- **AC-04-03**: Submitting an attachment of 6MB or of type `.exe` is rejected by validation with HTTP 422.
- **AC-04-04**: When an admin approves an excuse for 2026-10-12 (Monday) to 2026-10-14 (Wednesday), three `attendances` records are inserted/updated with status `sick` and `scan_method = null`.
- **AC-04-05**: When an admin rejects an excuse with `review_note = "Lampiran surat dokter buram"`, the status becomes `rejected`, `reviewed_at` is stamped, and no attendance rows are altered.
- **AC-04-06**: A student with an approved `sick` record for today who accidentally taps their RFID card at the gate has their tap ignored (`ignored_excused`), and their attendance status remains `sick`.
- **AC-04-07**: A homeroom teacher attempting to approve or reject an excuse receives HTTP 403 Forbidden.

## Constraints & Assumptions

- Indonesian school week assumption: school sessions run Monday through Friday (or Monday through Saturday based on `school_operational_days` config). Weekends within an excuse range do not generate attendance records.
- Uploaded medical files are considered sensitive personal data; files are never stored in the public web root (`public/`).

## Open Questions

- `[NEEDS DECISION: Cancellation of Pending Excuses by Guardian]`: Can a guardian cancel/withdraw a pending excuse submission before the admin reviews it? (Recommended for v1.1; v1 requires contacting the school admin).
