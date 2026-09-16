# 05 — Absence Notifications

Status: final v1 (2026-09-16)

## Purpose

Notify guardians when their child is marked absent so absences surface the same day. Uses WhatsApp (and emails as fallback) for higher deliverability.

## Decisions

- Trigger: an attendance record is **created** with status `absent`.
- **Reasoning for excluding `late` status:** Status `late` does not trigger a notification to prioritize student safety over minor discipline issues, avoid notification fatigue (spamming parents for 2-minute delays), and save on WhatsApp API costs. Parents can still view `late` records by logging into the portal.
- Sent queued (never blocking the marking request), one notification per student per absence — sent to all linked guardians.
- Corrections (`absent` → anything else) do **not** send a follow-up/retraction notification in v1. Editing a record does not re-notify; only creation notifies.
- Content: child's name, class, date, and a pointer to log in for details. No actionable links beyond a login reminder in v1.
- Medium: Notifications will primarily be sent via WhatsApp to the guardian's `phone_number` number, falling back to `email` if the phone number is missing or unreachable.

## Requirements

1. Creating an `absent` record dispatches a queued job that sends a WhatsApp message (and/or email) to each linked guardian.
2. Editing an existing record (any status change) never triggers a notification.
3. Bulk marking a class sends at most one notification per guardian per student per day (no duplicates across retries — job is idempotent per record).
4. System uses a configured WhatsApp API (e.g., Fonnte, Twilio, or similar) alongside Laravel's mail configuration. Failures are retried per Laravel queue defaults and do not break marking.
5. A student with no linked guardians simply triggers no notifications (no error).

## Out of scope

- SMS / push notifications (Mobile apps)
- Daily or weekly digest emails
- Absence-pattern alerts ("3 absences this week")
- Notification preferences/opt-out per guardian

