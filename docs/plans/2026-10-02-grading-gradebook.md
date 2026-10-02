# Grading A — Objectives, Assessments, Gradebook & Scoring Implementation Plan

> **For implementer agents:** Spec: `docs/specs/10-grading.md` Requirements 1–3, Decisions (kinds, remedial, Merdeka score, missing scores, roster eligibility, release), ACs 10-01…05, 07, 11, 13, 14. Finalization/results/ledger/guardian views are Plan "Grading B". Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Teachers define TPs and assessments, enter scores in a grid, see live semester score vs KKTP, and release assessments.

**Architecture:** A `GradingScheme` interface (resolved from `classes.curriculum`) owns all score math; `MerdekaGradingScheme` is the only implementation and is pure (no DB), so the formulas are unit-tested exhaustively. Controllers load data, call the scheme, and shape props. Score writes go through a `ScoreRecorder` service that validates, upserts, and audits in one transaction.

**Prerequisites:** Plan 3 (`class_subjects`, `ClassAccess::canWriteCourse`) merged; plan 2 (`semesters`, `grade_level`).

**Blocking questions:** none. (TP description wording is fixed by spec; editable templates are v1.x.)

---

### Task 1: Scoring engine (pure)

**Files:**

- Create: `app/Grading/GradingScheme.php` (interface), `app/Grading/MerdekaGradingScheme.php`, `app/Grading/GradingSchemeResolver.php` (`for(SchoolClass): GradingScheme` via `Curriculum` enum), `app/Grading/ScoreInput.php` + `AssessmentInput.php` (readonly DTOs: kind, maxScore, score, remedial, tpIds)
- Test: `tests/Unit/Grading/MerdekaGradingSchemeTest.php`

**Dependencies:** none (DTOs only) · **Verification:** `php artisan test --compact tests/Unit/Grading`

Interface: `effectiveNormalized(ScoreInput $s, float $threshold): ?float`, `semesterScore(array $inputs, float $wScope, float $wFinal, float $threshold): ?float`, `roundedScore(?float): ?int` (half-up), `tpResults(array $inputs, float $threshold): array<tpId,float>`, `draftDescription(array $tpResults, array $tpDescriptions, float $threshold): string`, `allowedKinds(): array`.

- [ ] **Step 1:** Write tests from the ACs before the code:
    - AC-10-02: scope 80 & 70, final 90, 50/50 → 82.5 → 83.
    - AC-10-03: formative 20 ignored.
    - AC-10-04: threshold 75, 60 + remedial 90 → 75; 60 + remedial 70 → 70; remedial lower than original keeps original (`max`).
    - Normalization with `max_score = 50` (score 40 → 80).
    - Only-scope or only-final → that average alone; null scores excluded; all null → `null`.
    - Half-up rounding: 82.5 → 83, 82.49 → 82.
    - AC-10-06 draft: TP1 92, TP3 64, threshold 75 → "Menunjukkan penguasaan yang baik dalam {TP1 description}. Perlu bantuan dalam {TP3 description}."; lowest TP at/above threshold → "Perlu peningkatan dalam …"; when only one TP exists, or highest and lowest TP results are equal, emit exactly one sentence: result ≥ threshold → "Menunjukkan penguasaan yang baik dalam {TP}."; result < threshold → "Perlu bantuan dalam {TP}." (never both sentences about the same TP).
- [ ] **Step 2:** Implement with `effective = max(n(score), min(n(remedial), threshold))`; `n(x)=x/max*100`.

### Task 2: Schema + models

**Files:**

- Create: migrations `create_learning_objectives_table`, `create_course_semesters_table`, `create_assessments_table`, `create_assessment_objectives_table`, `create_scores_table`, `create_subject_results_table`, `add_default_weights_to_settings_table` (spec §Schema 1–7; create `subject_results` and `course_semesters.finalized_*` now so Plan B only adds behavior); models `LearningObjective`, `CourseSemester`, `Assessment`, `Score`, `SubjectResult` with factories; `app/Enums/AssessmentKind.php`
- Modify: `app/Models/ClassSubject.php` (`assessments()`, `courseSemesters()`; extend `isInUse()` to `assessments()->exists()`), `app/Services/SchoolSettings.php` (default weights)
- Test: `tests/Unit/Models/AssessmentTest.php` (relations, TP pivot restrict)

**Dependencies:** Plan 3 · **Verification:** `php artisan test --compact tests/Unit/Models`

- [ ] **Step 1:** Make `course_semesters` rows lazily via `CourseSemester::forCourse(ClassSubject, Semester)` (`firstOrCreate` with weights from settings), not at assignment time.

### Task 3: Learning objectives (TP)

**Files:**

- Create: `app/Http/Controllers/Teacher/LearningObjectiveController.php`, `app/Http/Requests/Teacher/{Store,Update}LearningObjectiveRequest.php`, `resources/js/pages/teacher/objectives/index.tsx`
- Modify: `routes/teacher.php`
- Test: `tests/Feature/teacher/LearningObjectiveTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/teacher/LearningObjectiveTest.php`

- [ ] **Step 1:** Scope: teacher manages TPs for `(year, subject, grade_level)` only if they hold a `class_subjects` row for that subject in a class of that grade level in that year; admin all. Unique `(year, subject, grade_level, code)`. Delete guard (422) when linked to an assessment.
- [ ] **Step 2:** "Copy from previous year" action: copies the previous academic year's list for the same subject + grade level, skipping existing codes; test count and idempotence.

### Task 4: Assessments

**Files:**

- Create: `app/Http/Controllers/Teacher/AssessmentController.php`, `app/Http/Requests/Teacher/{Store,Update}AssessmentRequest.php`, `resources/js/pages/teacher/assessments/index.tsx`
- Modify: `routes/teacher.php` (`courses/{class_subject}/assessments?semester=`)
- Test: `tests/Feature/teacher/AssessmentTest.php`

**Dependencies:** Tasks 2–3 · **Verification:** `php artisan test --compact tests/Feature/teacher/AssessmentTest.php`

- [ ] **Step 1:** Rules: `kind` enum; `assessed_on` within the semester's range; `max_score > 0`; `objective_ids[]` required (min 1) when `summative_scope` (AC-10-05), each TP must match the course's `(year, subject, grade_level)`.
- [ ] **Step 2:** Authorization via `ClassAccess::canWriteCourse` (AC-10-11 for foreign courses → 403). Delete with scores requires `confirm=true`; the audit row stores the deleted scores (`old_values.scores`). Finalized course guard hooks in Plan B (call `CourseSemester::assertOpen()`, which is a no-op until then — implemented now, always passes when `finalized_at` is null, so Plan B needs no change here).
- [ ] **Step 3:** Weight and assessment changes audited (`AuditLogger`).

### Task 5: Gradebook read model

**Files:**

- Create: `app/Services/Grading/GradebookBuilder.php` — returns `{students: [{id, name, cells: {assessmentId: {score, remedial, notes}}, live_score, status, complete}], assessments: [...], weights}`
- Create: `app/Http/Controllers/Teacher/GradebookController.php` (`show`), `resources/js/pages/teacher/gradebook.tsx`
- Test: `tests/Feature/teacher/GradebookTest.php`

**Dependencies:** Tasks 1–4 · **Verification:** `php artisan test --compact tests/Feature/teacher/GradebookTest.php`

- [ ] **Step 1:** Roster per assessment = students with a `classOn(assessed_on)` match for that class (enrollment window, AC-10-13): transferred-in student absent from a pre-transfer assessment's cells; use `EnrollmentService` / `Enrollment` date-range scope. Live score excludes nulls and flags `complete=false`.
- [ ] **Step 2:** Page: spreadsheet grid grouped by kind, keyboard navigation (arrows/Tab/Enter) via a small `useGridNavigation` hook in `resources/js/hooks/`, remedial input shown only when score < threshold, one batch save button. 50 students × 40 assessments rendered client-side; avoid per-cell Inertia visits.

### Task 6: Score recorder, weights, release

**Files:**

- Create: `app/Services/Grading/ScoreRecorder.php`, `app/Http/Controllers/Teacher/ScoreController.php` (`store` batch), `app/Http/Requests/Teacher/StoreScoresRequest.php`, `app/Http/Controllers/Teacher/CourseWeightController.php`, `app/Http/Controllers/Teacher/AssessmentReleaseController.php`
- Modify: `routes/teacher.php` (`POST courses/{id}/scores`, `PUT …/weights`, `POST …/assessments/{assessment}/release`)
- Test: `tests/Feature/teacher/ScoreEntryTest.php`

**Dependencies:** Task 5 · **Verification:** `php artisan test --compact tests/Feature/teacher/ScoreEntryTest.php`

- [ ] **Step 1:** Request validates each row: `0 ≤ score ≤ assessment.max_score` and same for remedial (AC-10-01: 105 and −5 → 422); student must be on that assessment's eligible roster; `remedial_score` only when `score` is set.
- [ ] **Step 2:** `ScoreRecorder::record(array $rows, User $by)` in one `DB::transaction`: upsert on `(assessment_id, student_id)`, set `graded_by_user_id`, and write one `AuditLogger` row per **changed** score with old/new `score`/`remedial_score` (AC-10-14, AC-15-10 atomicity: test by forcing the audit insert to throw and asserting the score unchanged).
- [ ] **Step 3:** Release sets `released_at = now()` (idempotent); weights editor validates `scope_weight`, `final_weight` ≥ 0 and sum > 0.

### Task 7: Close out

- [ ] Extend `Gate`/`Ability` check in plan 1's `MultiRoleAccessTest` so AC-15-05 (finance → gradebook 403) hits the real route. `composer ci:check`. Propose spec 10 status "partially shipped (A)".
