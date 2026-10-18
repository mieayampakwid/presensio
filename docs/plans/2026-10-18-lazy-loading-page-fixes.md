# Lazy-loading page failures: analysis and implementation plan

> **For implementers:** Read each task in full and execute in order. This document authorizes no application changes by itself; the current request is analysis and planning.

**Goal:** Restore populated Journals and Classes listings for their authorized roles while preserving totals, permissions, and teacher data scoping.

**Architecture:** Declare the relationships required by each listing on its owning Eloquent query. Keep the existing DTOs, money helpers, pagination, authorization, and non-production lazy-loading prevention. Avoid global model eager loading, exception suppression, and per-row `loadMissing()` calls.

**Prerequisites:** Existing dependencies only. Installed versions checked: Laravel 13.31.0, inertia-laravel 3.3.4, PHPUnit 12.5.35. No migrations, dependency changes, environment changes, or frontend changes are needed. Follow `.ai/rules/index.md` and matching rules before implementation.

## Findings and evidence

| Page | Affected roles | Mechanism | Evidence |
| --- | --- | --- | --- |
| `finance.journals.index` | principal, admin, finance | `ManualJournalController::index()` loads `author` and counts `lines`, but calls `totalDebit()` and `totalCredit()`, which access `$this->lines`. Counting does not load the relationship. | Recent local logs on October 17–18, including October 18 at 12:59:18, show `JournalEntry.php:121` → `ManualJournalController.php:64`. Isolated multi-row HTTP tests reproduced a 500 and a reported `LazyLoadingViolationException` for `lines` under all three roles. |
| `classes.index` | principal, admin, teacher | `SchoolClassController::index()` reads `$class->teacher` and `$class->teacher->employee` without loading either relationship. | Source trace plus isolated HTTP tests with two visible classes reproduced a 500 and a reported `LazyLoadingViolationException` for `teacher` under all three roles. |

### Why the suite misses these

The unchanged suite passed: **1,482 tests, 7,477 assertions** (`php artisan test --compact`). The principal journal test creates one journal; relevant class-list tests use one matching class or no visible classes. In the installed framework, `Illuminate\Database\Eloquent\Builder::hydrate()` sets the model's `preventsLazyLoading` flag only when the hydrated result contains more than one item. Single-row queries therefore hide these failures. Test both multiple root records and multiple distinct nested teachers.

`AppServiceProvider.php:75` enables lazy-loading prevention outside production. In production these omissions can instead cause N+1 queries. Preserve this guard. Laravel's [13.x relationship documentation](https://laravel.com/framework/docs/13.x/eloquent-relationships#preventing-lazy-loading) documents explicit eager loading and the exception mechanism; the collection-size detail above was verified against the installed source.

### Audit coverage and limits

- Reviewed relationship consumption and loading in controllers and related services for finance/fees, classes/courses/grading/report cards, attendance/reports, people, staff, dashboards, and shared Inertia props. This is a targeted source audit, not proof that every data-dependent page path is free of lazy loading.
- Isolated tests outside the repository ran **9 cases / 18 assertions**: six cases asserted the specific reported exceptions on the two failing lists; three control cases asserted successful journal detail and General Journal report responses with two journals and populated lines, for principal/admin/finance. These passing diagnostic cases prove the bugs exist; they are not post-fix regression tests.
- The older October 14 `Semester.academicYear` errors are testing logs. Current `app/ReportCards/MerdekaSnapshotBuilder.php` explicitly loads `academicYear` and `teacher.employee`. Do not report those historical errors as a newly confirmed defect.
- Sampled related collection pages already declare nested dependencies: class subjects, courses, gradebook, parent/student grades, bill/payment lists, verification queue, and report-card archives. No additional current exception was confirmed there. Existing full-suite coverage passed, but live browser verification remains outstanding.
- Relationship method calls such as `->exists()` or `->count()` can still produce N+1 queries without a lazy-loading exception. Keep that broader performance cleanup separate from these fixes.
- Boost's `search-docs` and log tools were unavailable in this session. Used installed framework source, local logs, and official Laravel 13 documentation as fallbacks.

## Task 1: Fix the journal listing and cover all three reader roles

**Files:**

- Modify: `app/Http/Controllers/Finance/ManualJournalController.php`, `index()` query around lines 38–40.
- Test: `tests/Feature/finance/ManualJournalTest.php`.

**Dependencies:** None.

**What this does:** Loads the line amounts once for the page so the existing exact string arithmetic can calculate totals without hidden queries.

- [ ] **Step 1: Add this regression test to the existing test class.** Import `Inertia\Testing\AssertableInertia as Assert` and `PHPUnit\Framework\Attributes\DataProvider`. Reuse its existing cash/expense account fixtures. The author is explicit to keep unrelated user fixtures out of the query path.

```php
public static function journalReaderRoles(): array
{
    return [
        'principal' => ['principal'],
        'admin' => ['admin'],
        'finance' => ['finance'],
    ];
}

#[DataProvider('journalReaderRoles')]
public function test_populated_journal_list_displays_exact_totals(string $role): void
{
    $user = User::factory()->{$role}()->create();
    $posted = JournalEntry::factory()->create([
        'entry_date' => '2026-10-05',
        'created_by_user_id' => $user->id,
    ]);
    JournalLine::factory()->for($posted)->create([
        'account_id' => $this->expenseAccount->id,
        'debit' => '123.45',
        'credit' => '0.00',
    ]);
    JournalLine::factory()->for($posted)->create([
        'account_id' => $this->cashAccount->id,
        'debit' => '0.00',
        'credit' => '123.45',
    ]);
    $draft = JournalEntry::factory()->draft()->create([
        'entry_date' => '2026-10-04',
        'created_by_user_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('finance.journals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/journals/index')
            ->has('journals.data', 2)
            ->where('journals.data.0.id', $posted->id)
            ->where('journals.data.0.total_debit', '123.45')
            ->where('journals.data.0.total_credit', '123.45')
            ->where('journals.data.0.lines_count', 2)
            ->where('journals.data.0.author', $user->username)
            ->where('journals.data.1.id', $draft->id)
            ->where('journals.data.1.total_debit', '0.00')
            ->where('journals.data.1.total_credit', '0.00')
            ->where('journals.data.1.lines_count', 0)
            ->where('journals.data.1.author', null)
        );
}
```

- [ ] **Step 2: Run the regression before the fix.**

```sh
php artisan test --compact tests/Feature/finance/ManualJournalTest.php --filter=test_populated_journal_list_displays_exact_totals
```

Expected: all three role cases fail at the index request because `lines` is unloaded.

- [ ] **Step 3: Replace the listing's existing `with()` declaration with this one.** Keep `withCount('lines')` and all remaining query/mapping behavior.

```php
->with(['author:id,username', 'lines:id,journal_entry_id,debit,credit'])
```

The line ID and journal foreign key are included for relationship matching. Keep `totalDebit()`/`totalCredit()` unchanged. This bounded, 15-entry page can use a single line eager-load query; there is no need to introduce aggregate aliases or change money formatting. If future profiling justifies SQL sums, treat that as a separate change with equivalent decimal guarantees.

- [ ] **Step 4: Run the owner tests.**

```sh
php artisan test --compact tests/Feature/finance/ManualJournalTest.php
```

Expected: new cases pass with exact totals and null handling; existing draft, post, reverse, attachment, and principal write-rejection tests remain green.

## Task 2: Fix the class listing and retain teacher scoping

**Files:**

- Modify: `app/Http/Controllers/Classes/SchoolClassController.php`, `index()` query around lines 36–37.
- Test: `tests/Feature/Classes/ClassManagementTest.php`.

**Dependencies:** None; execute after Task 1 for a simple review sequence.

**What this does:** Loads homeroom teacher names through employees for every visible class without changing which classes users can see.

- [ ] **Step 1: Add these tests to the existing test class.** `Assert` and `DataProvider` are already imported there. Use two distinct teachers in the school-wide test: loading only `teacher` must still expose the missing nested `employee` relationship.

```php
public static function schoolWideClassReaderRoles(): array
{
    return [
        'principal' => ['principal'],
        'admin' => ['admin'],
    ];
}

#[DataProvider('schoolWideClassReaderRoles')]
public function test_populated_class_list_displays_homeroom_names(string $role): void
{
    $user = User::factory()->{$role}()->create();
    $year = AcademicYear::factory()->current()->create();
    $firstTeacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);
    $secondTeacher = Teacher::factory()->create(['name' => 'Siti Aminah']);
    SchoolClass::factory()->withTeacher($firstTeacher)->create([
        'academic_year_id' => $year->id,
        'name' => '1A',
    ]);
    SchoolClass::factory()->withTeacher($secondTeacher)->create([
        'academic_year_id' => $year->id,
        'name' => '1B',
    ]);
    SchoolClass::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '1C',
    ]);

    $this->actingAs($user)
        ->get(route('classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('classes/index')
            ->has('classes.data', 3)
            ->where('classes.data.0.teacher.name', 'Budi Raharjo')
            ->where('classes.data.1.teacher.name', 'Siti Aminah')
            ->where('classes.data.2.teacher', null)
            ->where('can.manage', $role === 'admin')
        );
}

public function test_teacher_populated_class_list_stays_scoped(): void
{
    $user = User::factory()->teacher()->create();
    $year = AcademicYear::factory()->current()->create();
    $teacher = Teacher::factory()->create([
        'user_id' => $user->id,
        'name' => 'Budi Raharjo',
    ]);
    SchoolClass::factory()->withTeacher($teacher)->create([
        'academic_year_id' => $year->id,
        'name' => '1A',
    ]);
    SchoolClass::factory()->withTeacher($teacher)->create([
        'academic_year_id' => $year->id,
        'name' => '1B',
    ]);
    SchoolClass::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'Hidden Class',
    ]);

    $this->actingAs($user)
        ->get(route('classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('classes/index')
            ->has('classes.data', 2)
            ->where('classes.data.0.name', '1A')
            ->where('classes.data.0.teacher.name', 'Budi Raharjo')
            ->where('classes.data.1.name', '1B')
            ->where('classes.data.1.teacher.name', 'Budi Raharjo')
            ->where('can.manage', false)
        );
}
```

- [ ] **Step 2: Run these tests before the fix.**

```sh
php artisan test --compact tests/Feature/Classes/ClassManagementTest.php --filter='test_populated_class_list_displays_homeroom_names|test_teacher_populated_class_list_stays_scoped'
```

Expected: all three role cases fail because the list accesses an unloaded teacher relationship.

- [ ] **Step 3: Add the nested eager load immediately after `SchoolClass::query()`.**

```php
->with('teacher.employee:id,name')
```

Keep the existing `ClassAccess::academicClassIds` constraint, year/search filters, `can.manage` flag, and write route protections. Loading the full teacher row retains `employee_id`; restricting the employee selection to its actual `id,name` columns is sufficient.

- [ ] **Step 4: Run the owner and adjacent authorization tests.**

```sh
php artisan test --compact tests/Feature/Classes/ClassManagementTest.php tests/Feature/Classes/ClassSubjectVisibilityTest.php tests/Feature/Teacher/CourseTest.php
```

Expected: the three new list cases and existing authorized/unauthorized academic access cases pass. Teacher results contain only their permitted classes; principal has read-only controls.

## Task 3: Verify the role journeys and complete the gates

**Files:** Review only: `routes/finance.php`, `routes/classes.php`, `resources/js/components/app-sidebar.tsx`, and the two modified controllers/tests. No new navigation or shared test infrastructure is needed.

**Dependencies:** Tasks 1 and 2.

**What this does:** Confirms the visible entry paths work with realistic data, neighboring report pages still render, and the changes pass the project gates.

- [ ] **Step 1: Run the focused regression gate and formatter.**

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/finance/ManualJournalTest.php tests/Feature/finance/LedgerReportTest.php tests/Feature/Classes/ClassManagementTest.php tests/Feature/Classes/ClassSubjectVisibilityTest.php tests/Feature/Teacher/CourseTest.php
vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: tests and analysis pass; the diff contains only the two eager-load changes and focused tests. If formatting changes a test, rerun that owner file.

- [ ] **Step 2: Verify existing UI entry paths with at least two visible journals and two visible classes.** Use existing local data/accounts; do not write production fixtures. Resolve any shared project URL using Boost `get-absolute-url` when available.

| Role | Clickable path | Expected result |
| --- | --- | --- |
| principal | Login / switch role → Dashboard → Finance → Journals → journal detail | List totals and detail render; mutation controls remain restricted. |
| admin | Login / switch role → Dashboard → Finance → Journals → journal detail | List and detail render with current controls. |
| finance | Login / switch role → Dashboard → Finance → Journals → journal detail | List and detail render with current controls. |
| principal | Login / switch role → Dashboard → Classes → class Subjects action | List displays homeroom names; contextual subject page is read-only. |
| admin | Login / switch role → Dashboard → Classes → class Subjects action | List and subject page render with current management controls. |
| teacher | Login / switch role → Dashboard → Classes → permitted class Subjects action | Multiple permitted classes render; unrelated classes remain absent. |
| principal, admin, finance | Dashboard → Reports → General Journal | Populated report renders; this is distinct from the Journals listing. |

Also exercise the journal status filter and class teacher-name search. A one-row filtered result must keep working alongside the multi-row result. Inspect only new browser/server errors from these visits. Record any unavailable browser tooling or account/data prerequisites instead of claiming manual verification.

- [ ] **Step 3: Run the final full gate once.** In Sail use `vendor/bin/sail composer ci:check`. If running PHP on the host, the documented equivalent is:

```sh
vendor/bin/sail npm run check
vendor/bin/sail npm run types:check
composer test
```

If host PHPStan reaches its memory limit, run the same analysis with `vendor/bin/phpstan analyse --memory-limit=1G` and report that execution separately. Do not install host Node dependencies. Expected: all checks pass, including the six added role cases. Keep any newly discovered failures separate unless they arise from these changes.

## Acceptance criteria

- Principal/admin/finance can open a multi-row journal list with correct decimal-string totals and empty draft totals.
- Principal/admin/teacher can open a multi-row permitted class list with correct employee-backed homeroom names and null teachers.
- Teacher class scoping and existing read/write authorization are preserved.
- Root and nested relationships are loaded in the listing query; no per-row relationship queries are introduced.
- Lazy-loading prevention remains enabled as configured; no global eager-loading behavior is added.
- Owner tests pass, final gates are recorded, and manual UI verification is either completed or explicitly reported as outstanding.
