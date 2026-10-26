# In-App QR Scanner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver spec 19 v1.1: a staffed browser QR scanner for students and employees, with dedicated operator accounts and shared attendance/audit behavior.

**Architecture:** Keep attendance decisions in the existing student and employee engines. Add a thin authenticated web adapter, an explicit operator product-access boundary, and a camera/session controller whose submission state can be tested without camera hardware. Reuse the application shell with a minimal operator branch and existing user provisioning.

**Tech Stack:** PHP 8.5, Laravel 13, Fortify 1, Inertia 3, React 19, TypeScript, Tailwind 4, Wayfinder, PHPUnit 12; browser camera/audio/fullscreen APIs and a QR decoder behind a local adapter.

**Spec:** [19-in-app-scanner.md](../specs/19-in-app-scanner.md), draft v1.1, plus [03](../specs/03-attendance.md), [08](../specs/08-dashboard.md), [15](../specs/15-foundation.md), and [16](../specs/16-staff-attendance.md).

**Status:** Tasks 1–5 implemented and committed locally (2026-10-27); Task 6 acceptance remains pending for physical-camera performance and the full desktop-browser matrix. No merge, push, or deployment performed. Execute in `in-app-qr-scanner`, currently `/Users/arifinnor/.codex/worktrees/24c2/presensio`. Specs are uncommitted there. Worktree base is `67f8e6c`; main's subsequent `846ce89` updates spec 20 only. Do not merge, push, or deploy as part of writing this plan.

## Global Constraints

- `scanner_operator` cannot be combined with any other role; preserve student exclusivity and last-admin protection.
- Scanner operators, admins, and teachers can scan school-wide student and employee codes; no new editing/master-data privileges.
- Request: `{ credential: string }`, required, non-empty, maximum 255 characters. Web scans always use `ScanMethod::DynamicQr` and server time.
- Never expose `X-Scanner-Key` to the browser. Preserve hardware clock and response compatibility.
- Each completed engine attempt produces exactly one `scan_events` row; auth, CSRF, permission, validation, and throttle rejections produce none.
- Web throttle: 60 attempts per minute per authenticated user, with a retry delay on HTTP 429.
- One request in flight; global two-second minimum between request starts; one continuous second without a detected QR before rearming after submission. No queued scans or automatic replay.
- Result card: four seconds; log: latest 50 results in page memory only. No raw QR tokens in logs/storage/analytics.
- Start/Stop are explicit; release camera/decoder resources on cancellation, navigation, page hide, logout, and loss of access. Return requires explicit start.
- Production HTTPS; Windows Chrome/Edge and macOS Safari/Chrome are the required desktop verification matrix. Audio/fullscreen failure must not disable visual scanning.
- Enrollment is the only membership source. No new tables, scanner-source column, environment variables, or class pointers are planned.
- Follow `.ai/rules/index.md` and matching rules. Read domain skills before implementation; use Artisan for new PHP files, PHPUnit for PHP coverage, and Sail for Node tooling.
- Package changes require approval. This plan does not install or approve dependencies. No new frontend test framework is required.

## Review Focus

1. Operator login with a stale intended URL must land on Scanner; password, two-factor, and passkey completion must not escape the boundary. Owned by Task 2.
2. An operator linked to an employee profile must still be denied staff self-service and receive no notification payload. Owned by Task 2.
3. Camera permission resolving after Stop/unmount, including React Strict Mode startup repetition, must release the late stream and ignore callbacks. Owned by Task 5.
4. A continuously visible QR whose token rotates, with a response slower than cooldown, must never submit again until removal/re-presentation. Owned by Task 4.
5. Server commit followed by lost response or page exit must never cause automatic replay or a stale success notification on a new session. Owned by Tasks 4–5.

## Prerequisites and implementation references

- Read the spec and applicable rules before edits. Boost `search-docs` is not exposed in the planning session; use it at execution if available, otherwise installed package source and official docs. Checked worktree lockfile versions: Laravel `13.31.0`, Fortify `1.39.0`, inertia-laravel `3.3.4`, PHPUnit `12.5.35`. The primary checkout has matching installed PHP versions, but the scanner worktree lacks installed dependencies; never run its tests against a different checkout's application files.
- Establish worktree-local dependencies/runtime using the project's existing Sail setup. Inspect `compose.yaml`, `vendor/bin/sail artisan list`, `vendor/bin/sail artisan make:test --help`, and the container's `node --version`. Avoid simultaneous Sail projects fighting over ports/databases; use an isolated test database. Do not copy populated `.env` values into this plan or commit them.
- Before adding a decoder, inspect package metadata with `vendor/bin/sail npm view html5-qrcode version license engines time --json` and its official source/API. Candidate: `html5-qrcode`, behind Task 5's adapter. Confirm continuous success/failure callbacks, camera stop/clear behavior, default-camera startup, Safari support, and bundling. Present the exact package/version and rationale for approval, then install through Sail with an exact version. If it cannot satisfy the adapter/browser contract, resolve the replacement choice before Task 5; do not silently omit Safari or camera cleanup.
- Verify PHP/storage constraints before migration decisions. `user_roles.role` currently uses string storage and scan outcomes are PHP-backed values; inspect the actual database schema and migrations before declaring no migration necessary.
- Scaffolding examples: `vendor/bin/sail artisan make:controller Attendance/ScannerController --no-interaction`, `vendor/bin/sail artisan make:request Attendance/ScannerScanRequest --no-interaction`, `vendor/bin/sail artisan make:middleware RestrictScannerOperatorAccess --no-interaction`. Generate each new test with `make:test --phpunit` and move to the existing owning subdirectory; do not pass `Feature/` in its name.
- Test conventions: `RefreshDatabase`, explicit factory states, frozen `Date` clock, real services, and assertions on response plus stored mutations/events. Use `Student::factory()->enrolledIn($class)` for eligible students, not a global factory change that enrolls all students.
- API references: [Laravel HTTP tests](https://laravel.com/framework/docs/13.x/http-tests), [Laravel time control](https://laravel.com/framework/docs/13.x/mocking). Confirm installed Inertia `useHttp` submission/error/cancellation types before binding the web transport; do not assume Axios exists.

## File and responsibility map

| Area | Files | Responsibility |
|---|---|---|
| Shared eligibility | `app/Enums/ScanOutcome.php`, `app/Services/Attendance/{AttendanceScanService,ScanResult}.php` | Missing alumni guard and coarse ignored mapping |
| Roles | `app/Enums/{UserRole,Ability}.php`, `app/Authorization/RolePermissions.php`, `app/Http/Requests/Users/{StoreUserRequest,UpdateUserRequest}.php` | Exclusive operator role and scanner capability |
| Operator boundary/landing | new `app/Http/Middleware/RestrictScannerOperatorAccess.php`, `bootstrap/app.php`, `app/Http/Middleware/EnsureUserIsActive.php`, `app/Http/Controllers/Dashboard/DashboardController.php`, `app/Providers/FortifyServiceProvider.php`, new `app/Http/Responses/{ScannerLoginResponse,ScannerTwoFactorLoginResponse,ScannerPasskeyLoginResponse}.php` | Product denial, dashboard redirect, login completion |
| Web scan | new `app/Http/Controllers/Attendance/ScannerController.php`, new `app/Http/Requests/Attendance/ScannerScanRequest.php`, new `routes/scanner.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php` | Page, scan JSON, validation, limiter |
| Shared UI/privacy | `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/components/{app-sidebar,app-sidebar-header,app-header,user-menu-content}.tsx`, `resources/js/types/auth.ts`, `resources/js/pages/users/user-form.tsx` | Minimal operator UI, no unrelated shared data, provisioning |
| Scanner state/types | new `resources/js/lib/{scanner-session,scanner-feedback}.ts`, new `resources/js/types/scanner.ts` | Submission/rearming and result mapping |
| Camera/UI | new `resources/js/lib/scanner-camera.ts`, new `resources/js/hooks/use-scanner.ts`, new `resources/js/pages/scanner/index.tsx` | Decoder lifecycle, HTTP/audio bridge, camera page |
| Copy | new `lang/{id,en}/scanner.php`, `lang/{id,en}/roles.php` | Scanner states/results/controls and role name |
| Regression owners | existing attendance/auth/users/dashboard tests; new `tests/Feature/Attendance/{ScannerAccessTest,ScannerScanTest}.php`; new `tests/frontend/{scanner-session,scanner-camera}.test.mjs` | Endpoint, privacy, state and lifecycle coverage |

Generated `resources/js/{actions,routes,wayfinder}` change only through Wayfinder. `package.json`/lockfile change only through approved package tooling; add `test:scanner` as `node --experimental-strip-types --test tests/frontend/scanner-*.test.mjs` for dependency-free pure-state/adapter tests on the project's Node 22.18+ runtime. The pure state and feedback modules must use relative imports and erasable TypeScript syntax so Node can execute them directly; keep browser-only imports inside the camera implementation, with its decoder factory injected for Node tests. Production TypeScript uses existing import conventions; test `.mjs` files import the corresponding `.ts` modules directly and use Node's built-in test/assert APIs. No TypeScript config change or browser-test package is needed.

---

### Task 1: Complete shared student eligibility without changing hardware contracts

**Files:** Modify `app/Enums/ScanOutcome.php`, `app/Services/Attendance/{AttendanceScanService,ScanResult}.php`; extend `tests/Feature/Attendance/AttendanceScanApiTest.php`. Update only scan-success fixtures in other tests that call the student engine and now need enrollment.

**Interfaces:** Consumes existing `AttendanceScanService::scan(ScanMethod, string, ?CarbonInterface): ScanResult` and `Student::enrollments()`. Produces `ScanOutcome::IgnoredAlumni = 'ignored_alumni'` with `ScanResult::coarse(): string` returning `ignored` for it. Existing service signature and hardware envelope stay unchanged.

- [x] **Step 1: Add failing eligibility coverage to the existing API owner.** For `test_unenrolled_student_is_ignored_without_attendance_mutation`, freeze the existing test clock, create a student/RFID card with no enrollment, then assert:

```php
$this->scan(['credential_type' => 'rfid', 'credential' => 'ALUMNI'])
    ->assertOk()->assertJson(['outcome' => 'ignored', 'detail' => 'ignored_alumni']);
$this->assertDatabaseCount('attendances', 0);
$this->assertDatabaseHas('scan_events', ['student_id' => $student->id, 'outcome' => 'ignored_alumni']);
$this->assertDatabaseCount('scan_events', 1);
```

Add independent cases for ended enrollment, future-start enrollment, enrollment in an inactive year, and no active year. Include an existing attendance row and assert it remains unchanged when the student is ineligible. Keep valid credential error behavior intact.
- [x] **Step 2: Run** `vendor/bin/sail artisan test --compact tests/Feature/Attendance/AttendanceScanApiTest.php --filter=unenrolled`; expect failure because current engine checks in the student.
- [x] **Step 3: Implement the guard before attendance mutation.** Require an open (`ended_on IS NULL`) enrollment in the active academic year with `started_on <= effective local scan date`. If none, return the existing audited `log(...)` result with IgnoredAlumni. Do not use `classOn()` alone, since it can resolve an ended historical enrollment. Add the enum/coarse match case.
- [x] **Step 4: Repair positive scan fixtures explicitly.** In the API owner create an active year/class for the frozen date and use `enrolledIn`; keep ineligibility fixtures deliberately unenrolled. Search `rg -n 'AttendanceScanService|attendance.scan' tests` and adjust only tests expecting successful student attendance. Do not change `StudentFactory` default behavior or remove existing assertions.
- [x] **Step 5: Verify** `vendor/bin/sail artisan test --compact tests/Feature/Attendance/AttendanceScanApiTest.php tests/Feature/Staff/EmployeeScanTest.php`; expect all pass. Employee behavior and hardware authentication/error envelopes remain intact.
- [x] **Step 6: Commit this reviewed deliverable** as `fix(attendance): reject scans without active enrollment` after focused tests pass. Stage only Task 1 files, including any repaired owner fixtures.

### Task 2: Provision operator accounts and enforce the product boundary

**Dependencies:** None for role-only tests; scanner route access assertions finish with Task 3.

**Files:** Role/boundary/landing files in the map; extend `tests/Feature/{Users/UserManagementTest,Auth/MultiRoleAccessTest,Auth/AuthenticationTest,Auth/TwoFactorChallengeTest,DashboardTest}.php` and `tests/Unit/Authorization/RolePermissionsTest.php`; create `tests/Feature/Attendance/ScannerAccessTest.php`.

**Interfaces:** Add `Ability::OperateScanner = 'operate-scanner'` granted only to Admin, Teacher, ScannerOperator. `RestrictScannerOperatorAccess::handle(Request $request, Closure $next): Response` applies to web requests after account-active checks and before controller execution. New response classes implement the respective installed login contracts and override `toResponse($request): Response`, delegating unchanged behavior for other users.

- [x] **Step 1: Pin role/create-update failures and privacy assertions.** Use `User::factory()->withRoles(UserRole::ScannerOperator)`; request-role providers enumerate every other enum case for exclusivity, on both create and update. Assert persisted grants/account remain unchanged on rejection. Extend existing last-admin and student-exclusivity tests rather than reimplementing those guards. In ScannerAccessTest include:

```php
$this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('scanner.index'));
$this->actingAs($operator)->get('/settings/profile')->assertForbidden();
$this->actingAs($operator)->put('/settings/password', [])->assertForbidden();
$this->assertDatabaseCount('scan_events', 0);
```

Include linked employee GET/mutation denial, notification/announcement denial, profile-linked QR/self-service denial, and operator logout. For the scanner Inertia response assert `notifications` and `incomplete_class_count` are absent and the auth payload has only the intended identity/role fields. Seed an unread notification so a zero-count assertion cannot hide a leak.
- [x] **Step 2: Run the affected auth/users tests.** Expect missing enum/ability and access-boundary failures before implementation; do not skip tests to make the suite green.
- [x] **Step 3: Add enum, capability, role validation, and provisioning integration.** Extend both Form Requests' exclusive-role checks; honor legacy `role` and canonical `roles` normalization. Update the TS union, role list/toggle behavior, and both role translation files. Also audit exhaustive role matches/providers with `rg -n "UserRole::cases|UserRole::Student|Record<UserRole|roles\.student" app resources/js tests lang` and update role-label maps and expectation owners that must include the new role. Operators have no required profile link. Preserve `UserProfileLinker` behavior for existing roles and ensure converting a user to operator does not grant linked-profile self-service.
- [x] **Step 4: Enforce an explicit route-name boundary.** Allow operator `scanner.index`, `scanner.scan`, `dashboard`, and `logout`. Authentication completion uses existing routes `login`, `login.store`, `two-factor.login`, `two-factor.login.store`, `passkey.login-options`, `passkey.login`; these guest/challenge routes must remain functional. Public `home`, public password recovery, `.well-known.passkeys`, health, and static assets remain public; enumerate exceptions, never permit a blanket `settings.*`, `user.*`, or auth-only route group. Reject other protected product requests with 403 before their controllers run. Apply to held ScannerOperator role, not the UI active-role string. Keep account-active checks before the boundary.
- [x] **Step 5: Make login and shared props safe.** Override password, 2FA, and passkey login responses so operators discard stale intended product URLs and land at `scanner.index`; preserve existing JSON/redirect shapes for each contract and other roles. Bind these response contracts in FortifyServiceProvider. DashboardController returns `Response|RedirectResponse` with an early operator redirect before any read-model query. HandleInertiaRequests returns an explicit minimal operator auth payload plus locale/translations/layout primitives, without notification/dashboard/product closures. A JSON request from an account deactivated mid-session must receive an auth rejection, not a login HTML response misclassified as success; preserve browser redirect behavior in EnsureUserIsActive and add its file to this task if this adjustment is needed.
- [x] **Step 6: Verify** `vendor/bin/sail artisan test --compact tests/Feature/Users tests/Feature/Auth tests/Feature/DashboardTest.php tests/Unit/Authorization/RolePermissionsTest.php`. Add stale-intended-url tests for password and 2FA responses and installed passkey response contract, asserting Scanner redirect/JSON redirect and unchanged non-operator behavior. Run ScannerAccessTest after Task 3. Commit as `feat(scanner): isolate operator accounts and landing` only once its route-dependent tests pass.

### Task 3: Add the authenticated web scanner adapter

**Dependencies:** Tasks 1–2.

**Files:** New scanner controller/request/routes; providers/web include in the map; create `tests/Feature/Attendance/ScannerScanTest.php`, finish ScannerAccessTest.

**Interfaces:** `ScannerController::index(Request $request): Inertia\Response` renders `scanner/index` with no master data. `ScannerController::store(ScannerScanRequest $request, CredentialResolver $credentials, AttendanceScanService $students, EmployeeScanService $employees): JsonResponse` returns spec 19's envelopes. Routes named `scanner.index` (GET `/scanner`) and `scanner.scan` (POST `/scanner/scan`). Request `authorize(): bool` uses OperateScanner; `rules(): array` validates credential required/string/max:255.

- [x] **Step 1: Add a student check-in response test with real engine and enrollment.** Freeze school time before start, create `AcademicYear::factory()->current()`, its class, and enrolled student; issue token with `app(QrTokenService::class)->issue($student)->token`, then:

```php
$this->actingAs($operator)->postJson(route('scanner.scan'), ['credential' => $token])
    ->assertOk()->assertJson([
        'outcome' => 'checked_in', 'detail' => 'check_in',
        'student_name' => $student->full_name, 'subject_type' => 'student',
        'attendance_status' => 'present',
    ]);
$this->assertDatabaseHas('attendances', ['student_id' => $student->id, 'status' => 'present']);
$this->assertDatabaseCount('scan_events', 1);
```

Use existing factory namespaces/imports and RefreshDatabase. Add employee success/inactive tests, credential unknown/expired error envelopes, web alumni parity, status derivation for late and present absent upgrades, and one checkout/ignored result integration case. Do not duplicate all service permutations.
- [x] **Step 2: Verify red** with `vendor/bin/sail artisan test --compact tests/Feature/Attendance/ScannerScanTest.php`; expect missing route/adapter failures.
- [x] **Step 3: Implement thin dispatch and response mapping.** Use the spec's existing employee `scanResolved` versus student `scan` dispatch, passing `null` device time so engines choose server time. Never return CredentialResolver errors directly or log a second event in the controller. Build attendance_status from `$result->attendance?->status->value ?? $result->employeeAttendance?->status->value`; errors use only outcome/detail and HTTP 422. Do not modify the hardware response serializer. No browser timestamp is passed even if supplied.
- [x] **Step 4: Register auth, capability, and named user throttle.** Add `web-scanner` limiter using the existing AppServiceProvider pattern: `Limit::perMinute(60)->by((string) $request->user()->getAuthIdentifier())`. Wire routes through web/session/CSRF and `can:operate-scanner`; no scanner-key middleware or CSRF exemption. Verify middleware ordering refuses unauthorized attempts before engine calls.
- [x] **Step 5: Add rejection assertions.** Empty, whitespace-only, non-string, oversized credential: 422 validation envelope, zero events/mutations. Guest JSON: 401; insufficient role: 403. Rate-limit key must isolate two users sharing one IP and combine one user's sessions; request 61 returns 429 with `Retry-After`, with no extra event. Use malformed unknown credentials for accepted throttle attempts, assert exactly 60 error events. Verify server time by supplying a forged `scanned_at` and checking stored time, not just response. Exercise CSRF in a focused middleware-enabled request/browser check; standard Laravel test mode disables it, so ordinary postJson tests alone do not prove CSRF rejection.
- [x] **Step 6: Verify and generate routes.** Run `vendor/bin/sail artisan test --compact tests/Feature/Attendance tests/Feature/Staff/EmployeeScanTest.php`; expect pass, including access/privacy. Run `vendor/bin/sail artisan wayfinder:generate --with-form --no-interaction`; inspect `vendor/bin/sail artisan route:list --path=scanner`. Commit as `feat(scanner): add audited session-authenticated scan endpoint`.

### Task 4: Build and regression-test the submission state machine

**Dependencies:** Task 3's response contract; pure-state work can start before the camera package is approved.

**Files:** Create `resources/js/types/scanner.ts`, `resources/js/lib/{scanner-session,scanner-feedback}.ts`, `tests/frontend/scanner-session.test.mjs`; add `test:scanner` script through normal package.json editing (no dependency addition).

**Interfaces:** Export `createScannerSession(now: () => number): ScannerSession`. Methods: `start(): boolean`, `stop(): void`, `observe(qrPresent: boolean): boolean`, `settle(result: 'confirmed'|'unconfirmed'|'unauthorized'|'throttled', retryAfterMs?: number): void`, `resume(): boolean`, `snapshot(): ScannerSessionState`. `observe` returns true only when the caller may submit that frame and synchronously claims the in-flight slot. Export `scannerFeedback(result: ScanResponse): { tone: 'success'|'warning'|'checkout'|'ignored'|'error'; messageKey: string; sound: 'success'|'warning'|'error'|null }`. ScanResponse is a discriminated union of spec 19's HTTP 200 result and credential-error envelope; validation/transport failures are separate client states.

- [x] **Step 1: Add executable Node tests using injected elapsed time.** Import `test` from `node:test`, `assert` from `node:assert/strict`, and createScannerSession from `../../resources/js/lib/scanner-session.ts`. First test:

```js
test('slow response and rotating visible QR never rearm', () => {
    let now = 0;
    const scanner = createScannerSession(() => now);
    assert.equal(scanner.start(), true);
    assert.equal(scanner.observe(true), true);
    now = 3000;
    assert.equal(scanner.observe(true), false);
    scanner.settle('confirmed');
    now = 90000;
    assert.equal(scanner.observe(true), false);
    scanner.observe(false);
    now += 1000;
    assert.equal(scanner.observe(false), false);
    assert.equal(scanner.observe(true), true);
});
```

Add tests named `cooldown is global across different people`, `lost response pauses without replay`, `unauthorized cannot resume without restart`, `throttle requires delay and explicit resume`, `stop while pending cannot create a second request`, and `absent upgrade uses attendance status`. Assert 1999 ms rejects/2000 ms accepts only after removal and settlement; 999 ms absence is insufficient. For feedback use otherwise identical `absent_upgraded` results with present/late and assert distinct success/warning tones.
- [x] **Step 2: Run** `vendor/bin/sail npm run test:scanner`; expect missing module/export failures.
- [x] **Step 3: Implement the state transitions.** States: idle, ready, processing, waiting-removal, paused, unauthorized, throttled. Keep in-flight state synchronous rather than relying on a React rerender. Use performance.now in production, the injected clock in tests. No credential value is stored in session state. Reset removal timing whenever a QR is detected; token changes do not affect it. Stop invalidates readiness but does not pretend an outstanding request has settled. A separate explicit resume may re-enable detection after uncertainty/throttle but cannot replay data. Map exact detail/status values according to the spec table.
- [x] **Step 4: Verify** `vendor/bin/sail npm run test:scanner` and `vendor/bin/sail npm run types:check`; expect pass. No test should sleep or require a camera/network. Commit as `feat(scanner): guard submissions and map authoritative feedback`.

### Task 5: Integrate camera lifecycle, recoverable HTTP, scanner UI, and navigation

**Dependencies:** Tasks 2–4 and decoder approval/installation prerequisite.

**Files:** Camera/hook/page/copy/shared UI files in the map; approved package manifests; create `tests/frontend/scanner-camera.test.mjs`.

**Interfaces:** `ScannerCamera` is `{ start(): Promise<void>; stop(): Promise<void> }`; export `createScannerCamera(elementId: string, onFrame: (qr: string|null) => void, onEnded: () => void): ScannerCamera`. QR-present and no-decode observations feed the session state; default decode rate is 10 fps, configurable inside the adapter. `useScanner(): ScannerViewModel` owns start/stop/resume, transport, camera lifecycle, latest result/log, optional sound/fullscreen. ScannerViewModel exposes `state`, `result`, `log`, `start(): Promise<void>`, `stop(): Promise<void>`, `resume(): void`, and `toggleFullscreen(): Promise<void>`; result/log types derive from ScanResponse, never raw credentials.

- [x] **Step 1: Add lifecycle assertions around an injected decoder factory.** Permit an optional last adapter parameter `decoderFactory: DecoderFactory` for tests; production supplies the library implementation. DecoderFactory creates a handle with `start(onFrame): Promise<void>` and `stop(): Promise<void>`. In Node tests hold start unresolved, call Stop, resolve startup, and assert every fake track is stopped and no result callback is delivered. Repeat start/stop/start and assert at most one live decoder. Trigger ended and assert camera pauses. Callback after stop must not submit or update the log. No real browser API is needed for these adapter regressions.
- [x] **Step 2: Run** `vendor/bin/sail npm run test:scanner`; expect camera-module failures before implementation.
- [x] **Step 3: Implement the approved library adapter and hook.** Pair library cleanup with explicit track release; guard asynchronous startup/callbacks using an instance generation so late startup is cleaned up. React Strict Mode must not create two streams. Handle page hide/unmount/logout with stop and timer/listener cleanup. Recheck mounted/generation before applying responses; leaving a page does not imply a server request rolled back. Keep transport single-flight across restarts and ignore obsolete completion UI. Route POST through generated scanner action and the installed Inertia standalone HTTP client. Request JSON explicitly and retain normal CSRF handling. Distinguish credential-error 422 from validation 422; 401/403/419 halt, 429 honors Retry-After, timeout/network/5xx become unconfirmed with no retry. Default request timeout: 15 seconds; timeout must not clear the no-replay warning or claim rollback. Confirm installed client cancellation semantics before treating abort as terminal.
- [x] **Step 4: Implement the page using existing components.** Use Button, Card, Alert, existing layout/breadcrumb conventions, a default-camera viewfinder, explicit Start/Stop/Resume, live status text, latest card, newest-first bounded log, and optional fullscreen/audio. Copy comes from `lang/{id,en}/scanner.php`; reuse the spec's Indonesian text. Establish AudioContext on Start; catch blocked audio/fullscreen separately. A four-second result timer cannot block the next eligible scan; errors/processing states must remain accessible, including keyboard controls and aria-live status. Do not retain credential strings after request completion. Display only returned identity and status, not master-data links.
- [x] **Step 5: Make all UI doors work.** Add generated Scanner links for active admin/teacher and for operator. For operator replace default Dashboard/Pengumuman/other links with Scanner only; also change the sidebar brand/home destination to Scanner, remove notification bells, footer product links, settings links, and role switcher. Guard app-header alternative layout as well as app-sidebar-header. The global default AppLayout in `resources/js/app.tsx` remains usable; do not add a second parallel shell. Verify admin/teacher keep existing destinations and multi-role users keep their ordinary role switcher. Finish provisioning UI role exclusivity and translated role label from Task 2. For the log, add Node-testable `appendScannerResult(log: readonly ScannerLogEntry[], entry: ScannerLogEntry): ScannerLogEntry[]` to scanner-feedback.ts; ScannerLogEntry contains only receipt time and the normalized returned identity/result, never credential. Append 51 entries in scanner-session.test.mjs and assert length 50, newest first, oldest evicted, and no credential field. The hook drops all entries on exit/logout/loss of access; it must not use localStorage or sessionStorage.
- [x] **Step 6: Run** `vendor/bin/sail npm run test:scanner`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, and `vendor/bin/sail npm run build`; expect all pass. Manually run delayed-response, offline/lost-response, permission-denied, ignored-permission/cancel, tab-hide, busy/no-camera, and audio/fullscreen-blocked cases. Confirm no scan replay and camera indicator off after exit. Commit as `feat(scanner): add camera workflow and operator navigation` only after focused backend and frontend checks pass.

### Task 6: Complete delivery verification and update implementation status

**Dependencies:** Tasks 1–5.

**Files:** Update this plan's checkbox/evidence status, `docs/specs/{19-in-app-scanner,03-attendance,08-dashboard,15-foundation,README}.md` only when implementation actually satisfies their amendments.

**Interfaces:** No new runtime interfaces. Produces acceptance evidence tied to AC-19-01 through AC-19-16.

- [x] **Step 1: Run the combined regression gate.** Run `vendor/bin/sail npm run test:scanner`, then `vendor/bin/sail composer ci:check`. For PHP edits also run `vendor/bin/sail pint --dirty --format agent` and re-run affected tests if formatting changes code. Expect zero failing tests, JS checks, TypeScript errors, Pint differences, or PHPStan errors. Use `git diff --check` for the final diff. Do not claim the gate passed if the worktree runtime/dependency setup prevents execution.
- [ ] **Step 2: Verify navigation door to door.** Operator: admin creates account → logout → operator login → Scanner → Start → student and employee scans → logout. Admin and teacher: login/role switch → sidebar → Scanner → same scans. Include a teacher with no homeroom/subject assignments and a student outside teacher classes. Check direct forbidden GET and mutation endpoints. Inspect actual Inertia payloads/network requests for operator privacy, not only visible menus.
- [ ] **Step 3: Run the supported desktop matrix and ten-presentation fixture.** Record exact OS/browser versions, camera/device, permission outcomes, and server/network setup. For ten fresh eligible on-time check-ins under spec 19's ready-camera/warm-app/RTT ≤100 ms fixture, record camera-to-card latency; target ≤2 seconds each. Also verify late status, checkout, server debounce, inactive employee, alumni, expired QR, unconfirmed commit/lost response, role revocation, and cleared logs. Never use production student/employee data for synthetic scans.
- [x] **Step 4: Record limits honestly.** Distinguish automated state/HTTP coverage from manual real-camera checks. If any required browser or performance fixture is unavailable, keep that delivery criterion pending; do not mark spec 19 fully implemented. Remove draft amendment labels in related specs only for delivered behavior, retain historical status notes, and correct the README implementation row with evidence date.
- [x] **Step 5: Commit verified docs** as `docs(scanner): record implementation and verification evidence`; keep commits local unless the user separately asks to push or publish.

## Acceptance coverage map

| Criteria | Tasks |
|---|---|
| AC-19-01 login, shell, dashboard | 2, 5, 6 |
| AC-19-02 check-in and bounded performance | 3, 5, 6 |
| AC-19-03 credential errors | 3, 5 |
| AC-19-04 single-flight/cooldown/rearm | 4, 5 |
| AC-19-05 server debounce | 1, 3, 5 |
| AC-19-06 operator isolation | 2, 3, 6 |
| AC-19-07 authorized personas/entry paths | 2, 3, 5, 6 |
| AC-19-08 audit/rejection parity | 1, 3 |
| AC-19-09 camera failures/cleanup | 5, 6 |
| AC-19-10 employees/inactive | 3, 5 |
| AC-19-11 authoritative status/time | 3, 4 |
| AC-19-12 alumni prerequisite | 1, 3 |
| AC-19-13 role provisioning/exclusivity | 2, 5 |
| AC-19-14 session/revocation/logout/privacy | 2, 3, 5 |
| AC-19-15 ambiguity/throttle/no replay | 3, 4, 5 |
| AC-19-16 validation/log privacy/bounds | 3, 5 |

**Self-review:** All sixteen acceptance criteria have implementation/test owners. User chose native execution and approved html5-qrcode 2.3.8. Task 6 remains open for the physical-device/browser matrix and performance fixture; no full acceptance claim is made.

## Delivery evidence (2026-10-27)

- Local code commits: `9e8adbd` shared eligibility, `62f9f87` operator boundary/provisioning, `cee0da3` web endpoint, `d0f21ff` submission guards, `cd65320` camera/UI, `72368af` cancellation cleanup follow-up.
- Full `composer ci:check` in isolated Sail: JS formatting/lint, TypeScript, Pint, PHPStan passed; 1,748 PHP tests passed, 7 skipped (1,755 total, 9,435 assertions). The final camera/recovery corrections pass 20 Node scanner tests and TypeScript/build; the full final gate is rerun after review corrections.
- PostgreSQL 18: 89 focused attendance/scanner/employee/provisioning tests passed, 572 assertions. Schema uses `user_roles.role` varchar(30) and string scan outcomes; no migration required.
- Browser: Playwright HeadlessChrome 155 on macOS user agent, localhost port 8089, isolated synthetic accounts/data. Admin → Users → create operator; operator login → Scanner; teacher without class/profile assignments → dashboard/sidebar → Scanner; logout and operator Settings-link absence verified.
- Browser security: operator notifications GET 403; CSRF-less scan POST 419. Operator shared-prop privacy is covered with an actual unread-notification fixture.
- Synthetic camera: real html5-qrcode decodes a QR in a canvas capture stream and submits one actual request while continuously visible. Server processes the request but response is dropped → explicit unconfirmed pause, no replay; Stop leaves track ended. Delayed permission after Cancel → no live tracks and no video node. Permission denial gives retry instructions. No scanner key appears in browser scan headers.
- Remaining acceptance: physical webcam/phone lighting/readability, Windows Chrome/Edge and macOS Safari/Chrome checks, and ten fresh check-ins meeting the named two-second fixture. Synthetic camera evidence does not replace these requirements.
- Execution decisions: isolated Sail project protects other running applications; backend page tests omit Vite until the actual UI build; role and endpoint work was sequenced together for route-dependent tests; use Inertia's standalone HTTP client because installed useHttp discards credential-error 422 bodies; add the existing form's missing password confirmation; exclude the scanner-only role from announcement-reader test inputs while retaining all original personas.

### Whole-branch review and correction

Astra reviewed `67f8e6c..72368af`: no Critical issues, one Important recovery-state defect, and one Minor redundant-binding cleanup. The Important defect was reproduced with three failing tests, then corrected: uncertainty, authorization loss, and HTTP 429 deadlines survive camera Stop/Start and tab-hide; Resume requires explicit acknowledgement, and a stopped camera starts only afterward. Mounted-browser checks confirm one server-processed/response-lost request followed by Stop shows the unconfirmed warning, hides Start until recovery, stops tracks, and never replays; a 60-second HTTP 429 followed by Stop keeps Start hidden and Resume disabled.

Deferred minor: `FortifyServiceProvider` repeats the three response bindings in the password-authentication callback, despite already registering them in `register()`. The repeated definitions are identical; the reviewer classified this as cleanup, and the native workflow leaves minors outside the correctness fix pass.

### Execution rulings and tradeoffs

| Decision | Reason | Cost if wrong |
|---|---|---|
| Isolated Sail project/ports | Preserve other running apps | Runtime setup rework |
| Backend page test omits Vite until UI build | Separate backend contract from assets, then verify actual page in browser | Missing assets caught at UI/build gate |
| Sequence operator boundary and scan routes together | Access tests require the scanner routes | Review both commits together |
| Inertia standalone HTTP client adapter | Installed useHttp discards credential-error HTTP 422 bodies | Adapter rework |
| Add password confirmation to provisioning form | Browser-proven server validation prevented user creation | One additional creation field |
| Exclude operators from announcement reader provider | Operators have no announcement permission; all original personas remain tested | Separate targeting-assumption reconciliation |
| Keep physical-camera/browser acceptance pending | Only synthetic-camera Chromium is available | Real-device usability/performance may require correction before release |
| Keep mobile/multi-camera/offline/idempotency/source analytics deferred | Explicit spec non-goals | Later feature amendment for those workflows |

Final gate after the Important review fix: `composer ci:check` passed (1,748 PHP tests executed/passed, seven skipped, 9,435 assertions; JS lint/format, TypeScript, Pint, PHPStan passed). Scanner Node tests: 20/20. The mounted UI also preserves the unconfirmed warning after a synthetic visibility-hide/return event and releases tracks. All implementation commits remain local. Physical-camera/browser acceptance is still pending, so Task 6 is not marked complete and the ignored progress workspace is retained for follow-up.
