# 11 — Digital Report Cards (Rapor Semester & Buku Induk Nilai)

Status: draft v1.0 (2026-09-25)

## Problem

In Indonesian educational institutions, compiling semester report cards (Buku Rapor) represents one of the most labor-intensive and stressful periods of the academic calendar. Homeroom teachers (Wali Kelas) spend days manually transcribing grades submitted on paper or WhatsApp by various subject teachers, tallying attendance figures from separate physical books, and composing qualitative narratives in standalone word processors. This manual process causes significant administrative delays, introduces transcription typos, and risks premature grade leaks before administrative review is finalized. Furthermore, paper-only distribution requires in-person guardian attendance, leaves working parents without accessible records, and offers no archival resilience against lost booklets.

## Goals

1. Define structured academic reporting periods (`report_periods`, e.g. "Semester 1 Ganjil", "Semester 2 Genap") linked to the active academic year.
2. Automatically aggregate a complete composite digital report card per student synthesizing:
   - Subject grades, KKM targets, and passing status (derived from spec 09 and spec 10).
   - Semester attendance summaries: Hadir, Terlambat, Sakit, Izin, and Alpa totals (derived from spec 03, spec 04, and spec 06).
   - Qualitative evaluation narratives from the homeroom teacher and school principal.
3. Establish a controlled publication lifecycle (`report_card_publications`): grades remain in draft status, strictly invisible to parents and students, until formally published by the homeroom teacher or administrator.
4. Generate standardized, printable PDF report cards complying with Indonesian Ministry of Education (Kemendikbud) visual styling and layout standards.
5. Provide parents and students with a self-service digital report card archive on their portal to view and download official PDF copies at any time.

## Non-goals

- Automated AI generation of Kurikulum Merdeka Competency Achievement (Capaian Pembelajaran / CP) narratives in v1 (homeroom teachers write or paste qualitative remarks directly).
- Digital cryptographic PKI signatures or certified e-materai embedding in v1 (standard visual school stamp and principal signature image placeholders are supported).
- Class ranking or "Juara Kelas" badges printed on the official report card (aligning with Indonesian pedagogical standards discouraging student ranking).
- Extracurricular activity (Ekskul) grading, scouting (Pramuka), or physical height/weight tracking in v1.
- Direct synchronization with the Ministry of Education's centralized Dapodik / e-Rapor desktop application.

## User Stories

- **As an administrator**, I want to configure the report period "Semester Ganjil 2026/2027" from 2026-07-15 to 2026-12-20, so that all academic and attendance records within that window are aggregated into the mid-year report card.
- **As a homeroom teacher**, I want to open my classroom's report card dashboard, see that all 9 subject teachers have completed their grading, write a narrative note for Ahmad ("Ahmad menunjukkan minat belajar yang tinggi, pertahankan..."), and preview his PDF report card before publishing.
- **As a homeroom teacher**, I want to click "Publish All Rapor" on distribution day, instantly making report cards visible to all 30 families in my class.
- **As a parent**, I want to log into my portal on distribution day, view my daughter's semester grades and attendance breakdown, and download a clean, official PDF report card to save on my phone.
- **As a school principal**, I want to inspect drafted report cards across all classes to ensure evaluation quality before publication.

## Decisions

- **Configurable Period Entity**: Rather than hardcoding two semesters, schools configure `report_periods` linked to `academic_years`. This accommodates multi-term schedules, mid-term progress reports (PTS), or final semester reports (PAS/PAT).
- **Controlled Publication Gate**:
  - Report cards remain strictly inaccessible to parents and students until a record exists in `report_card_publications` for the given `(report_period_id, class_id)`.
  - While unpublished, parents see "Rapor belum diterbitkan untuk periode ini."
  - Admins and homeroom teachers can publish and unpublish (retract for corrections) at will.
- **Dynamic Data Synthesis**:
  - *Academic Component*: Queries all `class_subjects` assigned to the student's class, fetching each subject's computed score and KKM.
  - *Attendance Component*: Automatically queries `attendances` where `student_id = ?` and `date BETWEEN report_periods.starts_at AND report_periods.ends_at`. Computes total counts of `present`, `late`, `sick`, `leave`, and `absent`. No manual attendance re-entry is permitted.
- **Narrative Evaluation Storage**: Qualitative feedback is stored in `report_card_notes` with fields for `teacher_notes` (homeroom teacher comments) and `principal_notes` (general school endorsement).
- **Server-Side PDF Generation**:
  - PDFs are compiled dynamically from a clean, printable HTML Blade view using DomPDF or Browsershot/Puppeteer.
  - Standard layout: School Header (Kop Surat), Student Identity, Subject Grade Table (Mapel, KKM, Nilai Akhir, Keterangan), Attendance Summary Table (Sakit, Izin, Alpa), Homeroom Teacher Notes, Signature Blocks (Wali Kelas, Kepala Sekolah, Orang Tua).

## Requirements

1. **Report Period Management (`/admin/academic-years/{id}/report-periods`)**:
   - Admin CRUD for report periods.
   - Fields: `academic_year_id` (foreign key), `name` (required, string, e.g. "Semester Ganjil"), `starts_at` (required, date), `ends_at` (required, date >= `starts_at`), `is_active` (boolean).
   - Validates that date boundaries reside within the parent academic year's date range.
2. **Homeroom Report Card Review Workspace (`GET /teacher/classes/{id}/report-cards`)**:
   - Lists all enrolled students in the class.
   - Displays completion status: indicates how many subjects have finalized grades submitted.
   - Form fields per student: `teacher_notes` (textarea, max 1000 chars) and `principal_notes` (optional textarea, max 500 chars).
   - Preview action: Opens a modal or new tab rendering the printable student report card.
3. **Publication Workflow**:
   - "Publish Class Rapor" button: sends POST request to `/teacher/classes/{id}/report-cards/publish` for selected `report_period_id`.
   - Inserts row into `report_card_publications`.
   - Allows unpublishing (`DELETE /teacher/classes/{id}/report-cards/publish`) if revisions are required.
4. **Parent & Student Report Card Portal**:
   - Route `GET /parent/children/{id}/report-cards`: lists all published report periods for that child.
   - Viewing an active published period renders the digital report card on screen and provides a "Download PDF" button.
   - Route `GET /parent/children/{id}/report-cards/{period_id}/pdf`: streams the generated PDF binary with `Content-Disposition: attachment`.
   - If period is unpublished, attempts to access the route return HTTP 403 Forbidden with clear explanatory message.
5. **PDF Export Service**:
   - Compiles standard Indonesian A4 report card document with correct margins (2cm), clean typography, school header, and official table formatting.

## Schema

### 1. `report_periods` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `academic_year_id` | bigint | unsigned, not null | Foreign key -> `academic_years.id` |
| `name` | varchar(100) | not null | e.g. "Semester Ganjil", "Semester Genap" |
| `starts_at` | date | not null | Evaluation window start date |
| `ends_at` | date | not null | Evaluation window end date |
| `is_active` | boolean | not null, default: true | Status flag |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `INDEX (academic_year_id, starts_at, ends_at)`

### 2. `report_card_notes` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `report_period_id` | bigint | unsigned, not null | Foreign key -> `report_periods.id` |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` |
| `teacher_notes` | text | nullable | Homeroom teacher narrative assessment |
| `principal_notes` | text | nullable | Principal narrative remarks |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (report_period_id, student_id)`

### 3. `report_card_publications` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `report_period_id` | bigint | unsigned, not null | Foreign key -> `report_periods.id` |
| `class_id` | bigint | unsigned, not null | Foreign key -> `classes.id` |
| `published_at` | timestamp | not null | Timestamp of official release |
| `published_by_user_id` | bigint | unsigned, not null | Foreign key -> `users.id` (Publisher audit) |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (report_period_id, class_id)`

## Acceptance Criteria

- **AC-11-01**: Given an unpublished report period for Class 5A, when a parent attempts to view or download the report card, the system returns HTTP 403 Forbidden with message "Rapor belum diterbitkan".
- **AC-11-02**: When the homeroom teacher clicks "Publish Class Rapor", a `report_card_publications` row is created, and parents of Class 5A can immediately view and download report cards.
- **AC-11-03**: The attendance summary on Ahmad's report card dynamically reflects exactly the counts of Hadir, Terlambat, Sakit, Izin, and Alpa recorded in `attendances` between `starts_at` and `ends_at`.
- **AC-11-04**: When downloading the PDF report card, the generated document renders student name, NIS, class, subject table with KKM, attendance totals, homeroom notes, and signature lines on a standard A4 page.
- **AC-11-05**: A teacher attempting to edit report card notes or publish report cards for a class they do not manage receives HTTP 403 Forbidden.
- **AC-11-06**: An admin unpublishing a class report card deletes the `report_card_publications` row, immediately locking parent access until re-published.

## Constraints & Assumptions

- PDF generation tool (such as `barryvdh/laravel-dompdf` or Spatie Browsershot) is installed and configured in the application environment.
- Single-school scale: batch printing an entire class (30 PDFs) completes in under 15 seconds.

## Open Questions

- `[NEEDS DECISION: Digital Signature Assets]`: Should the PDF template support uploading image files for the school stamp and principal/teacher signatures? (Recommended for v1.1 via `school_settings`; v1 uses physical ink signature lines).
