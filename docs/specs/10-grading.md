# 10 — Student Assessment & Grading (Asesmen & Buku Nilai)

Status: draft v1.1 (2026-10-01) — revised against spec 15 (AUDIT-2026-10-01 P10-01…06). Supersedes draft v1.0 (2026-09-25): per-assessment weights replaced by Kurikulum Merdeka assessment kinds; semester scoping, learning objectives (TP), remedial policy, release and finalization added; curriculum seam introduced.

## Problem

In most Indonesian schools lacking integrated academic software, subject teachers record assessment results in personal spreadsheets or notebooks. This fragmented workflow creates severe operational friction:
1. Homeroom teachers must collect score sheets from every subject teacher at the end of each semester and transcribe them into report cards — slow and error-prone.
2. Parents and students have no visibility into performance during the semester and discover learning difficulties only when the report card is issued.
3. Kurikulum Merdeka report cards require a per-subject achievement description based on learning objectives (Tujuan Pembelajaran / TP), which is laborious to write by hand for every student and subject.
4. School leadership has no oversight of grading progress before report card day.

## Goals

1. Give subject teachers a spreadsheet-like gradebook per course (`class_subjects`) per semester.
2. Model Kurikulum Merdeka assessment: formative (feedback, not counted in the report score), summative per scope of material (sumatif lingkup materi), and end-of-semester summative (sumatif akhir semester / tahun).
3. Link summative assessments to learning objectives (TP) so the system can draft each student's achievement description.
4. Compute each student's semester subject score and compare it to the passing threshold (KKTP; spec 09).
5. Let teachers release individual assessment results to students and guardians during the semester.
6. Provide a finalization step that freezes semester results as the input to report cards (11).
7. Keep raw assessment data curriculum-neutral; curriculum-specific computation sits behind one interface (spec 15).

## Non-goals

- Computer-Based Testing, online quizzes, question banks (v2 candidate).
- More than one curriculum implementation in v1 (only `merdeka`).
- Letter predicates (A–D) — not used by Merdeka report cards.
- Class ranking (discouraged under Kurikulum Merdeka).
- Digital homework submission.
- Assessment of Projek Penguatan Profil Pelajar Pancasila (P5) — separate report, v1.x candidate.
- Attitude (sikap) grades as a separate component (a K13 construct).

## User Stories

- **As a Mathematics teacher**, I want to define the semester's TPs for grade 5 Matematika once and reuse them in every grade-5 class I teach.
- **As a Mathematics teacher**, I want to create "Sumatif Lingkup Materi: Pecahan" linked to TP 1 and TP 2, enter all 30 scores in one grid with keyboard navigation, and release them to parents when I am done checking.
- **As a teacher**, I want to record a remedial score for students below KKTP without losing the original score.
- **As a teacher at the end of the semester**, I want the system to draft each student's achievement description from TP results, edit the ones I disagree with, and finalize the course.
- **As a guardian**, I want to see my son's released IPAS results and whether they reach the KKTP.
- **As a homeroom teacher**, I want one ledger showing every subject's running score for my class and which courses are not yet finalized.

## Decisions

- **Semester scope**: every assessment belongs to one `class_subjects` row and one `semesters` row (15). `assessed_on` must fall inside the semester.
- **Learning objectives (TP)**: `learning_objectives` are scoped to `(academic_year_id, subject_id, grade_level)`, so parallel classes (5A, 5B, 5C) share one list. Teachers holding any assignment for that subject and grade level in that year, and admins, may manage them. "Copy from previous year" is available.
- **Assessment kinds**:
  - `formative`: recorded and releasable, **excluded** from the semester score.
  - `summative_scope` (sumatif lingkup materi): must link to ≥ 1 TP.
  - `summative_final` (sumatif akhir semester/tahun): TP links optional.
- **Remedial policy** (resolves v1.0 open question): a score row keeps the original `score` and an optional `remedial_score`. Effective normalized score = `max(n(score), min(n(remedial_score), passing_threshold))`, where `n(x) = x / max_score × 100`. Remedial can lift a student to the threshold, not above it.
- **Merdeka semester score** (curriculum implementation `merdeka`):
  - `scope_avg` = mean of effective normalized scores over `summative_scope` assessments.
  - `final_avg` = mean over `summative_final` assessments.
  - Score = `(scope_avg × w_scope + final_avg × w_final) / (w_scope + w_final)`; if one kind has no assessments, the other average alone.
  - Weights per course per semester, pre-filled from school settings (default 50 / 50).
  - Report value = score rounded half-up to an integer. `below_threshold` = rounded score < `passing_threshold`.
- **Missing scores**: `score = null` means not yet entered. Live averages exclude nulls and are labelled "belum lengkap"; finalization requires every summative cell to be filled (see below).
- **Draft achievement description** (Merdeka): per TP, the mean of the student's effective normalized scores on linked `summative_scope` assessments. The draft has two sentences: *"Menunjukkan penguasaan yang baik dalam {TP with highest result}."* and *"Perlu bantuan dalam {TP with lowest result}."* (or *"Perlu peningkatan dalam …"* when that TP is at or above the threshold). Teachers can edit the text; edits are kept.
- **Release**: assessment results are hidden from students and guardians until the teacher releases that assessment (`released_at`). The semester score and description are visible to students and guardians only through a published report card (11).
- **Finalization** (per course per semester):
  - Requires every `summative_*` assessment to have a non-null score for each student enrolled in the class on that assessment's `assessed_on`.
  - Freezes `subject_results` (score and description) and blocks edits to that course's assessments and scores for the semester (HTTP 422).
  - Can be undone by the course teacher or an admin **unless** the class's report cards for that semester are published (11). Those must be unpublished first.
- **Curriculum seam**: a `GradingScheme` interface (computation of semester score, per-TP results, draft description, allowed assessment kinds) is resolved from `classes.curriculum` (15). `merdeka` is the only v1 implementation. Tables are curriculum-neutral.
- **Roster eligibility**: an assessment's grid lists students enrolled in the class on `assessed_on` (`classOn`, spec 02). Students who transferred in or out appear only for assessments inside their enrollment window.
- **Audit**: score changes, assessment deletion, weight changes and (un)finalization are audit-logged (15).
- **Access** (matrix in 15): course teacher writes own course; admin writes all; homeroom teacher and principal read; guardians/students read released results of own child / self.

## Requirements

1. **Learning Objectives (`/teacher/objectives`)**:
   - Filter by year, subject, grade level (limited to the teacher's assignments).
   - Fields: `code` (e.g. "TP 1"), `description` (max 500), `sort_order`.
   - Delete guard: a TP linked to an assessment cannot be deleted (HTTP 422).
   - "Copy from previous year" copies the previous year's list for the same subject and grade level.
2. **Assessment Management (`/teacher/courses/{id}/assessments?semester=`)**:
   - Fields: `name`, `kind`, `assessed_on` (within semester), `max_score` (> 0, default 100), linked TPs (required for `summative_scope`), `sort_order`.
   - Deleting an assessment with scores requires confirmation; the audit row stores the deleted scores. Blocked when the course is finalized.
3. **Gradebook (`GET /teacher/courses/{id}/gradebook?semester=`)**:
   - Grid: eligible students × assessments grouped by kind; columns for live semester score, KKTP status ("Tuntas" / "Belum Tuntas"), completeness.
   - Inline entry of `score` (0 – `max_score`), `remedial_score` (shown when score is below threshold), and optional `notes` per cell.
   - Keyboard navigation (arrows, Tab, Enter); batch save `POST /teacher/courses/{id}/scores` with `[{ assessment_id, student_id, score, remedial_score, notes }]`.
   - Weight editor for `w_scope` / `w_final`.
   - "Rilis nilai" action per assessment sets `released_at`.
4. **Descriptions & Finalization (`/teacher/courses/{id}/results?semester=`)**:
   - Lists each student's score, per-TP results, and drafted (or edited) description.
   - "Finalisasi" validates completeness (HTTP 422 listing missing cells) and freezes results; "Batalkan finalisasi" subject to the publication rule.
5. **Homeroom Ledger (`GET /teacher/classes/{id}/grades?semester=`)**:
   - Students × subjects with live or finalized score; cells below threshold highlighted; per-subject finalization status header. Read-only.
6. **Guardian & Student Views**:
   - `GET /parent/children/{id}/grades?semester=` and `GET /student/grades?semester=`: subjects, teacher, KKTP, and released assessment results with dates, remedial result, and teacher notes. No semester score or description before report card publication.

## Schema

### 1. `learning_objectives` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `academic_year_id` | bigint | unsigned, not null | FK -> `academic_years.id` |
| `subject_id` | bigint | unsigned, not null | FK -> `subjects.id` |
| `grade_level` | tinyint | unsigned, not null | 1–12 |
| `code` | varchar(20) | not null | e.g. "TP 1" |
| `description` | varchar(500) | not null | Objective text used in descriptions |
| `sort_order` | integer | not null, default: 0 | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (academic_year_id, subject_id, grade_level, code)`

### 2. `course_semesters` Table

Per-course per-semester settings and finalization state.

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `class_subject_id` | bigint | unsigned, not null | FK -> `class_subjects.id` |
| `semester_id` | bigint | unsigned, not null | FK -> `semesters.id` |
| `scope_weight` | decimal(5,2) | not null | Weight of `summative_scope` average |
| `final_weight` | decimal(5,2) | not null | Weight of `summative_final` average |
| `finalized_at` | timestamp | nullable | Null = open |
| `finalized_by_user_id` | bigint | unsigned, nullable | FK -> `users.id` |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (class_subject_id, semester_id)`

### 3. `assessments` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `class_subject_id` | bigint | unsigned, not null | FK -> `class_subjects.id` |
| `semester_id` | bigint | unsigned, not null | FK -> `semesters.id` |
| `name` | varchar(100) | not null | e.g. "Sumatif Lingkup Materi: Pecahan" |
| `kind` | varchar(20) | not null | Enum: `formative`, `summative_scope`, `summative_final` |
| `assessed_on` | date | not null | Within the semester |
| `max_score` | decimal(5,2) | not null, default: 100.00 | |
| `released_at` | timestamp | nullable | Visible to guardians/students when set |
| `sort_order` | integer | not null, default: 0 | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `INDEX (class_subject_id, semester_id, sort_order)`

### 4. `assessment_objectives` Table (Pivot)

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `assessment_id` | bigint | unsigned, not null | FK -> `assessments.id` (cascade delete) |
| `learning_objective_id` | bigint | unsigned, not null | FK -> `learning_objectives.id` (restrict) |

**Primary Key:** `(assessment_id, learning_objective_id)`

### 5. `scores` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `assessment_id` | bigint | unsigned, not null | FK -> `assessments.id` (cascade delete) |
| `student_id` | bigint | unsigned, not null | FK -> `students.id` |
| `score` | decimal(5,2) | nullable | 0 – `max_score`; null = not entered |
| `remedial_score` | decimal(5,2) | nullable | 0 – `max_score` |
| `notes` | varchar(255) | nullable | Teacher feedback |
| `graded_by_user_id` | bigint | unsigned, not null | FK -> `users.id` |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (assessment_id, student_id)`, `INDEX (student_id)`

### 6. `subject_results` Table

Working record for descriptions; frozen values at finalization. Input to report cards (11).

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `course_semester_id` | bigint | unsigned, not null | FK -> `course_semesters.id` |
| `student_id` | bigint | unsigned, not null | FK -> `students.id` |
| `final_score` | tinyint | unsigned, nullable | Rounded score, set at finalization |
| `description` | text | nullable | Drafted or teacher-edited achievement description |
| `description_edited` | boolean | not null, default: false | True once the teacher overrides the draft |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (course_semester_id, student_id)`

### 7. `settings` — added columns

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `default_scope_weight` | decimal(5,2) | not null, default: 50.00 | Pre-fill for `course_semesters.scope_weight` |
| `default_final_weight` | decimal(5,2) | not null, default: 50.00 | Pre-fill for `course_semesters.final_weight` |

## Acceptance Criteria

- **AC-10-01**: Saving a score of 105 or −5 on an assessment with `max_score = 100` returns HTTP 422.
- **AC-10-02**: Course with weights 50/50; student has `summative_scope` results 80 and 70 (avg 75) and `summative_final` 90; semester score = (75 × 50 + 90 × 50) / 100 = 82.5 → report value 83.
- **AC-10-03**: A `formative` result of 20 does not change the semester score computed in AC-10-02.
- **AC-10-04**: Threshold 75; original score 60, remedial 90 → effective score 75 (capped at threshold); original 60, remedial 70 → effective 70.
- **AC-10-05**: Creating a `summative_scope` assessment without any linked TP returns HTTP 422.
- **AC-10-06**: A student whose TP 1 result is 92 and TP 3 result is 64 (threshold 75) gets the draft "Menunjukkan penguasaan yang baik dalam {TP 1}. Perlu bantuan dalam {TP 3}."
- **AC-10-07**: A guardian requesting grades before an assessment is released does not receive that assessment in the payload; after "Rilis nilai" it appears.
- **AC-10-08**: Finalizing a course with one empty summative cell returns HTTP 422 listing the student and assessment; once filled, finalization succeeds and `subject_results.final_score` is set.
- **AC-10-09**: Editing a score in a finalized course returns HTTP 422.
- **AC-10-10**: Undoing finalization while the class's report cards for that semester are published returns HTTP 422.
- **AC-10-11**: Teacher Siti (5A Matematika) saving scores for 5A IPAS (taught by Budi) receives HTTP 403.
- **AC-10-12**: A guardian requesting grades of an unlinked student receives HTTP 403.
- **AC-10-13**: A student who transferred into 5A on 2026-10-01 does not appear in the grid of a 5A assessment dated 2026-09-20.
- **AC-10-14**: Updating a score writes an audit log row with old and new values (15).

## Constraints & Assumptions

- `decimal(5,2)` scores; class gradebooks ≤ 50 students and ≤ 40 assessments per semester render client-side.
- Requires specs 09 and 15.

## Open Questions

- `[NEEDS DECISION: Description wording per school]`: Draft sentence templates are fixed in v1. Some schools use different phrasing; making templates editable in settings is a v1.x candidate.
