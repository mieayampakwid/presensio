# 11 — Digital Report Cards (Rapor Semester)

Status: draft v1.1 (2026-10-01) — revised against specs 10 and 15 (AUDIT-2026-10-01 P11-01…06). Supersedes draft v1.0 (2026-09-25): `report_periods` replaced by `semesters` (15); publication now snapshots immutable versions; mid-semester transfer rule, promotion decision and extracurricular notes added; `principal_notes` removed.

## Problem

Compiling semester report cards (Rapor) is one of the most labor-intensive periods of the Indonesian school calendar. Homeroom teachers (Wali Kelas) collect grades from every subject teacher, tally attendance from separate books, and write narratives in standalone word processors. The process causes delays and transcription errors, and risks premature leaks before review. Paper-only distribution requires in-person attendance, leaves working parents without accessible records, and offers no archival resilience against lost booklets. Once issued, a report card is an official document: it must not silently change afterwards.

## Goals

1. Produce one report card per student per semester (15), synthesizing:
   - Finalized subject results — score and achievement description (10).
   - Semester attendance — Sakit, Izin, Tanpa Keterangan (03, 04).
   - Homeroom teacher notes, extracurricular notes, and (in semester 2) the promotion decision.
2. Gate visibility: nothing is visible to guardians or students until the class's report cards are published.
3. Make every publication an immutable, versioned snapshot so later data changes never alter an issued report card.
4. Render a printable A4 PDF following the Kurikulum Merdeka report layout, using the school profile (15).
5. Give guardians and students a self-service archive of published report cards.
6. Feed the semester-2 promotion decision into roll-over (07).

## Non-goals

- AI-generated narratives (descriptions are drafted by rule in spec 10 and edited by teachers).
- Digital PKI signatures or e-meterai (signature lines and school identity only; signature/stamp images are a v1.x candidate).
- Class ranking.
- Structured extracurricular grading, P5 project report, health/physical records (extracurricular is free text in v1).
- Mid-semester progress reports (PTS) — v1.x candidate.
- Dapodik / e-Rapor synchronization.
- Report cards for students who left the school mid-semester (transfer-out documents are a separate future feature; AUDIT F-06).

## User Stories

- **As a homeroom teacher**, I want to see which of my class's 9 subjects are finalized, write a note for each student, preview Ahmad's PDF, and publish the whole class when everything is complete.
- **As a homeroom teacher in semester Genap**, I want to record "Naik ke kelas 6" or "Tinggal di kelas 5" for each student so the admin's roll-over is pre-filled.
- **As a homeroom teacher who found a mistake after publishing**, I want to unpublish with a reason, fix it, and republish, with the parents' copy clearly marked as a new version.
- **As a parent**, I want to open my daughter's published report card on my phone and download the PDF.
- **As a principal**, I want to review all classes' drafts before distribution day.

## Decisions

- **Per semester, per class**: report cards are generated for `(semester_id, class_id)`. `report_periods` from v1.0 is dropped.
- **Roster**: students enrolled in the class on the *as-of date* = `min(today, semester.ends_at)` (`classOn`, spec 02).
- **Subject rows**: one row per `class_subjects` of the class, ordered by `subjects.group` then `sort_order` (09). For each row, use the student's `subject_results` (10) from that course. If the student transferred in and has no result there, use a finalized result for the **same `subject_id`** from their previous class in the same semester, and label it with that class name. Otherwise the row is shown empty and publication is blocked for that student.
- **Attendance**: counts over the semester range up to the as-of date, across all of the student's enrollments (attendance follows the student, not the class). Displayed as Sakit (`sick`), Izin (`leave`), Tanpa Keterangan (`absent`). `late` counts as present and is not shown.
- **Publication preconditions**:
  1. Every `class_subjects` row of the class has a finalized `course_semesters` row for the semester (10).
  2. Semester 2 only: every student has a `promotion_decision`.
  - Missing homeroom notes produce a warning, not a block.
- **Immutable snapshot**: publishing creates a new `report_card_publications` version and one `report_cards` row per student containing the full rendered payload as JSON. The payload includes the school profile, principal name and NIP, homeroom teacher name, class, phase, subjects, scores, descriptions, attendance, notes, decision, version number, and publication date. Guardians, students and PDFs read **only** the snapshot.
- **Unpublish = retract**: retracting requires a reason and is audited (15). The version stays in the database with `retracted_at` set and becomes invisible to guardians and students. Republishing creates version n+1. The PDF footer prints "Versi {n}, diterbitkan {date}" so stale downloads are identifiable.
- **Promotion decision → roll-over**: in semester 2, `promotion_decision` (`promoted`, `retained`, `graduated`) pre-fills the roll-over screen (07). `retained` students are pre-mapped to a class of the same `grade_level`; `graduated` students have no successor enrollment. The admin can still override in roll-over.
- **Curriculum seam**: the snapshot builder and PDF template are resolved from `classes.curriculum` (15); `merdeka` is the only v1 implementation.
- **Access** (matrix in 15): homeroom teacher of the class and admins edit notes and publish/retract; principals and the class's subject teachers view drafts; guardians and students view published snapshots of their own child / self.

## Requirements

1. **Report Card Workspace (`GET /teacher/classes/{id}/report-cards?semester=`)**:
   - Header: subject finalization checklist (subject, teacher, finalized yes/no).
   - Student list with per-student completeness (subjects with results, notes present, decision present for semester 2).
   - Per-student form: `homeroom_notes` (max 1000), `extracurricular_notes` (max 500), `promotion_decision` (semester 2 only).
   - Preview renders the would-be snapshot (HTML and PDF) without persisting it.
2. **Publish / Retract**:
   - `POST /teacher/classes/{id}/report-cards/publish` with `semester_id`: validates preconditions (HTTP 422 listing what is missing), then in one transaction creates the publication version and all student snapshots, and writes an audit log row.
   - `POST /teacher/classes/{id}/report-cards/retract` with `semester_id` and required `reason`: sets `retracted_at` on the current version and writes an audit log row.
   - While a version is current (published, not retracted), undoing finalization of any of the class's courses for that semester is blocked (10).
3. **Guardian & Student Portal**:
   - `GET /parent/children/{id}/report-cards` and `GET /student/report-cards`: semesters with a current published version.
   - View renders the snapshot; `GET …/report-cards/{semester_id}/pdf` streams the PDF (`Content-Disposition: attachment`).
   - No current version → HTTP 403 with message "Rapor belum diterbitkan untuk periode ini."
4. **PDF (Merdeka template)**:
   - A4, 2 cm margins.
   - School header (kop) from the school profile, with logo.
   - Identity block: Nama, NIS/NISN, Kelas, Fase, Semester, Tahun Ajaran.
   - Subject table grouped by subject group: No, Mata Pelajaran, Nilai Akhir, Capaian Kompetensi.
   - Ekstrakurikuler (free text).
   - Ketidakhadiran: Sakit / Izin / Tanpa Keterangan.
   - Catatan Wali Kelas.
   - Semester 2 only: Keputusan (naik ke kelas …, tinggal di kelas …, lulus).
   - Signature blocks: Orang Tua/Wali, Wali Kelas (name), Kepala Sekolah (name, NIP), place and date.
   - Footer: version and publication date.
   - "Cetak semua" produces one combined PDF for the class.

## Schema

### 1. `report_card_entries` Table

Editable homeroom inputs before publication.

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `semester_id` | bigint | unsigned, not null | FK -> `semesters.id` |
| `student_id` | bigint | unsigned, not null | FK -> `students.id` |
| `homeroom_notes` | text | nullable | Catatan wali kelas |
| `extracurricular_notes` | text | nullable | Free-text ekstrakurikuler |
| `promotion_decision` | varchar(20) | nullable | Enum: `promoted`, `retained`, `graduated` (semester 2 only) |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (semester_id, student_id)`

### 2. `report_card_publications` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `semester_id` | bigint | unsigned, not null | FK -> `semesters.id` |
| `class_id` | bigint | unsigned, not null | FK -> `classes.id` |
| `version` | smallint | unsigned, not null | 1, 2, … per (semester, class) |
| `published_at` | timestamp | not null | |
| `published_by_user_id` | bigint | unsigned, not null | FK -> `users.id` |
| `retracted_at` | timestamp | nullable | Null = current version |
| `retracted_by_user_id` | bigint | unsigned, nullable | FK -> `users.id` |
| `retract_reason` | varchar(255) | nullable | Required when retracted |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (semester_id, class_id, version)`. At most one row per `(semester_id, class_id)` with `retracted_at IS NULL` (enforced in the publish transaction).

### 3. `report_cards` Table (Snapshot)

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `report_card_publication_id` | bigint | unsigned, not null | FK -> `report_card_publications.id` |
| `student_id` | bigint | unsigned, not null | FK -> `students.id` |
| `curriculum` | varchar(30) | not null | Template key used to render |
| `payload` | json | not null | Complete rendered data; never updated |
| `created_at` | timestamp | not null | |

**Indexes:** `UNIQUE (report_card_publication_id, student_id)`, `INDEX (student_id)`

## Acceptance Criteria

- **AC-11-01**: With no current publication for 5A Ganjil, a guardian requesting the report card or PDF receives HTTP 403 "Rapor belum diterbitkan untuk periode ini."
- **AC-11-02**: Publishing while one 5A course is not finalized returns HTTP 422 naming the subject and teacher.
- **AC-11-03**: In semester Genap, publishing while one student lacks `promotion_decision` returns HTTP 422 naming the student.
- **AC-11-04**: After publishing, changing a student's address and the school's principal name does not change the published snapshot or its PDF.
- **AC-11-05**: Ahmad's attendance block equals the counts of `sick`, `leave` and `absent` in `attendances` between the semester start and the as-of date, including days spent in his previous class.
- **AC-11-06**: Ahmad moved from 5A to 5B on 2026-10-01; 5B's report card uses 5B's Matematika result if present, otherwise his finalized 5A Matematika result labelled "dari kelas 5A".
- **AC-11-07**: Retracting version 1 with a reason hides it from guardians; republishing creates version 2, and the PDF footer shows "Versi 2".
- **AC-11-08**: Retracting without a reason returns HTTP 422.
- **AC-11-09**: A teacher who is not 5A's homeroom teacher attempting to publish 5A returns HTTP 403; a principal can view drafts but publishing returns HTTP 403.
- **AC-11-10**: In roll-over (07) after semester Genap, a student with `retained` is pre-mapped to a same-`grade_level` class and a `graduated` student has no successor.
- **AC-11-11**: The PDF contains school header, identity block with Fase, subject table with Capaian Kompetensi, Sakit/Izin/Tanpa Keterangan, homeroom notes, and signature blocks on A4.

## Constraints & Assumptions

- PDF rendering uses `barryvdh/laravel-dompdf` (dependency approved 2026-10-01). Templates use table-based layout (dompdf supports roughly CSS 2.1: no flexbox/grid), embedded fonts, and `page-break` rules with repeating table headers.
- "Cetak semua" for a class runs as a queued job and offers a download link when ready.
- Requires specs 10 and 15.

## Open Questions

- `[NEEDS DECISION: Signature & stamp images]`: Upload principal signature and school stamp images to the school profile (15) for printing? (v1.x candidate; v1 prints blank signature lines.)
- ~~Notify guardians on publication~~ — resolved by spec 17 (`report_card_published`, `report_card_retracted`).
