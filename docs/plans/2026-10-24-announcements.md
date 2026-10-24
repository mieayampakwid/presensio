# Announcements implementation plan

> Execute each task in order against spec 12 v1.2. This replaces `2026-10-02-announcements.md`. User authorized implementation and every-role testing on 2026-10-24. No automatic commit, push or deployment.

**Delivery status:** Completed 2026-10-24. Concrete evidence and the browser DELETE restriction are recorded below.

**Goal:** Deliver a targeted, private bulletin board with teacher/manager authoring, read/acknowledgement, reports, dashboard entries and recoverable notification publication.

**Architecture:** `AnnouncementAudience` owns reader eligibility and independently scoped teacher authoring. `AnnouncementService` owns transactional writes/files/receipts; `AnnouncementPublisher` captures notification recipients once and dispatches through the existing pipeline. Thin management/reader controllers return Inertia pages using server capabilities. One set of pages supports feed, management and class contexts.

**Prerequisites:** Laravel 13.31.0, Inertia 3.3.4, Wayfinder 0.1.21, PHPUnit 12.5.35, React 19 and Inertia React 3 confirmed. Node commands run only through Sail. No new package, environment variable or external integration. CommonMark 2.10.1 is installed; upstream 2.10.2 fixes the two advisory issues. Ask for explicit approval to run `vendor/bin/sail composer update league/commonmark --with-dependencies --no-interaction` before changing the dependency. Meanwhile implement safe core-only rendering; no vulnerable optional extension is used. Do not run migrations/reset against business data for browser fixtures.

## File map

| Area | Files |
| --- | --- |
| Domain/schema | `app/Enums/AnnouncementAudienceType.php`, `app/Models/Announcement.php`, `app/Models/AnnouncementReceipt.php`, generated migrations `create_announcements_table`, `create_announcement_receipts_table`, factories |
| Domain services | `app/Services/Announcements/AnnouncementAudience.php`, `AnnouncementService.php`, `AnnouncementPublisher.php`, `AnnouncementReadModel.php`, `MarkdownRenderer.php` |
| HTTP | `app/Http/Controllers/Announcements/AnnouncementController.php`, `AnnouncementReaderController.php`; `app/Http/Requests/Announcements/SaveAnnouncementRequest.php`; `routes/announcements.php`, `routes/web.php` |
| Schedule/dashboard | `app/Console/Commands/PublishDueAnnouncements.php`, `routes/console.php`, `app/Http/Controllers/Dashboard/DashboardController.php` |
| UI | `resources/js/pages/announcements/index.tsx`, `form.tsx`, `show.tsx`, `recipients.tsx`; `resources/js/components/app-sidebar.tsx`, `resources/js/pages/classes/index.tsx`, `resources/js/pages/dashboard.tsx` |
| Regression proof | `tests/Feature/Announcements/AnnouncementTest.php`, `AnnouncementPublicationTest.php`, `tests/Unit/Services/Announcements/MarkdownRendererTest.php`; preserve existing scope/dashboard/notification tests |

## Task 1: Schema and safe text rendering

**Dependencies:** none. **Verification:** model lifecycle exercised by Task 2; renderer owner independently executable.

- [x] Scaffold with `php artisan make:model Announcement --factory --no-interaction`, `php artisan make:model AnnouncementReceipt --factory --no-interaction`, `php artisan make:enum AnnouncementAudienceType --string --no-interaction`, and `php artisan make:migration create_announcements_table --create=announcements --no-interaction` / `create_announcement_receipts_table`. Use actual generated timestamps.
- [x] Implement spec schema including captured `academic_year_id`, JSON nullable `notification_recipients`, nullable `notifications_dispatched_at`, restrictive author/class/year FKs, receipt ID and unique announcement/user key. Both `down()` methods drop only their own tables. Target enum cases are `All`, `Role`, `GradeLevel`, `Classroom`; values `all`, `role`, `grade_level`, `class`. Model casts booleans, UTC datetimes and recipient JSON; relationships use actual profile fields (`teacher.employee.user`).
- [x] Renderer uses the installed converter API and only its core extension:

```php
$environment = new Environment([
    'html_input' => 'strip', 'allow_unsafe_links' => false,
    'max_nesting_level' => 50, 'max_delimiters_per_line' => 1000,
]);
$environment->addExtension(new CommonMarkCoreExtension);
return (string) (new MarkdownConverter($environment))->convert($body);
```

Validate body at 50,000 characters. No GFM, Attributes or raw-HTML-filter extension; server-produced HTML is the only content passed to `dangerouslySetInnerHTML`. Treat title/excerpt/attachment name as plain React text.
- [x] Create the renderer test with Artisan and assert scripts/raw HTML/javascript links absent while a Markdown heading and safe link remain. Run `php artisan test --compact tests/Unit/Services/Announcements/MarkdownRendererTest.php`.

## Task 2: Audience, writes and private files

**Dependencies:** 1. **Verification:** `php artisan test --compact tests/Feature/Announcements/AnnouncementTest.php`.

- [x] `AnnouncementAudience::visibleTo(User, bool $liveOnly = true)` builds a grouped SQL query; no per-announcement calls to `classOn()`. Gather class IDs with `enrollments.activeOn(SchoolSettings::todayDate())`, student's user relation, guardian-child relation, and the linked teacher's homeroom/subject assignments. Admin/principal bypass audience but not feed live filtering. Owner detail access includes drafts. Captured class/year target prevents future cohort drift. User-role targets use account role union.
- [x] `teachingClassIds(User)` requires Teacher role and active linked employee, then queries teacher ID directly. It never takes the counselor-wide branch of `ClassAccess`. `canManage(User, ?Announcement)` returns manager capability or author+current teaching scope; `canReport` has the same teacher guard. No request state in singleton/static properties.
- [x] Request authorization runs before validation for forged authoring/foreign-class audiences (403). Rules enforce one matching target, installed role values, valid active-year class/grade, PDF/JPG/PNG ≤5MB, 50,000-character body and 255-character title. Form date is school-local and converted to UTC. HTTP never accepts recorder IDs, captured snapshots or dispatch marker. No active year returns 422 for class/grade only.
- [x] Service locks updates/deletes, freezes live target/flags/date, uploads privately with hash name and retains original name. Rollback deletes a new upload; committed replace/delete deletes old files. Persist audits through `AuditLogger`. Save returns committed record before attempting publication; a fanout failure is reported and recovered by scheduler without rolling back a saved announcement.
- [x] Regression cases: role teacher excludes parent/student; current/ended enrollment; parent with two children; grade/year rollover; draft/future; scoped subject teacher; teacher+counselor rejection; foreign report/file; target-field errors; immutable live fields; attachment replacement/delete/failure cleanup. Assert rejection leaves persisted announcement/file facts unchanged.

## Task 3: HTTP routes and receipt/report read models

**Dependencies:** 2. **Verification:** same feature owner, covering real endpoint behavior.

- [x] Register `announcements/manage`, `announcements/create`, `announcements/{announcement}/edit`, `classes/{school_class}/announcements` before the parameterized detail route. Names use `announcements.index/manage/create/store/edit/update/destroy/show/acknowledge/attachment/recipients` and `classes.announcements.index/create/store`. All reads authenticate; writes check server authority. Include `routes/announcements.php` from `routes/web.php`.
- [x] Feed: search title/body with `whereLike`, local-date UTC bounds, validated filters, 10/page and pinned/publication/ID descending. Management/context feeds expose draft/scheduled states and per-row capabilities. Do not shadow shared Inertia props; use `announcements`, `announcement`, `recipientReport`.
- [x] Detail authorize before marking read. Use unique-key insert-ignore for first read; acknowledgement inserts if absent then updates only a null `acknowledged_at`. Repeated opens/ack retain first timestamps. Non-required ack is 422; draft/future/invisible ack is 403. Attachment/feed/report/dashboard do not mark read.
- [x] Per-student report uses current eligible enrollments in captured year, eager-loaded guardian/user receipts, one student row, any guardian read/ack, no guardian/accountless labels and page size 30. Role/all reports use actual eligible active users; manager inspection privilege is not recipient membership. Apply unread/unack filters before pagination. Student receipts never satisfy guardian completion.
- [x] Required owner assertions include:

```php
$this->actingAs($parent)->get(route('announcements.show', $announcement))->assertOk();
$first = $announcement->receipts()->where('user_id', $parent->id)->sole()->read_at;
$this->actingAs($parent)->post(route('announcements.acknowledge', $announcement))->assertRedirect();
$this->assertTrue($first->equalTo($announcement->receipts()->where('user_id', $parent->id)->sole()->read_at));
$this->assertNotNull($announcement->receipts()->where('user_id', $parent->id)->sole()->acknowledged_at);
```

Separate negative tests assert zero foreign receipts and immutable records after 403/422. Report fixture has 30 students plus duplicate-guardian siblings, one no-guardian child and an accountless guardian.

## Task 4: Publication and notification recovery

**Dependencies:** 2–3. **Verification:** `php artisan test --compact tests/Feature/Announcements/AnnouncementPublicationTest.php`.

- [x] `AnnouncementPublisher::publishDue(Announcement)` locks/captures identity JSON once; null means uncaptured, empty list means captured nobody. Resolve active user audience excluding manager-only inspection grants, and accountless guardians for all/parent/class/grade. Prefer user identity for linked profiles so siblings/multiple roles yield one account recipient. Commit snapshot before fanout; never wrap `NotificationDispatcher::dispatch` inside the snapshot transaction and prematurely mark completion before its afterCommit work.
- [x] Dispatch generic title/body and named detail route with stable `dedupeBase = "announcement:{$announcement->id}"`. Process `AnnouncementUrgent` only for urgent flag and `AnnouncementAckRequired` only for ack flag. Recheck current eligibility for saved identities, skip revoked/deleted recipients, never grow the snapshot on retry. Set completion only after every selected dispatch returns. A failure leaves marker null; same identities retry and existing dispatcher keys prevent duplicate in-app/delivery rows. Respect existing catalog and channel settings.
- [x] Artisan command `announcements:publish-due` selects due uncompleted rows in bounded chunks. Register every minute with `withoutOverlapping`; no raw external sends in HTTP/scheduler. Initial immediate write attempts publication and command is recovery for its failure. Deletion safely skips stale work. Existing notification queue is retained.
- [x] Tests freeze school time; future item produces zero notices, due item sends once, next run no-ops, both flags produce two types, no-flag publication completes, siblings dedupe, disabled users excluded, accountless guardian uses external channel only. Simulated midway failure retries fixed audience and does not notify a new enrollee. A removed recipient cannot receive body/title through stale notifications because payload is generic. Fake outbound HTTP and queue; do not contact WAHA/real email.

## Task 5: Reader/manager/teacher pages and navigation

**Dependencies:** 3–4. **Verification:** Sail lint/types/build plus role browser table in Task 7.

- [x] Build `announcements/index.tsx` for feed/manage/class contexts, with search/date filters, pagination, statuses/pin badges and capability-based actions. `form.tsx` handles create/edit, audience options, school-local schedule, private file replace/remove and errors. Teacher class context locks audience; ordinary roles never see mutation buttons. Reuse existing form/dialog/table components and Wayfinder controller imports.
- [x] `show.tsx` renders safe HTML, original attachment label/download and required ack button with loading/idempotent confirmation feedback; managers/authorized author get edit/delete/report links. `recipients.tsx` renders student or user report, filters, no-account states and pagination. Required acknowledgement is explicitly a read confirmation, not consent.
- [x] Sidebar adds primary `Pengumuman` for all installed roles. Manager action is inside feed. Existing `Classes` listing adds a contextual `Pengumuman kelas` row action only where user can author; return that capability from `SchoolClassController` with independent teacher-scope checks. Do not expose class mutations to teachers or open unrelated class data.
- [x] Regenerate with `vendor/bin/sail artisan wayfinder:generate --with-form --no-interaction`; run `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`.

## Task 6: Dashboard and role regression matrix

**Dependencies:** 3,5. **Verification:** `php artisan test --compact tests/Feature/Announcements tests/Feature/DashboardTest.php tests/Feature/Classes`.

- [x] Add `announcementSummary` with the top three live relevant entries and `unacknowledged_count` over all live relevant required notices. Dashboard remains a read-only request. Widget links through Wayfinder to archive/detail, visible for all role layouts. Reuse audience service so feed/detail/dashboard agree.
- [x] Feature owner enumerates `UserRole::cases()` to prove every role receives `all`, matching role notices and the sidebar entry; only admin/principal can create globally, teacher scoped class only. Role switch does not change union capability. Unauthorized POST/edit/delete/ack/report/file tests assert status and no side effects. Detail reads/ack are the only receipt writers.

## Task 7: End-to-end acceptance and close-out

**Dependencies:** all. **Verification:** every row below must have real browser evidence or explicit unavailable status.

| Persona | Clickable journey | Expected proof |
| --- | --- | --- |
| Admin | Login → Dashboard → Pengumuman → Kelola → create live role/class/grade/all → detail/report | Correct targets, private file, urgent/ack flags, CRUD and teacher controls |
| Principal | Same management journey | Same management capability; report data authorized |
| Teacher homeroom | Login → Classes → own class Pengumuman kelas → create → own detail/report | Locked class audience; no all/urgent/out-of-scope writes |
| Subject teacher | Classes → assigned class → same journey | Subject-only assignment suffices; unrelated class absent/403 |
| Parent | Dashboard → Pengumuman → detail → Saya sudah membaca | Union of linked children's classes, role/all feed, private attachment; foreign class hidden/403 |
| Student | Dashboard → Pengumuman → detail/ack | Own current class and role/all only; no manage/report controls |
| Counselor | Dashboard → Pengumuman → detail/ack | Role/all; no teacher authoring from counselor-only scope |
| Finance | Dashboard → Pengumuman → detail/ack | Role/all and no authoring unless multi-role |
| Staff | Dashboard → Pengumuman → detail/ack | Staff target works; no authoring controls |
| Multi-role | Switch Role → Dashboard/sidebar → feed/class context | Union permissions retained; teacher+counselor cannot manufacture a teaching assignment |
| Accountless guardian | Manager recipient report plus fake delivery proof | Explicit no-account state, eligible external delivery only, no acknowledgement |

- [x] Use disposable `testing` data only, isolated server, built assets and no live external messages. Check draft/future exclusion, pinning, empty state, malicious Markdown, file errors, mobile sidebar, keyboard dismiss, loading, reports, date filters and pagination. Run direct authenticated CSRF POST probes for each role boundary; save pre/post operational state for rejected writes. Resolve project URLs via Boost if available; otherwise use the isolated server URL and never present it as a deployment URL.
- [x] Run `vendor/bin/pint --dirty --format agent`, narrow changed owner suites, `vendor/bin/sail composer ci:check` and build. If dependency update was approved, run `composer audit --locked`. Verify migrations with guarded disposable database, not application resets. Move temporary browser artifacts outside repository before frontend gates; stop isolated server.
- [x] Update spec/index and this plan with exact test counts/browser results only after acceptance passes. Keep partial/unavailable proof explicit. No claim that simulated WhatsApp delivery proves real gateway operation.

## Coverage and baseline record

AC-12-01…08 → Tasks 2,3,5; AC-12-09…10 → Task 3; AC-12-11…13 → Tasks 2,5,6; AC-12-14…15 → Task 4; AC-12-16…18 → Tasks 2,3; AC-12-19 → Tasks 3,6; AC-12-20 → Task 4. Task 7 exercises all role entry paths and closes the proof.

Review baseline (before implementation): 23 scope/dispatcher/type tests, 121 assertions; 36 dashboard/inbox/delivery tests, 382 assertions; all passed. `php artisan route:list --path=announcements --no-interaction` found no routes. These are dependency proofs, not announcement acceptance results.


## Completion and evidence — 2026-10-24

All seven tasks shipped. The implementation stays within the existing Laravel/Inertia/Tailwind component conventions; no new UI system or notification transport was introduced. The old plan's composite Eloquent receipt key was replaced with an ID plus unique announcement/user constraint. The unused scaffolded receipt factory was removed because all receipt fixtures use the real read/ack service. Routes keep the existing flat URL convention; a typed optional class parameter ensures route-model binding happens before the shared class POST request validates its locked audience.

- **Feasibility verdict:** v1.1 had actionable gaps and was revised to v1.2 before implementation. The Staff audience, role-union capability, counselor/teacher scope separation, captured academic year, live-field immutability, generic notification privacy, first-read semantics and resumable recipient snapshot are explicit contracts. Teacher class listings also apply reader visibility so other authors' drafts/scheduled items are not exposed.
- **Dependency approval:** user explicitly approved the proposed CommonMark patch command. Composer installed `league/commonmark` **2.10.4**, `nette/schema` 1.4.0, `nette/utils` 4.1.6 and `symfony/polyfill-php80` 1.43.0; no new package was added. `vendor/bin/sail composer audit --locked --format=json` completed with empty advisory and abandoned-package lists.
- **Focused proof:** the announcement/renderer/dashboard/classes set passed **92 tests / 906 assertions** after the class-route binding correction; the final archive-edit change additionally passed its owner, **21 tests / 335 assertions**. The route-tampering test had first reproduced the defect as a 302 instead of 403. A final full gate includes the archive fix.
- **Full CI on settled source:** `vendor/bin/sail composer ci:check` passed formatting/lint, TypeScript, Pint and PHPStan, then **1,644 cases: 1,637 passed, 7 existing PostgreSQL-only skips, 8,728 assertions**. An initial attempt stopped on one formatter discrepancy; container Pint corrected it before the successful gate. No test was weakened or skipped to pass. `vendor/bin/sail npm run build` passed; generated actions/routes use form variants.
- **PostgreSQL:** configuration was explicitly checked as database `testing`, then the two announcement feature owners executed there: **25 tests / 362 assertions, zero skips**. This verifies the role queries, receipt uniqueness, lifecycle and publication behavior on the application database engine. The seven unrelated concurrency cases skipped by the main SQLite suite were not rerun for this feature; no new real-lock contention claim is made.
- **Migrations:** both new migrations were installed, rolled back together and installed again on `/private/tmp/presensio-announcements-20261024/rollback.sqlite`. After verifying `app.env=local` and all older migrations already applied, only the two new additive migrations were applied to the local application database. No demo notices, profiles, receipts or transactions were inserted there; all browser interactions used the separate test database below.
- **Reader browser matrix:** actual login → Dashboard/widget → sidebar Pengumuman → feed → pinned circular → detail → private PDF → acknowledgement succeeded for **admin, principal, teacher, counselor, finance, staff, parent and student**. The script-injection fixture did not execute. Managers alone saw global management actions. Parent feed combined both children's classes and the relevant grade notice; the student saw only their own class. Staff/counselor/finance had their role/all notices. Draft/future items stayed out of ordinary feeds, top items were pinned and the acknowledgement badge changed after confirmation.
- **Author browser journeys:** admin → Pengumuman → Kelola → create a Staff notice with private PDF and required acknowledgement → publish → edit body → recipient report succeeded. Live audience fields were disabled. Delete confirmation opened and Escape dismissed it. Principal used the same entry path to create a draft. Homeroom teacher → Classes → 5A row action → create/publish/report and subject-only teacher → Classes → 6B row action → create/publish/report succeeded with a locked class audience. The final route-bound teacher create was repeated successfully after the binding fix.
- **Reports and mixed roles:** subject-class report showed the linked guardian plus the accountless mother and explicitly labelled her lack of an account. A parent acknowledged the subject notice. A Principal+Staff account switched to the Staff UI and retained the authorized management link/capability. A Teacher+Counselor account without an assignment could not author for any unrelated class. The 30-student report, student-versus-guardian acknowledgement distinction and filtered/no-account cases have feature-test proof.
- **Denied writes and private reads:** seven non-manager personas (six ordinary roles plus Teacher+Counselor) attempted create-global, update-foreign and class-out-of-scope POSTs through real authenticated browser sessions with CSRF headers: **21/21 returned 403**. Saved before/after counts of announcements, receipts, audits, notifications and deliveries were identical. A further class-route/body-target mismatch returned 403 after the regression fix. Parent direct foreign detail, attachment and report requests each returned 403. HTTP DELETE refusal and successful deletion/file cleanup are covered by the isolated feature tests.
- **Browser limitation:** automatic approval review rejected the initial batch because it included DELETE of a fixture announcement, treating deletion as outside the explicitly authorized POST probes. That batch was not executed. A safer POST-only batch was run; no indirect browser deletion bypass was attempted. Deletion's server authorization and committed file/receipt cleanup passed the automated owner tests; browser evidence for actual delete submission remains unavailable. The UI confirmation and keyboard dismissal were verified.
- **UI evidence:** desktop (1440 px) and mobile (390 px) screenshots of the live reader feed were inspected together. Mobile sidebar navigation worked and horizontal overflow was false. Search produced a deliberate empty state and recovered. One mechanical detector warning targeted Markdown blockquote styling (`border-l-2`), a quote affordance rather than a card accent; no unrelated visual change was made. The scope preserves the application's existing typography, neutral colors and component language.
- **Cleanup and practical limits:** browser fixtures/screenshots, failed-probe snapshots and logs remain under `/private/tmp/presensio-announcements-20261024`. The isolated server was stopped. Expected intentional 403 console entries are distinguished from application errors. WhatsApp/email routing was tested with fakes/queued delivery assertions; no real external message was sent. Existing production scheduler/worker deployment configuration is unchanged. No commit, push or deployment was performed; the pre-existing `.gitignore` change remains separate.
