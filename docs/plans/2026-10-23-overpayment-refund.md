# Overpayment Receipt and Refund Implementation Plan

> **For implementers:** Execute tasks in order; read the complete task and its owner tests before editing. Completed 2026-10-23; verification evidence follows the acceptance coverage table.

**Goal:** Record guardian excess receipts and settle them with a full refund liability debit, with bank fees deducted from the amount received by the guardian and visible as a separate journal line.

**Source:** `docs/specs/22-overpayment-refund.md` v1.3, including AC-22-01 through AC-22-27. Spec 21 is implemented. User decisions on 2026-10-23 approve account 2-1200 for planning and make the guardian responsible for bank fees. Accountant confirmation is required before go-live, not before technical planning.

**Architecture:** An immutable `Overpayment` owns receipt/refund/cancellation facts and journal links. A transactional service uses the existing `JournalPoster`, `Money`, `PeriodService` and `AuditLogger`. A separate reconciliation service reconstructs each captured liability account as of a journal date. Existing payment/bill services remain unchanged; mixed receipts follow the documented manual split procedure.

**Prerequisites:** No new package, environment variable or external service. Laravel 13.31.0, inertia-laravel 3.3.4, Wayfinder 0.1.21 and PHPUnit 12.5.35 were confirmed in this session; `package.json` declares Inertia React ^3 and React ^19.2. Read `.ai/rules/index.md`, matching rules and the Laravel/testing/Inertia/Wayfinder skills. Use Boost `search-docs` for API changes when available; if absent, inspect installed framework/package source as well as existing consumers. Node runs only through `vendor/bin/sail npm`.

**Boundaries:** Do not implement school-borne fees, partial liability settlement, credit balances, notifications, approval roles, bank integrations or a general period-lock redesign. Do not rerun the whole chart seeder against an existing live chart to install one new account. No automatic commit, push, database reset or deployment is authorized by this document.

## File map

| Area | Exact files |
| --- | --- |
| Domain | Create `app/Models/Overpayment.php`, `database/factories/OverpaymentFactory.php`, `app/Enums/OverpaymentStatus.php`, `app/Enums/OverpaymentReason.php` |
| Schema | Create the migrations emitted by the two explicit Artisan commands in Task 1; names are timestamped at execution, not pinned to this plan's date |
| Settings | Modify `app/Models/SchoolSetting.php`, `app/Services/SchoolSettings.php`, `app/Http/Requests/Settings/UpdateSchoolProfileRequest.php`, `app/Http/Controllers/Settings/SchoolProfileController.php`, `resources/js/pages/settings/school.tsx`, `database/seeders/ChartOfAccountsSeeder.php` |
| Lifecycle | Create `app/Services/Finance/OverpaymentService.php`, `app/Services/Finance/Exceptions/OverpaymentOperationException.php`; modify `bootstrap/app.php`, `app/Enums/JournalSource.php`, `app/Services/Finance/JournalPoster.php` |
| HTTP/read model | Create `app/Http/Controllers/Fees/OverpaymentController.php`, `app/Http/Requests/Fees/StoreOverpaymentRequest.php`, `app/Http/Requests/Fees/RefundOverpaymentRequest.php`, `app/Http/Requests/Fees/CancelOverpaymentRequest.php`, `app/Services/Finance/OverpaymentReadModel.php`; modify `routes/fees.php` |
| Reconciliation | Create `app/Services/Finance/GuardianDepositReconciliation.php`, `app/Services/Finance/Preconditions/GuardianDepositReconciliationPrecondition.php`; modify `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Finance/ReportController.php` |
| Navigation/UI | Create `resources/js/pages/fees/overpayments.tsx`, `resources/js/pages/fees/overpayment.tsx`; modify `resources/js/components/app-sidebar.tsx`, `resources/js/pages/finance/reports/receivables-reconciliation.tsx`, `resources/js/pages/finance/journals/show.tsx` |
| Source navigation/guards | Modify `app/Http/Controllers/Finance/ManualJournalController.php`, `app/Services/Finance/Reports/CashBookReport.php`, `app/Services/Finance/Reports/GeneralLedgerReport.php`, `resources/js/pages/finance/reports/cash-book.tsx`, `resources/js/pages/finance/reports/general-ledger.tsx`, `app/Http/Controllers/Guardians/GuardianController.php` |
| Tests | Create `tests/Feature/fees/OverpaymentTest.php`, `tests/Feature/fees/OverpaymentConcurrencyTest.php`, `tests/Feature/finance/GuardianDepositReconciliationTest.php`, `tests/Unit/Models/OverpaymentTest.php`; extend `tests/Feature/Settings/SchoolProfileTest.php`, `tests/Feature/finance/ManualJournalTest.php`, `tests/Feature/finance/YearEndClosingTest.php`, `tests/Feature/fees/FeeAccessTest.php`, `tests/Feature/Guardians/GuardianManagementTest.php` |

Generated frontend action/route files are regenerated with `--with-form`; never edit them by hand. Reuse existing settings, table, dialog, money and pagination patterns. Do not create a separate generic ledger framework or test superclass.

## Task 1: Add the captured receipt model and approved account configuration

**Files:** Domain/schema/settings rows in the map; test `tests/Unit/Models/OverpaymentTest.php` and `tests/Feature/Settings/SchoolProfileTest.php`.

**Dependencies:** None.

**What this does:** Persists immutable historical receipt facts and supplies a dedicated liability account for new receipts.

**Verification:** Schema/model owner tests prove FK preservation and immutability; settings tests accept only a dedicated active postable non-contra liability.

- [x] **Step 1: Scaffold with Artisan.**

```sh
php artisan make:model Overpayment --factory --no-interaction
php artisan make:enum OverpaymentStatus --string --no-interaction
php artisan make:enum OverpaymentReason --string --no-interaction
php artisan make:migration create_overpayments_table --create=overpayments --no-interaction
php artisan make:migration add_guardian_deposit_account_id_to_settings_table --table=settings --no-interaction
php artisan make:test --phpunit --unit Models/OverpaymentTest --no-interaction
```

Read the filenames printed by `make:migration`; keep their generated timestamps. Implement `up`/`down` in each. The settings migration adds exactly:

```php
$table->foreignId('guardian_deposit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
```

Its `down` drops that FK/column. The overpayments migration creates this field contract:

| Fields | Storage / constraints |
| --- | --- |
| `guardian_id` | Required FK guardians, restrict deletion |
| `student_id` | Nullable FK students, null on deletion |
| `related_payment_id` | Nullable FK payments, restrict deletion |
| `liability_account_id`, `deposit_account_id` | Required FK accounts, restrict deletion |
| `amount` | decimal(15,2), positive receipt amount |
| `bank_mutation_date`, `posting_date` | Required dates; posting >= mutation |
| `reason`, `status` | varchar(30), typed enums; status initially held |
| `note`, `proof_path`, `proof_hash` | Nullable text note; required string path and 64-character hash |
| `receipt_entry_id` | Nullable unique FK journal_entries, restrict deletion; populated inside the receipt transaction before commit |
| `refund_entry_id`, `cancellation_entry_id` | Nullable unique FKs journal_entries, restrict deletion |
| `refund_date`, `requested_cancel_date` | Nullable dates |
| `refund_account_id` | Nullable FK accounts, restrict deletion |
| `bank_fee`, `net_refund` | Nullable decimal(15,2), set on refund only |
| `bank_reference` | Nullable varchar(255), required when refunding |
| `refund_proof_path`, `refund_proof_hash` | Nullable string/64-character hash |
| `cancel_reason` | Nullable text, required when cancelling |
| `recorded_by_user_id`, `refunded_by_user_id`, `cancelled_by_user_id` | Required recorder / nullable terminal actors, users FK null on deletion only if field is nullable; recorder restricts deletion |
| `refunded_at`, `cancelled_at`, timestamps | Nullable UTC terminal stamps plus standard timestamps |

Index `(status, guardian_id)`, `posting_date`, `proof_hash` and `liability_account_id`. The model casts money to decimal:2 and dates/enums consistently with `Payment`. Relationships: `guardian`, `student`, `relatedPayment`, `liabilityAccount`, `depositAccount`, `refundAccount`, `receiptEntry`, `refundEntry`, `cancellationEntry`, and actor relations. Enum values are `Held`, `Refunded`, `Cancelled`; reasons `DoublePayment`, `WrongAmount`, `Other`, with Indonesian display labels.

The service is the sole operational writer. Model events reject receipt-field edits and deletion after the receipt entry is attached. Permit only the initial one-time receipt-entry attachment, then the held-to-refunded or held-to-cancelled fields listed above; reject edits to terminal records. Factory fixtures may construct incomplete rows for integrity tests, but lifecycle/reconciliation tests must create real receipts through the service.

- [x] **Step 2: Extend the chart/settings boundary.** Add this entry to `ChartOfAccountsSeeder`:

```php
[
    'code' => '2-1200', 'name' => 'Titipan Wali (Kelebihan Pembayaran)',
    'type' => AccountType::Liability, 'is_contra' => false, 'restriction' => null,
    'is_postable' => true, 'is_cash' => false, 'is_active' => true,
],
```

For fresh installations set the new setting to this account; preserve an already configured custom guardian-deposit account. Existing installations migrate the nullable column and let admin select/create the approved account through the existing chart/settings UI. Add the field, relationship, fillable/cast/PHPDoc and a `SchoolSettings::guardianDepositAccountId(): ?int` accessor without introducing new request-state caches.

`UpdateSchoolProfileRequest` validates a nullable account ID with `Rule::exists` restricted to `type=liability`, `is_active=true`, `is_postable=true`, `is_contra=false`, `is_cash=false`. Reject equality with the current prepaid-revenue account. Include eligible account options and the setting in the profile payload; use a native select as the current transfer-account setting does. Admin configures it; finance/principal get a configuration notice, not an inaccessible settings link.

Add this concrete settings regression to the existing test class, importing `AccountType` and `Account`:

```php
public function test_guardian_deposit_account_must_be_a_dedicated_liability(): void
{
    $admin = User::factory()->admin()->create();
    $valid = Account::factory()->create(['type' => AccountType::Liability, 'is_cash' => false, 'is_contra' => false, 'is_active' => true, 'is_postable' => true]);
    $payload = ['school_name' => 'Sekolah QA', 'default_curriculum' => 'merdeka', 'guardian_deposit_account_id' => $valid->id];
    $this->actingAs($admin)->put(route('school-profile.update'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('settings', ['guardian_deposit_account_id' => $valid->id]);
    $contra = Account::factory()->create(['type' => AccountType::Liability, 'is_contra' => true]);
    $this->put(route('school-profile.update'), [...$payload, 'guardian_deposit_account_id' => $contra->id])
        ->assertSessionHasErrors('guardian_deposit_account_id');
    $this->assertDatabaseHas('settings', ['guardian_deposit_account_id' => $valid->id]);
}
```

Extend owner cases for cash, non-postable, inactive, prepaid and null choices; keep existing profile fields unaffected. Model tests must prove held amount/account/proof cannot be edited after posting and guardian deletion is restricted.

- [x] **Step 3: Run model/settings checks.**

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/Models/OverpaymentTest.php
php artisan test --compact tests/Feature/Settings/SchoolProfileTest.php
```

Expected: settings changes affect only future receipt defaults; no expense account or school-borne fee setting is added.

## Task 2: Implement receipt, three-line refund and cancellation services

**Files:** Lifecycle files in the map. Create `tests/Feature/fees/OverpaymentTest.php`.

**Dependencies:** Task 1.

**What this does:** Posts balanced lifecycle journals with historical accounts, dates, actors and immutable evidence.

**Verification:** Service owner cases assert exact journal lines and persisted terminal facts, not just HTTP status.

- [x] **Step 1: Scaffold the service and register sources/exceptions.**

```sh
php artisan make:class Services/Finance/OverpaymentService --no-interaction
php artisan make:class Services/Finance/Exceptions/OverpaymentOperationException --no-interaction
php artisan make:test --phpunit fees/OverpaymentTest --no-interaction
```

`OverpaymentOperationException` extends `RuntimeException`; add the same `RejectionResponse` rendering as existing finance operation exceptions in `bootstrap/app.php`. Add `JournalSource` cases/labels `OverpaymentReceipt`, `OverpaymentRefund`, `OverpaymentCancellation` with values from the spec. Add the receipt-to-cancellation mapping in `JournalPoster::reverse`, preserving existing payment/bill mappings.

Inject `JournalPoster`, `SchoolSettings`, `PeriodService` and `AuditLogger` into `OverpaymentService`. Public contracts:

```php
public function record(array $data, User $actor): Overpayment
public function refund(Overpayment $overpayment, array $data, User $actor): Overpayment
public function cancel(Overpayment $overpayment, string $date, string $reason, User $actor): Overpayment
```

Use array-shape PHPDoc: record data contains the receipt columns from Task 1 except generated IDs/status/actor/journal links; refund data is `{refund_date: string, refund_account_id: int, bank_fee: string, bank_reference: string, refund_proof_path?: string|null, refund_proof_hash?: string|null}`. Derive net and bank total server-side; reject inconsistent caller totals in the request validator if supplied. All three methods require the held admin or finance capability, regardless of active role.

For `record`, in one transaction: resolve/validate the configured account; validate guardian, optional student and related verified bank-transfer links; validate active/postable/non-contra bank asset; require positive amount and open posting date >= bank date; create held row with captured liability ID; post `Dr deposit / Cr captured liability` with `sourceId=$record->id`; attach receipt entry; write `overpayment_recorded` audit. Related-payment checks run under its row lock if supplied. No bill/allocation is changed.

For `refund`, lock/reload the overpayment and verify held before posting; validate the captured account and paying account, reference, open date >= receipt date, fee >=0 and < amount. Construct exactly these lines inside the transaction:

```php
$fee = Money::toDecimal($data['bank_fee']);
$net = Money::sub($locked->amount, $fee);
$lines = [
    new PostableLine(accountId: $locked->liability_account_id, debit: $locked->amount, studentId: $locked->student_id),
    new PostableLine(accountId: $data['refund_account_id'], credit: $net, studentId: $locked->student_id, memo: 'Dana bersih ke wali'),
];
if (Money::isPositive($fee)) {
    $lines[] = new PostableLine(accountId: $data['refund_account_id'], credit: $fee, studentId: $locked->student_id, memo: 'Biaya bank ditanggung wali');
}
$entry = $this->poster->post(new PostableEntry(
    entryDate: $data['refund_date'], description: 'Pengembalian kelebihan pembayaran #'.$locked->id,
    sourceType: JournalSource::OverpaymentRefund, sourceId: $locked->id,
    lines: $lines, createdByUserId: $actor->id,
));
```

Persist the entry link, fee, net, paying account/reference/proof, UTC actor stamp and `Refunded` in the same transaction; write one `overpayment_refunded` audit including net/fee/gross. If the captured account is inactive, reject with a request for admin reactivation; never substitute the new default.

For `cancel`, lock/reload held row, require nonblank reason and requested date >= receipt posting. Use `poster->reverse($locked->receiptEntry, Date::parse($date), $reason)`; verify its effective date is also >= receipt posting; store requested date/reversal link/actor/reason and `Cancelled`, and audit once. A rejected operation rolls back journals/record/audit together. Neither terminal operation sends notifications.

- [x] **Step 2: Use this refund-line regression after the fixture helper below.** In `OverpaymentTest::setUp`, authenticate a finance factory user, freeze `2026-10-23 03:00:00`, initialize settings then seed `ChartOfAccountsSeeder`, refresh `SchoolSettings` and fake local storage. Declare `$finance: User`, `$bank: Account`, `$service: OverpaymentService`; resolve the bank by seeded code `1-1200` and inject the service from the container. Add this local receipt helper:

```php
private function held(): Overpayment
{
    $guardian = Guardian::factory()->create();
    Storage::disk('local')->put('overpayment-proofs/receipt.pdf', 'receipt fixture');

    return $this->service->record([
        'guardian_id' => $guardian->id, 'student_id' => null, 'related_payment_id' => null,
        'amount' => '2700000.00', 'bank_mutation_date' => '2026-10-20', 'posting_date' => '2026-10-20',
        'deposit_account_id' => $this->bank->id, 'reason' => 'wrong_amount', 'note' => null,
        'proof_path' => 'overpayment-proofs/receipt.pdf', 'proof_hash' => hash('sha256', 'receipt fixture'),
    ], $this->finance);
}

public function test_guardian_fee_is_visible_as_a_separate_bank_credit(): void
{
    $held = $this->held();
    $result = $this->service->refund($held, [
        'refund_date' => '2026-10-22', 'refund_account_id' => $this->bank->id,
        'bank_fee' => '6500.00', 'bank_reference' => 'QA-REFUND-22',
    ], $this->finance);
    $lines = $result->refundEntry->lines()->orderBy('id')->get();
    $this->assertSame(['2700000.00', '0.00', '0.00'], $lines->pluck('debit')->all());
    $this->assertSame(['0.00', '2693500.00', '6500.00'], $lines->pluck('credit')->all());
    $this->assertSame([$held->liability_account_id, $this->bank->id, $this->bank->id], $lines->pluck('account_id')->all());
    $this->assertSame('Dana bersih ke wali', $lines[1]->memo);
    $this->assertSame('Biaya bank ditanggung wali', $lines[2]->memo);
    $this->assertSame('2693500.00', $result->net_refund);
    $this->assertSame(OverpaymentStatus::Refunded, $result->status);
    $this->assertSame(1, AuditLog::where('action', 'overpayment_refunded')->count());
}
```

Add owner cases with exact values: fee 0 gives two lines; fee -1, 2700000 and 2700001 reject atomically; refund date 2026-10-19 rejects; valid receipt date 2026-10-20 plus requested cancellation 2026-10-19 rejects even when that month is closed; cancellation dated in a closed period reverses into the next open period with original account/lines and requested date retained. Repeating refund/cancel and crossing terminal states reject without additional audit/journal. Snapshot bill `paid_amount/status` before each lifecycle operation and assert unchanged. Confirm receipt/refund/cancel each has exactly one audit with the current actor.

- [x] **Step 3: Run the owner suite.**

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/fees/OverpaymentTest.php
php artisan test --compact tests/Unit/Services/Finance/PaymentServiceTest.php
```

Expected: the fee is a Bank credit, not an expense or a separate guardian-fee account; unchanged payment-service tests still pass.

## Task 3: Add guarded routes, private proofs and journal-source navigation

**Files:** HTTP/read-model and source-guard rows in the map; extend existing guardian and manual-journal tests.

**Dependencies:** Tasks 1–2.

**What this does:** Exposes authorized operations while preventing a general journal action from bypassing the overpayment lifecycle.

**Verification:** Endpoint tests prove permission unions, proof authorization, upload cleanup and direct reversal denial.

- [x] **Step 1: Scaffold controller/requests and register route contracts.**

```sh
php artisan make:controller Fees/OverpaymentController --no-interaction
php artisan make:request Fees/StoreOverpaymentRequest --no-interaction
php artisan make:request Fees/RefundOverpaymentRequest --no-interaction
php artisan make:request Fees/CancelOverpaymentRequest --no-interaction
php artisan make:class Services/Finance/OverpaymentReadModel --no-interaction
```

Add read routes under `auth, role:admin,finance,principal`:

```php
Route::get('fees/overpayments', [OverpaymentController::class, 'index'])->name('fees.overpayments.index');
Route::get('fees/overpayments/{overpayment}', [OverpaymentController::class, 'show'])->name('fees.overpayments.show');
Route::get('fees/overpayments/{overpayment}/proof/{kind}', [OverpaymentController::class, 'proof'])
    ->whereIn('kind', ['receipt', 'refund'])->name('fees.overpayments.proof');
```

Write routes under `auth, role:admin,finance`: POST `fees/overpayments` → `store` / `fees.overpayments.store`; POST `fees/overpayments/{overpayment}/refund` → `refund`; POST `fees/overpayments/{overpayment}/cancel` → `cancel`, named under `fees.overpayments`. Register literal paths before parameter paths where they could conflict.

The requests validate receipt IDs/links, numeric amount <=9999999999999.99 and >0, date format `Y-m-d`, reason enum, proof JPG/PNG/PDF <=5120KB; refund validates date/account/reference/fee and optional proof; cancel validates date/nonblank reason. Repeat mutable account/date/status checks in the service transaction. The read model paginates 20 newest IDs, scopes filters to status/guardian, eager-loads required relations and serializes only authorized fields; it produces `canManage` and held-only action flags. Use `whereLike` for any name search on PostgreSQL.

Controllers store proofs under private opaque filenames with SHA-256; wrap the service call in `try/catch (Throwable)` to delete only a newly stored file on any failed operation and rethrow. Authenticate/authorize before storage and before probing file existence. Refund and receipt proof routes check all read roles; an unauthorized user receives 403 before a possible 404. Do not expose private paths in page props.

- [x] **Step 2: Guard direct reversal and guardian deletion.** In `ManualJournalController::reverse`, after writer authorization and before posting, reject the three overpayment sources and a journal whose `reversesEntry` is one of those sources. Keep `JournalPoster::reverse` available to `OverpaymentService::cancel`. Expose `can_reverse` and a typed overpayment source link on journal detail; hide the manual reverse button for these sources. In `GuardianController::destroy`, reject referenced guardians with a user-facing explanation before attempting detach/delete; the FK is the final backstop.

Add this source-guard test after creating a held receipt with Task 2's service fixture in the owner test (use its actual finance actor):

```php
$before = JournalEntry::count();
$this->actingAs($this->finance)->postJson(route('finance.journals.reverse', $held->receipt_entry_id), [
    'reason' => 'forged bypass', 'date' => '2026-10-23',
])->assertUnprocessable();
$this->assertSame($before, JournalEntry::count());
$this->assertSame(OverpaymentStatus::Held, $held->refresh()->status);
```

Cover refund and cancellation journals after the corresponding service actions, including terminal records. Add principal GET list/detail/proof success plus write 403; parent/teacher/student/counselor/staff read/proof/write 403; admin+parent and finance+parent retain writer capabilities. Update `FeeAccessTest` for all six new route names, with a real receipt and suitable existing-record parameters; keep actor/data checks in `OverpaymentTest` rather than duplicating its state matrix. Prove invalid receipt/refund requests remove newly uploaded files and successful cancellation retains original evidence.

- [x] **Step 3: Regenerate routes and verify boundaries.**

```sh
vendor/bin/sail artisan wayfinder:generate --with-form --no-interaction
php artisan route:list --name=fees.overpayments
php artisan test --compact tests/Feature/fees/OverpaymentTest.php
php artisan test --compact tests/Feature/finance/ManualJournalTest.php
php artisan test --compact tests/Feature/Guardians/GuardianManagementTest.php
php artisan test --compact tests/Feature/fees/FeeAccessTest.php
```

Expected: no forged general reversal changes state and no unauthorized proof request discloses whether the file exists.

## Task 4: Reconcile every captured liability account and gate closing

**Files:** Reconciliation files, provider/report controller and reconciliation page in the map. Create `tests/Feature/finance/GuardianDepositReconciliationTest.php`; extend year-end tests.

**Dependencies:** Tasks 1–3.

**What this does:** Preserves historical month-end evidence even after refunds, cancellations or default-account changes.

**Verification:** As-of tests compare GL and held subledger per account; month/year close reject real mismatches while legitimate held money remains closable.

- [x] **Step 1: Implement the service contract and precondition.**

```sh
php artisan make:class Services/Finance/GuardianDepositReconciliation --no-interaction
php artisan make:class Services/Finance/Preconditions/GuardianDepositReconciliationPrecondition --no-interaction
php artisan make:test --phpunit finance/GuardianDepositReconciliationTest --no-interaction
```

Contract `asOf(string $date): array` returns `{as_of: string, configured: bool, accounts: list<array{account_id: int, code: string, name: string, gl_balance: string, subledger_balance: string, difference: string, is_balanced: bool}>, held: list<array{id: int, guardian_name: string, amount: string, liability_account_id: int, age_days: int}>, is_balanced: bool}`.

Use the union of distinct captured `liability_account_id` and the current setting, including inactive/settled accounts. Aggregate posted journal credits minus debits dated <=D for each account. For the subledger, include each receipt with receipt-entry date <=D and no refund/cancellation journal dated <=D, independent of current operational status. Group by captured account. Use `Money` for final differences and check every account separately; do not offset differences across accounts. Held age is D minus bank mutation date. No default/history yields `configured=false`, empty rows and balanced=true. Eager-load guardian names and journal dates; no per-row relationship queries.

`GuardianDepositReconciliationPrecondition` implements the existing `ClosePrecondition`. Its `check(AccountingPeriod $period): void` evaluates month end and throws `RuntimeException` when any account differs. Register it through the existing `PeriodService` binding with `addPrecondition`; year-end uses that same service when closing its final month. Do not change general period-lock behavior in this task.

- [x] **Step 2: Add the exact historical-case test.** Reuse Task 2's local receipt helper in this distinct owner class with a small local setup; do not extract a generic shared fixture superclass. Call the service, not a bare recognized/settled factory stamp:

```php
$held = $this->held();
$accountA = $held->liability_account_id;
$accountB = Account::factory()->create(['type' => AccountType::Liability, 'is_cash' => false, 'is_contra' => false]);
app(SchoolSettings::class)->row()->update(['guardian_deposit_account_id' => $accountB->id]);
app(SchoolSettings::class)->refresh();
$this->service->refund($held, [
    'refund_date' => '2026-11-05', 'refund_account_id' => $this->bank->id,
    'bank_fee' => '6500.00', 'bank_reference' => 'NOV-REFUND',
], $this->finance);
$october = app(GuardianDepositReconciliation::class)->asOf('2026-10-31');
$row = collect($october['accounts'])->firstWhere('account_id', $accountA);
$this->assertSame('2700000.00', $row['gl_balance']);
$this->assertSame('2700000.00', $row['subledger_balance']);
$this->assertTrue($october['is_balanced']);
$november = app(GuardianDepositReconciliation::class)->asOf('2026-11-30');
$this->assertTrue($november['is_balanced']);
$this->assertSame('0.00', collect($november['accounts'])->firstWhere('account_id', $accountA)['gl_balance']);
```

Extend with a manual journal to old account A after clearing/changing the default: reconciliation and month close must detect it even when all A receipts are settled. Create opposite manual differences on A/B to prove they do not cancel. Prove missing configuration with no history allows unrelated close; missing configuration with history still checks A. Test October close with a held balance succeeds; year-end failure rolls back its closing entry and locked periods when guardian deposits mismatch. Add a deterministic receipt/refund/cancel sequence checking trial-balance equality and the existing receivables reconciliation remains balanced.

- [x] **Step 3: Run owner checks.**

```sh
php artisan test --compact tests/Feature/finance/GuardianDepositReconciliationTest.php
php artisan test --compact tests/Feature/finance/PeriodTest.php
php artisan test --compact tests/Feature/finance/YearEndClosingTest.php
```

Expected: clearing a default cannot remove historical account checks, and held liability alone never prevents closing.

## Task 5: Complete role journeys, fee confirmation and journal links

**Files:** The two new fee pages, sidebar, settings page and report/journal pages in the map; read-model payloads from Tasks 1–4.

**Dependencies:** Tasks 1–4.

**What this does:** Makes recording, fee disclosure and auditing reachable from the normal app navigation.

**Verification:** JS/type gates plus the exact browser journeys in Task 7. Use existing controls/layouts and applicable frontend skills; no redesign or new library.

- [x] **Step 1: Build the list/detail/forms with these route/field contracts.** Add `Kelebihan Pembayaran` to the sidebar for active admin/finance/principal. List uses `overpayments` (20-row paginator), `filters`, `canManage` and `configuration`; detail uses `overpayment` with captured account and receipt/refund/cancel journal metadata. Do not shadow shared `auth`, `notifications` or `translations` props. Principal sees detail/proof links and no write buttons.

Record submits receipt fields and a file to the generated `OverpaymentController.store` action. Refund submits `refund_date`, `refund_account_id`, `bank_fee`, `bank_reference` and optional `proof` to the generated refund action; cancel submits `date` and `reason`. The browser may preview net using integer cents, while the service remains authoritative:

```tsx
const heldCents = Math.round(Number(overpayment.amount) * 100);
const feeCents = Math.round(Number(form.data.bank_fee || '0') * 100);
const netCents = heldCents - feeCents;
const validFee = feeCents >= 0 && netCents > 0;
```

Refund confirmation visibly labels `Nilai titipan`, `Biaya bank ditanggung wali`, `Diterima wali`, and `Total keluar dari bank`. Show both Bank credit memos in the journal and cash book; zero fee creates no zero journal row. Use `useForm` processing state, `InputError`, confirmation/dismiss actions and preserve recoverable input on failure. Configuration notice offers an admin School Profile link only when that user can configure it.

- [x] **Step 2: Add journal/source links and the reconciliation section.** Extend CashBook/GeneralLedger journal eager-load selections with `id,source_type,source_id`; serialize the source destination through named routes and use Wayfinder/controller actions for frontend links. Existing amount, memo and running-balance semantics remain unchanged. Journal detail gets `overpayment_id` and `can_reverse`; protected sources show a link to their owner instead of manual reverse controls. The receivables-reconciliation screen gains a distinct guardian-deposit section without combining the two subledgers; keep existing report/CSV columns intact and append a clearly labelled deposit section if exporting it from the same endpoint.

- [x] **Step 3: Run frontend and read-model checks.**

```sh
vendor/bin/sail artisan wayfinder:generate --with-form --no-interaction
vendor/bin/sail npm run check
vendor/bin/sail npm run types:check
php artisan test --compact tests/Feature/fees/OverpaymentTest.php
php artisan test --compact tests/Feature/finance/LedgerReportTest.php
```

Expected: every primary destination has a real sidebar path; every source link resolves to the correct overpayment; principal rendering is read-only and the bank-fee line is visible separately.

## Task 6: Prove terminal races on PostgreSQL

**Files:** Create `tests/Feature/fees/OverpaymentConcurrencyTest.php`.

**Dependencies:** Tasks 2–4.

**What this does:** Proves that two separate DB sessions cannot refund or cancel the same liability twice.

**Verification:** Real blocked-session tests, not sequential service calls or SQLite locks.

- [x] **Step 1: Scaffold the owner and copy only the bounded contention helper.**

```sh
php artisan make:test --phpunit fees/OverpaymentConcurrencyTest --no-interaction
```

Use the completed `tests/Feature/fees/PaymentReturnConcurrencyTest.php` as the exact source for `contend` (including process timeout, PID output, `pg_stat_clear_snapshot`, observed Lock, transaction cleanup and worker cleanup). Keep the helper local; change only the child dispatch to call `OverpaymentService`. Do not change the existing payment-return class or extract shared infrastructure. SQLite skips this new class before any reset; PostgreSQL asserts database name `testing` before `migrate:fresh`. Fixtures are committed before starting contention and auth is set in both processes.

Worker payload is `{kind: 'refund'|'cancel', user: int, overpayment: int, bank: int}`. Child calls one of these exact operations:

```php
$service->refund(Overpayment::findOrFail($op['overpayment']), [
    'refund_date' => '2026-10-23', 'refund_account_id' => $op['bank'],
    'bank_fee' => '6500.00', 'bank_reference' => 'CONCURRENT-REFUND',
], $user);
$service->cancel(Overpayment::findOrFail($op['overpayment']), '2026-10-23', 'Salah catat', $user);
```

Dispatch one based on kind; retain explicit 200/422/403 worker results from the existing helper. Parent holds the overpayment row lock before starting the child; wait until that child is observed blocked, then run/commit the winning service operation.

- [x] **Step 2: Exercise these three winner/loser combinations.** Two refunds: one refund journal and one refund audit. Parent refund/child cancel: refunded with only a refund entry. Parent cancel/child refund: cancelled with only a cancellation entry. Every loser is 422 and leaves no journal/audit addition. Assert exact linked entry IDs and balances, not only row counts; the original receipt remains posted.

For each completed case include:

```php
$record->refresh();
$this->assertNotSame($record->refund_entry_id !== null, $record->cancellation_entry_id !== null);
$this->assertTrue(app(GuardianDepositReconciliation::class)->asOf('2026-10-31')['is_balanced']);
$this->assertSame(1, AuditLog::where('auditable_type', $record->getMorphClass())
    ->where('auditable_id', $record->id)
    ->whereIn('action', ['overpayment_refunded', 'overpayment_cancelled'])->count());
```

Use the known receipt fixture from Task 2 with `2700000.00`, October dates, fee 6500 and a real receipt journal. Retrying either terminal operation after the race must not create a second settlement.

- [x] **Step 3: Execute the database-specific suite.**

```sh
php artisan test --compact tests/Feature/fees/OverpaymentConcurrencyTest.php
vendor/bin/sail exec -e APP_ENV=testing -e DB_CONNECTION=pgsql -e DB_DATABASE=testing -e DB_HOST=pgsql laravel.test php vendor/bin/phpunit tests/Feature/fees/OverpaymentConcurrencyTest.php
```

Expected: SQLite skips; PostgreSQL executes all three contention cases with no skips. Never point this test at development or production data.

## Task 7: Verify mixed receipts, browser journeys and release gates

**Files:** Existing owner tests from Tasks 2–6; spec 22, spec index and this plan for truthful completion status.

**Dependencies:** Tasks 1–6.

**What this does:** Verifies the approved accounting examples and the paths a real operator uses.

**Verification:** All ACs plus the table below; record missing evidence explicitly instead of marking delivery complete.

- [x] **Step 1: Add the split-transfer integration case to `OverpaymentTest`.** Create a linked parent/student, a 300000 bill recognized through `BillService`, and one bank proof. Submit/verify the ordinary bank-transfer payment with amount/allocation 300000 through the existing services, then record a related overpayment of 2700000 using the same bank account and payment-journal date. Assert total receipt Bank debits are 3000000, bill paid amount is 300000 and liability is 2700000. The shared proof warns but adds no debit. Refund with fee 6500 and assert net 2693500, total Bank credits 2700000, bill still paid, trial balance balanced and both subledgers reconciled. An original 3000000 payment allocating 300000 must still reject; no silent resizing or cashier substitution is allowed.

- [x] **Step 2: Walk each browser path on disposable test data.** Resolve URLs through Boost when available; use the same built-asset/isolated-testing-server approach recorded in the completed spec 21 plan if dev HMR prevents reliable navigation.

| Persona / path | Required evidence |
| --- | --- |
| Admin → Settings → School Profile | Select 2-1200, reject prepaid/contra choices; a new receipt captures the selected account |
| Admin/finance → Dashboard/sidebar → Kelebihan Pembayaran | Record with proof, open detail, preview evidence, see linked receipt journal |
| Finance → detail → Refund confirmation | Held 2700000, fee 6500, net 2693500, Bank total 2700000 visible before submission; resulting journal has one debit and two labelled Bank credits |
| Finance → held detail → Cancel | Mandatory reason, date handling and retained receipt proof; terminal buttons disappear after success |
| Principal → sidebar → list → detail/proof | Read-only controls; journal/report links work; forged writes fail |
| Admin/finance/principal → General Ledger/Cash Book → journal → overpayment | Source links are correct; general reverse action is unavailable for these sources |
| Finance → reconciliation → Accounting Periods | Held balances reconcile and allow close; a deliberate test-only unmatched journal prevents close with a useful message |
| Missing setting with existing history | Record disabled, old detail/refund works with captured account after valid account reactivation |
| Parent/student/teacher-only | Destination absent and direct read/proof/write requests forbidden |

Inspect only fresh console/server errors. Cover empty list, invalid proof, failed upload cleanup, zero fee, page filters/pagination, keyboard confirmation/dismissal and loading states. Use existing role switcher for multi-role personas; changing active role does not change server capability.

- [x] **Step 3: Run final gates and record status.**

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/fees/OverpaymentTest.php
php artisan test --compact tests/Feature/finance/GuardianDepositReconciliationTest.php
php artisan test --compact tests/Feature/finance/ManualJournalTest.php
php artisan test --compact tests/Feature/Settings/SchoolProfileTest.php
php artisan test --compact tests/Feature/finance/YearEndClosingTest.php
vendor/bin/sail composer ci:check
vendor/bin/sail npm run build
```

Run each changed test owner after its last edit, and the PostgreSQL gate separately. If CI exposes old route-matrix or fixture assumptions, adjust only those owner assertions to the new documented contract, preserving rejection atomicity and balanced-ledger checks. Move generated browser artifacts outside the repository before the final formatting gate. Only after all required proof passes, update spec 22/index to Implemented and MOC delivery status. Accountant deployment review remains a go-live gate even after code completion.

## Acceptance coverage

| Criteria | Owner |
| --- | --- |
| AC-22-01…08 | Tasks 1–3: receipt/refund/cancel fields, exact journal lines, dates and link validation |
| AC-22-09…10 | Tasks 1–3: duplicate warning and account configuration |
| AC-22-11…13 | Task 4 and Task 7: historical/per-account closing and ledger invariants |
| AC-22-14…15 | Tasks 2–3: unchanged bills, audits, permission/proof boundaries |
| AC-22-16 | Task 6: two refunds with real contention |
| AC-22-17…18 | Tasks 2/5: separate fee Bank credit, zero-fee omission and authoritative totals |
| AC-22-19…20 | Tasks 1/4: captured historical accounts, default changes and non-offsetting differences |
| AC-22-21…22 | Tasks 2–3: cancellation chronology and general-journal bypass prevention |
| AC-22-23 | Task 7: explicit split-transfer integration workflow |
| AC-22-24 | Task 6: refund/cancel race, both winners and retry immutability |
| AC-22-25…27 | Tasks 1/3/5/7: navigation, guardian preservation, private evidence and cleanup |


## Completion and verification — 2026-10-23

- All seven tasks implemented. No new package, environment variable, payment/bill schema change or school-borne fee policy was introduced. Existing installations receive a nullable setting and configure their dedicated liability account; the chart seeder supplies 2-1200 for fresh installations without overwriting a custom guardian-deposit setting.
- Full `vendor/bin/sail composer ci:check` passed: 1,616 cases, 1,609 passed, 7 PostgreSQL-only skips, 8,330 assertions. JS format/lint, TypeScript, Pint and PHPStan passed. `vendor/bin/sail npm run build` passed.
- `OverpaymentConcurrencyTest` executed separately against guarded PostgreSQL database `testing`: **3 tests / 45 assertions passed**, observing real lock contention for two refunds and both refund/cancel winners. Every loser and terminal retry left the settlement journal/audit unchanged.
- The overpayment owner suite passed **19 tests / 234 assertions** after the final controller edit. Coverage includes exact fee/zero-fee postings, immutable terminal operations, closed-period chronology, invalid references/ownership, original accounts after setting removal, private evidence, failed-upload/audit rollback, automatic journal guards/source links and the 3,000,000 / 300,000 / 2,700,000 split-transfer example. General-journal protection assertions live in this owner instead of duplicating lifecycle setup in `ManualJournalTest`.
- Model/settings/reconciliation/year-end/guardian owners passed. Historical month-end values use journal dates; old settled/inactive accounts remain checked; opposite differences cannot offset; held liability allows close; a year-end mismatch rolls back its closing entry and period locks. FeeAccess includes all six new endpoints for all existing personas.
- Browser verification used a built-asset server backed only by disposable `testing` data. Finance Dashboard → sidebar → list → proof upload/detail → refund confirmed held 2,700,000, fee 6,500, net 2,693,500 and bank total 2,700,000. The resulting journal showed one full liability debit and two labelled Bank credits with no general reversal control. Cancellation, Escape/dismissal and removal of terminal buttons were checked. Admin Settings → School Profile configured/cleared/restored 2-1200; clearing it disabled new receipt controls while a historical receipt refunded successfully with zero fee.
- Principal Dashboard → sidebar → detail → private proof and journal worked with no write controls. General Ledger/Cash Book → journal/overpayment source links worked and displayed the fee memo separately. Role switching admin → principal changed navigation while preserving held admin capability. Parent/teacher/student-only users had no destination and received 403 for direct detail, proof and forged refund requests.
- Finance browser closing succeeded in October with 10,000 held. A test-only manual journal added 100 to Titipan Wali in November without operational backing; reconciliation showed GL 10,100 versus subledger 10,000, and November close rejected with a Titipan Wali message while retaining an open period. No unexpected browser console error remained; deliberate unauthorized probes produced expected 403 logs.
- Temporary browser fixture/artifacts were moved to `/private/tmp`; the isolated server was stopped. Development/production business data was not modified. No push or deployment was performed.
- Remaining deployment gate: treasurer/accountant confirms account mappings, guardian-borne fee treatment and manual split procedure before real transactions. The pre-existing period-close/posting race remains outside this feature.
