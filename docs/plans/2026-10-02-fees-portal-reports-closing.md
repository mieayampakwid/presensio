# Fees C — Guardian Portal, Verification, Reports & Closing Implementation Plan

> **For implementer agents:** Spec: `docs/specs/14-school-fees.md` Requirements 7, 8, 12 (remaining reports), 13 (year-end), §Accounting periods & closing; ACs 14-06, 07, 10, 12, 14-19…22, 24; AC-17 for fee notifications. Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Guardian bill portal and transfer upload, treasurer verification queue with duplicate-proof detection, receivable reports (card, aging, reconciliation), period-close preconditions, year-end closing, and fee notifications.

**Architecture:** Portal and queue are thin controllers over `PaymentService` (Fees B). `ReceivablesReconciliation` is the keystone read service: it computes subledger vs GL for each receivable account and prepaid revenue and is reused by the report, the month-close precondition, and tests. Year-end closing is a single posted entry via `JournalPoster`.

**Prerequisites:** Fees B merged; Plan 4 for Task 7.

**Blocking questions:** none.

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
