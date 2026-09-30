# 12 — School Announcements (Papan Pengumuman Digital)

Status: draft v1.0 (2026-09-25)

## Problem

Schools traditionally distribute institutional announcements, circular letters (Surat Edaran), holiday notices, exam schedules, and activity invitations through informal WhatsApp groups. This reliance on messaging channels creates significant communication failures:
1. Critical official notices are rapidly buried under parental chat, stickers, and casual conversations, causing families to miss important deadlines, fee notices, or dress code updates.
2. Communications lack audience targeting: announcements meant strictly for faculty are inadvertently leaked to parents, or Grade 1 notices clutter the feeds of Grade 6 families.
3. Schools have no centralized, searchable historical repository of past official circulars, leaving new teachers or parents without an authoritative reference point.

## Goals

1. Provide an authenticated digital bulletin board (`announcements`) accessible across all user role portals.
2. Support granular audience targeting: `all` (school-wide), `parents`, `teachers`, `students`, or `class` (scoped to a specific classroom roster).
3. Allow authors to format notices with rich Markdown text and attach official supporting documents (PDF circulars, schedules, image posters) up to 5MB.
4. Support pinning critical notices to guarantee they remain at the top of the feed until unpinned.
5. Integrate recent announcements directly into the role landing dashboards (spec 08) and provide a dedicated, searchable announcement archive.
6. Support a draft/publish workflow so administrators and teachers can review content prior to public release.

## Non-goals

- Interactive social media features: comment threads, discussion forums, or like/emoji reactions in v1 (announcements are strictly one-way institutional broadcasts).
- Automated bulk WhatsApp broadcasts upon publishing an announcement in v1 (reserved for emergency alerts in v1.x; portal presentation is standard in v1).
- Time-based auto-expiration or self-deleting notices in v1 (authors manually unpin or delete outdated notices).
- SMS broadcast dispatch.

## User Stories

- **As a school administrator**, I want to publish a school-wide announcement regarding the upcoming National Holiday schedule with an attached official PDF circular, so all families and staff can reference it.
- **As a school administrator**, I want to publish a faculty-only notice about next week's curriculum workshop that is completely invisible to parents and students.
- **As a homeroom teacher**, I want to post an announcement targeted specifically to my Class 5A parents regarding field trip permission slips.
- **As a parent**, I want to glance at my dashboard upon login and see the latest school circular at the top of my feed, with a link to download the official letter.
- **As a student**, I want to view announcements regarding school sports day and club schedules on my portal.

## Decisions

- **Audience Targeting Model**:
  - `audience` enum: `all`, `parents`, `teachers`, `students`, `class`.
  - When `audience = 'class'`, `class_id` is required.
  - Queries filter notices strictly by matching the authenticated user's role or classroom enrollment.
- **Authoring Permissions**:
  - `admin`: Can create and publish announcements targeting any audience across the institution.
  - `teacher`: Can create and publish announcements strictly targeting their assigned homeroom class (`audience = 'class'`). Attempts by teachers to broadcast school-wide or faculty-wide return HTTP 403 Forbidden.
- **Draft and Publication Lifecycle**:
  - The `published_at` timestamp determines visibility. If `published_at IS NULL` or in the future, the notice is a draft visible only to its author and administrators.
  - An announcement becomes live immediately when `published_at <= now()`.
- **Attachment Storage**:
  - Stored in application storage with unique hashed filenames. Accepts `application/pdf`, `image/jpeg`, and `image/png` up to 5120 KB (5MB).
- **Dashboard Widget Presentation**:
  - The top 3 most recent published notices matching the user's role render in a dedicated card on `/dashboard` (spec 08).
  - Pinned notices (`is_pinned = true`) sort ahead of unpinned notices regardless of publication date.

## Requirements

1. **Admin Announcement Management (`/admin/announcements`)**:
   - Admin CRUD interface for all announcements.
   - Form fields: `title` (required, string, max 255 chars), `body` (required, text, Markdown supported), `audience` (required enum), `class_id` (conditional: required if audience is `class`), `is_pinned` (boolean, default false), `attachment` (optional file), `published_at` (nullable datetime).
2. **Teacher Class Announcement Management (`/teacher/classes/{id}/announcements`)**:
   - Homeroom teacher can create, edit, and delete announcements for their assigned class.
   - Audience is locked to `class` with `class_id` bound to that homeroom.
3. **Public Announcement Feed (`GET /announcements`)**:
   - Dedicated searchable bulletin board page.
   - Filterable by keyword search (matches `title` and `body`) and category/date.
   - Shows author name, publication date, pinned badge, rendered Markdown body, and downloadable attachment link.
   - Paginated at 10 announcements per page.
4. **Dashboard Integration Widget**:
   - `/dashboard` controller injects the latest 3 visible announcements matching the user:
     $$\text{Targeted Notices} = \{ a \in \text{announcements} \mid a.\text{published\_at} \le \text{now}() \land (a.\text{audience} = \text{'all'} \lor a.\text{audience} = \text{user.role} \lor a.\text{class\_id} \in \text{user.classes}) \}$$
   - Sorted by `is_pinned DESC, published_at DESC`.

## Schema

### `announcements` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `title` | varchar(255) | not null | Headline title |
| `body` | text | not null | Full announcement content (Markdown) |
| `audience` | varchar(20) | not null, default: 'all' | Enum: `all`, `parents`, `teachers`, `students`, `class` |
| `class_id` | bigint | unsigned, nullable | Foreign key -> `classes.id` (when audience = class) |
| `is_pinned` | boolean | not null, default: false | Top-of-feed pin flag |
| `attachment_path` | varchar(255) | nullable | Path to uploaded file |
| `attachment_name` | varchar(255) | nullable | Original filename for download |
| `published_at` | timestamp | nullable | Live publication timestamp (null = draft) |
| `created_by_user_id` | bigint | unsigned, not null | Foreign key -> `users.id` (Author audit) |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `INDEX (audience, published_at)`
- `INDEX (class_id, published_at)`
- `INDEX (is_pinned, published_at)`

## Acceptance Criteria

- **AC-12-01**: Given an announcement with `audience = 'teachers'`, when a parent or student views the announcements feed or dashboard, that announcement is completely absent from props.
- **AC-12-02**: Given an announcement targeted to Class 5A, when parents of Class 5B log in, the announcement is absent from their feed.
- **AC-12-03**: Given a draft announcement (`published_at = null`), when a non-admin, non-author user queries announcements, the draft is not returned.
- **AC-12-04**: An announcement marked `is_pinned = true` published three weeks ago renders above an unpinned announcement published yesterday.
- **AC-12-05**: A teacher attempting to create an announcement with `audience = 'all'` receives HTTP 403 Forbidden.
- **AC-12-06**: Clicking the attachment link on an announcement downloads the original uploaded file with correct MIME headers.

## Constraints & Assumptions

- Markdown parsing is sanitized on the server or rendered using safe client-side Markdown libraries (e.g. `marked` with DOMPurify) to prevent Cross-Site Scripting (XSS).
- File storage leverages standard Laravel storage disks (`public` or `private` with signed stream downloads).

## Open Questions

- `[NEEDS DECISION: WhatsApp Push for Urgent Announcements]`: When an admin flags an announcement as `urgent`, should the system trigger a WhatsApp broadcast to all guardians using spec 05 infra? (Recommended for v1.1; deferred in v1 to protect third-party messaging costs).
