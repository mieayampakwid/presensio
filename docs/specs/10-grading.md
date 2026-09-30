# 10 — Student Assessment & Grading (Penilaian & Buku Nilai)

Status: draft v1.0 (2026-09-25)

## Problem

In most Indonesian schools lacking integrated academic software, subject teachers record student scores (Tugas, Ulangan Harian, UTS, UAS, Praktik) in personal spreadsheets or physical notebooks. This fragmented workflow creates severe operational friction:
1. Homeroom teachers must repeatedly request score sheets from multiple subject teachers at the end of each term, manually transcribing numbers into report cards—a process rife with transcription errors, lost scores, and delays.
2. Parents and students have zero visibility into academic performance during the semester. Families discover learning difficulties or failing grades only when the final report card is issued, missing crucial windows for remedial tutoring.
3. School leadership has no centralized oversight over grading progress, curriculum completion, or student academic achievement.

## Goals

1. Provide subject teachers with an intuitive, spreadsheet-like online gradebook (Buku Nilai) for their assigned courses (`class_subjects`).
2. Support customizable assessment categories (e.g. Tugas/PR, Ulangan Harian, Ujian Tengah Semester, Ujian Akhir Semester, Praktik) with flexible weighting and maximum scores.
3. Automatically compute composite weighted or arithmetic averages per student per subject and compare them dynamically against the subject's KKM (passing threshold).
4. Provide students and guardians with real-time academic progress dashboards displaying recorded scores, class averages, passing status, and teacher remarks.
5. Provide homeroom teachers with a consolidated academic ledger view of all subject scores across their entire classroom roster.
6. Serve as the verified data source for semester report card compilation (spec 11).

## Non-goals

- Computer-Based Testing (CBT), online quiz builders, or automated digital exam question banks in v1 (Presensio records grades evaluated through classroom assignments, paper tests, or external platforms).
- Automated letter grade conversions (e.g. A, B, C, D) in v1 (numerical scoring 0.00 to 100.00 is standard; letter scale mapping is scoped in report cards; spec 11).
- Automated statistical bell-curve score normalizations.
- Cohort-wide student GPA / Class ranking in v1 (ranking is pedagogically discouraged under Kurikulum Merdeka).
- Digital homework submission / student file upload in v1.

## User Stories

- **As a Mathematics teacher**, I want to create an assessment named "Ulangan Harian 1 (Aljabar)" with a maximum score of 100 and a 20% weight, and rapidly enter grades for all 30 students in a single table, so that my gradebook is organized and calculated automatically.
- **As an English teacher**, I want to enter a note ("Perlu perbaikan pengucapan") alongside a speaking test score, so that the student and their parents receive actionable feedback.
- **As a working guardian**, I want to open the Presensio portal and check my son's latest Science test score, seeing whether he surpassed the KKM of 75.
- **As a homeroom teacher**, I want to open my classroom academic summary and see which students are struggling below KKM across any subject, so I can coordinate parent-teacher conferences before semester finals.
- **As a student**, I want to view my grades online to see my progress and identify which subjects require extra study.

## Decisions

- **Flexible Assessment Hierarchy Per Course**:
  - `grade_categories` belong to `class_subjects`. This allows distinct grading schemes per subject: a Science course may define "Praktikum Laboratorium", while a Religion course defines "Hafalan Surat".
  - Each category defines a `name`, `max_score` (default 100.00), an optional `weight` percentage, and a sort order.
- **Composite Scoring Formula**:
  - *Weighted Average (when weights are configured)*:
    $$\text{Final Score} = \frac{\sum_{i=1}^{n} (\text{score}_i / \text{max\_score}_i \times 100 \times \text{weight}_i)}{\sum_{i=1}^{n} \text{weight}_i}$$
  - *Simple Mean (when weights are null or zero)*:
    $$\text{Final Score} = \frac{1}{m} \sum_{j=1}^{m} \left(\frac{\text{score}_j}{\text{max\_score}_j} \times 100\right)$$
  - Any score `< class_subjects.kkm` is flagged as `below_kkm` (remedial candidate).
- **Batch Grid Input UI**:
  - The teacher interface presents a grid: Students (rows) $\times$ Assessments (columns).
  - Keyboard navigation (Arrow keys, Tab, Enter) allows rapid numerical data entry without clicking individual forms.
  - Autosaves in batches or on form submission with instant validation.
- **Enrollment Eligibility Guard**:
  - Grades can only be recorded for students who have an active or historical enrollment in the course's class that covers the assessment date.
- **Access Control Matrix**:
  - `admin`: Full read, create, update, delete across all courses and grades.
  - `subject teacher`: Full read, create, update for their assigned `class_subjects`.
  - `homeroom teacher`: Read-only access to all `class_subjects` belonging to their homeroom class.
  - `parent`: Read-only access to scores of their linked children.
  - `student`: Read-only access to their own scores.

## Requirements

1. **Assessment Category Management (`/teacher/courses/{id}/categories`)**:
   - Authorized subject teacher can create, edit, reorder, and delete categories for their course.
   - Fields: `name` (required, string, e.g. "UTS"), `max_score` (required, decimal > 0, default 100.00), `weight` (optional, decimal 0.00 to 100.00), `assessed_on` (required, date).
   - Deletion guard: Deleting a category with recorded student scores requires explicit confirmation and cascade deletes child scores with audit warning.
2. **Gradebook Entry Interface (`GET /teacher/courses/{id}/gradebook`)**:
   - Matrix interface listing all enrolled students in the class.
   - Displays all defined categories as columns, with an auto-calculated "Rata-Rata" (Average) column and "Status KKM" badge (Tuntas / Belum Tuntas).
   - Inline score input: validates numerical value between 0.00 and `max_score`.
   - Cell click opens popover to input optional `notes` (e.g. feedback, remedial note).
   - Bulk save endpoint: `POST /teacher/courses/{id}/grades` accepting array of `{ student_id, grade_category_id, score, notes }`.
3. **Homeroom Class Academic Ledger (`GET /teacher/classes/{id}/grades`)**:
   - Homeroom teacher view consolidating all subjects taught in that classroom.
   - Table showing each student and their running average score across all subjects.
   - Highlights any cell where the score is below the subject's KKM in soft red.
4. **Parent & Student Grade Views**:
   - Parent portal: `GET /parent/children/{id}/grades` displaying enrolled subjects, teacher name, KKM, current average, and breakdown of recorded test scores with dates and teacher notes.
   - Student portal: `GET /student/grades` displaying the authenticated student's scores.

## Schema

### 1. `grade_categories` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `class_subject_id` | bigint | unsigned, not null | Foreign key -> `class_subjects.id` |
| `name` | varchar(100) | not null | Name of assessment (e.g. "Tugas 1", "UTS") |
| `assessed_on` | date | not null | Date assessment was conducted |
| `max_score` | decimal(5,2) | not null, default: 100.00 | Maximum possible score |
| `weight` | decimal(5,2) | nullable | Relative weight percentage (e.g. 25.00) |
| `sort_order` | integer | not null, default: 0 | Display column order |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `INDEX (class_subject_id, sort_order)`

### 2. `grades` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `grade_category_id` | bigint | unsigned, not null | Foreign key -> `grade_categories.id` (cascade delete) |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` |
| `score` | decimal(5,2) | nullable | Numerical score obtained (0.00 to max_score) |
| `notes` | varchar(255) | nullable | Teacher qualitative comment |
| `graded_by_user_id` | bigint | unsigned, not null | Foreign key -> `users.id` (Audit of grader) |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (grade_category_id, student_id)`
- `INDEX (student_id)`

## Acceptance Criteria

- **AC-10-01**: Given a category with `max_score = 100.00`, attempting to save a student score of `105.00` or `-5.00` is rejected with validation error HTTP 422.
- **AC-10-02**: Given a course with two categories: "Tugas" (weight 40%, score 80) and "UAS" (weight 60%, score 90), the computed subject average is exactly $(80 \times 0.40) + (90 \times 0.60) = 86.00$.
- **AC-10-03**: Given a subject with KKM 75.00, a student with score 70.00 is tagged with `below_kkm = true` and displays "Belum Tuntas".
- **AC-10-04**: When Teacher Siti (Math teacher for 5A) submits grades via `/teacher/courses/{id}/grades`, the records are saved and stamped with `graded_by_user_id = Siti.user_id`.
- **AC-10-05**: Teacher Siti attempting to edit grades for Class 5A Science (taught by Teacher Budi) receives HTTP 403 Forbidden.
- **AC-10-06**: When a parent logs in, they can view only the scores belonging to their linked children; attempting to query an unlinked student ID returns HTTP 403.

## Constraints & Assumptions

- Scores are stored with two decimal places of precision (`decimal(5,2)` accommodates 0.00 to 999.99).
- Single-school scale: class gradebooks contain <= 50 students and <= 30 categories per term, enabling instant client rendering.

## Open Questions

- `[NEEDS DECISION: Remedial Score Policy]`: When a student retakes a test (Remedial), should the system overwrite the original score, cap the score at KKM (standard Indonesian public school practice), or store both scores? (In v1, teachers update the score or add a "Remedial" category; automated KKM-capping rule deferred to v1.1).
