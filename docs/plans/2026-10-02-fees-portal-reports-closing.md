# Fees C — Guardian Portal, Verification, Reports & Closing Implementation Plan

> **For implementer agents:** Spec: `docs/specs/14-school-fees.md` Requirements 7, 8, 12 (remaining reports), 13 (year-end), §Accounting periods & closing; ACs 14-06, 07, 10, 12, 14-19…22, 24; AC-17 for fee notifications. Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Guardian bill portal and transfer upload, treasurer verification queue with duplicate-proof detection, receivable reports (card, aging, reconciliation), period-close preconditions, year-end closing, and fee notifications.

**Architecture:** Portal and queue are thin controllers over `PaymentService` (Fees B). `ReceivablesReconciliation` is the keystone read service: it computes subledger vs GL for each receivable account and prepaid revenue and is reused by the report, the month-close precondition, and tests. Year-end closing is a single posted entry via `JournalPoster`.

**Prerequisites:** Fees B merged; Plan 4 for Task 7.

**Blocking questions:** none. **Revised 2026-10-17** after Fees B shipped: `PaymentService::{submitTransfer,verify,reject,duplicateProofWarnings}`, the notification types, the bank fields and `bill_reminder_days_before` already exist, so Tasks 1, 2 and 7 are HTTP/UI/wiring. Added: Task 0 (`default_transfer_account_id` setting), Task 1b (student read-only page), year-end closing closes the last month itself and locks the year (see spec 14 Decisions). The working slice list is in the approved session plan; slice order: 0, 1, 1b, 2, 3, 4, 5, 6, 7, 8.

---

### Task 1: Guardian portal and transfer upload

**Files:**

- Create: `app/Http/Controllers/Parent/FeeController.php` (`index`, `store`), `app/Http/Requests/Parent/StoreTransferRequest.php`, `resources/js/pages/parent/fees.tsx`, upload modal component
- Modify: `routes/parent.php`
- Test: `tests/Feature/parent/FeePortalTest.php`

**Dependencies:** Fees B · **Verification:** `php artisan test --compact tests/Feature/parent/FeePortalTest.php`

- [ ] **Step 1:** `GET my-fees`: bills for linked children (guardian ↔ `guardian_student`), totals (belum dibayar, menunggu verifikasi, lunas), per bill period/due/net/paid/status + pending flag derived from pending allocations; school bank account from the profile; payment history with reject reason and receipt link for verified.
- [ ] **Step 2:** Upload: bills must be open and belong to linked children; transfer date, origin bank, amount, proof (JPG/PNG/PDF ≤ 5 MB, private disk), SHA-256 `proof_hash` stored; creates a `pending` payment with proposed allocations (Σ = amount). AC-14-06: bill stays `unpaid` with the pending flag. Unlinked student's bill → 403 (AC-14-12). Guardian cannot exceed a bill's remaining balance (422).

### Task 2: Verification queue

**Files:**

- Create: `app/Http/Controllers/Fees/VerificationController.php` (`index`, `verify`, `reject`, `proof`), `app/Http/Requests/Fees/{Verify,Reject}TransferRequest.php`, `resources/js/pages/fees/verifications.tsx`
- Modify: `routes/fees.php`
- Test: `tests/Feature/fees/VerificationTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/fees/VerificationTest.php`

- [ ] **Step 1:** Queue shows student(s), allocations, amount, date, bank, proof preview (authorized private route), duplicate warning (matching `proof_hash` on another non-rejected payment — AC-14-10). Verify may re-allocate (validated as in Fees B), sets `verified`, posts per accrual rule, assigns receipt number, recomputes bills. Reject requires `reject_reason`; posts nothing; AC-14-07: a bill with Rp 200.000 verified earlier remains `partially_paid` after a rejection.
- [ ] **Step 2:** Finance/admin only; principal read-only list (verify → 403).

### Task 3: Remaining reports

**Files:**

- Create: `app/Services/Finance/Reports/{StudentReceivableCard,ReceivablesAging}Report.php`, `app/Services/Finance/Reports/CollectionsByClass.php`, controllers/pages under `resources/js/pages/finance/reports/`
- Modify: `app/Http/Controllers/Finance/ReportController.php`, `routes/finance.php`
- Test: `tests/Unit/Services/Finance/Reports/AgingTest.php`, `tests/Feature/finance/ReceivableReportsTest.php`

**Dependencies:** Fees B · **Verification:** `php artisan test --compact tests/Unit/Services/Finance/Reports tests/Feature/finance/ReceivableReportsTest.php`

- [ ] **Step 1:** Student receivable card: bills, allocations, write-offs, running balance. Aging buckets: belum jatuh tempo, 1–30, 31–60, 61–90, >90 days past due, per class and student. Class grouping by `classOn(today)`; students without open enrollment under "Alumni / Keluar". Withdrawal "needs review" list (Fees B Task 8) shown here. CSV with BOM; principal read.

### Task 4: Receivables reconciliation + month-close preconditions

**Files:**

- Create: `app/Services/Finance/ReceivablesReconciliation.php`, `app/Services/Finance/ClosePreconditions/ReconciliationPrecondition.php`, report page
- Modify: `app/Services/Finance/PeriodService.php` (register precondition), `ReportController`
- Test: `tests/Unit/Services/Finance/ReceivablesReconciliationTest.php`, `tests/Feature/finance/PeriodCloseTest.php`

**Dependencies:** Tasks 1–3 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance tests/Feature/finance/PeriodCloseTest.php`

- [ ] **Step 1:** Invariant (spec): per receivable account, GL balance = Σ over recognized, non-cancelled, non-written-off bills of (net − allocations made after recognition); prepaid revenue balance = Σ allocations to not-yet-recognized bills. Report shows both sides and difference.
- [ ] **Step 2:** Fuzz test (seeded): random operation sequences (generate, pay, void, discount change, cancel, write-off) ⇒ trial balance balanced and difference 0 (AC-14-19). Month close with a deliberately corrupted subledger row → 422 (AC-14-20).

### Task 5: Year-end closing

**Files:**

- Create: `app/Services/Finance/YearEndCloser.php`, controller action in `PeriodController`, UI button on `periods.tsx`
- Test: `tests/Feature/finance/YearEndClosingTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Feature/finance/YearEndClosingTest.php`

- [ ] **Step 1:** Fiscal year from `fiscal_year_start_month`. All periods of the year must be closed first; post one balanced `closing` entry zeroing every revenue and expense account into `net_assets_closing_account_id`, then lock the year (AC-14-22). Contra accounts handled by their opposite normal balance. Re-running is rejected.

### Task 6: Principal read-only + access matrix tests

**Files:**

- Test: `tests/Feature/fees/FeeAccessTest.php`

**Dependencies:** Tasks 1–5 · **Verification:** `php artisan test --compact tests/Feature/fees/FeeAccessTest.php`

- [ ] **Step 1:** Table-driven matrix: finance/admin full; principal reports + ledger 200, any write 403 (AC-14-12, 14-24); teacher/counselor `fees` 403; parent own children only; student read own.

### Task 7: Fee notifications and reminder job (needs plan 4)

**Files:**

- Modify: `PaymentService::verify/reject` (dispatch `payment_verified` / `payment_rejected` after commit, `dedupeBase = "payment:{id}"`)
- Create: `app/Console/Commands/SendBillRemindersCommand.php` (daily 07:00 school time via `routes/console.php`; `bill_reminder_days_before` from settings; one reminder per guardian per day covering all children's open bills — AC-17-06; dedupe `bill_due_reminder:{guardian_id}:{date}`)
- Test: `tests/Feature/Notifications/FeeNotificationTest.php`

**Dependencies:** Task 2, Plan 4 · **Verification:** `php artisan test --compact tests/Feature/Notifications/FeeNotificationTest.php`

- [ ] **Step 1:** Rejected notice includes the reason only in the portal (payload has no reason text — AC-17-10); reminders skip bills not due in exactly N days and bills already `paid/waived/cancelled/written_off`.

### Task 8: Close out

- [ ] `composer ci:check`. Propose spec 14 → implemented; flag the go-live checklist: accountant review of chart + posting rules, opening-balance journal, queue worker for `notifications`, scheduler for `finance:recognize-bills`.

## Browser verification — 2026-10-24

Verification covered the Fees C portal, transfer queue, reports and closing slice using the existing built frontend, Playwright and an isolated PHP server. Docker/Sail was unavailable. The server used only `/private/tmp/presensio-spec14-browser-20261024/testing.sqlite`; factories and real finance services prepared users, linked children, one unrelated student and balanced bill/payment journals. No development business data, product code or dependencies were changed.

- **Pass — guardian entry path:** login → Dashboard → Biaya Sekolah showed only the two linked children and the configured bank. Uploading a PDF for Rp350,000 kept its bill unpaid with a pending badge; the finance sidebar queue received the transfer. The private proof endpoint returned PDF/200.
- **Pass — verification/rejection:** finance login → Dashboard → Verifikasi Transfer. An empty rejection reason disabled submission. Rejecting a Rp150,000 claim preserved the bill's existing Rp200,000 payment and partially-paid status; the guardian saw the rejection reason. Verifying the other Rp350,000 transfer paid its bill, removed it from the queue, assigned KWT-202610-00002 and produced a PDF receipt (200). The empty queue rendered correctly. Uploading the identical proof for the sibling displayed a warning referencing the earlier verified payment; the duplicate was then rejected.
- **Pass — reports:** finance sidebar → Rekonsiliasi Piutang showed GL/subledger Rp850,000 and zero difference; Trial Balance balanced at Rp1,400,000 per side before the separate prior-year fixture. Umur Piutang showed Rp700,000 at 1–30 days and Rp150,000 at 31–60 days, with the partially-paid unenrolled child in the review section. Penerimaan per Kelas showed Rp550,000 received and Rp850,000 receivable under Alumni / Keluar. Kartu Piutang Siswa → search QA14 Budi → select student showed both bills, the two payments and matching GL/subledger Rp150,000. General Ledger and Cash Book rendered. CSV downloads for aging, collections, student card and reconciliation returned 200, UTF-8 BOM and amounts matching their screens.
- **Pass — month close:** finance sidebar → Accounting Periods → October close confirmation; Escape dismissed the dialog, confirmation closed the month, reopening required a reason and restored open status. A test-only Rp100 bill/subledger discrepancy caused close to show an actionable reconciliation error and retain open status. The discrepancy was restored afterward.
- **Pass — year close:** separate prior-year fixture posted Rp1,000,000 bank/revenue activity and prepared the first eleven months as closed. Finance → Accounting Periods → 2025-07 through 2026-06 → confirm year close created one closing entry dated June 30, debiting revenue and crediting net assets Rp1,000,000, and locked all twelve months. The completed year disappeared from the closing choices and locked rows had no reopen action. Persisted entry/period evidence is in the temporary fixture directory.
- **Pass — student/principal read paths:** student login → Dashboard → Biaya Sekolah displayed only that student's bills/history with no upload controls or sibling/unrelated data. Principal login → Dashboard → General Ledger / Trial Balance worked. Principal Journals, Chart of Accounts, Bills and Payments rendered read-only; payment void controls were absent.
- **Fail — principal period controls:** principal login → Dashboard → Accounting Periods still showed four `Tutup Periode` buttons for open months. Clicking October → confirming sent POST `/finance/periods/2026-10/close`, which correctly returned 403; October remained open. Server authorization holds, but the read-only persona is offered an unavailable mutation. `resources/js/pages/finance/periods.tsx` renders monthly actions without a capability prop. Hide both monthly close/reopen controls for users lacking admin/finance capability, then repeat this journey before marking Fees C close-out complete. No fix was included in this verification-only task.
- **Automated supporting proof:** `php artisan test --compact` with `tests/Feature/Parent/FeePortalTest.php`, `tests/Feature/fees/VerificationTest.php`, `tests/Feature/Student/FeeTest.php`, `tests/Feature/finance/YearEndClosingTest.php`, `tests/Feature/finance/PeriodTest.php`, `tests/Feature/fees/FeeAccessTest.php`, and `tests/Feature/Notifications/FeeNotificationTest.php`: **477 passed / 880 assertions**. A second focused run with `tests/Feature/finance/LedgerReportTest.php`, `tests/Unit/Services/Finance/Reports/ReceivableReportsTest.php`, `tests/Unit/Services/Finance/Reports/AgingTest.php`, and `tests/Unit/Services/Finance/ReceivablesReconciliationTest.php`: **30 passed / 139 assertions**. No tests were edited. Full CI/build and PostgreSQL contention were not rerun for this browser-only review.
- **Limits and cleanup:** extra direct POST authorization probes were rejected by automatic tool review and were not executed; the existing isolated access suite supplies that coverage. Browser logs had no unexpected errors; the deliberate principal click produced an expected 403. This does not verify live WhatsApp/email delivery, production workers/scheduler, opening balances or accountant sign-off. Accountant review is deferred until project completion per the user's instruction and remains a gate before real transactions. Browser artifacts and disposable fixtures are retained under `/private/tmp/presensio-spec14-browser-20261024`; the isolated server was stopped.


## Permission fix and authorized POST follow-up — 2026-10-24

The user authorized hiding the unavailable controls and running the previously rejected direct POST probes, including possible changes to development transactions. All checks still used the disposable SQLite browser database.

- The period controller now supplies `canManage`, using the same Admin/Finance role capability as the write routes and existing journal UI. Monthly close/reopen controls, yearly actions, confirmation dialogs and submit handlers honor it. Existing period POST calls use generated Wayfinder controller functions. No route, ledger or period-service behavior changed.
- The principal regression assertion and two multi-role cases failed before the fix because the capability prop was absent, then passed afterward. A principal-only account receives false and no closable years; accounts holding Principal plus either Admin or Finance receive true even with the principal role active.
- Browser recheck on rebuilt assets: login → Dashboard → Accounting Periods showed zero close, reopen or annual-close buttons for principal, while retaining both open and closed rows. Admin saw three close buttons, one reopen button and the yearly option. Finance successfully closed September, reopened it with a reason and closed it again, proving both authorized operations remain usable. These were intentional changes only to disposable test data.
- All five direct POST probes as principal returned **403**: create/post manual journal, void payment #3, close October, reopen September and annual close. Requests used the real browser session and CSRF cookie/header. The saved operational snapshot immediately after those probes matched the earlier snapshot exactly (bills, periods and closing entries). The earlier tool-review rejection is resolved by the user's explicit authorization and these completed checks.
- Focused backend gate after Pint: `php artisan test --compact tests/Feature/finance/PeriodTest.php tests/Feature/finance/YearEndClosingTest.php tests/Feature/fees/FeeAccessTest.php` — **446 passed / 571 assertions**. `vendor/bin/pint --dirty --format agent` passed. `vendor/bin/phpstan analyse --memory-limit=1G --debug` passed with zero errors; debug mode avoided the sandbox's parallel-worker socket restriction.
- OrbStack/Sail was started for the frontend gates. `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run build` all passed after formatting the changed file and moving temporary browser artifacts outside the repository. No new dependency was added. No unexpected browser console error remained on the corrected journeys.
- The principal period-control finding above is resolved. Spec/index status now reflects completed browser verification. Full CI and PostgreSQL concurrency were not rerun for this permission-only fix; accountant review, live delivery, opening balances and deployment worker/scheduler checks retain their previously documented go-live status. The isolated browser server was stopped; evidence remains in `/private/tmp/presensio-spec14-browser-20261024`.
