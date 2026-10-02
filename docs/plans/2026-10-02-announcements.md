# School Announcements Implementation Plan

> **For implementer agents:** Spec: `docs/specs/12-announcements.md` (ACs 12-01…10). Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Authenticated bulletin board with audience targeting (all/role/grade level/class) resolved through enrollments, private attachments, drafts/scheduling, read + acknowledgement tracking, and a dashboard widget.

**Architecture:** One `AnnouncementAudience` service answers both directions: `visibleTo(User): Builder` for feeds and `recipientsOf(Announcement)` for reports and notification fan-out. Authoring authority is a policy. Scheduled publication is a minute-level command that dispatches notifications when `published_at` is reached.

**Prerequisites:** Plan 3 (subject-teacher scope) and Plan 4 (dispatcher; Task 6 only).

**Blocking questions:** none.

---

### Task 1: Schema and models

**Files:**

- Create: migrations `create_announcements_table`, `create_announcement_receipts_table` (spec §Schema); `app/Models/Announcement.php`, `AnnouncementReceipt.php`, factories (states: `draft()`, `scheduled()`, `pinned()`, `forRole()`, `forGrade()`, `forClass()`); `app/Enums/AudienceType.php`
- Test: `tests/Unit/Models/AnnouncementTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Models/AnnouncementTest.php`

- [ ] **Step 1:** Scopes: `live()` (`published_at <= now`), `ordered()` (`is_pinned DESC, published_at DESC`). Validation of "exactly the target column for the type" lives in the request (Task 3), but add a model test for `scopeLive` boundaries (null, future, now).

### Task 2: Audience resolution

**Files:**

- Create: `app/Services/Announcements/AnnouncementAudience.php`
- Test: `tests/Unit/Services/Announcements/AnnouncementAudienceTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Unit/Services/Announcements`

- [ ] **Step 1:** Rules (spec Decisions): `all`; `role` = user holds `audience_role` (union of roles); `class`/`grade_level` = student's `classOn(today)`, guardian's linked children's `classOn(today)`, teacher homeroom or any `class_subjects` row (`ClassAccess::academicClassIds`); admin/principal see everything; drafts/scheduled only to author, admin, principal.
- [ ] **Step 2:** Tests: AC-12-01 (teacher-role notice absent for parent/student), AC-12-02 (5B parent doesn't see 5A; parent with 5A + 6B children sees it), AC-12-03 (student moved 5A → 5B yesterday), AC-12-04 (grade 6 via any grade-6 class of the active year), AC-12-05 (draft/scheduled), AC-12-06 (ordering). Query-count assertion for a parent with 3 children (no N+1; `classOn` for all children in one query).
- [ ] **Step 3:** `grade_level` audience matches classes in the **active** academic year only.

### Task 3: Admin/principal CRUD + attachments

**Files:**

- Create: `app/Http/Controllers/Announcements/AnnouncementController.php`, `app/Http/Requests/Announcements/{Store,Update}AnnouncementRequest.php`, `app/Policies/AnnouncementPolicy.php`, `app/Http/Controllers/Announcements/AnnouncementAttachmentController.php`, `routes/announcements.php`, `resources/js/pages/announcements/{admin-index,form}.tsx`
- Test: `tests/Feature/announcements/AnnouncementManagementTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/announcements/AnnouncementManagementTest.php`

- [ ] **Step 1:** Request: `audience_type` enum; exactly the matching target set (`audience_role` ∈ teacher/parent/student/principal/counselor/finance, `audience_grade_level` 1–12, `class_id` in active year), the others null; `attachment` PDF/JPG/PNG ≤ 5 MB stored on private disk with hashed name, original name kept; `notify_urgent` accepted only from admin/principal; `published_at` nullable, future allowed.
- [ ] **Step 2:** Attachment route applies the same audience check (AC-12-08 → 403). Test `Storage::fake('local')`; assert never on `public`.
- [ ] **Step 3:** Policy: admin/principal any audience; teacher only `class` in their scope (AC-12-07: `all` → 403, foreign class → 403, subject-teacher's class → 201); teachers edit/delete only their own.

### Task 4: Teacher class announcements

**Files:**

- Create: `app/Http/Controllers/Teacher/ClassAnnouncementController.php`, `resources/js/pages/teacher/class-announcements.tsx`
- Modify: `routes/teacher.php` (`classes/{school_class}/announcements`)
- Test: `tests/Feature/announcements/TeacherAnnouncementTest.php`

**Dependencies:** Task 3 · **Verification:** `php artisan test --compact tests/Feature/announcements/TeacherAnnouncementTest.php`

- [ ] **Step 1:** Audience locked to the class from the route; reuse Task 3 request logic (shared rules trait), not a copy.

### Task 5: Feed, detail, acknowledge, recipient report, markdown safety

**Files:**

- Create: `app/Http/Controllers/Announcements/{AnnouncementFeedController,AnnouncementAcknowledgeController,AnnouncementRecipientController}.php`, `app/Services/Announcements/MarkdownRenderer.php` (CommonMark with `html_input => strip`, `allow_unsafe_links => false`; **do not** register the GFM tables extension — see "Known advisory" in the roadmap: `league/commonmark` ≤ 2.10.1 has open advisories; run `composer show league/commonmark` and `composer update league/commonmark` first and use the fixed version if one exists), pages `resources/js/pages/announcements/{index,show,recipients}.tsx`
- Test: `tests/Feature/announcements/AnnouncementFeedTest.php`, `tests/Unit/Services/Announcements/MarkdownRendererTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/announcements tests/Unit/Services/Announcements`

- [ ] **Step 1:** `GET /announcements`: search (title, body), date filter, 10/page. `GET /announcements/{id}` visible-only (else 403/404 by existing convention), `firstOrCreate` receipt → one `read_at` after two opens (AC-12-09). `POST …/acknowledge`: 422 when `requires_acknowledgement = false`, 403 when not visible.
- [ ] **Step 2:** Recipient report (author/admin/principal): class/grade audiences list **per enrolled student** with guardian names, read/ack where _any_ linked guardian counts (AC-12-10: 30 students → 30 rows), filters "belum membaca"/"belum konfirmasi"; role/all audiences show counts and readers.
- [ ] **Step 3:** XSS test: `<script>`, `javascript:` links, and raw HTML are neutralized.

### Task 6: Dashboard widget + notifications + scheduled publish

**Files:**

- Modify: `app/Http/Controllers/Dashboard/DashboardController.php` (3 latest visible, unacknowledged-required badge count), `resources/js/pages/dashboard.tsx`
- Create: `app/Console/Commands/PublishScheduledAnnouncementsCommand.php`, scheduled every minute in `routes/console.php`; `app/Services/Announcements/AnnouncementPublisher.php` (`dispatchNotifications`)
- Test: `tests/Feature/DashboardTest.php`, `tests/Feature/announcements/AnnouncementNotificationTest.php`

**Dependencies:** Tasks 3, 5; Plan 4 · **Verification:** `php artisan test --compact tests/Feature/announcements tests/Feature/DashboardTest.php`

- [ ] **Step 1:** On publication (immediately or when the command reaches `published_at`) dispatch `announcement_urgent` when `notify_urgent` and `announcement_ack_required` when `requires_acknowledgement`, `dedupeBase = "announcement:{id}"`; the command marks nothing itself — idempotency is the dedupe key.

### Task 7: Close out

- [ ] `composer ci:check`; propose spec 12 → implemented.
