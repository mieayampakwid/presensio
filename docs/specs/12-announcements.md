# 12 — School Announcements (Papan Pengumuman Digital)

Status: Implemented 2026-10-24 (v1.2). Feasibility review, revised plan, role-scoped authoring/reading, private attachments, receipts, reports, dashboard and recoverable scheduled publication completed. SQLite/full CI, PostgreSQL feature tests and every-role browser journeys passed; browser DELETE was restricted by tool review and its lifecycle/access proof is automated. Evidence: `docs/plans/2026-10-24-announcements.md`. Supersedes drafts v1.1 (2026-10-01) and v1.0 (2026-09-25).

## Problem

Schools distribute announcements, circular letters (Surat Edaran), holiday notices, exam schedules and permission requests through informal WhatsApp groups. This causes three failures:
1. Official notices are buried under chat and stickers, so families miss deadlines, fee notices and dress-code changes.
2. There is no audience targeting: faculty notices leak to parents, and Grade 1 notices clutter Grade 6 families' feeds.
3. There is no searchable archive, and no way to know which families have actually read a notice that required their attention (e.g. a study-tour permission letter).

## Goals

1. Provide an authenticated bulletin board across all role portals.
2. Target audiences precisely: everyone, a role, a grade level, or a class — resolved from enrollments, never from a stored class pointer.
3. Support Markdown bodies and one attachment (PDF/JPG/PNG, max 5 MB) stored privately.
4. Support pinning, scheduled publication, and drafts.
5. Track who has read each announcement, and, when requested, who has explicitly acknowledged it.
6. Show the latest relevant announcements on the dashboard (08) and in a searchable archive.

## Non-goals

- Comments, reactions, discussion threads.
- Consent forms with yes/no answers or digital signatures (acknowledgement means "I have read this"; consent forms are a v1.x candidate).
- Auto-expiry of announcements.
- Multiple attachments per announcement.

## User Stories

- **As an admin**, I want to publish a school-wide holiday circular with the official PDF attached.
- **As an admin**, I want to publish a teachers-only notice about a curriculum workshop that students and parents never see.
- **As an admin**, I want to send a notice to all Grade 6 families about graduation, without picking six classes one by one.
- **As a homeroom teacher**, I want to post a field-trip permission letter to my class, require acknowledgement, and see which students' guardians have not acknowledged yet.
- **As a subject teacher**, I want to announce a project deadline to the classes I teach.
- **As a parent with two children**, I want to see announcements for both children's classes and the school-wide ones in one feed.

## Decisions

- **Audience model**: `audience_type` ∈ `all`, `role`, `grade_level`, `class`, with exactly one matching target column:
  - `role` → `audience_role` (any installed `UserRole`, including `admin` and `staff`).
  - `grade_level` → `audience_grade_level` (1–12) in the active academic year.
  - `class` → `class_id`.
- **Audience resolution** (S-05). A user sees a published announcement if any of these holds:
  - `audience_type = all`.
  - `role`: the user holds `audience_role` (union of roles, spec 15).
  - `class` / `grade_level`: the user is linked to a matching class:
    - **Student**: the class from `classOn(today)`.
    - **Guardian**: the `classOn(today)` of any linked child.
    - **Teacher**: holds homeroom or any `class_subjects` assignment for the class (09).

  For `grade_level`, a matching class is in the captured `academic_year_id` with that `grade_level`. Class/grade targets capture the active academic year on creation; on draft retargeting they capture the then-active year. They never drift to a new cohort at rollover. Ordinary readers resolve current membership on the school-local date in that captured year; managers retain archive access. An author retains access to their own items. Admins and principals see every announcement. Feeds and dashboard widgets contain live items only, including for managers/authors; their separate management pages expose drafts/scheduled items.
- **Authoring permissions**:
  - `admin`, `principal`: any audience.
  - `teacher`: only `audience_type = class` for classes in their scope (homeroom or subject assignment in the active year). Anything else → HTTP 403.
- **Lifecycle**: `published_at` null = draft; future = scheduled; past or now = live. Drafts and scheduled items are visible only to their author, admins and principals. Editing a live announcement is allowed and shows "Diperbarui {date}".
- **Attachments**: private disk, hashed filename, served via an authorized download route that applies the same audience check. Never on the public disk.
- **Reads and acknowledgements**:
  - First open of an announcement's detail page records `read_at` for that user.
  - When `requires_acknowledgement = true`, the reader sees a "Saya sudah membaca" button that records `acknowledged_at`.
  - Authors, admins and principals see a recipient status report. For class and grade-level audiences it is listed **per student**: for each student, whether any linked guardian has read or acknowledged it.
- **Markdown safety**: rendered with HTML disabled and sanitized output (XSS).
- **Ordering**: `is_pinned DESC, published_at DESC`.

### Lifecycle and authorization clarifications (v1.2)

- Permissions use the union of account roles, never the active UI role. All eight installed roles (`admin`, `principal`, `teacher`, `counselor`, `finance`, `staff`, `parent`, `student`) can read relevant live items and acknowledge them. Finance/staff/counselor have no authoring power unless they also hold an author role. Managers see all live items but are notification recipients only when included in the actual audience.
- Teacher authoring/report access requires an active linked teacher/employee and the specific active-year homeroom/subject assignment. Do not pass a teacher+counselor account through `ClassAccess::academicClassIds` unchanged: its counselor branch returns every class, which must not grant school-wide teacher authoring. A manager role still grants full management. A teacher who loses the assignment retains their own text/detail but loses edit/delete and recipient-report access; managers can maintain that item.
- No active year: role/all publishing still works; class/grade creation and retargeting return 422 with a useful configuration message. Grade 0 is not a valid target. Inactive user accounts are excluded from publication recipients and reports of eligible user counts.
- Before go-live, drafts and scheduled items can be edited, rescheduled or deleted by authorized owners/managers. After `published_at <= now`, audience, captured academic year, publication timestamp, acknowledgement requirement and urgent flag are immutable (422). Title/body/attachment/pin edits remain allowed and show the updated date; existing receipts are preserved. A material correction requiring a new acknowledgement is a new announcement. Teacher requests setting urgent=true return 403.
- A deleted item is no longer readable/downloadable/acknowledgeable; stored receipts cascade. References in old inbox entries return 404. Attachments are replaced/removed only through authorized update and are never exposed as storage URLs. Failed writes remove new uploads, successful replacement/delete removes old private files after commit. Missing files return 404 without leaking paths. Keep an audit of creation/update/delete; notification payloads never carry the body or attachment.
- Acknowledgement requires a live, currently visible item. Draft/future acknowledgement returns 403, even for authors/managers. A direct acknowledgement inserts its first-read receipt if needed; retries and concurrent requests preserve both original timestamps. Report/download/feed/dashboard requests do not write read receipts. List previews use an escaped excerpt; only detail renders the Markdown body.
- Publication is processed immediately after a committed immediate-publication write and recovered by `announcements:publish-due` every minute. The first processing captures a persisted, deduplicated notification-recipient identity list. A completion timestamp stops future scans, including items with neither notification flag. A failure retries that same list with stable keys; a newly enrolled recipient does not receive every historic announcement on the next scheduler run. Current authorization is rechecked before dispatch; recipients who lost access are skipped. Publication flags and snapshots are never accepted from HTTP input.
- Publication audience is deduplicated by account ID (or guardian ID for a guardian without an account). Accountless guardians are supported for all, parent-role, class and grade targets through spec 17's enabled external channels. They appear as `Belum memiliki akun` in reports and cannot acknowledge. The same guardian/account linked to siblings gets one notification per type. Exclude disabled linked accounts rather than treating them as accountless.
- Urgent publishing dispatches `announcement_urgent`; acknowledgement-required publishing dispatches `announcement_ack_required`; when both are selected both distinct types are expected. Existing spec 17 channel settings still apply: urgent does not bypass disabled WhatsApp, quota, quiet hours or opt-out. External/in-app payload is generic (`Ada pengumuman sekolah baru. Buka aplikasi untuk membaca.`) plus the authorized detail link, so later enrollment changes do not expose the old text through an inbox.
- Class/grade reports are current-membership views in the captured year, explicitly labelled as of the school-local date. They show one row per active enrolled student, guardian names, account availability and whether any linked eligible guardian has read/acknowledged. A student's own acknowledgement does not stand in for a guardian's. No-guardian/no-account rows stay visible and unacknowledged. Reports are paginated at 30 students; role/all reports count eligible active users and list readers with pagination. Historical recipient reconstruction is outside v1; existing receipts remain retained. Reports include no cross-class guardian details.
- Read/ack at the announcement level is separate from reading a spec 17 notification; neither silently updates the other. The dashboard badge counts visible live required announcements without that account's acknowledgement, over the whole feed rather than just the top three.

## Requirements

1. **Admin/Principal Management (`/announcements/manage`)**:
   - CRUD. Fields: `title` (max 255), `body` (Markdown), `audience_type` plus the matching target, `is_pinned`, `requires_acknowledgement`, `notify_urgent` (admin/principal only: also sends `announcement_urgent` via spec 17, including WhatsApp subject to quota), `attachment`, `published_at` (nullable; future allowed).
   - On publication (immediately, or when a scheduled `published_at` is reached), `announcement_urgent` and/or `announcement_ack_required` are dispatched to the audience through spec 17.
   - Validation: exactly the target column matching `audience_type` is set; `class_id` must belong to the active academic year.
2. **Teacher Class Announcements (`/classes/{school_class}/announcements`)**:
   - Same form with audience locked to `class` = `{id}`; `{id}` must be in the teacher's scope.
   - A teacher edits and deletes only their own announcements.
3. **Feed & Archive (`GET /announcements`)**:
   - Visible announcements per the resolution rule; keyword search on title and body; date filter; 10 per page.
   - Shows author, publication date, pinned badge, escaped excerpt, authorized detail/attachment links, and acknowledgement state. Detail renders the safe Markdown body. Date bounds are inclusive school-local dates, converted to UTC query bounds; `from > to` returns 422.
4. **Detail & Acknowledge**:
   - `GET /announcements/{id}` records `read_at` (first view only).
   - `POST /announcements/{id}/acknowledge` records `acknowledged_at`. Only allowed when the announcement requires acknowledgement and is visible to the user.
5. **Recipient Report (`GET /announcements/{id}/recipients`)**:
   - Author, admin or principal only.
   - For class/grade audiences: rows per enrolled student with guardian names, read status and acknowledgement status. Filter "belum membaca" / "belum konfirmasi".
   - For role/all audiences: counts plus a list of users who have read.
6. **Dashboard Widget (08)**:
   - The 3 latest visible announcements (pinned first).
   - A badge counting unacknowledged announcements that require acknowledgement.
7. **Clickable entry paths and states**:
   - Every role: Login/Switch Role → Dashboard → sidebar `Pengumuman` → feed → detail → acknowledgement (when required). Dashboard widget links lead to the same detail/archive.
   - Admin/principal: feed → `Kelola pengumuman` → create/edit/delete → detail → `Status penerima`.
   - Teacher: sidebar `Classes` → authorized class row action `Pengumuman kelas` → class list → create/edit own item → detail → recipient report. The class list also links to the school-wide feed. No arbitrary first-class redirects.
   - Mutation/report buttons use server-supplied capabilities, including on mixed-role accounts. Cover empty lists, invalid input/upload, drafts, schedule times, loading, pagination, mobile navigation and keyboard confirmation/dismissal. Other roles retain their reader journey without management controls.

## Schema

### 1. `announcements` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `title` | varchar(255) | not null | |
| `body` | text | not null | Markdown |
| `audience_type` | varchar(20) | not null, default: 'all' | Enum: `all`, `role`, `grade_level`, `class` |
| `audience_role` | varchar(20) | nullable | Set when `audience_type = role` |
| `audience_grade_level` | tinyint | unsigned, nullable | Set when `audience_type = grade_level` |
| `class_id` | bigint | unsigned, nullable | FK -> `classes.id`; set when `audience_type = class` |
| `academic_year_id` | bigint | unsigned, nullable | FK -> `academic_years.id`; captured for class/grade, otherwise null |
| `is_pinned` | boolean | not null, default: false | |
| `requires_acknowledgement` | boolean | not null, default: false | |
| `notify_urgent` | boolean | not null, default: false | Push via spec 17 on publication |
| `attachment_path` | varchar(255) | nullable | Private storage path |
| `attachment_name` | varchar(255) | nullable | Original filename |
| `published_at` | timestamp | nullable | Null = draft; future = scheduled |
| `notification_recipients` | json | nullable | Persisted first-publication snapshot: list of `{type: user\|guardian, id: int}`; null means not captured, empty array is captured empty audience |
| `notifications_dispatched_at` | timestamp | nullable | Set only when publication processing finishes; retries retain the snapshot |
| `created_by_user_id` | bigint | unsigned, not null | FK -> `users.id` |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `INDEX (audience_type, published_at)`, `INDEX (class_id, published_at)`, `INDEX (is_pinned, published_at)`

### 2. `announcement_receipts` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `announcement_id` | bigint | unsigned, not null | FK -> `announcements.id` (cascade delete) |
| `user_id` | bigint | unsigned, not null | FK -> `users.id` (cascade delete) |
| `read_at` | timestamp | not null | First view |
| `acknowledged_at` | timestamp | nullable | Explicit acknowledgement |

**Primary Key:** `id`; **Unique:** `(announcement_id, user_id)`

Foreign-key behavior: restrict author/class/academic-year deletion while an announcement references it; receipt deletes cascade with the announcement/user. Use Laravel's normal `id` on the receipt model and enforce a unique `(announcement_id, user_id)` instead of asking Eloquent to update a composite primary key. Both receipt timestamps are UTC. Index `(notifications_dispatched_at, published_at)` for recovery scans and `(academic_year_id, audience_grade_level)` for year-scoped targets.

## Acceptance Criteria

- **AC-12-01**: An announcement with `audience_type = role`, `audience_role = teacher` is absent from parent and student feeds and dashboards.
- **AC-12-02**: An announcement for Class 5A is absent from feeds of 5B parents; a parent with children in 5A and 6B sees it.
- **AC-12-03**: A student who transferred from 5A to 5B yesterday no longer sees new 5A class announcements and does see 5B's.
- **AC-12-04**: An announcement with `audience_grade_level = 6` is visible to guardians of students currently enrolled in any grade-6 class of the active year.
- **AC-12-05**: A draft or future-scheduled announcement is not returned to non-author, non-admin, non-principal users.
- **AC-12-06**: A pinned announcement from three weeks ago renders above an unpinned one from yesterday.
- **AC-12-07**: A teacher creating an announcement with `audience_type = all`, or for a class outside their scope, receives HTTP 403. A subject teacher creating one for a class they teach succeeds.
- **AC-12-08**: Requesting the attachment of an announcement the user cannot see returns HTTP 403.
- **AC-12-09**: Opening an announcement twice records one `read_at` (the first); acknowledging sets `acknowledged_at`; acknowledging an announcement that does not require it returns HTTP 422.
- **AC-12-10**: The recipient report for a 30-student class announcement lists 30 rows. A student counts as acknowledged when any linked guardian has acknowledged.
- **AC-12-11**: Each of the eight installed roles reaches the feed/detail from Dashboard/sidebar; only managers reach school-wide CRUD, and a teacher reaches class authoring through a scoped class action. Multi-role authorization is stable across UI role switches.
- **AC-12-12**: Staff-role targeting works. A teacher+counselor without a class assignment cannot author for that class. No active academic year blocks only class/grade authoring, not all/role announcements.
- **AC-12-13**: Class/grade targets keep their academic year across rollover; a new cohort never inherits an old grade announcement. Editing live target/flags/publication time is atomic 422; editing text preserves receipts and shows the updated date.
- **AC-12-14**: First publication and repeated scheduler runs create exactly one notification per selected type/recipient. Simulated failure midway resumes the captured recipient list without duplicates or adding newly eligible recipients; completion stops future scans. Future items produce nothing until due.
- **AC-12-15**: Disabling the urgent WhatsApp channel, opt-out or quota does not suppress the in-app entry. Both flags yield both notification types. Accountless eligible guardians have no in-app entry/ack but use enabled external channels.
- **AC-12-16**: Feed, dashboard, receipt report and attachment request create no read receipt. Direct acknowledgement creates one read/ack pair; repeats/concurrent submissions preserve original timestamps. A non-required acknowledgement is 422; invisible/draft/future is 403.
- **AC-12-17**: Replacing/removing an attachment deletes only the superseded private file after commit; failed validation/database write cleans up the new upload. Foreign detail/attachment/report requests reveal no content. Raw HTML, script tags and unsafe links never execute in detail.
- **AC-12-18**: Recipient reports retain no-guardian/accountless rows, deduplicate siblings' guardian status, ignore student-only acknowledgement for guardian completion, apply their filters and paginate correctly. A teacher losing scope cannot access the report by direct URL.
- **AC-12-19**: Search, inclusive school-local date filters, 10-item feed pagination and tie-breaking by descending ID work without loading/rendering every announcement body. Top three follow pinned/publication/ID order; badge counts required unacknowledged items across all pages.
- **AC-12-20**: Publication recovery handles deletion and skips recipients who are no longer authorized; notification text is generic and contains no announcement body, title or attachment. A post-commit immediate dispatch failure leaves a recoverable publication, not a falsely completed marker.

## Constraints & Assumptions

- Requires spec 15 (roles, `grade_level`) and spec 09 (subject-teacher scope).
- Audience resolution runs as indexed queries over enrollments at single-school scale.

## Open Questions

- ~~WhatsApp push for urgent announcements~~ — resolved by spec 17 (`notify_urgent`, `announcement_urgent`, daily quota).

## Readiness review — 2026-10-24

**Verdict:** v1.1 was not implementation-ready; v1.2 is technically feasible with the clarified contracts above. The dated implementation plan supersedes the 2026-10-02 plan. At the start of review, no announcement route/model/migration/controller/page or feature test existed; the notification type names alone represented infrastructure. Implementation and verification subsequently completed as recorded in the dated plan.

Resolved gaps: omitted Staff target; counselor privilege widening teacher scope; mutable grade cohort at rollover; live editing versus acknowledgement meaning; repeat publication scan versus changing recipient membership; first-read races and Eloquent composite-key mismatch; private file cleanup; per-announcement versus globally enabled WhatsApp; and missing role entry paths. The chosen v1 scope keeps current-membership reports and announcement-level acknowledgement, without versioned consent or historical recipient analytics.

Installed versions checked: Laravel 13.31.0, Inertia Laravel 3.3.4, Wayfinder 0.1.21, PHPUnit 12.5.35 and CommonMark 2.10.1. The [upstream 2.10.2 security release](https://github.com/thephpleague/commonmark/releases/tag/2.10.2) fixes the RawHtml/Table advisories recorded in the old roadmap. The plan requires approval before changing dependencies and renderer proof using only CommonMark core, `html_input=strip`, `allow_unsafe_links=false`, bounded body/nesting/delimiters and no Table/Attributes/DisallowedRawHtml extensions. The user subsequently approved the patch update; implementation installed CommonMark 2.10.4 and its compatible transitive updates, with a clean locked-package audit.

Baseline proof: 23 existing class-scope/dispatcher/type-catalog tests passed (121 assertions), then 36 dashboard/inbox/delivery tests passed (382 assertions). This establishes available dependencies only. Those baseline runs preceded implementation; the completed announcement acceptance results are recorded in the plan, separately from the dependency baseline.
