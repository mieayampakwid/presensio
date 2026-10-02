# Foundation A — Roles, Permissions & Audit Log Implementation Plan

> **For implementer agents:** Execute in order. Spec: `docs/specs/15-foundation.md` §Roles & permissions, §Audit log, Requirements 1, 2, 6; AC-15-01…05, 10, 11. Shared conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Replace `users.role` with multiple roles per user, a code-defined permission matrix, an active-role switcher, and an append-only audit log.

**Architecture:** Expand → migrate → contract. Add `user_roles` and role helpers on `User` while `users.role` still works; move every caller to the helpers/abilities; drop the column last. Permissions are an `Ability` enum + `RolePermissions` matrix registered as Gates; data scope (homeroom/subject/guardian links) stays data-derived in `ClassAccess` and policies. Audit rows are written by `AuditLogger` inside the caller's transaction.

**Prerequisites:** none. No new package.

**Blocking questions:** none (counselor = attendance only, audit retention = indefinite are spec defaults).

---

### Task 1: Role enum, `user_roles`, `User` helpers (expand)

**Files:**

- Modify: `app/Enums/UserRole.php` (add `Principal`, `Counselor`, `Finance`, `Staff`; keep order admin, principal, teacher, counselor, finance, staff, parent, student — used for default active role)
- Create: migration `create_user_roles_table` (via `make:migration`) (columns per spec §Schema 1; backfills one row per existing `users.role`)
- Create: `app/Models/UserRoleGrant.php` (no `id`, composite key, `$timestamps` only `created_at`)
- Modify: `app/Models/User.php` — `roleGrants()` hasMany; `roles(): Collection<int,UserRole>`; `hasRole(UserRole)`; `hasAnyRole(UserRole ...)`; `activeRole(): UserRole` (session `active_role` if still held, else first held role in enum order)
- Test: `tests/Unit/Models/UserTest.php`, `tests/Feature/Auth/UserRolesMigrationTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Models/UserTest.php tests/Feature/Auth/UserRolesMigrationTest.php`

- [ ] **Step 1:** `php artisan make:model UserRoleGrant --no-interaction`, `make:migration create_user_roles_table`. Backfill inside `up()`: `DB::table('user_roles')->insertUsing(['user_id','role','created_at'], DB::table('users')->select('id','role', DB::raw('CURRENT_TIMESTAMP')))`.
- [ ] **Step 2:** Test (AC-15-11): seed users with each old role via `DB::table('users')` before migrating is impractical in RefreshDatabase; instead assert via the migration class: create users with `UserFactory`, truncate `user_roles`, run the migration's backfill closure extracted to a static `backfill()` method, assert exactly one grant equal to `users.role` per user.
- [ ] **Step 3:** Unit tests for `hasRole`, `hasAnyRole`, `roles()` ordering, `activeRole()` falling back when the session role is no longer held.
- [ ] **Step 4:** `UserFactory` — add `->withRoles(UserRole ...$roles)` state using `afterCreating`; existing states (`admin()`, `teacher()`, …) also create the grant so both representations agree during the transition.

### Task 2: Permission matrix and Gates

**Files:**

- Create: `app/Enums/Ability.php` — string-backed cases mirroring the spec 15 matrix rows (`ManageMasterData`, `OverrideAttendance`, `ReviewExcuses`, `ViewAttendanceReports`, `EnterGrades`, `ViewGrades`, `PublishReportCards`, `ViewReportCards`, `ManageFees`, `ViewFees`, `ViewAuditLog`, `ManageStaffAttendance`)
- Create: `app/Authorization/RolePermissions.php` — `static function grants(UserRole $role): array<Ability>`; the matrix table as a literal map
- Modify: `app/Providers/AppServiceProvider.php` — in `boot()`, loop `Ability::cases()` and `Gate::define($ability->value, fn (User $user) => $user->hasAnyRole(...RolePermissions::rolesFor($ability)))`
- Test: `tests/Unit/Authorization/RolePermissionsTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Unit/Authorization`

- [ ] **Step 1:** Encode the matrix. "scoped" cells (teacher) grant the _ability_; the scope check stays in `ClassAccess`/policies. Parent/student "own" cells are expressed by the ability plus the existing guardian/self checks, not by the role alone.
- [ ] **Step 2:** One parametrised test (data provider) asserting every cell of the spec matrix: e.g. `[Ability::OverrideAttendance, [UserRole::Admin, UserRole::Teacher], [Principal, Counselor, Finance, Parent, Student]]`. AC-15-04/05 are covered at the HTTP level in Task 4.

### Task 3: Migrate callers to helpers/abilities

**Files (all `grep -rnE "->role\b|UserRole::|role:" app routes resources/js/pages resources/js/types tests database`):**

- Modify: `app/Http/Middleware/EnsureUserHasRole.php` → `$user->hasAnyRole(...)` against parsed enum values
- Modify: `routes/*.php` `role:` middleware stays as coarse any-of gate; change write routes that must exclude principal/counselor to `can:<ability>` (attendance override/bulk, excuse review, settings)
- Modify: `app/Services/Attendance/ClassAccess.php` — read scope: admin/principal/counselor see all classes, teacher sees homeroom (+ `class_subjects` once plan 3 lands); add `canWrite(User, classId)` for override: admin all, teacher scoped, others false
- Modify: `app/Http/Requests/Attendance/{UpsertAttendanceRequest,StoreBulkAttendanceRequest}.php`, `app/Http/Controllers/{Dashboard/DashboardController,Excuses/*,Reports/*}.php`, `app/Services/{UserProfileLinker,Reports/AttendanceReportService}.php` → helpers
- Modify: `app/Http/Controllers/Users/UserController.php`, `app/Http/Requests/Users/{Store,Update}UserRequest.php` — accept `roles[]` (min 1); `student` exclusive (422); last-admin guard (422) when removing `admin` from the only active admin; write/replace `user_roles` rows in one transaction
- Modify: `resources/js/pages/users/{create,edit,index,user-form}.tsx` (multi-select, show all roles), `resources/js/types/auth.ts` (`roles: UserRole[]`, `active_role`), `resources/js/pages/dashboard.tsx`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` — share `auth.user.roles` and `auth.active_role`
- Test: update `tests/Feature/Users/UserManagementTest.php`, `tests/Feature/Auth/RoleMiddlewareTest.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/Reports/*`, `tests/Feature/attendance/*`; add `tests/Feature/Auth/MultiRoleAccessTest.php`

**Dependencies:** Tasks 1–2 · **Verification:** `php artisan test --compact` (whole suite green) — this is the risky task, run the full suite

- [ ] **Step 1:** Migrate in this order, running the full suite after each group: middleware → `ClassAccess` → requests/controllers → user management → frontend types.
- [ ] **Step 2:** `MultiRoleAccessTest`: AC-15-01 (teacher+parent → homeroom attendance 200 and child report 200), AC-15-02 (student + other → 422), AC-15-03 (last admin → 422), AC-15-04 (principal override/bulk → 403, class report → 200), AC-15-05 (finance → grade route 403 — assert against a placeholder `Gate::allows(Ability::EnterGrades)` false until plan 5 exists).
- [ ] **Step 3:** Password-reset contact lookup (spec 15 "Profiles", spec 01): order teacher profile → guardian profile → `users.email`. Locate in `app/Http/Controllers/Auth/PasswordResetController.php`; add a test with a user holding both profiles.

### Task 4: Active role switcher

**Files:**

- Create: `app/Http/Controllers/Auth/ActiveRoleController.php`, `app/Http/Requests/Auth/UpdateActiveRoleRequest.php`
- Modify: `routes/web.php` (`PUT active-role` → `active-role.update`, in the `auth` group)
- Modify: `resources/js/components/app-header.tsx` / `nav-user.tsx` (switcher shown only when `roles.length > 1`)
- Modify: `DashboardController` + `app-sidebar.tsx` to render for `activeRole()`
- Test: `tests/Feature/Auth/ActiveRoleTest.php`

**Dependencies:** Task 3 · **Verification:** `php artisan test --compact tests/Feature/Auth/ActiveRoleTest.php`

- [ ] **Step 1:** Request validates `role` is `Rule::enum(UserRole::class)` and held by the user (422 otherwise). Controller stores `session(['active_role' => $role->value])` and redirects to `route('dashboard')`.
- [ ] **Step 2:** Tests: switching to a held role stores it; switching to an unheld role → 422; authorization is unchanged by the active role (teacher+parent with active role parent can still open teacher attendance → 200).

### Task 5: Audit log

**Files:**

- Create: migration `create_audit_logs_table` (via `make:migration`) (spec §Schema 5)
- Create: `app/Models/AuditLog.php` (append-only: `updating`/`deleting` model events throw `LogicException`), `database/factories/AuditLogFactory.php`
- Create: `app/Services/Audit/AuditLogger.php` — `record(Model $auditable, string $action, ?array $old, ?array $new, ?string $reason = null): AuditLog`; uses `auth()->id()` (null for console), `request()?->ip()`; **must be called inside the caller's `DB::transaction`** and lets exceptions propagate (AC-15-10)
- Create: `app/Models/Concerns/Auditable.php` — optional trait with `audited(string $action, ...)` helper (no automatic observers in v1; each spec calls `AuditLogger` explicitly)
- Create: `app/Http/Controllers/Admin/AuditLogController.php`, `app/Http/Requests/Admin/AuditLogIndexRequest.php`, `resources/js/pages/admin/audit-logs/index.tsx`, `routes/audit-logs.php`
- Modify: `routes/web.php` (`require`), `User` role changes in Task 3 call `AuditLogger` (`user_roles` is audited), settings update controllers call it for `settings`
- Test: `tests/Unit/Services/Audit/AuditLoggerTest.php`, `tests/Feature/Admin/AuditLogTest.php`

**Dependencies:** Task 1 (users); can run in parallel with Tasks 2–4 · **Verification:** `php artisan test --compact tests/Unit/Services/Audit tests/Feature/Admin/AuditLogTest.php`

- [ ] **Step 1:** `AuditLoggerTest`: row has actor, old/new JSON, reason, IP; updating or deleting a row throws; a closure that records then throws leaves no audit row **and** no business change (wrap a throwaway model update in `DB::transaction` and make the logger insert fail by passing an oversize `action` to prove atomicity — AC-15-10 shape).
- [ ] **Step 2:** `GET audit-logs` (`can:view-audit-log` → admin, principal): paginated 25/page, filters `auditable_type`, `auditable_id`, `user_id`, `from`, `to`. Test: principal 200, finance 403, filters narrow results.
- [ ] **Step 3:** Reusable "Riwayat perubahan" panel component `resources/js/components/audit-history.tsx` taking `entries: {action, user, old_values, new_values, reason, created_at}[]`; first consumer is the user-roles screen (role assignment writes `updated` with `old_values.roles`/`new_values.roles`).

### Task 6: Contract — drop `users.role`

**Files:**

- Create: migration `drop_role_from_users_table` (via `make:migration`) (`down()` re-adds the column and restores from the first grant in enum order)
- Modify: `app/Models/User.php` (remove `role` from `#[Fillable]`, casts, docblock), `database/factories/UserFactory.php` (states only create grants), `database/seeders/DatabaseSeeder.php`, `app/Console/Commands/CreateAdminUserCommand.php`, `app/Services/UserProfileLinker.php`, `tests/Unit/Models/UserTest.php`, `tests/Feature/Console/CreateAdminUserCommandTest.php`

**Dependencies:** Tasks 3–5 · **Verification:** `grep -rn "'role'\|->role\b" app database tests resources/js/pages` returns no user-role hits; `composer ci:check`

- [ ] **Step 1:** Run the grep; fix stragglers. Run `php artisan migrate:fresh --seed` in a scratch DB and log in as the seeded admin.
- [ ] **Step 2:** `composer ci:check`. Propose README status line for spec 15 (partial: roles + audit shipped; semesters/class level/profile in plan 2).
