# Fees A — Chart of Accounts, Journals & Accounting Periods Implementation Plan

> **For implementer agents:** Spec: `docs/specs/14-school-fees.md` §Chart of accounts, §Journals, §Accounting periods & closing (month close part), Requirements 1, 2 (manual part), 11, 13; ACs 14-16, 14-18 (manual part), 14-23, 14-24. Conventions: `2026-10-02-roadmap-wave-2-3.md`.

**Goal:** A correct, immutable double-entry core that later plans post into: accounts, journal entries/lines, posting service with invariants, period lock, manual journals (draft/post/reverse), and the first ledger reports.

**Architecture:** All money logic lives in `app/Services/Finance/`. `JournalPoster` is the only code that inserts `journal_entries`/`journal_lines`; it enforces balanced entries, postable accounts, one-sided lines, open periods, and entry-number sequencing inside the caller's transaction. Models for posted rows reject update/delete. Money values are handled as integer-valued strings via `bcmath` helpers (no float).

**Prerequisites:** Plan 1 (`finance` role, `ManageFees` ability, audit log). Plan 2's school profile only for later reports.

**Blocking questions:** the spec says the school's accountant should validate the chart and posting rules before go-live — put that on the go-live checklist, not a blocker for coding. Separation-of-duties (spec open question) is **not** implemented; add later as a rule inside `JournalPoster`/void if decided.

---

### Task 1: Money helper + schema

**Files:**

- Create: `app/Support/Money.php` (`add`, `sub`, `cmp`, `isZero`, `format` — `bcmath` on 2-dp strings), migrations `create_accounts_table`, `create_journal_entries_table`, `create_journal_lines_table`, `create_accounting_periods_table`, `create_journal_sequences_table` (`period`, `last_number`), `add_ledger_settings_to_settings_table` (`fiscal_year_start_month`, three account FKs nullable until seeded) — spec §Schema 7–11
- Create: models `Account`, `JournalEntry`, `JournalLine`, `AccountingPeriod` (+factories); enums `AccountType`, `JournalStatus`, `JournalSource`, `NetAssetRestriction`
- Test: `tests/Unit/Support/MoneyTest.php`

**Dependencies:** none · **Verification:** `php artisan test --compact tests/Unit/Support`

- [ ] **Step 1:** `ext-bcmath` is confirmed present in the Sail image (2026-10-02); add `"ext-bcmath": "*"` to `composer.json` `require` (platform requirement, not a package) so CI/prod fail loudly if missing. Never substitute floats.
- [ ] **Step 2:** Models for posted data: `JournalEntry` and `JournalLine` `updating`/`deleting` throw when `status = posted` (drafts may be edited/deleted).

### Task 2: Default chart seeder + account management

**Files:**

- Create: `database/seeders/ChartOfAccountsSeeder.php` (spec table incl. sample 5-xxxx expense accounts; sets the three settings FKs), `app/Http/Controllers/Finance/AccountController.php`, `app/Http/Requests/Finance/SaveAccountRequest.php`, `resources/js/pages/finance/accounts.tsx` (tree view), `routes/finance.php`
- Modify: `routes/web.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/finance/AccountTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Feature/finance/AccountTest.php`

- [ ] **Step 1:** Rules: unique `code`; `type` immutable once any line is posted; delete 422 when lines exist (AC-14-23) — deactivate instead; contra flag; `restriction` only for net assets; only leaves are postable; seeded chart editable until the first posted entry (then structural edits limited to name/active).
- [ ] **Step 2:** Admin and finance manage; principal read-only (403 on write, AC-14-24 partial).

### Task 3: Periods and entry numbering

**Files:**

- Create: `app/Services/Finance/PeriodService.php` (`ensure(YYYY-MM)`, `isOpen(date)`, `nextOpenPeriodStart(date)`), `app/Services/Finance/JournalNumberGenerator.php` (`SELECT … FOR UPDATE` on `journal_sequences`, `JU-YYYYMM-NNNNN`)
- Test: `tests/Unit/Services/Finance/PeriodServiceTest.php`, `tests/Feature/finance/JournalNumberConcurrencyTest.php`

**Dependencies:** Task 1 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance tests/Feature/finance/JournalNumberConcurrencyTest.php`

- [ ] **Step 1:** Missing period rows are treated as open and created lazily. Number test: two sequential allocations inside separate transactions are consecutive and gap-free for a period; concurrency is asserted by running the generator twice in nested connection transactions where the DB driver supports it, otherwise a unit test documenting the lock query (state in the PR which was done; the real race test belongs to plan 13's receipt numbers, same pattern).

### Task 4: `JournalPoster`

**Files:**

- Create: `app/Services/Finance/JournalPoster.php`, `app/Services/Finance/Exceptions/{UnbalancedEntry,ClosedPeriod,InvalidLine}Exception.php` (extend `RuntimeException`, mapped to HTTP 422 in `bootstrap/app.php` renderable for the finance routes), DTOs `PostableEntry`, `PostableLine`
- Test: `tests/Unit/Services/Finance/JournalPosterTest.php`

**Dependencies:** Tasks 1–3 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance/JournalPosterTest.php`

- [ ] **Step 1:** API: `post(PostableEntry $entry): JournalEntry` — requires an active outer transaction (`DB::transactionLevel() > 0`, else throw `LogicException`), validates Σ debit = Σ credit and each line exactly-one-sided > 0 (AC-14-16), accounts postable and active, entry date's period open; otherwise throws and nothing is written. Also `reverse(JournalEntry $original, CarbonInterface $on, string $reason): JournalEntry` mirroring every line, `reverses_entry_id` set, original untouched; if `$on`'s period is closed, post on `nextOpenPeriodStart` with the original date in the memo (AC-14-18 second half).
- [ ] **Step 2:** Tests: balanced happy path, unbalanced rejected with no rows, two-sided line rejected, closed period rejected, reversal mirrors and leaves original unchanged (AC-14-17 core), reversal into closed period lands in the next open period.

### Task 5: Manual journals

**Files:**

- Create: `app/Http/Controllers/Finance/ManualJournalController.php`, `app/Http/Requests/Finance/SaveJournalRequest.php`, `resources/js/pages/finance/journals/{index,form,show}.tsx`, attachment handling (private disk, nota/kuitansi)
- Modify: `routes/finance.php`
- Test: `tests/Feature/finance/ManualJournalTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Feature/finance/ManualJournalTest.php`

- [ ] **Step 1:** Save as `draft` (editable/deletable, not in ledger), `post` (runs `JournalPoster`; unbalanced → 422, closed period → 422), `reverse` with required reason. Access: finance + admin write, principal read (AC-14-24: principal posting → 403).
- [ ] **Step 2:** Opening balances use this screen with a go-live-date-minus-one entry and a net-assets counterpart; document in the page helper text, no special code (per-student opening bills are plan 13).

### Task 6: Ledger reports (General Journal, Ledger, Trial Balance, Cash Book)

**Files:**

- Create: `app/Services/Finance/Reports/{GeneralJournal,GeneralLedger,TrialBalance,CashBook}Report.php`, `app/Http/Controllers/Finance/ReportController.php`, `resources/js/pages/finance/reports/*.tsx`
- Modify: `routes/finance.php`
- Test: `tests/Unit/Services/Finance/Reports/TrialBalanceTest.php`, `tests/Feature/finance/LedgerReportTest.php`

**Dependencies:** Task 4 · **Verification:** `php artisan test --compact tests/Unit/Services/Finance/Reports tests/Feature/finance/LedgerReportTest.php`

- [ ] **Step 1:** Trial balance totals equal after any random sequence of posted entries (property-style test with seeded random entries); ledger running balance respects normal balance and contra accounts; cash book = one `is_cash` account. CSV export (UTF-8 BOM) via the shared writer; date-range filters.
- [ ] **Step 2:** Principal and above read (AC-14-24 reading ledger/TB → 200).

### Task 7: Month close and reopen (preconditions arrive with plan 14)

**Files:**

- Create: `app/Http/Controllers/Finance/PeriodController.php`, `resources/js/pages/finance/periods.tsx`
- Modify: `app/Services/Finance/PeriodService.php` (`close`, `reopen`)
- Test: `tests/Feature/finance/PeriodTest.php`

**Dependencies:** Task 6 · **Verification:** `php artisan test --compact tests/Feature/finance/PeriodTest.php`

- [ ] **Step 1:** `close` requires trial balance to balance now; the receivables-reconciliation precondition is added by plan 14 Task 4 through a `ClosePrecondition` interface registered here with the trial-balance check only (AC-14-20 completed there). `reopen` requires a reason and audits. Closed period blocks posting (covered in Task 4).

### Task 8: Close out

- [ ] `composer ci:check`. Nothing user-visible ships in fees until Plan 13; spec 14 stays Draft, README gets a "ledger core shipped" note on approval.
