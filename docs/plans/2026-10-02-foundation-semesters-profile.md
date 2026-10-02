# Foundation B — Semesters, Class Level, School Profile & Operational Days Implementation Plan

> **For implementer agents:** Execute in order. Spec: `docs/specs/15-foundation.md` §Semesters, §Grade level & curriculum, §School profile; Requirements 3–5; AC-15-06…09. Task 5 implements the missing `school_operational_days` setting from `AUDIT-2026-10-01.md` D-05 (spec 03 amendment), which plans 8 and 11 depend on. Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Add the term entity, class grade level/curriculum, the school profile on `settings`, and configurable operational weekdays.

**Architecture:** `semesters` hang off `academic_years` and are auto-created by the existing academic-year creation path. "Current semester" is a derived lookup in a small service, never a flag. `grade_level`/`curriculum` are plain columns plus a derived Merdeka-phase accessor. Profile fields extend the existing single `settings` row via `SchoolSettings`.

**Prerequisites:** Plan 1 merged (audit log used for settings changes).

**Blocking questions:** none.

---

### Task 1: `semesters` table, model, auto-create, current-semester resolver

**Files:**

- Create: migration `create_semesters_table` (via `make:migration`) (spec §Schema 2; data migration creates both semesters for every existing year, split 1 Jan)
- Create: `app/Models/Semester.php`, `database/factories/SemesterFactory.php`
- Create: `app/Services/AcademicYears/SemesterService.php` — `createFor(AcademicYear)`, `current(?CarbonInterface $on = null): ?Semester`, `updateBoundaries(AcademicYear, array $semesters)`
- Modify: `app/Models/AcademicYear.php` (`semesters()` hasMany), `app/Http/Controllers/AcademicYears/AcademicYearController.php` (call `createFor` in the same transaction as year creation)
- Test: `tests/Unit/Services/AcademicYears/SemesterServiceTest.php`, `tests/Unit/Models/SemesterTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Services/AcademicYears`

- [ ] **Step 1:** Tests first, from the ACs: AC-15-06 (2027-07-13→2028-06-24 yields Ganjil 2027-07-13→2027-12-31 and Genap 2028-01-01→2028-06-24); AC-15-08 (on 2027-09-01 `current()` = Ganjil); gap rule (date between semesters → most recently started); year with no semesters → `null`.
- [ ] **Step 2:** `current()` resolves "today" through `SchoolSettings::todayDate()` when `$on` is null (school timezone). Query: `starts_at <= date <= ends_at`, else latest `starts_at <= date`.
- [ ] **Step 3:** Migration backfill uses a one-off closure over `academic_years` (no model dependencies); `down()` drops the table.

### Task 2: Semester admin UI + validation

**Files:**

- Create: `app/Http/Controllers/AcademicYears/SemesterController.php` (`edit`, `update`), `app/Http/Requests/AcademicYears/UpdateSemestersRequest.php`, `resources/js/pages/academic-years/semesters.tsx`
- Modify: `routes/academic-years.php` (`GET/PUT academic-years/{academic_year}/semesters`), `resources/js/pages/academic-years/index.tsx` (link)
- Test: `tests/Feature/AcademicYears/SemesterManagementTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/AcademicYears/SemesterManagementTest.php`

- [ ] **Step 1:** Request rules (422): Ganjil `starts_at` = year start, Genap `ends_at` = year end, Ganjil `ends_at` < Genap `starts_at` and contiguous (Genap start = Ganjil end + 1 day), all inside the year's range. AC-15-07 covers overlap and out-of-range.
- [ ] **Step 2:** Admin-only (`role:admin`); no delete route (spec: deleting a semester unsupported). Audit boundary changes via `AuditLogger` (`updated`, old/new dates).

### Task 3: `grade_level` and `curriculum` on classes

**Files:**

- Create: migration `add_grade_level_and_curriculum_to_classes_table` (via `make:migration`) (spec §Schema 3; `grade_level` tinyint unsigned **NOT NULL default 0** — `0` is the sentinel "belum diatur" for legacy classes whose `name` has no parsable level; backfill via leading digits of `name` (`preg_match('/^\d{1,2}/')`) kept only when within 1–12; `curriculum` default `merdeka`). Form/request validate `grade_level` 1–12, so `0` can only exist on legacy rows until an admin sets it.
- Create: `app/Enums/Curriculum.php` (`Merdeka` only), `app/Support/MerdekaPhase.php` (or accessor on `SchoolClass`: `phase()` A=1–2, B=3–4, C=5–6, D=7–9, E=10, F=11–12)
- Modify: `app/Models/SchoolClass.php` (casts, `phase()`), `app/Http/Requests/Classes/{Store,Update}ClassRequest.php`, `app/Http/Controllers/Classes/SchoolClassController.php`, `resources/js/pages/classes/{class-form,index}.tsx`, `database/factories/SchoolClassFactory.php`
- Modify: `app/Services/AcademicYears/RollOverService.php` — new target classes get `grade_level = source + 1` (suggestion) and `default_curriculum`
- Test: `tests/Unit/Models/SchoolClassTest.php` (AC-15-09: grade 5 → phase C, all boundaries), `tests/Feature/classes/ClassManagementTest.php` (required fields), `tests/Feature/AcademicYears/RollOverTest.php` (suggestion)

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Models/SchoolClassTest.php tests/Feature/classes`

- [ ] **Step 1:** Add a "complete class data" banner for admins listing classes with `grade_level = 0` — a shared Inertia prop `incomplete_class_count` from `HandleInertiaRequests` (admin only, one count query) and a banner in `app-layout`.
- [ ] **Step 2:** "Editable until the class has finalized grades" (spec) — leave a `SchoolClass::curriculumLocked(): bool` returning `false` with a comment-free single-line body now; plan 6 replaces it when `course_semesters.finalized_at` exists. (Single-use seam, intentional.)

### Task 4: School profile settings

**Files:**

- Create: migration `add_school_profile_to_settings_table` (via `make:migration`) (spec §Schema 4; also `default_curriculum`)
- Create: `app/Http/Controllers/Settings/SchoolProfileController.php`, `app/Http/Requests/Settings/UpdateSchoolProfileRequest.php`, `app/Http/Controllers/Settings/SchoolLogoController.php` (authorized streaming from private disk), `resources/js/pages/settings/school.tsx`
- Modify: `app/Models/SchoolSetting.php`, `app/Services/SchoolSettings.php` (`defaults()` + accessors `schoolName()`, `principal()`), `routes/settings.php` (`GET/PUT settings/school`, `GET settings/school/logo`), settings layout nav
- Modify: `app/Jobs/SendAbsenceNotifications.php` / message builder so `{school_name}` resolves from the profile (spec 15 Req 5; find the template substitution via `grep -rn school_name app`)
- Test: `tests/Feature/settings/SchoolProfileTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Feature/settings/SchoolProfileTest.php`

- [ ] **Step 1:** Request: logo `image|mimes:png,jpg,jpeg|max:1024`, stored with `Storage::disk('local')->putFile('school', ...)` (private); old logo deleted on replace. Test with `Storage::fake('local')`: PNG ≤1 MB OK, 2 MB → 422, GIF → 422.
- [ ] **Step 2:** Logo route: any authenticated user may fetch (it is the school identity on documents); returns 404 when unset.
- [ ] **Step 3:** Update writes an audit row (`settings`, changed attributes only). Admin-only.

### Task 5: Operational weekdays setting (AUDIT D-05)

**Files:**

- Create: migration `add_school_operational_days_to_settings_table` (via `make:migration`) (json, default `[1,2,3,4,5]` ISO)
- Modify: `app/Services/SchoolSettings.php:~107` — `isSchoolDay()` uses the setting instead of hardcoded Sat/Sun; add `operationalWeekdays(): array<int,int>`
- Modify: `app/Http/Requests/Settings/UpdateAttendanceSettingsRequest.php`, `app/Http/Controllers/Settings/AttendanceSettingsController.php`, `resources/js/pages/settings/attendance.tsx` (weekday checkboxes)
- Test: `tests/Unit/Services/SchoolSettingsTest.php` (6-day school: Saturday is a school day; sweep command schedules/runs on Saturday), `tests/Feature/settings/*`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Services/SchoolSettingsTest.php tests/Feature/Attendance`

- [ ] **Step 1:** Validate: array, ≥1 weekday, values 1–7. Re-check the callers of `isSchoolDay` (`ExcuseReviewController:170`, `ExcuseApprovalService:76`, dashboard, presence board) with an excuse spanning a Saturday under a 6-day config.

### Task 6: Close out

- [ ] Run `composer ci:check`; fix. Propose README/status edits for 15 (fully shipped) and AUDIT D-05 resolved; apply on approval.
