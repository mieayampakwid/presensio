# Notification Center Implementation Plan

> **For implementer agents:** Spec: `docs/specs/17-notifications-center.md` (ACs 17-01…11). Spec 05 (`app/Jobs/SendAbsenceNotifications.php`, `app/Services/Attendance/WhatsAppClient.php`) stays authoritative for absence alerts. Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** One dispatcher for all non-absence events: in-app inbox, WhatsApp/email with ledger, quota, quiet hours, opt-out.

**Architecture:** Laravel `database` notifications for the inbox (with the extra `dedupe_key` column) plus a `NotificationDispatcher` that resolves recipients from a code-defined `NotificationType` catalog, writes in-app rows, and queues one `DeliverNotification` job per external channel against `notification_deliveries`. Triggering specs call the dispatcher in `DB::afterCommit`.

**Prerequisites:** Plans 1–2 merged. Queue worker for the `notifications` queue (note in deploy docs; no new package).

**Blocking questions:** none. Trigger wiring for 11/12/14/16 happens in those plans; this plan wires only the existing-spec triggers (04 excuses, 05 absence copy).

---

### Task 1: Tables, catalog, settings

**Files:**

- Create: migrations `create_notifications_table` (`php artisan notifications:table` shape plus `dedupe_key` varchar(191) unique), `create_notification_deliveries_table`, `create_notification_preferences_table`, `add_notification_settings_to_settings_table` (spec §Schema 1–4)
- Create: `app/Enums/NotificationType.php` (13 cases from the catalog) with methods `inApp(): bool`, `defaultWhatsapp()`, `defaultEmail()`, `whatsappAllowed()`, `optOutAllowed()`, `quotaBound()`, `quietHoursBound()`; `app/Enums/DeliveryStatus.php` (extends existing semantics: `Pending, Sent, Failed, SkippedQuota, SkippedOptOut, SkippedNoContact`)
- Create: `app/Models/NotificationDelivery.php`, `NotificationPreference.php`, factories
- Modify: `app/Models/SchoolSetting.php`, `app/Services/SchoolSettings.php` (`notificationChannels()` merges catalog defaults with stored json; `whatsappDailyQuota()`, `quietHours()`, `billReminderDaysBefore()`)
- Test: `tests/Unit/Enums/NotificationTypeTest.php` (catalog table cell by cell), `tests/Unit/Services/SchoolSettingsTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Enums tests/Unit/Services/SchoolSettingsTest.php`

- [ ] **Step 1:** Catalog test asserts each row of the spec table (e.g. `payment_rejected`: whatsapp default on, opt-out not allowed, quota/quiet not bound; `absence_alert`: in-app copy only).
- [ ] **Step 2:** `notification_channels` toggles may enable only what the catalog allows; `SchoolSettings::notificationChannels()` clamps.

### Task 2: In-app pipeline + dispatcher core

**Files:**

- Create: `app/Notifications/AppNotification.php` (single generic Laravel `Notification` taking `type`, `title`, `body`, `url`; `via()` = `['database']`; `toDatabase()` returns `{key,title,body,url}`), `app/Services/Notifications/NotificationDispatcher.php`, `app/Services/Notifications/RecipientResolver.php` (user / guardian / employee contact resolution per spec Recipients), `app/Services/Notifications/Message.php` (value object)
- Modify: `app/Models/User.php` (`Notifiable`; custom `DatabaseNotification` model override to fill `dedupe_key`)
- Test: `tests/Unit/Services/Notifications/NotificationDispatcherTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Unit/Services/Notifications`

- [ ] **Step 1:** `dispatch(NotificationType $type, Collection<Recipient> $recipients, Message $message, string $dedupeBase): void`. For each recipient the key is `"{$type->value}:{$dedupeBase}:{$recipient->key()}"`; in-app insert uses `insertOrIgnore` semantics on the unique key (catch the duplicate-key `UniqueConstraintViolationException` and skip) — second dispatch is a no-op (AC-17-02 re-run).
- [ ] **Step 2:** Message `data` must never contain reason text, attachment links or score values (AC-17-10): `Message` takes only `title`, `body`, `route` and builds `url` from a named route; add a unit test that rejects `body` containing a fixture secret via the call sites' tests later (each trigger plan asserts payload keys).
- [ ] **Step 3:** Fire in-app writes via `DB::afterCommit(fn () => ...)` so a rolled-back trigger never notifies.

### Task 3: External channels, ledger, retries, quota, quiet hours, opt-out

**Files:**

- Create: `app/Jobs/DeliverNotification.php` (queue `notifications`, `tries = 4`, `backoff = [60, 300, 900]`), `app/Services/Notifications/ChannelPolicy.php` (enabled? opted-out? contact present?), `app/Services/Notifications/WhatsAppQuota.php`, `app/Services/Notifications/QuietHours.php`
- Modify: `app/Services/Notifications/NotificationDispatcher.php` (queue deliveries), reuse `app/Services/Attendance/WhatsAppClient.php` (do not modify its public contract), `routes/console.php` (schedule a minute command `notifications:release-delayed` that dispatches `scheduled_for <= now` pending rows)
- Test: `tests/Feature/Notifications/DeliveryPipelineTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/Notifications/DeliveryPipelineTest.php`

- [ ] **Step 1:** Tests map 1:1 to ACs with `Http::fake()` for WAHA and `Queue`/`Mail` fakes: AC-17-03 opt-out → `skipped_opt_out` + in-app still present; AC-17-04 quota 2 → third `skipped_quota`, `payment_rejected` still sent; AC-17-05 22:00 → `scheduled_for` next day 06:00 (and `payment_rejected` immediate); AC-17-07 four WA failures with email on → email sent, email off → `failed` with `error_message`.
- [ ] **Step 2:** Ledger uniqueness `UNIQUE (dedupe_key, channel)` makes retries idempotent; the job re-reads the row and exits unless `status = pending`. Quota counts `sent` WhatsApp rows of quota-bound types for the school-timezone day (index `status, created_at`).
- [ ] **Step 3:** Guardians without accounts receive WhatsApp-only types (spec Recipients); `skipped_no_contact` when no phone.

### Task 4: Inbox UI

**Files:**

- Create: `app/Http/Controllers/Notifications/NotificationController.php` (`index`, `read`, `readAll`), `routes/notifications.php`, `resources/js/pages/notifications/index.tsx`, `resources/js/components/notification-bell.tsx`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (lazy shared prop `notifications` = `{unread_count, latest10}` computed with one indexed count + one limited query; use `Inertia::optional`-style closure so guests do no query), `resources/js/components/app-header.tsx`
- Test: `tests/Feature/Notifications/InboxTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/Notifications/InboxTest.php`

- [ ] **Step 1:** `POST /notifications/{id}/read` scopes `auth()->user()->notifications()->findOrFail` so another user's id is 404 (AC-17-09). Index 20/page with `?unread=1` filter. Opening marks read and navigates to `data.url`.

### Task 5: Admin settings, delivery log, guardian preferences

**Files:**

- Create: `app/Http/Controllers/Settings/NotificationSettingsController.php` + request, `app/Http/Controllers/Admin/NotificationDeliveryController.php`, `app/Http/Controllers/Settings/NotificationPreferenceController.php` + request; pages `resources/js/pages/settings/notifications.tsx`, `resources/js/pages/admin/notifications/deliveries.tsx`; routes in `routes/notifications.php`
- Test: `tests/Feature/Notifications/NotificationSettingsTest.php`

**Dependencies:** Task 3 · **Verification:** `php artisan test --compact tests/Feature/Notifications/NotificationSettingsTest.php`

- [ ] **Step 1:** `/settings/notifications` (admin): per-type toggles clamped by catalog, quota, quiet hours, reminder days, plus today's WhatsApp usage vs quota. `notification-deliveries` (admin, principal): filter type/channel/status/date.
- [ ] **Step 2:** Guardian preferences on the profile page: toggles only for opt-out-allowed types (`excuse_reviewed`, `report_card_published`, `payment_verified`, `bill_due_reminder`, `announcement_urgent`); only users with a guardian profile see them. Auditing not required.

### Task 6: Wire existing triggers (04 and 05) + prune

**Files:**

- Modify: `app/Services/Attendance/ExcuseApprovalService.php` and `app/Http/Controllers/Excuses/GuardianExcuseController.php` (`excuse_submitted` → admins + homeroom teacher; `excuse_reviewed` → submitting guardian)
- Modify: `app/Jobs/SendAbsenceNotifications.php` (additional in-app write for linked guardians with accounts — a write only; sending logic untouched; `dedupe_key = absence_alert:{attendance_id}:{user_id}`)
- Create: `app/Console/Commands/PruneNotificationsCommand.php`; schedule daily in `routes/console.php` (read in-app > 180 days, deliveries > 365 days, unread kept)
- Test: `tests/Feature/Notifications/ExcuseNotificationTest.php`, `tests/Feature/Notifications/AbsenceCopyTest.php`, `tests/Feature/Console/PruneNotificationsCommandTest.php`

**Dependencies:** Tasks 2–4 · **Verification:** `php artisan test --compact tests/Feature/Notifications tests/Feature/Console` and the full existing 05 suite (AC-05-01…07 unchanged)

- [ ] **Step 1:** AC-17-01 (approval → one in-app, `url` to My Excuses, bell +1; payload has no reason text), AC-17-08 (absence alert adds in-app, `absence_notifications` rows unchanged), AC-17-11 (prune).
- [ ] **Step 2:** `leave_request_*`, `report_card_*`, `payment_*`, `bill_due_reminder`, `announcement_*`, `staff_absence_digest` triggers are wired in plans 7, 9, 10, 14 (each lists a dispatcher task). Catalog entries exist now so those plans only call the dispatcher.

### Task 7: Close out

- [ ] `composer ci:check`. Propose spec 17 status update (note: trigger wiring for 11/12/14/16 pending in their plans).
