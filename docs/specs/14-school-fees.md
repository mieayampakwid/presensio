# 14 — School Fees, Receivables & General Ledger (SPP, Piutang & Buku Besar)

Status: draft v2.0 (2026-10-01). Decided scope: double-entry general ledger plus student receivables, accrual basis. Supersedes draft v1.1 (2026-10-01): the receivables/payments model of v1.1 is kept, and every financial event now posts a balanced journal entry to a general ledger.

New in v2.0:
- Chart of accounts and accounting periods with closing.
- Manual journals (including expenses).
- Trial balance and receivables reconciliation.
- Prepaid revenue (pendapatan diterima di muka) and bad-debt write-off.

## Problem

Private and independent schools in Indonesia (usually run by a yayasan) fund operations through monthly tuition (SPP) and one-time fees. Without an integrated system, several things go wrong:
1. Billing lives in payment booklets and spreadsheets. Receipts are lost, cash goes unrecorded, and payment disputes are hard to settle.
2. Leadership cannot see collections or arrears per class without days of reconciliation.
3. Parents cannot see what they owe or how to pay.
4. Even where a fee spreadsheet exists, it is a single-entry list. It cannot prove that cash, receivables and revenue agree, it cannot produce a trial balance, and corrections overwrite history. The yayasan's financial reporting (ISAK 35, accrual basis) is rebuilt by hand from these lists at year end.

## Goals

1. Keep the fee operations of v1.1:
   - Fee catalog, discounts and scholarships, bulk billing.
   - Cash and verified transfer payments with allocations, derived bill status.
   - Receipts and the guardian portal.
2. Record every financial event as a balanced double-entry journal (Σ debit = Σ credit) in a general ledger, generated automatically from fee operations.
3. Recognize revenue on an **accrual basis**:
   - SPP is revenue of the month it covers.
   - Money received before that month is a liability (pendapatan diterima di muka).
   - Unpaid recognized bills are receivables (piutang).
4. Make posted journals immutable. Every correction is a reversing or adjusting entry.
5. Let the treasurer record other transactions (expenses, opening balances, adjustments) as manual journals.
6. Provide General Journal, General Ledger, Trial Balance, Cash/Bank Book, Student Receivable Card, Aging and a receivables reconciliation (subledger = GL control account).
7. Lock closed accounting periods, and close the fiscal year into net assets.

## Non-goals

- Formal ISAK 35 financial statements (Laporan Posisi Keuangan, Laporan Penghasilan Komprehensif, Laporan Perubahan Aset Neto, Laporan Arus Kas) — v1.x. The chart of accounts is structured so they can be produced from it.
- Dedicated expense / purchasing module, budgeting (RAPBS), fixed-asset depreciation schedules. In v1, expenses are entered as manual journals.
- Payroll and honorarium (the staff attendance recap, spec 16, is the future input).
- Payment gateway / QRIS callbacks (v2 candidate).
- Tax computation and e-filing.
- Multi-currency (IDR only).
- Credit balances / deposits on student accounts: overpayment is allocated to other open bills of the same students or the transfer is rejected.
- Cash refunds to families (a refund is recorded as a manual journal in v1).

## User Stories

- **As a treasurer**, I want to generate the year's SPP bills for Grade 5 in July and have revenue appear month by month, not all in July.
- **As a cashier**, I want to receive cash for two siblings' September SPP in one transaction and print one receipt. The ledger should show Kas debited and Piutang SPP credited automatically.
- **As a guardian paying three months in advance**, I want my payment accepted; the school records it as prepaid revenue until each month arrives.
- **As a treasurer who made an entry error last week**, I want to void the payment and have a reversing journal posted, not the original deleted.
- **As a treasurer**, I want to record the electricity bill as a manual journal (Dr Beban Listrik / Cr Bank).
- **As a treasurer at month end**, I want a trial balance that balances, and a reconciliation showing that the sum of open student bills equals the Piutang balance. Then I want to close the month.
- **As a principal**, I want collections and arrears per class, and the ledger, read-only.

## Decisions

### Chart of accounts

- `accounts` with `code`, `name`, `type`, optional `parent_id` (for grouping), `is_postable` (only leaf accounts receive lines), `is_cash` (cash/bank accounts usable as payment deposit accounts), and `is_active`.
- Account types: `asset`, `liability`, `net_assets`, `revenue`, `expense`.
- Normal balance: debit for `asset` and `expense`; credit for the others. Contra accounts carry `is_contra = true` and the opposite normal balance (e.g. Potongan & Beasiswa under revenue).
- `net_assets` accounts carry `restriction` (`without_restriction` / `with_restriction`), following ISAK 35's net asset classes.
- A default school chart is seeded and editable before first use.

  | Code | Name |
  |---|---|
  | 1-1100 | Kas Tunai |
  | 1-1200 | Bank |
  | 1-1300 | Piutang SPP |
  | 1-1310 | Piutang Biaya Lainnya |
  | 2-1100 | Pendapatan Diterima di Muka |
  | 3-1000 | Aset Neto Tanpa Pembatasan |
  | 3-2000 | Aset Neto Dengan Pembatasan |
  | 4-1100 | Pendapatan SPP |
  | 4-1200 | Pendapatan Uang Pangkal |
  | 4-1900 | Potongan & Beasiswa (contra) |
  | 5-xxxx | Sample expense accounts |
  | 5-9100 | Beban Penghapusan Piutang |

- An account with posted lines cannot be deleted (it can be deactivated). Its `type` cannot change.

### Mapping fee operations to accounts

- Each `fee_types` row maps to a `receivable_account_id`, a `revenue_account_id` and a `discount_account_id`.
- Each payment records a `deposit_account_id`, which must be an account with `is_cash = true`:
  - Cash → Kas Tunai.
  - Transfer → the bank account shown to guardians.
- School settings hold `prepaid_revenue_account_id`, `bad_debt_expense_account_id` and `net_assets_closing_account_id`.

### Journals

- `journal_entries`: number `JU-{YYYYMM}-{NNNNN}`, `entry_date`, description, `source_type` / `source_id` (bill recognition, payment, void, bill adjustment, write-off, manual, closing), `reverses_entry_id`, author, `posted_at`.
- `journal_lines`: account, debit or credit (exactly one is > 0), optional `student_id` (receivables subledger key), memo.
- **Invariant**: Σ debit = Σ credit per entry, checked before insert in the same transaction. Unbalanced → rejected.
- **Immutability**: posted entries and lines are never updated or deleted. Corrections are new entries: a reversal mirrors every line of the original, dated on the correction date.
- Automatic entries are posted immediately. Manual entries may be saved as `draft` (editable, not in the ledger) and then posted.
- Entry numbers use a per-month sequence locked `FOR UPDATE` (same pattern as receipt numbers).

### Accrual posting rules

Posting rules for automatic entries. `R` = receivable, `Rev` = revenue, `D` = discount, `P` = prepaid revenue, `C` = deposit cash/bank account.

| Event | Entry date | Journal |
|---|---|---|
| **Bill recognition** | Recurring: first day of the bill's month (`period_key`). One-time: bill issue date | Dr R (gross − prepaid portion); Dr P (prepaid portion, if any); Dr D (discount, if any) / Cr Rev (gross) |
| **Payment verified**, allocation to a *recognized* bill | Verification date | Dr C / Cr R |
| **Payment verified**, allocation to a *not-yet-recognized* bill | Verification date | Dr C / Cr P |
| **Payment void** | Void date | Reversal of the payment's verification entry. If the bill was recognized after the payment, the prepaid portion is reclassified: Dr R / Cr P |
| **Discount changed** on a recognized bill | Change date | Adjusting entry for the difference (Dr D / Cr R, or the reverse) |
| **Bill cancelled** after recognition | Cancellation date | Reversal of the recognition entry for the unpaid portion. A cancelled bill must have no verified allocations; void them first |
| **Write-off** (penghapusan piutang) | Write-off date | Dr Beban Penghapusan Piutang / Cr R for the outstanding amount. The bill becomes `written_off` |

Further rules:
- **Recognition job**: bills are generated in advance (e.g. in July for the whole year). A daily scheduled command posts recognition for bills whose recognition date ≤ today and that are not yet recognized (idempotent: one recognition entry per bill). Bills whose date is already reached are recognized at generation.
- **Pending transfers** post nothing until verified. Rejected transfers post nothing.

### Receivables subledger

- Bill status is derived. With `net = amount − discount_amount` and `paid = Σ verified, non-void allocations`:
  - `cancelled` / `written_off` if stamped.
  - else `waived` if net = 0.
  - else `paid` if paid ≥ net.
  - else `partially_paid` if paid > 0.
  - else `unpaid`.

  `paid_amount` and `status` are cached columns recomputed in the same transaction.
- **Reconciliation invariant**: for each receivable account, the GL balance equals Σ over **recognized**, non-cancelled, non-written-off bills of (net − paid allocations made after recognition). Likewise, the prepaid revenue balance equals Σ allocations to not-yet-recognized bills. The reconciliation report shows both sides and the difference; a non-zero difference is a defect.

### Accounting periods & closing

- Accounting periods are calendar months, `open` or `closed`.
- Posting with an `entry_date` in a closed period is rejected. Automatic events that occur while their natural date's period is closed are posted on the first day of the next open period, with the original date in the memo.
- Closing a month requires the trial balance to balance and the receivables reconciliation to be zero. Reopening requires a reason and is audited (15).
- **Fiscal year**: setting `fiscal_year_start_month` (default 7 = July, matching the academic year; change to 1 if the yayasan uses a calendar year).
- Year-end closing posts one closing entry: revenue and expense balances → `net_assets_closing_account_id`. It then locks all periods of that year.

### Fee operations

- **Fee types**: `type` ∈ `monthly_recurring`, `one_time`, with `default_amount`. Optionally scoped to an academic year.
- **Billing period**: `period_key` is `YYYY-MM` (calendar month) for recurring fees and `once` for one-time fees. `UNIQUE (student_id, fee_type_id, period_key)` prevents duplicates, so re-running the generator is safe.
- **Discounts & scholarships**: `student_fee_discounts` gives a student a percentage or fixed reduction on a fee type, with a reason (beasiswa, saudara kandung, yatim), valid within an academic year. The generator applies it as the bill's `discount_amount`. An individual bill's discount can also be edited, which is audited.
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
- **Void**: requires a reason. It stamps `voided_at` and `voided_by_user_id`, writes an audit log row (15), recomputes the affected bills, and posts a reversing journal on the void date (see posting rules), so past days' cash totals do not change.
- **Receipt numbers**: `KWT-{YYYYMM}-{NNNNN}`, assigned when a payment becomes `verified`, from `receipt_sequences` locked with `SELECT … FOR UPDATE` inside the transaction. Pending transfers have no receipt number.
- **Duplicate proof detection**: the SHA-256 hash of each uploaded proof is stored. On verification, a matching hash on another non-rejected payment shows a warning.
- **Withdrawal / graduation**: when a student's open enrollment is closed without a successor (02 / 07), unpaid bills with zero payments whose period starts after the end date are cancelled automatically (`cancel_reason = 'Siswa keluar/lulus'`). Bills with partial payments are listed for treasurer review.
- **Class attribution in reports**: arrears are grouped by the student's current class (`classOn(today)`). Students without an open enrollment are grouped as "Alumni / Keluar".
- **School bank account and kop** come from the school profile (15).

### Access (matrix in 15)

- `finance`: all fee operations, manual journals, period closing.
- `admin`: same as finance, plus chart-of-accounts setup.
- `principal`: read-only for all reports and the ledger.
- `parent` / `student`: own bills and payments only.
- Others: none.

## Requirements

1. **Chart of Accounts (`/admin/finance/accounts`)**:
   - Tree view; CRUD subject to the deletion and type rules.
   - The seeded default chart can be edited until the first posted entry.
2. **Opening Balances**: a manual journal dated the day before go-live, with the counterpart in net assets. Per-student opening receivables are entered as opening bills with `is_opening = true`. Their recognition entry credits net assets instead of revenue.
3. **Fee Types (`/admin/fees/types`)**: CRUD with `name`, `code` (unique), `type`, `default_amount` (> 0), `academic_year_id` (optional), `description`, `is_active`.
   - **Ledger additions**: the fee type form requires the three account mappings; cashier and verification choose `deposit_account_id` (defaults: Kas Tunai / the school bank account); every state change posts the journal defined in the posting rules in the same transaction — if posting fails, the operation fails.
4. **Discounts (`/admin/fees/discounts`)**:
   - CRUD of `student_fee_discounts`: student, fee type, academic year, `percent` (0–100) **or** `fixed_amount`, `reason`.
   - Changes affect future generated bills only; existing bills are adjusted individually.
5. **Bulk Bill Generator (`/admin/fees/generate`)**:
   - Inputs:
     - Target: one class, one grade level, or all actively enrolled students in the active year.
     - Fee type and amount (default `default_amount`).
     - For recurring fees: a month range and the due day of the month. For one-time fees: a due date.
   - Creates bills in one transaction, applying discounts and skipping existing `(student, fee type, period_key)` rows.
   - Shows a dry-run summary (created / skipped / discounted) before commit.
6. **Cashier (`/admin/fees/cashier`)**:
   - Search students by name or NIS. Siblings (sharing a guardian) can be added to the same transaction.
   - Select open bills, enter the amount received and its allocation (auto-allocated oldest-due first, editable), and an optional note.
   - Saves a `verified` cash payment with allocations, assigns the receipt number, and opens the printable Kuitansi.
7. **Transfer Verification Queue (`/admin/fees/verifications`)**:
   - Lists `pending` payments with student(s), allocations, amount, transfer date, origin bank, proof preview, and duplicate-hash warning.
   - Verify: optionally re-allocate (e.g. an amount differing from the bills); sets `verified`, assigns the receipt number, recomputes bills.
   - Reject: requires `reject_reason`; the guardian sees it on the portal.
8. **Guardian Portal (`/parent/fees`)**:
   - Bills for all linked children: totals (belum dibayar, menunggu verifikasi, lunas), and per bill the period, due date, net amount, paid, status, and pending flag.
   - The school bank account is shown.
   - Upload modal: select one or more open bills (any linked child), transfer date, origin bank, amount, and proof (JPG/PNG/PDF, max 5 MB, private storage). Creates a `pending` payment with the proposed allocations.
   - Payment history with status, reject reason, and "Cetak Kuitansi" for verified payments.
9. **Void (`POST /admin/fees/payments/{id}/void`)**: `finance` or `admin`; `verified` payments only; requires `reason`.
10. **Write-off (`POST /admin/finance/bills/{id}/write-off`)**: requires a reason; bill must be recognized and not fully paid; audited.
11. **Manual Journals (`/admin/finance/journals`)**:
   - Fields: date, description, lines (account, debit/credit, optional student, memo), optional attachment (nota/kuitansi, private storage).
   - Save as draft; post (balance validation, open period); reverse a posted entry (reason required).
12. **Reports** (date-range filters, CSV export with UTF-8 BOM):
   - *Jurnal Umum*: entries with lines.
   - *Buku Besar*: per account, opening balance, lines, running balance.
   - *Neraca Saldo*: per postable account, debit and credit balances; totals must be equal.
   - *Buku Kas/Bank*: the ledger of one `is_cash` account (replaces v1.1 "Buku Kas Penerimaan").
   - *Kartu Piutang Siswa*: per student, bills, allocations, write-offs, running balance.
   - *Umur Piutang (Aging)*: per class and student, outstanding by bucket (belum jatuh tempo, 1–30, 31–60, 61–90, > 90 days past due).
   - *Rekonsiliasi Piutang*: GL vs subledger per receivable account and for prepaid revenue.
13. **Periods (`/admin/finance/periods`)**: list, close (with preconditions), reopen (reason), year-end closing.
14. **Kuitansi**:
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
| `default_amount` | decimal(15,2) | not null | |
| `description` | text | nullable | |
| `receivable_account_id` | bigint | unsigned, not null | FK -> `accounts.id` |
| `revenue_account_id` | bigint | unsigned, not null | FK -> `accounts.id` |
| `discount_account_id` | bigint | unsigned, not null | FK -> `accounts.id` |
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
| `fixed_amount` | decimal(15,2) | nullable | |
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
| `amount` | decimal(15,2) | not null | Gross amount |
| `discount_amount` | decimal(15,2) | not null, default: 0.00 | |
| `discount_reason` | varchar(255) | nullable | |
| `paid_amount` | decimal(15,2) | not null, default: 0.00 | Cached Σ verified, non-void allocations |
| `status` | varchar(20) | not null, default: 'unpaid' | Cached derived status: `unpaid`, `partially_paid`, `paid`, `waived`, `cancelled`, `written_off` |
| `recognized_at` | date | nullable | Recognition entry date; null = not yet recognized |
| `is_opening` | boolean | not null, default: false | Opening receivable at go-live |
| `written_off_at` | timestamp | nullable | |
| `write_off_reason` | varchar(255) | nullable | |
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
| `amount` | decimal(15,2) | not null | Total money received |
| `payment_method` | varchar(20) | not null | Enum: `cash`, `bank_transfer` |
| `deposit_account_id` | bigint | unsigned, not null | FK -> `accounts.id` (`is_cash`) |
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
| `amount` | decimal(15,2) | not null | > 0 |
| `created_at` | timestamp | nullable | |

**Indexes:** `UNIQUE (payment_id, bill_id)`, `INDEX (bill_id)`

### 6. `receipt_sequences` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `period` | char(6) | primary key | `YYYYMM` |
| `last_number` | integer | unsigned, not null | Last issued sequence |

### 7. `accounts` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `code` | varchar(20) | not null, unique | e.g. "1-1300" |
| `name` | varchar(150) | not null | |
| `type` | varchar(20) | not null | Enum: `asset`, `liability`, `net_assets`, `revenue`, `expense` |
| `is_contra` | boolean | not null, default: false | Opposite normal balance |
| `restriction` | varchar(30) | nullable | `without_restriction` / `with_restriction` (net assets only) |
| `parent_id` | bigint | unsigned, nullable | FK -> `accounts.id` |
| `is_postable` | boolean | not null, default: true | |
| `is_cash` | boolean | not null, default: false | Cash/bank deposit account |
| `is_active` | boolean | not null, default: true | |
| `created_at` / `updated_at` | timestamp | nullable | |

### 8. `journal_entries` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `number` | varchar(30) | nullable, unique | Assigned on posting: `JU-YYYYMM-NNNNN` |
| `entry_date` | date | not null | |
| `description` | varchar(255) | not null | |
| `status` | varchar(10) | not null | Enum: `draft`, `posted` (drafts: manual only) |
| `source_type` | varchar(30) | not null | `bill_recognition`, `payment`, `payment_void`, `bill_adjustment`, `bill_cancellation`, `write_off`, `manual`, `closing` |
| `source_id` | bigint | unsigned, nullable | Bill / payment id for automatic entries |
| `reverses_entry_id` | bigint | unsigned, nullable | FK -> `journal_entries.id` |
| `attachment_path` | varchar(255) | nullable | |
| `created_by_user_id` | bigint | unsigned, nullable | Null = system job |
| `posted_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | nullable | |

**Indexes:** `INDEX (entry_date)`, `INDEX (source_type, source_id)`, `UNIQUE (source_type, source_id)` for `source_type = bill_recognition` (enforced in application), `INDEX (reverses_entry_id)`

### 9. `journal_lines` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | |
| `journal_entry_id` | bigint | unsigned, not null | FK -> `journal_entries.id` |
| `account_id` | bigint | unsigned, not null | FK -> `accounts.id` (postable) |
| `debit` | decimal(15,2) | not null, default: 0 | |
| `credit` | decimal(15,2) | not null, default: 0 | Exactly one of debit/credit > 0 |
| `student_id` | bigint | unsigned, nullable | Subledger key |
| `bill_id` | bigint | unsigned, nullable | Traceability for receivable lines |
| `memo` | varchar(255) | nullable | |

**Indexes:** `INDEX (account_id, journal_entry_id)`, `INDEX (student_id)`, `INDEX (bill_id)`

### 10. `accounting_periods` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `period` | char(7) | primary key | `YYYY-MM` |
| `status` | varchar(10) | not null, default: 'open' | Enum: `open`, `closed` |
| `closed_at` | timestamp | nullable | |
| `closed_by_user_id` | bigint | unsigned, nullable | |

### 11. `settings` — added columns

`fiscal_year_start_month` (tinyint, default 7), `prepaid_revenue_account_id`, `bad_debt_expense_account_id`, `net_assets_closing_account_id` (FK -> `accounts.id`).

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

- **AC-14-13**: Generating SPP Rp 350.000 for 2026-07 … 2027-06 on 2026-07-10 posts recognition only for July (Dr Piutang SPP 350.000 / Cr Pendapatan SPP 350.000). August is posted by the daily job on 2026-08-01.
- **AC-14-14**: A bill with a 50% scholarship recognized posts Dr Piutang 175.000, Dr Potongan & Beasiswa 175.000 / Cr Pendapatan SPP 350.000.
- **AC-14-15**: Paying October SPP on 2026-09-20 posts Dr Kas / Cr Pendapatan Diterima di Muka. On 2026-10-01 recognition posts Dr Pendapatan Diterima di Muka 350.000 / Cr Pendapatan SPP 350.000, and Piutang is not increased.
- **AC-14-16**: Every entry, automatic or manual, has Σ debit = Σ credit. Posting an unbalanced manual journal returns HTTP 422.
- **AC-14-17**: Voiding a verified payment posts a reversal entry dated the void date that references the original; the original entry is unchanged.
- **AC-14-18**: Posting a manual journal dated in a closed period returns HTTP 422. A payment voided while its original period is closed posts its reversal in the current open period.
- **AC-14-19**: After any sequence of generation, payments, voids, discounts, cancellations and write-offs, the trial balance balances and the receivables reconciliation difference is 0.
- **AC-14-20**: Closing a month whose reconciliation difference is non-zero returns HTTP 422.
- **AC-14-21**: Writing off a Rp 200.000 outstanding bill posts Dr Beban Penghapusan Piutang / Cr Piutang SPP and sets the bill to `written_off`.
- **AC-14-22**: Year-end closing zeroes all revenue and expense accounts into the closing net-asset account in one balanced entry, and locks the year's periods.
- **AC-14-23**: Deleting an account with posted lines returns HTTP 422.
- **AC-14-24**: A principal can open the General Ledger and Trial Balance; posting a manual journal returns HTTP 403.

## Constraints & Assumptions

- IDR, `decimal(15,2)`; all amounts entered as whole rupiah in practice.
- Accrual basis, intended to be compatible with ISAK 35 (Penyajian Laporan Keuangan Entitas Berorientasi Nonlaba) for yayasan-run schools. The school's accountant should validate the chart of accounts and posting rules before go-live.
- Requires spec 15 (`finance` role, school profile, audit log). PDF rendering via `barryvdh/laravel-dompdf` (approved 2026-10-01), shared with spec 11.
- All posting runs in the same database transaction as the operational change.

## Open Questions

- `[NEEDS DECISION: Separation of duties]`: Should the user who recorded or verified a payment be prevented from voiding it, or from closing the period? Common internal-control practice, but small schools may have a single treasurer.
- ~~WhatsApp payment reminders~~ — resolved by spec 17 (`bill_due_reminder`, `payment_verified`, `payment_rejected`).
- `[NEEDS DECISION: Per-grade SPP amounts]`: v1 sets the amount per generation run; a per-grade amount table is a v1.x candidate.
