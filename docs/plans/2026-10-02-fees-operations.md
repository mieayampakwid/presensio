# Fees B — Fee Types, Billing, Payments, Void & Receipts Implementation Plan

> **For implementer agents:** Spec: `docs/specs/14-school-fees.md` §Mapping, §Accrual posting rules, §Receivables subledger, §Fee operations, Requirements 3–6, 9, 10, 14; ACs 14-01…05, 08, 09, 13…15, 17, 21. Guardian portal, verification queue, reports and closing are Plan "Fees C". Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** Fee catalog, discounts, bulk bill generation with accrual recognition, cashier payments with allocations, void, write-off, receipts (PDF), and withdrawal cancellation — every state change posting its journal in the same transaction.

**Architecture:** Two services own the rules: `BillService` (generate, recognize, adjust discount, cancel, write-off, recompute status) and `PaymentService` (create cash payment, verify, reject, void, allocate). Both call `JournalPoster` (Fees A) inside their own `DB::transaction`; failure to post fails the operation. Bill `status`/`paid_amount` are cached and recomputed by one `BillStatusCalculator` from allocations (derived-status rule). Receipt numbers use the locked-sequence pattern from Fees A.

**Prerequisites:** Fees A merged; Plan 2 (school profile for kuitansi); `barryvdh/laravel-dompdf` (already installed); the shared `pdf/partials/kop.blade.php` is created by plan 7 — if plan 7 has not landed, create it here.

**Blocking questions:** none. Per-grade amounts and separation of duties are spec v1.x/open and out of scope.

---

### Task 1: Schema and models

**Files:**

- Create: migrations `create_fee_types_table`, `create_student_fee_discounts_table`, `create_bills_table`, `create_payments_table`, `create_payment_allocations_table`, `create_receipt_sequences_table` (spec §Schema 1–6); models `FeeType`, `StudentFeeDiscount`, `Bill`, `Payment`, `PaymentAllocation` (+factories); enums `FeeKind`, `BillStatus`, `PaymentMethod`, `PaymentStatus`
- Test: `tests/Unit/Models/BillTest.php`

**Dependencies:** Fees A · **Verification:** `php artisan test --compact tests/Unit/Models`

- [ ] **Step 1:** `Payment` and `PaymentAllocation` immutability: verified payments never updated except the void stamp fields (guard in `updating`); allocations never updated/deleted once verified.

### Task 2: Bill status calculator + fee types + discounts

**Files:**

- Create: `app/Services/Finance/BillStatusCalculator.php`, `app/Http/Controllers/Fees/FeeTypeController.php`, `app/Http/Controllers/Fees/DiscountController.php`, requests, pages `resources/js/pages/fees/{types,discounts}.tsx`, `routes/fees.php`
- Test: `tests/Unit/Services/Finance/BillStatusCalculatorTest.php`, `tests/Feature/fees/FeeTypeTest.php`, `tests/Feature/fees/DiscountTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance tests/Feature/fees`

- [ ] **Step 1:** Calculator per spec order: `cancelled`/`written_off` stamped → else `waived` if net = 0 → `paid` if paid ≥ net → `partially_paid` if paid > 0 → `unpaid`. Table-driven test including AC-14-03 (full scholarship → waived) and AC-14-07 (partial stays `partially_paid` after rejection of another payment).
- [ ] **Step 2:** Fee type form requires the three account mappings (`receivable` ∈ asset, `revenue` ∈ revenue, `discount` = contra revenue) — validate types. Discount: exactly one of `percent` (0–100) / `fixed_amount`, unique `(student, fee_type, year)`; edits affect future bills only.

### Task 3: Bill generator, recognition, discount adjust, cancel, write-off

**Files:**

- Create: `app/Services/Finance/BillService.php`, `app/Services/Finance/BillGenerator.php` (dry-run + commit), `app/Console/Commands/RecognizeBillsCommand.php` (`finance:recognize-bills`, daily, idempotent), `app/Http/Controllers/Fees/BillGenerationController.php`, `app/Http/Controllers/Fees/BillController.php` (`discount`, `cancel`, `writeOff`), requests, `resources/js/pages/fees/generate.tsx`
- Modify: `routes/fees.php`, `routes/console.php`
- Test: `tests/Unit/Services/Finance/BillServiceTest.php`, `tests/Feature/fees/BillGenerationTest.php`, `tests/Feature/Console/RecognizeBillsCommandTest.php`

**Dependencies:** Task 2 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance tests/Feature/fees tests/Feature/Console`

- [ ] **Step 1:** Generator input: target class / grade level / all enrolled in active year (students resolved through enrollments only); recurring → month range + due day; one-time → due date. Dry-run returns created/skipped/discounted counts. Unique `(student, fee_type, period_key)` skips existing (AC-14-02). Discount applied to `discount_amount` (50% of 350.000 → 175.000, AC-14-03). AC-14-01 field values.
- [ ] **Step 2:** Recognition posting (spec table): recurring → entry date first day of `period_key` month; one-time → issue date. `Dr R (gross − prepaid) / Dr P (prepaid portion) / Dr D / Cr Rev (gross)`. Bills whose date has been reached are recognized at generation; others by the daily command (AC-14-13: generate 2026-07…2027-06 on 2026-07-10 → only July posted; command on 2026-08-01 posts August). Idempotent: one `bill_recognition` entry per bill (application-level uniqueness check under lock).
- [ ] **Step 3:** AC-14-14 exact lines; AC-14-15 reciprocal case (prepaid portion) is completed in Task 4 test.
- [ ] **Step 4:** Discount change on a recognized bill posts an adjusting entry for the difference (audited). Cancel: allowed only with no verified allocations (422), reverses recognition for the unpaid portion. Write-off (AC-14-21): reason required, bill recognized and not fully paid, `Dr 5-9100 / Cr R` for the outstanding, status `written_off`, audited.
- [ ] **Step 5:** Opening bills (`is_opening = true`): recognition credits the net-assets closing account instead of revenue.

### Task 4: Payments — cash, verify, reject, allocations, receipt numbers

**Files:**

- Create: `app/Services/Finance/PaymentService.php`, `app/Services/Finance/ReceiptNumberGenerator.php`, `app/Services/Finance/AllocationValidator.php`
- Test: `tests/Unit/Services/Finance/PaymentServiceTest.php`, `tests/Feature/fees/ReceiptNumberTest.php`

**Dependencies:** Task 3 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance tests/Feature/fees/ReceiptNumberTest.php`

- [ ] **Step 1:** Allocation rules: Σ allocations = payment amount, each ≤ bill remaining `net − paid`, otherwise 422 (AC-14-05); bills must belong to students chosen by the cashier / linked to the paying guardian.
- [ ] **Step 2:** Verified allocation posting: to a **recognized** bill → `Dr C / Cr R`; to a **not-yet-recognized** bill → `Dr C / Cr P` (AC-14-15 first half), with recognition later consuming the prepaid portion (Dr P instead of increasing R — AC-14-15 second half; assert Piutang unchanged). Pending and rejected post nothing.
- [ ] **Step 3:** Receipt number `KWT-YYYYMM-NNNNN` assigned on verification inside the transaction via `receipt_sequences` `FOR UPDATE` (AC-14-09; same concurrency-test note as Fees A Task 3). Recompute bill status/`paid_amount` in the same transaction.

### Task 5: Void

**Files:**

- Create: `app/Http/Controllers/Fees/PaymentVoidController.php`, `app/Http/Requests/Fees/VoidPaymentRequest.php`
- Modify: `PaymentService::void`, `routes/fees.php` (`POST fees/payments/{payment}/void`)
- Test: `tests/Feature/fees/PaymentVoidTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Feature/fees/PaymentVoidTest.php`

- [ ] **Step 1:** `verified` only; `reason` required (422 without — AC-14-08); stamps `voided_at/by`, status `void`, audit `voided`, recompute bills, post a reversal of the verification entry **dated the void date** referencing the original (AC-14-17); if a bill was recognized after the payment, reclassify the prepaid portion `Dr R / Cr P`. Closed original period → reversal in current open period (AC-14-18).
- [ ] **Step 2:** Cash book shows −350.000 on the void date and the original day's total is unchanged (assert via `CashBookReport` from Fees A).
- [ ] **Step 3:** Authorization finance/admin (principal 403 — AC-14-12).

### Task 6: Cashier

**Files:**

- Create: `app/Http/Controllers/Fees/CashierController.php` (`index` search by name/NIS with siblings via `guardian_student`, `store`), `app/Http/Requests/Fees/StoreCashPaymentRequest.php`, `resources/js/pages/fees/cashier.tsx`
- Test: `tests/Feature/fees/CashierTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Feature/fees/CashierTest.php`

- [ ] **Step 1:** Auto-allocate oldest-due first (editable in the UI, validated server-side); deposit account defaults to Kas Tunai. AC-14-04: Rp 700.000 across two siblings' September SPP → one verified payment, two allocations, both bills `paid`, one receipt listing both children.
- [ ] **Step 2:** AC-14-12: teacher → 403; guardian → 403.

### Task 7: Kuitansi PDF

**Files:**

- Create: `app/Services/Finance/Terbilang.php` (+ unit test), `app/Services/Finance/ReceiptPdf.php`, `resources/views/pdf/receipt.blade.php` (reuses `pdf/partials/kop`), `app/Http/Controllers/Fees/ReceiptController.php`
- Test: `tests/Unit/Services/Finance/TerbilangTest.php`, `tests/Feature/fees/ReceiptTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance/TerbilangTest.php tests/Feature/fees/ReceiptTest.php`

- [ ] **Step 1:** Terbilang edge cases: 0, 100, 1.000, 1.500.000, 350.000, 2.000.000.000 ("satu ribu" vs "seribu"). Receipt content per spec: kop, number/date, line items (student, NIS, class, bill title, amount), total figures + words, method, verifying staff; voided payment watermarked "BATAL". Access: finance/admin, the paying guardian (own children), principal read.

### Task 8: Withdrawal cancellation hook

**Files:**

- Modify: `app/Services/EnrollmentService.php` (or the spec 07 closing path — locate where an open enrollment is closed without successor), `app/Services/Finance/BillService.php` (`cancelForWithdrawal(Student, CarbonInterface $on)`)
- Test: `tests/Feature/fees/WithdrawalCancellationTest.php`

**Dependencies:** Task 3 · **Verification:** `php artisan test --compact tests/Feature/fees/WithdrawalCancellationTest.php`

- [ ] **Step 1:** AC-14-11: enrollment closed 2026-11-15 without successor → unpaid, zero-payment SPP bills with period start after that date become `cancelled` (`cancel_reason = 'Siswa keluar/lulus'`, reversing recognition if recognized); partially paid bills are returned in a "needs review" list (shown in Fees C reports). Roll-over promotions (with successor) never trigger it.

### Task 9: Close out

- [ ] `composer ci:check`. Property-style test: random sequence of generate → pay → void → discount → cancel → write-off keeps trial balance balanced (AC-14-16/19 partial; reconciliation is in Fees C).
