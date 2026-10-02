# Antigravity execution prompt

Run **one plan per session**, in the order below. Paste everything under "Prompt", replacing `{PLAN_FILE}` with the file name (e.g. `2026-10-02-foundation-roles-audit.md`). Start a fresh session for each plan.

## Order

1. `2026-10-02-foundation-roles-audit.md`
2. `2026-10-02-foundation-semesters-profile.md`
3. `2026-10-02-subjects.md`, `2026-10-02-notification-center.md`, `2026-10-02-staff-attendance-core.md`, `2026-10-02-fees-ledger-core.md` (independent of each other after 1–2)
4. `2026-10-02-grading-gradebook.md` → `2026-10-02-grading-finalization.md` → `2026-10-02-report-cards.md`
5. `2026-10-02-staff-leave-recap.md`, `2026-10-02-announcements.md`, `2026-10-02-timetable.md`
6. `2026-10-02-fees-operations.md` → `2026-10-02-fees-portal-reports-closing.md`

Tasks inside plans that say "needs plan 4" (notification wiring) can be skipped until `notification-center` is merged; the plan says so.

## Prompt

```
You are implementing one scoped plan in the Presensio repository (Laravel 13, PHP 8.5, Inertia v3 + React, PHPUnit, Postgres, Octane). Work autonomously, but stop and ask when a stop condition below is hit.

PLAN TO EXECUTE: docs/plans/{PLAN_FILE}

READ FIRST, IN THIS ORDER (do not write code before finishing all five):
1. AGENTS.md (project rules; they override your defaults)
2. docs/plans/2026-10-02-roadmap-wave-2-3.md (shared conventions, confirmed decisions, dependency order)
3. docs/plans/{PLAN_FILE} (your task list)
4. The spec the plan cites in its header (docs/specs/NN-*.md). The spec is the source of truth for schema columns, enums, and acceptance criteria (ACs). If the plan and the spec disagree, the spec wins, except for the decisions listed in the roadmap ("Decisions (confirmed …)"), which win over both.
5. Two or three existing sibling files for each kind of file you are about to create (controller, FormRequest, service, migration, factory, test, React page) and copy their structure, naming and comment density.

BEFORE TASK 1:
- Confirm every prerequisite listed in the plan is already in the codebase (tables, models, services). If a prerequisite is missing, STOP and report; do not implement it yourself.
- Run `git status`. Create and switch to a branch named `feat/<plan-name-without-date>`. Do not work on `main`.
- Run the full test suite once to record the baseline (`vendor/bin/sail artisan test --compact`). Report any pre-existing failures; do not fix unrelated ones.

HOW TO EXECUTE:
- Do tasks strictly in the order written. Do not start a task before the tasks it lists under "Dependencies" are done and green.
- Tests first where the plan lists ACs: write the test from the AC, see it fail for the right reason, then implement.
- After each task: run the narrowest verification command given in that task, fix until green, then run `vendor/bin/sail bin pint --dirty` (or `vendor/bin/pint --dirty --format agent`) and commit with a Conventional Commit message that names the task (e.g. `feat(subjects): add subject catalog CRUD`). One commit per task. Never push.
- After the last task: run `composer ci:check` via Sail (`vendor/bin/sail composer ci:check`) and report the real output.

ENVIRONMENT RULES:
- The app runs in Docker via Laravel Sail. Use `vendor/bin/sail artisan ...`, `vendor/bin/sail composer ...`, and `vendor/bin/sail npm ...` for everything. NEVER run npm on the host (it breaks the container's Vite).
- Use `php artisan make:...` equivalents through Sail to scaffold files; pass `--no-interaction`.
- Create every migration with `make:migration` (or `make:model -m`) so the filename gets the real current timestamp. Migration names in the plans are descriptive only; ignore any timestamp-looking prefix you see, and never hand-write a timestamp.
- Claude-specific hooks do NOT run in your environment, so enforce them yourself: never edit `.env*`, `composer.lock`, `package-lock.json`, or anything under `resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder` (regenerate with `vendor/bin/sail artisan wayfinder:generate`); run Pint on every PHP file you touch.
- Class membership comes ONLY from `enrollments` (`classOn(date)`). Never add a class pointer to students or attendance rows.
- Business dates use `SchoolSettings` (school timezone); the app runs in UTC. Never keep request state in singletons/statics (Octane).
- URLs are FLAT (e.g. `courses`, `my-fees`, `classes/{school_class}/grades`), as mapped in the roadmap. Do not introduce `/admin`, `/teacher`, `/parent`, `/student` URL prefixes.
- Money is handled with bcmath strings, never floats.

HARD LIMITS:
- Do not add, remove or upgrade any Composer/npm dependency. (`barryvdh/laravel-dompdf` is already installed.) If you believe another dependency is needed, STOP and ask.
- Do not edit docs/specs/*, docs/plans/*, or README status tables. When finished, PROPOSE the spec status edits in your final report instead.
- Do not invent env vars, secrets, API keys or endpoints. If something external is missing, report it.
- Do not refactor or reformat code unrelated to your tasks. If you notice unrelated problems, list them in the report; do not fix them.
- Do not swallow exceptions. Surface or handle them explicitly.
- Do not delete or weaken an existing test to make it pass. If an existing test must change because behavior intentionally changed, change it minimally and say why in the commit body.

STOP AND ASK (write the question, then wait) WHEN:
- A prerequisite is missing, or a task's instructions conflict with the spec or the codebase in a way you cannot resolve from the roadmap decisions.
- A task needs a new dependency, a destructive migration on non-test data, or a change to files outside the plan's scope.
- An AC cannot be met as written.
- You have failed the same verification three times.

FINAL REPORT (plain text, no filler):
1. Tasks completed (with commit hashes) and tasks skipped or blocked, with reasons.
2. Output of `vendor/bin/sail composer ci:check` (real output; state plainly if anything fails or was skipped).
3. A table of every AC the plan references: met / not met, with the test name as evidence.
4. Deviations from the plan and why.
5. Unrelated issues noticed but not touched.
6. Proposed edits to docs/specs/README.md status table and the spec's `Status:` line.
```
