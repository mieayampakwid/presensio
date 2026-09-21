# Spec 07 — Academic Roll-over & Historical Attribution (enrollment model)

## Context

Implements `docs/specs/07-academic-years.md` — the biggest schema change of the project, done now because zero production data exists (user decision 2026-09-21: academic years are core; enrollment history is the single source of truth for class membership, chosen over write-time stamping). `students.class_id` is **deleted**; a student's class on any date becomes derived from `enrollments` (student ↔ class ↔ period). After this ships, roll-over is one transactional admin operation and historical class reports stay true forever.

**Locked decisions (spec + user):**
- Enrollment is the source of truth; attribution is a domain query (`classOn(date)`), never stamped on `attendances`.
- Classes belong to a year; `unique(name, academic_year_id)`; exactly one active year.
- One open enrollment per student (partial unique index + overlap guard).
- Roll-over = one transactional admin use case (map old classes → new-year classes; unmapped = alumni). Idempotent re-apply.
- Alumni (no open enrollment): excluded from sweep/board/pickers; fully visible in historical reports.
- Spec 08 (dashboard) stays sequenced after this.

**Inventory facts driving the plan** (full sweep done): 8 `ClassAccess` call sites; roster scoping in `AttendanceController` (index+bulk), `ExcuseReviewController`, `StudentReportController` (canView + picker), `UpsertAttendanceRequest`; `SchoolClass::students()` hasMany consumed by board + class-delete blocker; import resolves classes via `ImportContext` preload + `commitRow` auto-create; sweep currently selects **all** students; unit tests pin FK names (`StudentTest:24`, `SchoolClassTest:28`); ~30 test sites do `Student::factory()->create(['class_id' => …])`.

## Task order

### 0. Docs
Copy this plan to `docs/plans/2026-09-21-academic-years.md`.

### 1. Migrations
- **New** `2026_09_19_043048_000001_create_academic_years_table.php` — lexicographically sorts before `043048_create_guardians` (`'0' < 'c'`), which is fine (no FK deps); the only hard requirement is preceding `043049_create_classes`: `id`, `name` unique, `starts_at`/`ends_at` (date), `is_active` bool.
- **Edit in place** `…043049_create_classes_table.php`: + `academic_year_id` constrained, + `unique(['name','academic_year_id'])` (sqlite ALTER-FK gotcha → create-migration-only).
- **Edit in place** `…043050_create_students_table.php`: remove `class_id` + its comment.
- **New** `2026_09_21_000001_create_enrollments_table.php`: `student_id` constrained + `cascadeOnDelete()` (students are only deletable with zero history anyway), `class_id` constrained, `started_on`/`ended_on` (date, nullable), index `[class_id, started_on]`, **partial unique via raw** `DB::statement('CREATE UNIQUE INDEX enrollments_open_unique ON enrollments (student_id) WHERE ended_on IS NULL')` (works on sqlite + pgsql).
- **New** `2026_09_21_000002_bootstrap_active_academic_year.php`: firstOrCreate "2026/2027" (starts 2026-07-01, ends 2027-06-30, `is_active = true`) — guarantees every fresh DB/tests have a stable active year.
- Dev DB: `sail artisan migrate:fresh --seed` required (in-place edits).

### 2. Models + factories
- `AcademicYear` (`#[Fillable]`, date/bool casts, `scopeActive`, `classes()` hasMany) + `AcademicYearFactory` (`is_active => false` default so extra test years never break "exactly one"; unique name via numerify).
- `Enrollment` (`#[Fillable]`, date casts, `student()`/`schoolClass()`, `scopeActiveOn($date)`: `started_on <= d AND (ended_on IS NULL OR ended_on >= d)`) + `EnrollmentFactory`.
- `Student`: remove `class_id` from Fillable/docblock, drop `schoolClass()`; add `enrollments()` hasMany, `currentEnrollment()` hasOne (whereNull ended_on, latest started_on), `classOn(string $date): ?SchoolClass` (activeOn → first → schoolClass).
- `SchoolClass`: `academicYear()` belongsTo; `enrollments()` hasMany (full history — deletion guard uses this); **`students()` re-implemented as hasManyThrough(Student, Enrollment) with `whereNull('enrollments.ended_on')`** — stays "current roster", so board payload code keeps working unchanged.
- `SchoolClassFactory`: `academic_year_id` resolves the active year (firstOrCreate via the bootstrap row).
- `StudentFactory`: drop the `'class_id' => null` definition line; new state `enrolledIn(SchoolClass $class, ?string $startedOn = null)` (afterCreating → EnrollmentService assign). **Factory default `startedOn` must be long past (the class year's `starts_at`), NOT today** — report tests create Sep 1–18 records under a Sep 21 pin; a today-default enrollment would make `classOn(Sep 1–18)` miss and silently zero every count. `assign()`'s live-write default (today) is unaffected — the state passes the date explicitly.

### 3. EnrollmentService — sole writer (house pattern: `UserProfileLinker`)
`app/Services/EnrollmentService.php`:
- `assign(Student, SchoolClass, ?string $startedOn = null): void` — transactional; same-class open enrollment → no-op (idempotent form saves); else end open (`ended_on = day before new start`), create open new (started_on default = school-tz today). Overlap guard mirrors the excuse inclusive-overlap pattern.
- `release(Student, ?string $endedOn = null): void` — end open enrollment, idempotent.

### 4. Roll-over — `app/Services/AcademicYears/RollOverService.php`
`rollOver(AcademicYear $target, array $mappings, string $effectiveOn): void` — transactional; asserts `$target` has no enrollments yet (idempotent no-op if already promoted); **activates `$target` and deactivates the source year inside the same transaction** (otherwise every active-year surface sees an empty school after roll-over); per source class: end open enrollments on `effectiveOn − 1 day`, mapped students open new enrollments (`effectiveOn`) in target class (existing id **or** create-new `{name, teacher_id}` in target year), unmapped → alumni (just ended). Validation: source classes must belong to the source (currently active) year; existing target classes must belong to `$target` — never map across the wrong year (validation error + test). Mappings come from the roll-over screen.

### 5. Write paths
- `StoreStudentRequest`/`UpdateStudentRequest`: keep `class_id` validation but constrain `Rule::exists` to active-year classes; `studentAttributes()` excludes `class_id`; controllers call `EnrollmentService::assign` inside the existing transaction when a class was chosen (form options `classes` = active year only, `StudentController:137`).
- `StudentController` index eager → `currentEnrollment.schoolClass`; keep the `school_class` row shape (map from currentEnrollment) so `students/index.tsx` is untouched; edit payload keeps supplying `class_id` (from current enrollment) for `edit.tsx:59`.
- `StudentController::destroy`: nothing extra (enrollments cascade on student delete).
- Import: `ImportContext` preloads **active-year** classes only and remembers the active year; `commitRow` auto-create sets `academic_year_id` = active year; after `Student::create` → `EnrollmentService::assign($student, $class)` (L112–150). Preview/validation untouched.

### 6. Read paths
- `ClassAccess::classIds(User, ?int $yearId = null)` — optional year filter; today-surfaces pass the active year. Signature change ripples 8 call sites (inventory §6).
- `AttendanceController::index`: roster → `whereHas('enrollments', class + activeOn($date))` (**as-of-date**, spec req 3). **Class dropdown stays all-years (no year filter)** — spec 03 §4 grants teachers corrections "on any date", and the as-of roster already handles historical correctness; the roll-over screen is where years flip.
- `UpsertAttendanceRequest`: teacher branch → resolve `$student->classOn($date)` with a `Date::hasFormat` guard first (authorize runs pre-validation — an unparseable date falls back to the current-enrollment class instead of a 500); must be `canAccess`-ible (same "no class = teacher can't touch" semantics).
- Sweep `MarkAbsencesCommand:33`: add `->whereHas('enrollments', fn ($q) => $q->activeOn($today))` (alumni never swept).
- `AttendanceReportService::classReport`: roster = students whose enrollment in this class **overlaps** [from,to]; fetch ALL their enrollments once, resolve `classOn(record.date)` in PHP per record; only records attributed to this class enter counts/statuses/rate. Roster rows can legitimately carry zero attributed records (row present, counts 0, rate null) — locked by test. `board()`: unchanged code (students() relation is already open-only) — caller passes active-year `classIds`.
- `ExcuseReviewController`: teacher scoping → `whereHas('student.enrollments', open + whereIn class_id active-year)`; eager/class_name → `student.currentEnrollment.schoolClass` (admins still see enrollment-less students — preserve L52 comment).
- `ExcuseAttachmentController`: teacher check → class on the excuse's `start_date` (`classOn`).
- **`GuardianExcuseController`** (missed in draft): `with('schoolClass…')` at :41/:49 → `currentEnrollment.schoolClass`; `class_name` at :59/:110 from the current enrollment.
- **`SendAbsenceNotifications`** (missed in draft): `load('student.schoolClass')` at :55/:136 and class name at :88/:150/:227 → `student.currentEnrollment.schoolClass` (absence records are same-day, so the open enrollment is the right class).
- `StudentReportController`: canView teacher branch + picker → open-enrollment class in active-year homerooms.
- `SchoolClassController`: index/create scoped to a year (`filters.year_id` default active); destroy blocker → `enrollments()->exists()` ("students have enrollment history in this class" — update `ClassManagementTest:161`).

### 7. Admin UI (new pages, house template)
- `routes/academic-years.php` (`role:admin`): `academic-years.index` CRUD (list with active badge, create/edit/activate — activating deactivates others in a transaction; delete blocked when classes/enrollments exist) + `academic-years.rollOver` (GET mapping screen, POST apply).
- Pages `resources/js/pages/academic-years/`: `index.tsx` (table + create/edit dialog + activate) and `roll-over.tsx` (source year fixed = active; target year create/pick; per-class mapping row: keep-name-in-new-year / pick existing target / create-new with teacher / none = alumni; student-count preview; confirm dialog). Sidebar: "Academic Years" in the admin block.
- `classes/index.tsx`: year filter select (default active) — options prop from controller.
- `reports/class-report.tsx`: year select (`name="year_id"`, default active) above the class select; `classes` prop is per selected year; export query includes `year_id` if the controller needs it (it doesn't — class id is enough).

### 8. Seeder
`DatabaseSeeder`: create classes in the active year; assign each student via `EnrollmentService::assign($student, $class, startedOn: year->starts_at)`; keep the printed cheat-sheet. (Zero attendance rows exist at seed → no attribution concerns.)

### 9. Tests
- Update mechanical sites (inventory §1 tests list): `['class_id' => $class->id]` → `Student::factory()->enrolledIn($class)`; `StudentTest:24`/`SchoolClassTest:28` FK-name pins → assert new relation shapes; `StudentManagementTest` `assertSame($class->id, $student->class_id)` → currentEnrollment class id; `AbsenceNotificationTest:59,192`; `ClassReportTest`/`PresenceBoardTest` fixtures to `enrolledIn`.
- **Sweep fixtures are their own category**: `MarkAbsencesCommandTest` (:29–32, :55, :69, :80) builds students with NO enrollment — the new sweep skips them all, so every sweep test fails until fixtures get `enrolledIn` (+ the new alumni-skip case).
- New: `tests/Unit/Models/EnrollmentTest` (classOn resolution incl. boundaries, activeOn scope, partial-unique DB failure, overlap guard), `tests/Feature/AcademicYears/YearManagementTest` (CRUD, exactly-one active, delete guards), `tests/Feature/AcademicYears/RollOverTest` (the killer scenario: promote seeded year → old class report unchanged + new roster + **target year becomes active** + alumni invisible today but present in history; unmapped → alumni; re-apply no-op; wrong-year mapping rejected), `MarkAbsencesCommandTest` + alumni-skip case, `ClassReportTest` + mid-year-move attribution (5A Sep → 5B Nov) + overlap-with-zero-attributed-records row, `StudentImportServiceTest` + active-year mapping/auto-create.

### 10. Gates
`vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → `sail npm run build` + `sail npm run types:check` → `php artisan test --compact` narrow then full → `sail artisan migrate:fresh --seed` smoke (board/report against demo data).

### 11. Commit + wrap-up
`feat(academic-years): enrollment history, year-scoped classes, and roll-over` (Co-Authored-By trailer). Update `docs/specs/README.md` status column (02 v2.0 / 03 v2.5 / 06 v1.5 amendments → implemented; 07 → implemented) + MOC Active work. The dev DB requires `migrate:fresh --seed` (in-place migration edits) — smoke data regenerates via seeder.

## Gotchas carried into execution
- sqlite ALTER + FK = broken rebuild SQL → year FK only in create migrations; academic_years table must sort BEFORE create_classes (filename `2026_09_19_043048_000001_…`).
- Partial unique index = raw DB::statement (Laravel builder can't express it).
- `SchoolClass::students()` semantic becomes open-enrollment-only — audit consumers (board ✓ unchanged, class-delete blocker moves to `enrollments()`).
- `preventLazyLoading` is on — eager-load `enrollments`/`currentEnrollment.schoolClass` wherever names render.
- `Date::setTestNow` pins: `assign()` defaults `started_on` to school-tz today → enrollment is "active" under every existing test pin (2026-09-20/21).
- `ExcuseReviewController` L52 comment (admins see class-less students) must survive the rewrite.
- `ImportContext` is constructed per import — its class preload gains the active-year filter; auto-create classes inherit that year.
- Pint strips "unused" imports — keep trait/use lines.
- ClassAccess signature change: update all 8 callers, not just the ones that compile differently (default param keeps admin behavior identical unless a year is passed).

## Verification
- Full suite green (398 existing, heavily updated + ~25 new); pint/phpstan/tsc/build clean.
- Manual smoke after `sail artisan migrate:fresh --seed`: class report for Kelas 5A (2026/2027) shows the seeded roster; create year 2027/2028 → roll-over mapping 5A→5A, leave 2 students unmapped → apply: 5A 2026/2027 report unchanged, 5A 2027/2028 shows only mapped students, board/pickers exclude alumni, sweep skips them, mid-year move of one student attributes Sep records to the old class and Nov to the new.
