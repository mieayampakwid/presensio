# Digital Report Cards Implementation Plan

> **For implementer agents:** Spec: `docs/specs/11-report-cards.md` (ACs 11-01…11). Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Homeroom workspace, immutable versioned snapshots per class publication, Merdeka A4 PDF, guardian/student archive, and roll-over pre-fill from promotion decisions.

**Architecture:** `ReportCardSnapshotBuilder` (resolved per `classes.curriculum`, like `GradingScheme`) produces the full payload array from live data; publish stores it as JSON in `report_cards`; every reader (portal, PDF) reads only the snapshot. PDF via `barryvdh/laravel-dompdf` with a table-based Blade template.

**Prerequisites:** Grading B merged. `barryvdh/laravel-dompdf` ^3.1 is **already installed** (2026-10-02). Plan 4 for guardian notifications (Task 8 can be last).

**Blocking questions:** none (signature/stamp images are v1.x).

---

### Task 1: Schema, models, publication guard

**Files:**

- Create: migrations for `report_card_entries`, `report_card_publications`, `report_cards` (spec §Schema 1–3); models `ReportCardEntry`, `ReportCardPublication`, `ReportCard` (+factories); `app/Enums/PromotionDecision.php` (`Promoted`, `Retained`, `Graduated`)
- Modify: `app/Support/ReportCardGuard.php` (Plan Grading B seam) → real check: current (non-retracted) publication exists for the class + semester
- Test: `tests/Unit/Models/ReportCardPublicationTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Models/ReportCardPublicationTest.php`

- [ ] **Step 1:** `ReportCardPublication::current()` scope = `whereNull('retracted_at')`; model refuses `payload` updates on `ReportCard` (`updating` event throws) — snapshots never change.
- [ ] **Step 2:** Re-run Grading B's AC-10-10 test with the real guard.

### Task 2: Snapshot builder

**Files:**

- Create: `app/ReportCards/ReportCardSnapshotBuilder.php` (interface), `app/ReportCards/MerdekaSnapshotBuilder.php`, `app/ReportCards/Snapshot/AttendanceCounter.php`
- Test: `tests/Unit/ReportCards/MerdekaSnapshotBuilderTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Unit/ReportCards`

- [ ] **Step 1:** Rules from spec Decisions, each a test: roster as of `min(today, semester.ends_at)` via `classOn`; subject rows ordered `subjects.group` then `sort_order`; transfer-in fallback uses the same `subject_id` finalized result from the previous class and labels it "dari kelas 5A" (AC-11-06); no result at all → row empty and student flagged incomplete.
- [ ] **Step 2:** Attendance counts: `sick`, `leave`, `absent` from `attendances` between `semester.starts_at` and as-of date, regardless of which class the student was in (AC-11-05); `late` not shown.
- [ ] **Step 3:** Payload includes school profile (name, NPSN, address, logo path, principal name/NIP), homeroom teacher name, class, derived phase, version, publication date. Test AC-11-04: mutate student address and settings principal afterwards → stored snapshot unchanged.

### Task 3: Workspace (notes, decisions, preview)

**Files:**

- Create: `app/Http/Controllers/Teacher/ReportCardWorkspaceController.php` (`show`, `updateEntry`, `preview`), `app/Http/Requests/Teacher/UpdateReportCardEntryRequest.php`, `resources/js/pages/teacher/report-cards.tsx`
- Modify: `routes/teacher.php` (`GET classes/{school_class}/report-cards?semester=`, `PUT …/entries/{student}`, `GET …/preview/{student}`)
- Test: `tests/Feature/teacher/ReportCardWorkspaceTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/teacher/ReportCardWorkspaceTest.php`

- [ ] **Step 1:** Header checklist (subject, teacher, finalized yes/no), per-student completeness. Entry validation: `homeroom_notes` ≤ 1000, `extracurricular_notes` ≤ 500, `promotion_decision` accepted only in semester 2. Authorization: homeroom teacher + admin edit; principal and the class's subject teachers view drafts only (PUT → 403).
- [ ] **Step 2:** Preview returns the would-be payload without persisting (assert no rows created).

### Task 4: Publish / retract

**Files:**

- Create: `app/Services/ReportCards/ReportCardPublisher.php`, `app/Http/Controllers/Teacher/ReportCardPublicationController.php`, `app/Http/Requests/Teacher/{Publish,Retract}ReportCardRequest.php`
- Modify: `routes/teacher.php`
- Test: `tests/Feature/teacher/ReportCardPublicationTest.php`

**Dependencies:** Task 3 · **Verification:** `php artisan test --compact tests/Feature/teacher/ReportCardPublicationTest.php`

- [ ] **Step 1:** Preconditions return 422 with a list: every `class_subjects` row has a finalized `course_semesters` (message names subject + teacher — AC-11-02); semester 2 only: each student has `promotion_decision` (names student — AC-11-03). Missing notes → warning in the response, not an error.
- [ ] **Step 2:** Publish transaction: lock the class+semester, next `version = max + 1`, insert publication, one `report_cards` row per student with payload, `AuditLogger` `published` — all or nothing. Retract: required `reason` (AC-11-08 → 422), sets `retracted_at/by/reason`, audits `retracted`; republish creates version n+1 (AC-11-07).
- [ ] **Step 3:** Authorization: homeroom of that class and admin; non-homeroom teacher 403, principal 403 (AC-11-09).

### Task 5: PDF template

**Files:**

- Create: `app/Services/ReportCards/ReportCardPdf.php`, `resources/views/pdf/report-card-merdeka.blade.php` (table layout, A4, 2 cm margins, repeating table header, `page-break-inside: avoid` rows), `resources/views/pdf/partials/kop.blade.php` (shared with plan 14's kuitansi)
- Config: `php artisan vendor:publish --provider="Barryvdh\\DomPDF\\ServiceProvider"` only if the defaults need changing (set `isRemoteEnabled` false, keep fonts local)
- Test: `tests/Feature/ReportCards/ReportCardPdfTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Feature/ReportCards/ReportCardPdfTest.php`

- [ ] **Step 1:** Render from the **snapshot only**. Test asserts response is `application/pdf`, starts `%PDF`, and the rendered HTML (call the view directly) contains kop, Nama/NIS/NISN/Kelas/Fase/Semester/Tahun Ajaran, group headings, "Capaian Kompetensi", "Sakit / Izin / Tanpa Keterangan", "Catatan Wali Kelas", signature blocks, and footer "Versi {n}, diterbitkan {date}" (AC-11-11, AC-11-07). Semester 2 shows Keputusan.
- [ ] **Step 2:** Embed a font with Indonesian glyph coverage (dompdf default DejaVu is acceptable; no external font download). Logo read from private disk and inlined as base64.
- [ ] **Step 3:** Visual check once by opening a generated PDF (`Read` supports PDFs) and noting layout defects in the PR.

### Task 6: Guardian & student portal

**Files:**

- Create: `app/Http/Controllers/Parent/ChildReportCardController.php`, `app/Http/Controllers/Student/ReportCardController.php`, `resources/js/pages/parent/child-report-cards.tsx`, `resources/js/pages/student/report-cards.tsx`, `resources/js/components/report-card-view.tsx` (renders snapshot)
- Modify: `routes/parent.php`, `routes/student.php`
- Test: `tests/Feature/parent/ChildReportCardTest.php`, `tests/Feature/student/ReportCardTest.php`

**Dependencies:** Tasks 4–5 · **Verification:** `php artisan test --compact tests/Feature/parent tests/Feature/student`

- [ ] **Step 1:** List semesters with a current publication; show/PDF `…/report-cards/{semester}/pdf` as `attachment`. No current version → 403 "Rapor belum diterbitkan untuk periode ini." (AC-11-01, for page and PDF). Unlinked child → 403.

### Task 7: "Cetak semua" + roll-over promotion pre-fill

**Files:**

- Create: `app/Jobs/BuildClassReportCardPdf.php` (queued; stores combined PDF on private disk), `app/Http/Controllers/Teacher/ReportCardBundleController.php` (`store`, `download`)
- Modify: `app/Services/AcademicYears/RollOverService.php` + `AcademicYearController` roll-over screen payload, `resources/js/pages/academic-years/roll-over.tsx`
- Test: `tests/Feature/ReportCards/ReportCardBundleTest.php`, `tests/Feature/AcademicYears/RollOverPromotionTest.php`

**Dependencies:** Tasks 5, 6 · **Verification:** `php artisan test --compact tests/Feature/ReportCards tests/Feature/AcademicYears`

- [ ] **Step 1:** Bundle: `Bus::fake()` asserts dispatch; job test builds from the current publication; download route authorizes homeroom/admin.
- [ ] **Step 2:** Roll-over (AC-11-10): after Genap publication, `retained` students pre-map to a class of the same `grade_level`, `graduated` have no successor, `promoted` map to `grade_level + 1`; admin override still possible. Roll-over without publications behaves as before (existing tests).

### Task 8: Guardian notifications (needs plan 4)

**Files:**

- Modify: `app/Services/ReportCards/ReportCardPublisher.php`
- Test: `tests/Feature/Notifications/ReportCardNotificationTest.php`

**Dependencies:** Task 4, Plan 4 · **Verification:** `php artisan test --compact tests/Feature/Notifications/ReportCardNotificationTest.php`

- [ ] **Step 1:** After commit dispatch `report_card_published` (guardians + students) and `report_card_retracted` with `dedupeBase = "publication:{id}"`. AC-17-02 (re-run no duplicates). Payload carries no scores.

### Task 9: Close out

- [ ] `composer ci:check`. Propose spec 11 status update; dompdf is already in `composer.json`.
