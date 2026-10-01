# 15 — Academic Foundation (Roles, Semesters, Class Levels, School Profile & Audit)

Status: draft v1.0 (2026-10-01) — prerequisite for 09–14 (resolves AUDIT-2026-10-01 S-01, S-02, S-03, S-04, S-06, P10-05)

## Problem

Wave 1 was built around four fixed roles, a year-only academic calendar and an attendance-only settings row. Wave 2–3 specs (09–14) already depend on things this foundation does not provide:

1. Personas with no role: principal (05, 06, 11), counselor / Guru BK (06), finance staff / bendahara (14). Teachers whose own children attend the school need two accounts.
2. No term concept below the academic year: gradebooks (10) mix Semester 1 and 2 scores, and report cards (11) invent their own `report_periods`.
3. Classes have no grade level, yet roll-over suggestions (07), fee generation (14) and curriculum phases need it.
4. School identity (name, NPSN, principal, bank account) needed by message templates (05), report cards (11) and receipts (14) is stored nowhere.
5. Only attendance overrides carry an actor stamp; grades, report publication and payments need a full change history.
6. Indonesian curricula change roughly every decade and transitions are staged per grade level. The data model must survive a curriculum change without migrating historical grades.

## Goals

1. Replace the single `users.role` column with multiple roles per user and a code-defined permission matrix.
2. Introduce `semesters` as the term entity under `academic_years`, used by grading (10), report cards (11) and timetables (13).
3. Give every class a `grade_level` and a `curriculum`, so curriculum transitions can be staged per grade.
4. Store the school profile once and reuse it in every document and message.
5. Provide an append-only audit log for sensitive academic and financial mutations.

## Non-goals

- Admin-editable permissions or custom roles in v1 (the role → permission matrix is defined in code).
- Supporting more than one curriculum implementation in v1 (only `merdeka` is implemented; the seam exists so a later curriculum is an addition, not a migration).
- Trimester / quarter systems (two semesters per year in v1).
- Audit log UI beyond a per-record history panel and an admin list filterable by entity and user.
- Multi-school / multi-campus.

## User Stories

- **As a teacher whose child attends the school**, I want one login that lets me take attendance for my class and also check my child's attendance and report card.
- **As a principal**, I want read-only access to all attendance, grades and report card drafts, without being able to change data.
- **As a counselor (Guru BK)**, I want to view attendance reports for every class so I can follow up chronic absence.
- **As finance staff**, I want access to fees and payments only, with no access to grades.
- **As an admin**, I want creating academic year 2027/2028 to create its Ganjil and Genap semesters automatically, which I can then adjust.
- **As an admin during a curriculum transition**, I want classes in grade 1 and 4 to use the new curriculum while grades 2, 3, 5 and 6 stay on the old one for that year.
- **As a principal investigating a dispute**, I want to see who changed a student's score, when, and from what value.

## Decisions

### Roles & permissions (S-01)

- **Roles** (enum, stored in `user_roles`): `admin`, `principal`, `teacher`, `counselor`, `finance`, `staff`, `parent`, `student`. `staff` = non-teaching employee self-service (spec 16).
- **Multiple roles per user.** A user holds one or more roles. `student` is exclusive: it cannot be combined with any other role.
- **Authorization = union of the user's roles.** Policies and gates check whether *any* held role grants the ability. No request-time "active role" affects authorization.
- **Active role is UI-only.** Users with several roles pick an active role from a header switcher (stored in the session — never in static/singleton state, Octane). Navigation and the dashboard (08) render for the active role. Default: the first role in the order above.
- **Data scope stays data-derived, not role-derived.** Homeroom and subject-teacher scope (02, 09) and guardian ↔ child links (02) remain the scoping mechanism; roles only grant capabilities.
- **Profiles**: a user may link to both a `teachers` and a `guardians` row (each `user_id` is already unique per table). Password-reset contact lookup (01) order: teacher profile → guardian profile → `users.email`.
- **Last-admin guard**: removing the `admin` role from the last active admin is rejected.

**Permission matrix (v1, code-defined):**

| Capability | admin | principal | teacher | counselor | finance | parent | student |
|---|---|---|---|---|---|---|---|
| Manage users, roles, master data, settings | ✓ | | | | | | |
| Attendance override / bulk marking | ✓ all | | scoped | | | | |
| Excuse review (approve/reject) | ✓ | | | | | | |
| Attendance reports & presence board | ✓ all | ✓ all | scoped | ✓ all | | own children | self |
| Gradebook entry | ✓ all | | own `class_subjects` | | | | |
| View grades & report card drafts | ✓ all | ✓ all | scoped | | | | |
| Publish / unpublish report cards | ✓ | | homeroom class | | | | |
| View published report cards | ✓ | ✓ | scoped | | | own children | self |
| Fees & payments (14) | ✓ | read | | | ✓ | own children | self (read) |
| Audit log | ✓ | ✓ (read) | | | | | |
| Staff attendance overview, recap, leave approval (16) | ✓ | ✓ | | | | | |

Own staff attendance and leave requests (16) are available to any user linked to an employee record, regardless of role; `staff` grants nothing beyond that self-service. "scoped" = homeroom classes plus classes with an assignment in `class_subjects` (09), in the selected academic year.

### Semesters (S-02)

- `semesters` belong to `academic_years`; exactly two per year (`number` 1 = Ganjil, 2 = Genap).
- Creating an academic year auto-creates both semesters, split at 1 January (Ganjil: `starts_at` → 31 Dec; Genap: 1 Jan → `ends_at`). Admin may adjust the boundary.
- Semesters within a year are contiguous and non-overlapping, and lie inside the year's range.
- **Current semester is derived, not flagged**: the semester whose range contains the school-timezone date. During a gap (none by default) the most recently started semester is current.
- Report cards (11) are per semester; `report_periods` is dropped.

### Grade level & curriculum (S-03, P10-05)

- `classes.grade_level`: integer 1–12 (SD 1–6, SMP 7–9, SMA/SMK 10–12).
- `classes.curriculum`: string enum; v1 value set = `merdeka`. Set when the class is created (default from school setting `default_curriculum`), editable until the class has finalized grades.
- Merdeka phase is **derived** from `grade_level`, never stored: A = 1–2, B = 3–4, C = 5–6, D = 7–9, E = 10, F = 11–12.
- **Curriculum seam**: score aggregation, subject descriptions and the report card template are resolved per class from `classes.curriculum` through one interface (specified in 10 and 11). Core data — subjects, assignments, assessments, raw scores, attendance — is curriculum-neutral.
- Published report cards are stored as immutable snapshots (11), so old report cards remain readable after a curriculum's code is retired.
- Roll-over (07) sets `grade_level` and `curriculum` on newly created target classes (auto-suggest: source `grade_level + 1`, `default_curriculum`).

### School profile (S-04)

- Profile fields extend the existing single-row `settings` table (spec 03), since it is already the school configuration row.
- The principal is stored as name + NIP text (not a user link): the signatory on documents may not have an account, and documents snapshot the value at issue time.

### Audit log (S-06)

- `audit_logs` is append-only (never updated or deleted in v1).
- One row per mutation of an audited entity: actor, action, entity, old/new values of changed attributes, optional reason.
- Audited entities: `user_roles`, `settings`, manual attendance overrides (03), assessments, scores and course finalization (10), report card publication (11), bills and payments (14).
- Written in the same database transaction as the mutation; a failed audit write fails the mutation.

## Requirements

1. **Role management (`/admin/users/{id}`)**:
   - Admin assigns/removes roles via multi-select; `student` exclusive; last-admin guard.
   - Data migration: every existing `users.role` becomes one `user_roles` row; `users.role` is then dropped.
2. **Active role switcher**:
   - Shown only for users with more than one role. `PUT /active-role` validates the role is held, stores it in the session, redirects to `/dashboard`.
3. **Semester management (`/admin/academic-years/{id}/semesters`)**:
   - Auto-creation on academic year create; edit dates with contiguity and containment validation (HTTP 422 on violation).
   - Deleting a semester is not supported; deleting an academic year follows spec 02 guards.
   - Data migration: create both semesters for every existing academic year using the 1 January split.
4. **Class level & curriculum**:
   - Class create/edit forms require `grade_level` (1–12) and `curriculum`.
   - Data migration: backfill `grade_level` from the leading digits of `classes.name` where present; classes without a parsable level are listed on an admin "complete class data" banner until set. `curriculum` backfilled as `merdeka`.
5. **School profile (`/settings/school`)**:
   - Admin form for the profile fields; logo upload (PNG/JPG, max 1 MB, private storage, served via authorized route).
   - `{school_name}` in spec 05 templates resolves from this profile.
6. **Audit log**:
   - Per-record "Riwayat perubahan" panel on audited screens (admin, principal).
   - `GET /admin/audit-logs`: paginated list filterable by entity type, entity id, user and date range.

## Schema

### 1. `user_roles` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `user_id` | bigint | unsigned, not null | FK -> `users.id` (cascade delete) |
| `role` | varchar(20) | not null | Enum: `admin`, `principal`, `teacher`, `counselor`, `finance`, `staff`, `parent`, `student` |
| `created_at` | timestamp | nullable | |

**Primary Key:** `(user_id, role)` · **Index:** `INDEX (role)`

`users.role` is removed (supersedes the spec 01 column once this spec ships).

### 2. `semesters` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `academic_year_id` | bigint | unsigned, not null | FK -> `academic_years.id` |
| `number` | tinyint | unsigned, not null | 1 = Ganjil, 2 = Genap |
| `name` | varchar(50) | not null | e.g. "Semester Ganjil" |
| `starts_at` | date | not null | |
| `ends_at` | date | not null | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (academic_year_id, number)`, `INDEX (starts_at, ends_at)`

### 3. `classes` — added columns

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `grade_level` | tinyint | unsigned, not null | 1–12 |
| `curriculum` | varchar(30) | not null, default: 'merdeka' | Curriculum implementation key |

**Index:** `INDEX (academic_year_id, grade_level)`

### 4. `settings` — added columns (school profile)

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `school_name` | varchar(255) | not null, default: '' | Official school name |
| `npsn` | varchar(20) | nullable | Nomor Pokok Sekolah Nasional |
| `school_address` | text | nullable | |
| `school_phone` | varchar(30) | nullable | |
| `school_email` | varchar(255) | nullable | |
| `logo_path` | varchar(255) | nullable | Private storage path |
| `principal_name` | varchar(255) | nullable | Signatory name with titles |
| `principal_nip` | varchar(30) | nullable | |
| `bank_name` | varchar(100) | nullable | For transfer instructions (14) |
| `bank_account_number` | varchar(50) | nullable | |
| `bank_account_holder` | varchar(255) | nullable | |
| `default_curriculum` | varchar(30) | not null, default: 'merdeka' | Default for new classes |

### 5. `audit_logs` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `user_id` | bigint | unsigned, nullable | FK -> `users.id` (null = system/scheduler) |
| `action` | varchar(30) | not null | `created`, `updated`, `deleted`, or domain verb (`published`, `unpublished`, `finalized`, `verified`, `voided`, …) |
| `auditable_type` | varchar(100) | not null | Entity type |
| `auditable_id` | bigint | unsigned, not null | Entity id |
| `old_values` | json | nullable | Changed attributes before |
| `new_values` | json | nullable | Changed attributes after |
| `reason` | varchar(255) | nullable | Required by some actions (e.g. payment void, report unpublish) |
| `ip_address` | varchar(45) | nullable | |
| `created_at` | timestamp | not null | |

**Indexes:** `INDEX (auditable_type, auditable_id, created_at)`, `INDEX (user_id, created_at)`

## Acceptance Criteria

- **AC-15-01**: A user with roles `teacher` and `parent` can open their homeroom attendance page and their linked child's report with one session; both return HTTP 200.
- **AC-15-02**: Assigning `student` together with any other role is rejected with HTTP 422.
- **AC-15-03**: Removing `admin` from the last active admin is rejected with HTTP 422.
- **AC-15-04**: A `principal` attempting any attendance override, grade entry or publication receives HTTP 403; viewing all class reports returns HTTP 200.
- **AC-15-05**: A `finance` user requesting a gradebook receives HTTP 403.
- **AC-15-06**: Creating academic year 2027/2028 (2027-07-13 → 2028-06-24) creates Semester Ganjil (2027-07-13 → 2027-12-31) and Semester Genap (2028-01-01 → 2028-06-24).
- **AC-15-07**: Editing semesters so they overlap or leave the year's range is rejected with HTTP 422.
- **AC-15-08**: On 2027-09-01 the current semester resolves to Ganjil 2027/2028.
- **AC-15-09**: A class with `grade_level = 5` resolves Merdeka phase C.
- **AC-15-10**: Updating a score writes one `audit_logs` row with actor, old and new score in the same transaction; if the audit insert fails, the score update is rolled back.
- **AC-15-11**: After migration, every pre-existing user holds exactly one role equal to their former `users.role`.

## Constraints & Assumptions

- Permission checks use Laravel Gates/Policies over the code-defined matrix; no new package dependency is assumed (adding one, e.g. a permissions package, requires approval).
- Active role lives in the session only (Octane: no request state in singletons).

## Open Questions

- `[NEEDS DECISION: Counselor scope]`: Counselors get school-wide read access to attendance reports in v1. Should they also see grades? (Default: no, until the BK module exists.)
- `[NEEDS DECISION: Audit retention]`: Retained indefinitely in v1; revisit together with the `scan_events` retention question (03) and personal-data obligations (AUDIT F-08).
