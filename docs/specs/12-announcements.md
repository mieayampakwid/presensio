# 12 — School Announcements (Papan Pengumuman Digital)

Status: draft v1.1 (2026-10-01) — revised against spec 15 (AUDIT-2026-10-01 S-05, P12-01…03). Supersedes draft v1.0 (2026-09-25): audience resolution defined through enrollments; grade-level audience, subject-teacher authoring, private attachments, and read/acknowledgement tracking added.

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
  - `role` → `audience_role` (`teacher`, `parent`, `student`, `principal`, `counselor`, `finance`).
  - `grade_level` → `audience_grade_level` (1–12) in the active academic year.
  - `class` → `class_id`.
- **Audience resolution** (S-05). A user sees a published announcement if any of these holds:
  - `audience_type = all`.
  - `role`: the user holds `audience_role` (union of roles, spec 15).
  - `class` / `grade_level`: the user is linked to a matching class:
    - **Student**: the class from `classOn(today)`.
    - **Guardian**: the `classOn(today)` of any linked child.
    - **Teacher**: holds homeroom or any `class_subjects` assignment for the class (09).

  For `grade_level`, a matching class is any class in the active year with that `grade_level`. Admins and principals see every announcement.
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

## Requirements

1. **Admin/Principal Management (`/admin/announcements`)**:
   - CRUD. Fields: `title` (max 255), `body` (Markdown), `audience_type` plus the matching target, `is_pinned`, `requires_acknowledgement`, `notify_urgent` (admin/principal only: also sends `announcement_urgent` via spec 17, including WhatsApp subject to quota), `attachment`, `published_at` (nullable; future allowed).
   - On publication (immediately, or when a scheduled `published_at` is reached), `announcement_urgent` and/or `announcement_ack_required` are dispatched to the audience through spec 17.
   - Validation: exactly the target column matching `audience_type` is set; `class_id` must belong to the active academic year.
2. **Teacher Class Announcements (`/teacher/classes/{id}/announcements`)**:
   - Same form with audience locked to `class` = `{id}`; `{id}` must be in the teacher's scope.
   - A teacher edits and deletes only their own announcements.
3. **Feed & Archive (`GET /announcements`)**:
   - Visible announcements per the resolution rule; keyword search on title and body; date filter; 10 per page.
   - Shows author, publication date, pinned badge, rendered body, attachment link, and acknowledgement state.
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
| `is_pinned` | boolean | not null, default: false | |
| `requires_acknowledgement` | boolean | not null, default: false | |
| `notify_urgent` | boolean | not null, default: false | Push via spec 17 on publication |
| `attachment_path` | varchar(255) | nullable | Private storage path |
| `attachment_name` | varchar(255) | nullable | Original filename |
| `published_at` | timestamp | nullable | Null = draft; future = scheduled |
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

**Primary Key:** `(announcement_id, user_id)`

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

## Constraints & Assumptions

- Requires spec 15 (roles, `grade_level`) and spec 09 (subject-teacher scope).
- Audience resolution runs as indexed queries over enrollments at single-school scale.

## Open Questions

- ~~WhatsApp push for urgent announcements~~ — resolved by spec 17 (`notify_urgent`, `announcement_urgent`, daily quota).
