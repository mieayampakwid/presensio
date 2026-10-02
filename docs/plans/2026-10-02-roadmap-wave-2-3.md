# Wave 2–3 Roadmap (Specs 09–17) — Plan Index

> **For implementer agents:** This is the index and the shared conventions. Each linked plan is one independently shippable scope. Read this file first, then the plan you are executing, then the spec it cites (the spec is the source of truth for schema columns, enums, ACs; plans do not copy schema tables).

**Goal:** Take specs 09–17 from "draft" to implemented, in dependency order, in scopes small enough to review and merge one at a time.

**State found (2026-10-02):** Specs 01–08 are shipped. None of 09–17 exists in code: no `semesters`, `subjects`, `employees`, `notifications`, `accounts` tables or models; `users.role` is still a single enum column (`app/Enums/UserRole.php` has admin/teacher/student/parent only); `barryvdh/laravel-dompdf` is approved by spec 11/14 but **not** in `composer.json`; `school_operational_days` (AUDIT D-05) does not exist.

## Plans and order

| #   | Plan                                                                                                                 | Spec                                                  | Depends on              | Size                                    |
| --- | -------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------- | ----------------------- | --------------------------------------- |
| 1   | [Foundation A: roles, permissions, audit log](2026-10-02-foundation-roles-audit.md)                                  | 15 §Roles, §Audit                                     | —                       | L (touches ~50 files via role refactor) |
| 2   | [Foundation B: semesters, class level, school profile, operational days](2026-10-02-foundation-semesters-profile.md) | 15 §Semesters/§Grade level/§School profile (+03 D-05) | 1                       | M                                       |
| 3   | [Subjects & teaching assignments](2026-10-02-subjects.md)                                                            | 09                                                    | 1, 2                    | M                                       |
| 4   | [Notification center](2026-10-02-notification-center.md)                                                             | 17                                                    | 1, 2                    | L                                       |
| 5   | [Grading A: objectives, assessments, gradebook, scoring](2026-10-02-grading-gradebook.md)                            | 10                                                    | 3                       | L                                       |
| 6   | [Grading B: results, finalization, ledger, guardian views](2026-10-02-grading-finalization.md)                       | 10                                                    | 5                       | M                                       |
| 7   | [Digital report cards](2026-10-02-report-cards.md)                                                                   | 11                                                    | 6, 4 (dispatch)         | L                                       |
| 8   | [Staff attendance A: employee master, scanner, sweep, overview](2026-10-02-staff-attendance-core.md)                 | 16                                                    | 1, 2                    | L                                       |
| 9   | [Staff attendance B: leave, recap, self-service, dashboard](2026-10-02-staff-leave-recap.md)                         | 16                                                    | 8 (4 for notifications) | M                                       |
| 10  | [Announcements](2026-10-02-announcements.md)                                                                         | 12                                                    | 3, 4                    | M                                       |
| 11  | [Timetables](2026-10-02-timetable.md)                                                                                | 13                                                    | 3, 2                    | M                                       |
| 12  | [Fees A: chart of accounts, journals, periods](2026-10-02-fees-ledger-core.md)                                       | 14                                                    | 1                       | M                                       |
| 13  | [Fees B: fee types, billing, payments, void, receipts](2026-10-02-fees-operations.md)                                | 14                                                    | 12                      | L                                       |
| 14  | [Fees C: portal, verification, reports, closing](2026-10-02-fees-portal-reports-closing.md)                          | 14                                                    | 13, 4                   | L                                       |

Parallel lanes after plan 2: `{3 → 5 → 6 → 7}`, `{4}`, `{8 → 9}`, `{12 → 13 → 14}`. Plans 10 and 11 follow plan 3 (and 4 for 10). README says 17 should land before 11/12/14 ship; plans 7, 10, 14 contain a task that calls the dispatcher and are written so that task can be done last.

## Shared conventions (every plan assumes these)

- **Skills:** activate `inertia-react-development` for any page/component task, `testing-best-practices` before writing tests, `wayfinder` before touching routes. Run `search-docs` before using a Laravel/Inertia API you have not already used in this repo.
- **Migrations:** always create with `php artisan make:migration <name>` (or `make:model -m`) so the timestamp reflects real execution order across plans. Migration names in plans are descriptive names only, never fixed timestamps.
- **Scaffolding:** `php artisan make:model X -mf --no-interaction`, `make:controller`, `make:request`, `make:test --phpunit`, `make:class`. Tests are PHPUnit classes (not Pest). Feature tests live in `tests/Feature/<area>/`, unit tests in `tests/Unit/{Models,Services}/`, mirroring existing files.
- **Style:** match sibling files — controllers under `app/Http/Controllers/<Area>/`, FormRequests under `app/Http/Requests/<Area>/`, services under `app/Services/<Area>/`, one `routes/<area>.php` file `require`d from `routes/web.php`, enums under `app/Enums/`. Array-shape PHPDoc (PHPStan level in `phpstan.neon`). Enum cases TitleCase.
- **Frontend:** pages in `resources/js/pages/<area>/`; Wayfinder imports from `@/actions/...` / `@/routes/...` (never edit those dirs; regenerate with `sail artisan wayfinder:generate`). Node only through Sail: `sail npm ...`.
- **Class membership:** only via `enrollments` / `classOn(date)` (`app/Models/Enrollment.php`, `app/Services/EnrollmentService.php`). Never add a class pointer to students or any row that derives from attendance.
- **Business dates:** `SchoolSettings::todayDate()` / `->now()` in the school timezone; the app itself is UTC. Tests freeze time like `tests/Feature/DashboardTest.php` (`Date::setTestNow(..., 'UTC')`).
- **Octane:** no request state in singletons/statics. Active role lives in session.
- **Verification per task:** `php artisan test --compact <path-or---filter>`; before closing a plan: `vendor/bin/pint --dirty --format agent`, then `composer ci:check` (JS check, TS types, Pint, PHPStan, tests). Report real output.
- **Status upkeep:** at plan close, propose the `docs/specs/README.md` status-table edit (and the spec's `Status:` line); apply only on approval. Capture shipped state in the Cortex vault MOC (`10_Projects/Presensio/MOC.md`) per global instructions.

## Decisions (confirmed 2026-10-02)

1. **Flat URLs.** New routes follow the existing flat style (`classes`, `teachers`, `my-excuses`, `reports/...`), not the spec's `/admin|teacher|parent|student/...` prefixes. Authorization is by middleware/ability, never by URL prefix. Mapping used in all plans: `admin/subjects`→`subjects`; `admin/classes/{c}/subjects`→`classes/{c}/subjects`; `teacher/courses…`→`courses…`; `teacher/classes/{c}/(grades|report-cards|announcements)`→`classes/{c}/…`; `teacher/schedule|objectives`→`schedule|objectives`; `parent/children/{id}/…`→`children/{student}/…`; `parent/fees`→`my-fees`; `student/grades|report-cards`→`my-grades|my-report-cards`; `admin/fees|finance|employees|bell-schedules|audit-logs|staff-attendance|staff-leave`→ same name without `admin/`; `admin/notifications/deliveries`→`notification-deliveries`; `admin/announcements`→`announcements/manage` (register **before** `announcements/{announcement}`); timetable editor `classes/{c}/timetable/edit`, weekly view `classes/{c}/timetable`. Spec ACs name behavior, not URLs, so ACs are unaffected. Update the spec URL mentions when each spec ships (propose in close-out).
2. **`barryvdh/laravel-dompdf` ^3.1 is installed** (2026-10-02, via `sail composer require`; `composer.json`/`composer.lock` modified, uncommitted). `ext-bcmath` confirmed present in the Sail image.
3. **`classes.grade_level` is `NOT NULL` per spec** (plan 2): unparsable legacy rows get the sentinel `0` = "belum diatur", the form requires 1–12, and the admin banner lists `grade_level = 0`.
4. **`teachers` → `employees` is a one-shot rewrite**, no accessor shim (plan 8 Tasks 1–2 land in one commit).
5. **Role refactor is expand → migrate → contract** (plan 1) so the suite stays green at every task; `users.role` is dropped only in the last task.
6. **Single-TP / equal-result description rule** (plan 5): see plan 5 Task 1.
7. **Out of scope here:** AUDIT code-drift fixes D-01…D-04, D-06…D-08. D-01 and D-04 are touched by plan 8 (scanner/override pattern) — flagged there, not fixed.

## Known advisory

`composer audit` reports two `league/commonmark` advisories (≤2.10.1: DisallowedRawHtml bypass, GFM-table quadratic DoS). Pre-existing, not from dompdf. Plan 10 (announcements render user Markdown) must disable raw HTML, must **not** enable the GFM tables extension, and should bump commonmark once a fixed version exists.
