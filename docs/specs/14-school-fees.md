# 14 — School Fees & Tuition (SPP & Tagihan Siswa)

Status: draft v1.1 (2026-10-01) — revised against spec 15 (AUDIT-2026-10-01 P14-01…09). Supersedes draft v1.0 (2026-09-25):
- Payments now split into header + allocations (one payment can cover several bills, including siblings).
- Bill status is derived from verified allocations.
- Verified payments are immutable and can only be voided with a reason.
- Discounts, scholarships, bill cancellation on withdrawal, and an unambiguous billing period added.
- Receipt numbering made concurrency-safe.
- `finance` role from spec 15.

## Problem

Private and independent schools in Indonesia fund operations through monthly tuition (SPP) and one-time fees (Uang Pangkal, Seragam, Buku, Kegiatan). Without an integrated ledger:
1. Treasurers (Bendahara / Staf TU) track billing in payment booklets or spreadsheets: receipts get lost, cash goes unrecorded, and disputes ("saya sudah transfer bulan lalu tapi masih ditagih") are hard to settle.
2. Leadership cannot see collection totals or arrears per class (tunggakan per kelas) without days of reconciliation.
3. Parents cannot see what they owe, the due dates, or the school's bank account, and must visit the cashier during office hours for receipts.
4. Mistakes are corrected by editing or deleting records, which destroys the audit trail a school needs when money is disputed.

## Goals

1. Catalog fee types: monthly recurring (SPP) and one-time.
2. Generate bills in bulk for active students, applying per-student discounts and scholarships.
3. Collect payments two ways:
   - Cash at the cashier.
   - Bank transfer with proof upload and staff verification.
4. Let one payment settle several bills, including bills of siblings paid together.
5. Derive bill status from verified money, so there is a single source of truth.
6. Keep verified payments immutable; corrections are voids with a reason, visible in the cash book.
7. Issue official receipts (Kuitansi) with concurrency-safe unique numbers.
8. Give guardians a self-service bills and payments portal.
9. Report the daily cash book and arrears per class, exportable to CSV.

## Non-goals

- Payment gateway / QRIS callbacks (v2 candidate).
- Double-entry general ledger, balance sheet, profit and loss.
- Expenses and payroll.
- Late-payment penalties or interest.
- Credit balances / deposits: overpayment is not stored as credit in v1. The verifier allocates the excess to other open bills of the same students, or rejects the payment.
- Refunds of money to families (voiding records an error; actual refunds are handled outside the system in v1).

## User Stories

- **As a treasurer**, I want to generate 12 monthly SPP bills of Rp 350.000 for all Grade 5 students, with the 50% scholarship for two students applied automatically.
- **As a cashier**, I want to take cash from a parent for September and October SPP of two siblings in one transaction, and print one receipt listing both children.
- **As a guardian**, I want to see that September SPP is due, view the school's bank account, transfer, upload the slip, and track verification.
- **As a cashier**, I want to verify a transfer after checking the bank statement, and be warned if the same slip image was already used.
- **As a treasurer who made an entry error yesterday**, I want to void the payment with a reason so it appears as a correction in the cash book, instead of silently disappearing.
- **As a principal**, I want to see this month's collections and which classes have the highest arrears.

## Decisions

- **Fee types**: `type` ∈ `monthly_recurring`, `one_time`, with `default_amount`. Optionally scoped to an academic year.
- **Billing period**: `period_key` is `YYYY-MM` (calendar month) for recurring fees and `once` for one-time fees. `UNIQUE (student_id, fee_type_id, period_key)` prevents duplicates, so re-running the generator is safe.
- **Discounts & scholarships**: `student_fee_discounts` gives a student a percentage or fixed reduction on a fee type, with a reason (beasiswa, saudara kandung, yatim), valid within an academic year. The generator applies it as the bill's `discount_amount`. An individual bill's discount can also be edited, which is audited.
- **Derived bill status**. With `net = amount − discount_amount` and `paid = Σ verified, non-void allocations`:
  - `cancelled` if `cancelled_at` is set.
  - else `waived` if `net = 0`.
  - else `paid` if `paid ≥ net`.
  - else `partially_paid` if `paid > 0`.
  - else `unpaid`.

  `paid_amount` and `status` are stored as cached columns, recomputed by one routine in the same transaction as any change to allocations, discounts or cancellation. "Menunggu verifikasi" is a separate derived flag (the bill has an allocation on a `pending` payment), **not** a status.
- **Payments = header + allocations**:
  - A `payments` row is the money movement: method, total, proof, status.
  - `payment_allocations` split it over bills. The sum of allocations must equal the payment amount, and no allocation may exceed the bill's remaining `net − paid`.
  - Allocated bills may belong to several students, but only students linked to the paying guardian (transfer) or chosen together by the cashier (cash).
- **Payment status**:
  - `pending` → `verified` or `rejected`.
  - `verified` → `void` (with reason).
  - Cash payments are created directly as `verified`.
  - Rejected and voided payments never count toward `paid`.
  - Verified payments are never edited or deleted.
- **Void**: requires a reason. It stamps `voided_at` and `voided_by_user_id`, writes an audit log row (15), and recomputes the affected bills. The cash book shows the void as a negative line on the **void date**, so past days' totals do not change.
- **Receipt numbers**: `KWT-{YYYYMM}-{NNNNN}`, assigned when a payment becomes `verified`, from `receipt_sequences` locked with `SELECT … FOR UPDATE` inside the transaction. Pending transfers have no receipt number.
- **Duplicate proof detection**: the SHA-256 hash of each uploaded proof is stored. On verification, a matching hash on another non-rejected payment shows a warning.
- **Withdrawal / graduation**: when a student's open enrollment is closed without a successor (02 / 07), unpaid bills with zero payments whose period starts after the end date are cancelled automatically (`cancel_reason = 'Siswa keluar/lulus'`). Bills with partial payments are listed for treasurer review.
- **Class attribution in reports**: arrears are grouped by the student's current class (`classOn(today)`). Students without an open enrollment are grouped as "Alumni / Keluar".
- **School bank account and kop** come from the school profile (15).
- **Access** (matrix in 15):
  - `finance`, `admin`: full access.
  - `principal`: read-only.
  - `parent`: own children only; uploads proofs.
  - `student`: read-only, self.
  - `teacher` / `counselor`: none.

## Requirements

1. **Fee Types (`/admin/fees/types`)**: CRUD with `name`, `code` (unique), `type`, `default_amount` (> 0), `academic_year_id` (optional), `description`, `is_active`.
2. **Discounts (`/admin/fees/discounts`)**:
   - CRUD of `student_fee_discounts`: student, fee type, academic year, `percent` (0–100) **or** `fixed_amount`, `reason`.
   - Changes affect future generated bills only; existing bills are adjusted individually.
3. **Bulk Bill Generator (`/admin/fees/generate`)**:
   - Inputs:
     - Target: one class, one grade level, or all actively enrolled students in the active year.
     - Fee type and amount (default `default_amount`).
     - For recurring fees: a month range and the due day of the month. For one-time fees: a due date.
   - Creates bills in one transaction, applying discounts and skipping existing `(student, fee type, period_key)` rows.
   - Shows a dry-run summary (created / skipped / discounted) before commit.
4. **Cashier (`/admin/fees/cashier`)**:
   - Search students by name or NIS. Siblings (sharing a guardian) can be added to the same transaction.
   - Select open bills, enter the amount received and its allocation (auto-allocated oldest-due first, editable), and an optional note.
   - Saves a `verified` cash payment with allocations, assigns the receipt number, and opens the printable Kuitansi.
5. **Transfer Verification Queue (`/admin/fees/verifications`)**:
   - Lists `pending` payments with student(s), allocations, amount, transfer date, origin bank, proof preview, and duplicate-hash warning.
   - Verify: optionally re-allocate (e.g. an amount differing from the bills); sets `verified`, assigns the receipt number, recomputes bills.
   - Reject: requires `reject_reason`; the guardian sees it on the portal.
6. **Void (`POST /admin/fees/payments/{id}/void`)**: `finance` or `admin`; `verified` payments only; requires `reason`.
7. **Guardian Portal (`/parent/fees`)**:
   - Bills for all linked children: totals (belum dibayar, menunggu verifikasi, lunas), and per bill the period, due date, net amount, paid, status, and pending flag.
   - The school bank account is shown.
   - Upload modal: select one or more open bills (any linked child), transfer date, origin bank, amount, and proof (JPG/PNG/PDF, max 5 MB, private storage). Creates a `pending` payment with the proposed allocations.
   - Payment history with status, reject reason, and "Cetak Kuitansi" for verified payments.
8. **Reports (`/admin/fees/reports`)**:
   - *Buku Kas Penerimaan*: per date range, verified payments by verification date and voids as negative lines by void date; subtotals per method and per staff member.
   - *Rekap Tunggakan*: per class, students with overdue open bills (`due_date < today`, status `unpaid` / `partially_paid`), billed, collected and outstanding totals.
   - CSV export (UTF-8 BOM, as spec 06).
9. **Kuitansi**:
   - Contents:
     - Header: school kop (15), receipt number, date.
     - Line items: student, NIS, class, bill title, amount.
     - Total in figures and words (terbilang), method.
     - Signature: verifying staff name.
   - A voided payment's receipt is watermarked "BATAL".
   - PDF uses the same library as spec 11 (requires approval).

## Schema

### 1. `fee_types` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `academic_year_id` | bigint | unsigned, nullable | FK -> `academic_years.id` |
| `name` | varchar(100) | not null | |
| `code` | varchar(30) | not null, unique | |
| `type` | varchar(30) | not null | Enum: `monthly_recurring`, `one_time` |
| `default_amount` | decimal(12,2) | not null | |
| `description` | text | nullable | |
| `is_active` | boolean | not null, default: true | |
| `created_at` / `updated_at` | timestamp | nullable | |

### 2. `student_fee_discounts` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `student_id` | bigint | unsigned, not null | FK -> `students.id` |
| `fee_type_id` | bigint | unsigned, not null | FK -> `fee_types.id` |
| `academic_year_id` | bigint | unsigned, not null | FK -> `academic_years.id` |
| `percent` | decimal(5,2) | nullable | 0–100; exactly one of `percent` / `fixed_amount` |
| `fixed_amount` | decimal(12,2) | nullable | |
| `reason` | varchar(255) | not null | e.g. "Beasiswa prestasi" |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (student_id, fee_type_id, academic_year_id)`

### 3. `bills` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `student_id` | bigint | unsigned, not null | FK -> `students.id` |
| `fee_type_id` | bigint | unsigned, not null | FK -> `fee_types.id` |
| `academic_year_id` | bigint | unsigned, not null | FK -> `academic_years.id` |
| `period_key` | varchar(7) | not null | `YYYY-MM` or `once` |
| `title` | varchar(255) | not null | e.g. "SPP September 2026" |
| `amount` | decimal(12,2) | not null | Gross amount |
| `discount_amount` | decimal(12,2) | not null, default: 0.00 | |
| `discount_reason` | varchar(255) | nullable | |
| `paid_amount` | decimal(12,2) | not null, default: 0.00 | Cached Σ verified, non-void allocations |
| `status` | varchar(20) | not null, default: 'unpaid' | Cached derived status: `unpaid`, `partially_paid`, `paid`, `waived`, `cancelled` |
| `due_date` | date | not null | |
| `cancelled_at` | timestamp | nullable | |
| `cancel_reason` | varchar(255) | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (student_id, fee_type_id, period_key)`, `INDEX (student_id, status)`, `INDEX (academic_year_id, due_date, status)`

### 4. `payments` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `receipt_number` | varchar(30) | nullable, unique | Assigned on verification |
| `amount` | decimal(12,2) | not null | Total money received |
| `payment_method` | varchar(20) | not null | Enum: `cash`, `bank_transfer` |
| `payment_date` | date | not null | Cash date or stated transfer date |
| `origin_bank` | varchar(100) | nullable | Transfer origin |
| `proof_path` | varchar(255) | nullable | Private storage |
| `proof_hash` | char(64) | nullable | SHA-256 of proof file |
| `status` | varchar(20) | not null, default: 'pending' | Enum: `pending`, `verified`, `rejected`, `void` |
| `reject_reason` | varchar(255) | nullable | |
| `void_reason` | varchar(255) | nullable | |
| `notes` | varchar(255) | nullable | |
| `submitted_by_user_id` | bigint | unsigned, not null | Guardian (transfer) or cashier (cash) |
| `verified_by_user_id` | bigint | unsigned, nullable | |
| `verified_at` | timestamp | nullable | |
| `voided_by_user_id` | bigint | unsigned, nullable | |
| `voided_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (receipt_number)`, `INDEX (status, created_at)`, `INDEX (verified_at)`, `INDEX (voided_at)`, `INDEX (proof_hash)`

### 5. `payment_allocations` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `payment_id` | bigint | unsigned, not null | FK -> `payments.id` |
| `bill_id` | bigint | unsigned, not null | FK -> `bills.id` |
| `amount` | decimal(12,2) | not null | > 0 |
| `created_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (payment_id, bill_id)`, `INDEX (bill_id)`

### 6. `receipt_sequences` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `period` | char(6) | primary key | `YYYYMM` |
| `last_number` | integer | unsigned, not null | Last issued sequence |

## Acceptance Criteria

- **AC-14-01**: Generating SPP for September 2026 at Rp 350.000 creates a bill with `period_key = '2026-09'`, `amount = 350000.00`, `paid_amount = 0.00`, `status = 'unpaid'`.
- **AC-14-02**: Re-running the same generation creates zero new bills and reports them as skipped.
- **AC-14-03**: A student with a 50% SPP scholarship gets `discount_amount = 175000.00`; a full scholarship results in `status = 'waived'`.
- **AC-14-04**: A cash payment of Rp 700.000 allocated to two siblings' September SPP bills creates one `verified` payment, two allocations, both bills `paid`, and one receipt `KWT-YYYYMM-NNNNN` listing both children.
- **AC-14-05**: Allocations not summing to the payment amount, or exceeding a bill's remaining balance, return HTTP 422.
- **AC-14-06**: A guardian's transfer upload creates a `pending` payment; the bill keeps `status = 'unpaid'` and shows the "menunggu verifikasi" flag.
- **AC-14-07**: Rejecting a pending transfer on a bill that already had Rp 200.000 verified leaves the bill `partially_paid` with `paid_amount = 200000.00`.
- **AC-14-08**: Voiding a verified Rp 350.000 payment without a reason returns HTTP 422. With a reason, the bill returns to `unpaid`, an audit row is written, and the cash book shows −350.000 on the void date while the original day's total is unchanged.
- **AC-14-09**: Two cashiers verifying simultaneously receive distinct consecutive receipt numbers.
- **AC-14-10**: Verifying a transfer whose proof hash matches another non-rejected payment shows a duplicate warning.
- **AC-14-11**: When a student's enrollment is closed on 2026-11-15 without a successor, unpaid zero-payment SPP bills for 2026-12 onward become `cancelled`; a partially paid bill is listed for review instead.
- **AC-14-12**: A guardian requesting bills of an unlinked student receives HTTP 403; a teacher requesting `/admin/fees` receives HTTP 403; a principal can view reports but recording a payment returns HTTP 403.

## Constraints & Assumptions

- Currency IDR, displayed as `Rp 350.000`; `decimal(12,2)`.
- Requires spec 15 (`finance` role, school profile, audit log).
- PDF library shared with spec 11 — **new dependency, requires approval**.

## Open Questions

- `[NEEDS DECISION: WhatsApp payment reminders]`: Remind guardians N days before `due_date` via spec 05 infrastructure? Depends on AUDIT S-07 / F-04 and messaging cost.
- `[NEEDS DECISION: Per-grade SPP amounts]`: v1 sets the amount per generation run (run once per grade level when amounts differ). A per-grade amount table is a v1.x candidate if this proves error-prone.
