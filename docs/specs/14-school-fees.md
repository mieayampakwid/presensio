# 14 — School Fees & Tuition (Manajemen Keuangan SPP & Tagihan Siswa)

Status: draft v1.0 (2026-09-25)

## Problem

Private and independent schools in Indonesia rely heavily on periodic tuition (SPP - Sumbangan Pembinaan Pendidikan) and institutional fees (Uang Pangkal/Gedung, Seragam, Buku, Kegiatan) to fund daily school operations and staff salaries. In the absence of an integrated digital financial ledger:
1. School administration and treasurers (Tata Usaha / Bendahara) manage billing through physical payment booklets (Kartu SPP) or disconnected spreadsheets. This workflow is prone to lost receipts, unrecorded cash transactions, and frequent disputes with parents regarding payment status ("saya sudah transfer bulan lalu tapi masih ditagih").
2. School leadership lacks real-time oversight of financial health: compiling reports on total fee collection and identifying cumulative student arrears (tunggakan per kelas) requires days of manual spreadsheet reconciliation.
3. Parents have no self-service transparency into their financial obligations. They do not know their exact outstanding balance, payment deadlines, or official bank account details, and must visit the school cashier during working hours simply to pay tuition or retrieve a receipt.

## Goals

1. Define a centralized catalog of school fee categories (`fee_types`), accommodating recurring monthly tuition (SPP) and one-time capital/activity charges (Uang Gedung, Seragam, Kegiatan).
2. Automate the generation of student billings (`bills`) for all actively enrolled students across the active academic year with configurable due dates.
3. Support hybrid payment collection workflows:
   - In-person cash payments received directly by the school cashier/TU.
   - Bank transfer payments where guardians upload digital transfer receipts (Bukti Transfer) for administrative verification.
4. Support flexible payment resolution states: `unpaid`, `pending_verification`, `partially_paid`, `paid`, and `waived` (beasiswa / potongan khusus).
5. Generate official, printable digital payment receipts (Kuitansi Pembayaran) with unique receipt numbers and school validation stamps.
6. Provide guardians and students with a self-service "Tagihan & Pembayaran" portal to view current bills, download past payment receipts, and submit transfer proofs.
7. Deliver financial reporting tools for school leadership: daily cash book (Buku Kas Penerimaan), class arrears ledgers (Rekap Tunggakan per Kelas), and exportable CSV statements.

## Non-goals

- Third-party automated payment gateway integration (Midtrans, Xendit, Duitku, or QRIS dynamic callbacks) in v1. (Automated payment gateway onboarding requires business legal entities, escrow merchant fees, and API integration; manual bank transfer verification + physical cashier serves v1 reliably; payment gateways are scoped for v1.x).
- Full enterprise double-entry general ledger accounting (Jurnal Umum, Neraca Saldo, Buku Besar Akuntansi, Laporan Laba Rugi). Presensio is a school operations platform, not a corporate accounting system like SAP or Zahir.
- Operational expense management and teacher payroll (Pengeluaran Kas & Gaji/Honorarium Guru) in v1 (scope is strictly student receivables and fee collection).
- Automated compounding late payment interest or financial penalty calculations (Denda keterlambatan berbunga).
- Digital wallet (e-wallet balances or prepaid top-ups) within the school.

## User Stories

- **As a school treasurer (Bendahara)**, I want to define the monthly SPP fee for academic year 2026/2027 as Rp 350.000, and generate 12 monthly bills for all enrolled Grade 5 students with a single action.
- **As a school cashier (Staf TU)**, I want a parent who visits my office with cash to pay SPP for September and October, enter the payment, and immediately print an official receipt (Kuitansi) with a unique number.
- **As a working guardian**, I want to log into my Presensio portal, see that September's SPP is due next week, transfer the exact amount from my mobile banking app, upload a screenshot of the transfer slip, and track verification status.
- **As a school cashier**, I want to open my verification queue, review the uploaded transfer slip for Ahmad's October SPP, verify that the funds arrived in the school bank account, and click "Verifikasi Pembayaran" to mark the bill as paid.
- **As a school principal**, I want to view a real-time summary of total fees collected this month and see which classes have the highest outstanding arrears (tunggakan), so we can plan operational expenditures.

## Decisions

- **Fee Types Hierarchy (`fee_types`)**:
  - `type` enum: `monthly_recurring` (e.g. SPP Bulanan) vs `one_time` (e.g. Uang Pangkal, Uang Seragam, Biaya Ujian, Biaya Wisuda).
  - Fees belong to an academic year or are global with an active flag.
- **Billing Entity (`bills`)**:
  - A bill is an individual payable obligation issued to a specific student (`student_id`).
  - For recurring monthly fees, the bill specifies `bill_month` (integer 1 through 12, representing January through December, or July through June).
  - Status progression:
    $$\text{unpaid} \longrightarrow [\text{pending\_verification}] \longrightarrow \text{partially\_paid} \mid \text{paid} \mid \text{waived}$$
  - A bill tracks `amount` (total nominal due) and `paid_amount` (accumulated verified payments).
- **Payment Transaction Entity (`payments`)**:
  - Every verified or submitted payment generates a row in `payments`.
  - Holds a unique serialized invoice/receipt code (e.g. `KWT-202609-00142`).
  - Supports partial payments: if a student owes Rp 500.000 and pays Rp 300.000, the bill status transitions to `partially_paid`, leaving a remaining balance of Rp 200.000.
  - Payment method enum: `cash` (tunai) or `bank_transfer` (transfer).
- **Verification Gate for Bank Transfers**:
  - When a guardian submits proof of transfer, the payment is created with status `pending_verification`, and the parent bill shows "Menunggu Verifikasi".
  - Only authorized cashiers/admins can verify (`status = 'verified'`) or reject (`status = 'rejected'` with explanatory reason).
  - Only verified payments increment the bill's `paid_amount` and alter bill status.
- **Official Digital Kuitansi**:
  - Generated on-the-fly via printable Blade template / PDF.
  - Contains School Kop Surat, Kuitansi Number, Student Name, NIS, Class, Payment Item, Amount (angka & terbilang), Cashier Name, and Date.
- **Role Scoping & Access Control**:
  - `admin` / `treasurer` (staff with finance role): Full access to create fee types, generate bills, record cash payments, verify transfers, and view school-wide financial reports.
  - `parent`: Can view bills and payments strictly belonging to their linked children (`guardian_student`), and upload transfer slips.
  - `student`: Read-only view of their own billing status (no payment submission).
  - `teacher`: No financial access by default (financial privacy from classroom teachers).

## Requirements

1. **Fee Type Catalog (`/admin/fees/types`)**:
   - Admin CRUD for fee types.
   - Fields: `name` (required, string, e.g. "SPP Bulanan 2026/2027"), `code` (required, unique, e.g. "SPP-2627"), `type` (required enum: `monthly_recurring`, `one_time`), `default_amount` (required, decimal > 0), `description` (optional text), `is_active` (boolean).
2. **Bulk Bill Generator (`/admin/fees/generate`)**:
   - Allows admin to generate bills in bulk:
     - Target cohort: Select Class, Grade Level, or All Active Students in the active academic year.
     - Fee type selector.
     - If `monthly_recurring`: Select range of months (e.g. Juli 2026 s/d Juni 2027).
     - Due date per month (e.g. tanggal 10 setiap bulan).
   - Generates individual `bills` rows transactionally, skipping students who already have an existing bill for that fee type and month (duplicate prevention).
3. **Cashier Point-of-Sale Payment Entry (`GET /admin/fees/cashier`)**:
   - Search student by name or NIS.
   - Displays all outstanding and unpaid bills for that student.
   - Cashier selects bill(s) to pay, inputs received amount, chooses payment method `cash`, adds optional note.
   - Automatically marks bill as `paid` or `partially_paid`, stamps `verified_by_user_id = auth()->id()`, and opens printable Kuitansi in a pop-up window.
4. **Transfer Verification Queue (`GET /admin/fees/verifications`)**:
   - Lists all payments submitted by guardians with `status = 'pending'`.
   - Displays student name, class, bill item, transfer amount, transfer timestamp, and clickable image preview of the bank receipt (`payment_proof_path`).
   - "Verify" action: Approves payment, updates `payments.status = 'verified'`, recalculates bill `paid_amount`, and updates bill status.
   - "Reject" action: Rejects payment with required rejection reason note (`reject_reason`). Parent is notified of the rejection on their portal.
5. **Guardian Tagihan Portal (`GET /parent/children/{id}/fees`)**:
   - Displays summarized balance: Total Belum Dibayar, Total Menunggu Verifikasi, Total Lunas.
   - Chronological list of bills: Month/Fee Name, Due Date, Nominal, Status Badge, Paid Amount, and "Bayar / Unggah Bukti" button.
   - Payment history tab: Lists all verified payments with a "Cetak Kuitansi" button.
   - Upload modal: Allows guardian to select target bill, input transfer date, transfer bank origin, transferred amount, and attach image/PDF file (max 5MB).
6. **Financial Reports & Arrears Ledger (`/admin/fees/reports`)**:
   - *Buku Kas Harian*: Chronological log of all verified payments within a date range, grouped by cashier, with total cash and total bank transfer subtotals.
   - *Laporan Tunggakan (Arrears)*: Per-class table showing enrolled student count, total billed, total collected, total outstanding tunggakan, and list of students with overdue bills.
   - All reports exportable to Excel-compatible CSV.

## Schema

### 1. `fee_types` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `academic_year_id` | bigint | unsigned, nullable | Foreign key -> `academic_years.id` |
| `name` | varchar(100) | not null | Name of fee (e.g. "SPP Bulanan", "Uang Gedung") |
| `code` | varchar(30) | not null, unique | Code (e.g. "SPP-2026", "GEDUNG-2026") |
| `type` | varchar(30) | not null | Enum: `monthly_recurring`, `one_time` |
| `default_amount` | decimal(12,2) | not null | Standard nominal amount (Rupiah) |
| `description` | text | nullable | Fee purpose details |
| `is_active` | boolean | not null, default: true | Active status |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (code)`
- `INDEX (academic_year_id, is_active)`

### 2. `bills` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `student_id` | bigint | unsigned, not null | Foreign key -> `students.id` |
| `fee_type_id` | bigint | unsigned, not null | Foreign key -> `fee_types.id` |
| `academic_year_id` | bigint | unsigned, not null | Foreign key -> `academic_years.id` |
| `title` | varchar(255) | not null | Descriptive title (e.g. "SPP September 2026") |
| `bill_month` | tinyint | unsigned, nullable | Month number (1 to 12) for recurring fees |
| `bill_year` | smallint | unsigned, nullable | Calendar year of bill (e.g. 2026) |
| `amount` | decimal(12,2) | not null | Total nominal billed (IDR) |
| `paid_amount` | decimal(12,2) | not null, default: 0.00 | Total nominal verified paid |
| `due_date` | date | not null | Payment deadline |
| `status` | varchar(30) | not null, default: 'unpaid' | Enum: `unpaid`, `pending_verification`, `partially_paid`, `paid`, `waived` |
| `notes` | varchar(255) | nullable | Special notes / scholarship remarks |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `INDEX (student_id, status)`
- `INDEX (fee_type_id, bill_month, bill_year)`
- `INDEX (academic_year_id, due_date)`

### 3. `payments` Table

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key | Internal identifier |
| `bill_id` | bigint | unsigned, not null | Foreign key -> `bills.id` |
| `payment_number` | varchar(50) | not null, unique | Serial receipt code (e.g. "KWT-202609-0012") |
| `amount` | decimal(12,2) | not null | Amount paid in this transaction |
| `payment_method` | varchar(20) | not null | Enum: `cash`, `bank_transfer` |
| `payment_proof_path` | varchar(255) | nullable | Path to uploaded bank transfer receipt |
| `payment_date` | date | not null | Date payment occurred |
| `status` | varchar(30) | not null, default: 'pending' | Enum: `pending`, `verified`, `rejected` |
| `reject_reason` | text | nullable | Reason if rejected by cashier |
| `notes` | varchar(255) | nullable | Bank account origin or cashier notes |
| `submitted_by_user_id` | bigint | unsigned, nullable | User who initiated / uploaded payment |
| `verified_by_user_id` | bigint | unsigned, nullable | Staff member who verified transaction |
| `verified_at` | timestamp | nullable | Timestamp of verification |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

**Indexes:**
- `UNIQUE (payment_number)`
- `INDEX (bill_id, status)`
- `INDEX (payment_date, status)`

## Acceptance Criteria

- **AC-14-01**: Given an active student, generating SPP for September 2026 with amount Rp 350.000 creates a bill with `amount = 350000.00`, `paid_amount = 0.00`, and `status = 'unpaid'`.
- **AC-14-02**: Attempting to generate a duplicate bill for the same student, same fee type, and same month/year is detected and skipped without duplicating rows.
- **AC-14-03**: When a cashier enters a cash payment of Rp 350.000 against an unpaid bill of Rp 350.000, a payment record is created with `status = 'verified'`, the bill status updates immediately to `paid`, and a unique receipt number `KWT-YYYYMM-XXXXX` is generated.
- **AC-14-04**: When a guardian uploads a transfer receipt via `/parent/children/{id}/fees`, a payment record is created with `status = 'pending'`, and the bill displays status `pending_verification`.
- **AC-14-05**: When the cashier rejects a pending payment with note "Nominal transfer kurang Rp 50.000", the payment status becomes `rejected`, the bill status reverts to `unpaid`, and the guardian sees the rejection note.
- **AC-14-06**: When a cashier verifies a partial payment of Rp 200.000 on a Rp 500.000 bill, the bill's `paid_amount` becomes `200000.00` and its status becomes `partially_paid`.
- **AC-14-07**: A parent attempting to view fee bills or payment receipts belonging to an unlinked student receives HTTP 403 Forbidden.
- **AC-14-08**: A classroom teacher attempting to access `/admin/fees` or student financial records receives HTTP 403 Forbidden.

## Constraints & Assumptions

- Currency format: Indonesian Rupiah (IDR / Rp) formatted with standard punctuation (e.g. `Rp 350.000,00`).
- Database precision: `decimal(12,2)` supports transactions up to Rp 9.999.999.999,99.
- Cashier receipt serial numbers reset or serialize monotonically (e.g. `KWT-{YYYYMM}-{00001}`).

## Open Questions

- `[NEEDS DECISION: WhatsApp Payment Reminders]`: Should the automated notification engine (spec 05) send a WhatsApp bill reminder to guardians 3 days before `due_date`? (Recommended for v1.1; v1 provides portal dashboard warnings).
- `[NEEDS DECISION: Automated QRIS Payment in v1.x]`: When the school registers an official bank/payment gateway account (e.g. Midtrans or BCA QRIS), dynamic QRIS codes can be rendered directly inside the parent payment modal for instant settlement without manual verification.
