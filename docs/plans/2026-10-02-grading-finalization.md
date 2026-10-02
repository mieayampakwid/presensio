# Grading B — Results, Finalization, Homeroom Ledger & Guardian Views Implementation Plan

> **For implementer agents:** Spec: `docs/specs/10-grading.md` Requirements 4–6, Finalization & Draft-description Decisions; ACs 10-06, 08, 09, 10, 12. Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Draft/edit achievement descriptions, finalize a course per semester (freezing results), homeroom ledger, and released-results views for guardians and students.

**Architecture:** `CourseFinalizer` service computes and freezes `subject_results` in one transaction; every score/assessment mutation path calls `CourseSemester::assertOpen()` (introduced in Grading A) so a finalized course returns 422. Read-only ledger and portal views reuse `GradebookBuilder` and the scheme.

**Prerequisites:** Grading A merged. The "published report cards block un-finalize" rule needs plan 7's publication table; this plan implements it behind `ReportCardGuard::isPublished()` which returns `false` until plan 7 replaces it (one-line seam, listed in plan 7 Task 1).

**Blocking questions:** none.

---

### Task 1: Results & descriptions page

**Files:**

- Create: `app/Services/Grading/SubjectResultBuilder.php` (per student: live score, per-TP results, draft description via scheme; keeps teacher edits when `description_edited`), `app/Http/Controllers/Teacher/CourseResultController.php` (`show`, `updateDescription`), `app/Http/Requests/Teacher/UpdateDescriptionRequest.php`, `resources/js/pages/teacher/results.tsx`
- Modify: `routes/teacher.php`
- Test: `tests/Feature/teacher/CourseResultTest.php`

**Dependencies:** Grading A · **Verification:** `php artisan test --compact tests/Feature/teacher/CourseResultTest.php`

- [ ] **Step 1:** AC-10-06 end-to-end: set up TP1 92 / TP3 64 via scored assessments → page prop `description` equals the sentence. Teacher edit sets `description_edited = true` and the next build keeps it (test: change a score afterwards, description stays).
- [ ] **Step 2:** Description max length 2000; only course teacher/admin may edit (403 otherwise).

### Task 2: Finalize / un-finalize

**Files:**

- Create: `app/Services/Grading/CourseFinalizer.php` (`finalize`, `undo`), `app/Http/Controllers/Teacher/CourseFinalizationController.php`, `app/Support/ReportCardGuard.php` (seam; `isPublished(ClassSubject, Semester): bool` = false)
- Modify: `app/Models/CourseSemester.php` (`assertOpen()`, `isFinalized()`), score/assessment/weight controllers from Grading A (call `assertOpen()` before writes), `app/Models/SchoolClass.php::curriculumLocked()` (true when any of its courses has `finalized_at`)
- Modify: `routes/teacher.php` (`POST courses/{id}/finalize`, `DELETE …/finalize`)
- Test: `tests/Feature/teacher/CourseFinalizationTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/teacher/CourseFinalizationTest.php`

- [ ] **Step 1:** `finalize`: for every `summative_*` assessment × student eligible on `assessed_on`, require non-null `score`; else 422 with `missing: [{student, assessment}]` (AC-10-08). When complete, in one transaction: create/update `subject_results.final_score` (rounded via scheme) for each _currently_ eligible student, set `finalized_at`/`finalized_by_user_id`, audit `finalized`.
- [ ] **Step 2:** AC-10-09: editing a score after finalization → 422. `undo`: course teacher or admin; 422 when `ReportCardGuard::isPublished()` (AC-10-10, tested with a fake guard binding now, real data in plan 7); audit `unfinalized`.
- [ ] **Step 3:** Remedial/weight/assessment edits and deletions are blocked as well (test one of each).

### Task 3: Homeroom ledger

**Files:**

- Create: `app/Http/Controllers/Teacher/ClassGradeLedgerController.php`, `resources/js/pages/teacher/class-grades.tsx`
- Modify: `routes/teacher.php` (`GET classes/{school_class}/grades?semester=`)
- Test: `tests/Feature/teacher/ClassGradeLedgerTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Feature/teacher/ClassGradeLedgerTest.php`

- [ ] **Step 1:** Students × subjects: finalized `final_score` or live rounded score, flag below threshold; header per subject with finalization status. Access: homeroom teacher, admin, principal (read); subject-only teacher of that class → also read (spec "scoped"); others 403. Roster as of `min(today, semester.ends_at)`.

### Task 4: Guardian & student released-results views

**Files:**

- Create: `app/Http/Controllers/Parent/ChildGradeController.php`, `app/Http/Controllers/Student/GradeController.php`, `resources/js/pages/parent/child-grades.tsx`, `resources/js/pages/student/grades.tsx`, `routes/parent.php`, `routes/student.php`
- Test: `tests/Feature/parent/ChildGradeTest.php`, `tests/Feature/student/GradeTest.php`

**Dependencies:** Grading A Task 6 · **Verification:** `php artisan test --compact tests/Feature/parent tests/Feature/student`

- [ ] **Step 1:** Payload per subject: teacher, KKTP, released assessments only (name, kind, date, score, remedial, notes). **No** semester score or description (spec). AC-10-07: assessment absent before release, present after. AC-10-12: unlinked child → 403 (`guardian_student` check); student only sees self.
- [ ] **Step 2:** Test asserting the JSON never contains `final_score` or `description` keys for these roles.

### Task 5: Close out

- [ ] `composer ci:check`. Spec 10 → "implemented (A+B)" proposal; note AC-10-10 verified with fake guard until plan 7.
