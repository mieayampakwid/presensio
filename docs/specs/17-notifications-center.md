# 17 — Notification Center (Notifikasi In-App, WhatsApp & Email)

Status: draft v1.0 (2026-10-01) — resolves AUDIT-2026-10-01 S-07 / F-04. Depends on spec 15. Spec 05 (absence alerts) remains authoritative for absence alerts and is not rewritten by this spec.

## Problem

Spec 05 built one dedicated pipeline for one event (absence alerts): its own trigger, queue job, ledger, and WhatsApp/email fallback. Later specs need users to be told about many other events:
- Excuse decisions (04).
- Report cards published (11).
- Transfers verified or rejected and bills due (14).
- Urgent announcements (12).
- Staff leave requests and decisions (16).

Building a separate pipeline for each would produce six inconsistent mechanisms, six ledgers, and no single place to control WhatsApp cost. Today, these specs can only show a status on their own page and wait for the user to look.

## Goals

1. One notification pipeline for all non-absence events, with a code-defined catalog of notification types.
2. An in-app inbox for every user with an account: bell icon, unread count, list, mark as read, and a deep link to the related page.
3. Per-type channel policy: in-app always; WhatsApp and email only where enabled, because WhatsApp messages cost money.
4. A delivery ledger with idempotency and retries, reusing the WhatsApp/email infrastructure of spec 05.
5. Cost and courtesy controls: a daily WhatsApp quota for non-critical types, and quiet hours.
6. Let guardians opt out of WhatsApp for optional types.

## Non-goals

- Replacing spec 05's absence pipeline in v1. Absence alerts additionally write an in-app copy (see Decisions), but their WhatsApp/email sending and `absence_notifications` ledger are unchanged. Migrating them onto this pipeline is a v1.x candidate.
- Admin-editable message templates (templates are code/lang files in v1).
- Web push, mobile push, or SMS.
- Two-way WhatsApp conversations or read receipts (spec 05 open question).
- Free-form mass messaging ("kirim WA ke semua wali") outside announcements.

## User Stories

- **As a guardian**, I want a notification when the school approves or rejects my child's excuse, so I don't have to keep checking.
- **As a guardian**, I want a WhatsApp message when my child's report card is published, with a link to open it.
- **As a guardian whose transfer slip was rejected**, I want to know immediately and see why.
- **As a guardian**, I want a reminder three days before SPP is due. If I find WhatsApp reminders annoying, I want to turn them off and still see them in the app.
- **As a principal**, I want an in-app notification when a teacher submits a leave request.
- **As an admin**, I want a cap on how many optional WhatsApp messages the school sends per day, so the gateway bill stays predictable.
- **As a guardian**, I don't want bill reminders at 23:00.

## Decisions

- **Built on Laravel notifications**: the in-app inbox uses Laravel's `database` notification channel and its `notifications` table. WhatsApp uses a custom channel over the existing WAHA client (05); email uses `mail`.
- **Recipients**:
  - In-app delivery requires a user account.
  - WhatsApp uses the phone number of the recipient's profile: guardian (02) or employee (16).
  - Email uses `users.email`, falling back to the profile email.
  - Guardians without an account can still receive WhatsApp for types that enable it.
- **Notification type catalog (v1)**, defined in code:

  | Key | Trigger (spec) | Recipients | In-app | WhatsApp default | Email default | WA opt-out allowed | Quota & quiet hours apply |
  |---|---|---|---|---|---|---|---|
  | `absence_alert` | absent record created (05) | linked guardians | ✓ (copy) | via spec 05 | via spec 05 | no | no |
  | `excuse_submitted` | guardian submits excuse (04) | admins, homeroom teacher | ✓ | off | off | — | — |
  | `excuse_reviewed` | excuse approved/rejected (04) | submitting guardian | ✓ | off | off | yes | yes |
  | `report_card_published` | class publication (11) | guardians and students of the class | ✓ | on (guardians only) | off | yes | yes |
  | `report_card_retracted` | retraction (11) | same | ✓ | off | off | — | — |
  | `payment_verified` | transfer verified (14) | submitting guardian | ✓ | off | off | yes | yes |
  | `payment_rejected` | transfer rejected (14) | submitting guardian | ✓ | on | off | no | no |
  | `bill_due_reminder` | daily job, N days before `due_date` (14) | linked guardians | ✓ | off | off | yes | yes |
  | `announcement_urgent` | admin publishes with "kirim notifikasi" (12) | announcement audience | ✓ | per announcement | off | yes | quota yes, quiet hours yes |
  | `announcement_ack_required` | publish with `requires_acknowledgement` (12) | announcement audience | ✓ | off | off | — | — |
  | `leave_request_submitted` | employee submits leave (16) | principals, admins | ✓ | off | off | — | — |
  | `leave_request_reviewed` | leave approved/rejected (16) | employee | ✓ | off | off | — | — |
  | `staff_absence_digest` | after staff absent sweep (16) | principals | ✓ | off | off | — | — |

  "—" = WhatsApp not available for that type in v1. Admins can toggle WhatsApp and email per type in settings (except `absence_alert`, governed by 05), within what the catalog allows.
- **Idempotency**: every notification has a `dedupe_key` (e.g. `report_card_published:{publication_id}:{user_or_guardian_id}`). A second dispatch with the same key is a no-op, so job retries and replays never double-send.
- **Delivery ledger**: in-app is written synchronously in the triggering transaction's `afterCommit`. WhatsApp and email are queued (`notifications` queue). Each external attempt is recorded in `notification_deliveries` with status `pending`, `sent`, `failed`, `skipped_quota`, `skipped_opt_out`, or `skipped_no_contact`.
- **Retries**: 1 attempt plus 3 retries with backoff of 1, 5 and 15 minutes (same policy as 05). After a WhatsApp failure is exhausted, email is used when the type enables email; otherwise the delivery stays `failed`.
- **Daily WhatsApp quota**: setting `whatsapp_daily_quota` (default 500, null = unlimited) counts WhatsApp sends of quota-bound types per school-timezone day. Above the quota the delivery is `skipped_quota`; in-app is unaffected. `absence_alert` and `payment_rejected` never count and are never skipped.
- **Quiet hours**: settings `quiet_hours_start` (default 21:00) and `quiet_hours_end` (default 06:00). Quiet-hour-bound WhatsApp sends created inside the window are delayed until the window ends.
- **Opt-out**: users with a guardian profile can turn off WhatsApp per opt-out-allowed type on their profile page. Guardians without an account cannot opt out in v1.
- **Absence copy**: spec 05's job additionally writes an in-app notification for linked guardians who have accounts. This is a write only; spec 05's sending logic is untouched.
- **Inbox data**: each in-app notification stores a title, a short body, and a `url` (Wayfinder route) to the related page. It never stores sensitive content (e.g. no medical details, no score values).
- **Retention**: read in-app notifications older than 180 days are pruned by a scheduled command. Delivery ledger rows are kept 365 days.

## Requirements

1. **Inbox UI**:
   - Bell icon in the app header with an unread count, shared as an Inertia prop via one indexed count query.
   - Dropdown with the latest 10; full page `GET /notifications` (20 per page, filter unread).
   - `POST /notifications/{id}/read` and `POST /notifications/read-all`. Opening a notification marks it read and navigates to its `url`.
2. **Dispatch API (internal)**:
   - A single `NotificationDispatcher` used by every triggering spec. It resolves recipients, applies the catalog, settings, opt-outs, quota and quiet hours, writes the in-app rows, and queues external deliveries.
   - Triggering specs call it after their transaction commits.
3. **Admin Settings (`/settings/notifications`)**:
   - Per type: WhatsApp on/off and email on/off (only where the catalog allows).
   - `whatsapp_daily_quota`, quiet hours, and `bill_reminder_days_before` (default 3).
   - Today's WhatsApp usage vs quota.
4. **Delivery Log (`/admin/notifications/deliveries`)**: filter by type, channel, status and date; shows recipient contact, error message and provider message id. Admin and principal only.
5. **Guardian Preferences (profile page)**: WhatsApp toggles for opt-out-allowed types.
6. **Bill Reminder Job**:
   - Runs daily at 07:00 school time.
   - For bills with status `unpaid` / `partially_paid` due in exactly `bill_reminder_days_before` days, dispatches one `bill_due_reminder` per guardian per day. Dedupe key: `bill_due_reminder:{guardian_id}:{date}`. One reminder covers all such bills of all the guardian's children.
7. **WhatsApp templates**: Indonesian lang files per type with placeholders, ending with the portal link and `— Pengelola {school_name}` (school profile, 15).

## Schema

### 1. `notifications` Table (Laravel standard + dedupe)

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | uuid | primary key | |
| `type` | varchar(255) | not null | Notification class |
| `notifiable_type` / `notifiable_id` | morph | not null | The user |
| `data` | json | not null | `{ key, title, body, url }` |
| `dedupe_key` | varchar(191) | not null, unique | |
| `read_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `INDEX (notifiable_type, notifiable_id, read_at)`

### 2. `notification_deliveries` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `dedupe_key` | varchar(191) | not null | Same key as the notification |
| `type_key` | varchar(50) | not null | Catalog key |
| `channel` | varchar(20) | not null | Enum: `whatsapp`, `email` |
| `recipient_type` | varchar(30) | not null | `guardian`, `employee`, `user` |
| `recipient_id` | bigint | unsigned, not null | |
| `recipient_contact` | varchar(255) | nullable | Phone/email used at send time |
| `status` | varchar(20) | not null, default: 'pending' | Enum: `pending`, `sent`, `failed`, `skipped_quota`, `skipped_opt_out`, `skipped_no_contact` |
| `scheduled_for` | timestamp | nullable | Set when delayed by quiet hours |
| `attempts` | tinyint | unsigned, not null, default: 0 | |
| `provider_message_id` | varchar(100) | nullable | |
| `error_message` | text | nullable | |
| `sent_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (dedupe_key, channel)`, `INDEX (status, created_at)`, `INDEX (type_key, created_at)`

### 3. `notification_preferences` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `user_id` | bigint | unsigned, not null | FK -> `users.id` (cascade delete) |
| `type_key` | varchar(50) | not null | Opt-out-allowed type |
| `whatsapp_enabled` | boolean | not null | |

**Primary Key:** `(user_id, type_key)`

### 4. `settings` — added columns

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `notification_channels` | json | not null, default: catalog defaults | Per type `{ whatsapp: bool, email: bool }` |
| `whatsapp_daily_quota` | integer | unsigned, nullable, default: 500 | Null = unlimited |
| `quiet_hours_start` | time | not null, default: '21:00:00' | |
| `quiet_hours_end` | time | not null, default: '06:00:00' | |
| `bill_reminder_days_before` | tinyint | unsigned, not null, default: 3 | |

## Acceptance Criteria

- **AC-17-01**: Approving an excuse creates one in-app notification for the submitting guardian, with a `url` to My Excuses; the bell count increases by 1.
- **AC-17-02**: Publishing 5A's report cards creates in-app notifications for every guardian and student of 5A with accounts, and queues one WhatsApp per guardian with a phone number. Re-running the publish job creates no duplicates.
- **AC-17-03**: A guardian who opted out of WhatsApp for `report_card_published` gets the in-app notification and a delivery row `skipped_opt_out`.
- **AC-17-04**: With `whatsapp_daily_quota = 2`, a third quota-bound WhatsApp that day is `skipped_quota`. A `payment_rejected` WhatsApp is still sent.
- **AC-17-05**: A `bill_due_reminder` created at 22:00 has `scheduled_for` = next day 06:00 and is sent then. A `payment_rejected` created at 22:00 is sent immediately.
- **AC-17-06**: A guardian with two children having bills due in 3 days receives one reminder that day, not one per bill.
- **AC-17-07**: WhatsApp failing 4 times for a type with email enabled results in an email send; with email disabled, the delivery ends `failed` with the error recorded.
- **AC-17-08**: An absence alert (05) additionally creates an in-app notification for a guardian with an account. Spec 05's ledger rows and AC-05-01…AC-05-07 are unchanged.
- **AC-17-09**: A user cannot read or mark another user's notification (HTTP 403/404).
- **AC-17-10**: Notification payloads contain no excuse reason text, attachment links, or score values.
- **AC-17-11**: Read notifications older than 180 days are removed by the prune command; unread ones are kept.

## Constraints & Assumptions

- Reuses the WAHA client and configuration from spec 05.
- Requires spec 15 (roles, school profile). Trigger points live in specs 04, 11, 12, 14 and 16; each calls the dispatcher after commit.
- Queue workers must run the `notifications` queue in production.

## Open Questions

- `[NEEDS DECISION: Merge spec 05 into this pipeline (v1.x)]`: Move absence alerts onto `notification_deliveries` and retire `absence_notifications`, keeping its history read-only.
- `[NEEDS DECISION: Guardian opt-out without an account]`: Allow opting out by replying to WhatsApp ("STOP")? Requires two-way WhatsApp (out of scope in v1).
