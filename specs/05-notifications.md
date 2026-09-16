# 05 — Absence Notifications

Status: draft v1 (2026-09-15)

## Purpose

Email guardians when their child is marked absent so absences surface the same day.

## Decisions

- Trigger: an attendance record is **created** with status `absent`. Status `late` does not notify.
- Sent queued (never blocking the marking request), one email per student per absence — sent to all linked guardians.
- Corrections (`absent` → anything else) do **not** send a follow-up/retraction email in v1. Editing a record does not re-notify; only creation notifies.
- Email content: child name, class, date, and a pointer to log in for details. No actionable links beyond login in v1.

## Requirements

1. Creating an `absent` record dispatches a queued job that emails each linked guardian.
2. Editing an existing record (any status change) never triggers a notification.
3. Bulk marking a class sends at most one email per guardian per student per day (no duplicates across retries — job is idempotent per record).
4. Emails use the app's mail configuration; failures are retried per Laravel queue defaults and do not break marking.
5. A student with no linked guardians simply triggers no emails (no error).

## Out of scope

- SMS / push / WhatsApp
- Daily or weekly digest emails
- Absence-pattern alerts ("3 absences this week")
- Notification preferences/opt-out per guardian
