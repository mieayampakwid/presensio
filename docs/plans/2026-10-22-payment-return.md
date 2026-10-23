# Payment Return Workflow Implementation Plan

> **For implementer agents:** Read each task in full and execute in order. The spec is the source of truth. Implementation completed 2026-10-23; see the verification record at the end.

**Goal:** Enforce pending-transfer claims and let every eligible guardian find and cancel a pending transfer without posting a journal or sending a rejection notification.

**Spec:** `docs/specs/21-payment-return.md`, draft v1.2. All AC-21-01 through AC-21-21 are covered below.

**Architecture:** Extend the existing `AllocationValidator`, `PaymentService` and `FeePortal`; keep HTTP coordination in the parent fee controller and use the existing shared parent/student React page. Store guardian cancellation as `rejected` plus the fixed reason and an audit action. Add a separately paginated pending list while retaining the latest-50 payment history.

**Prerequisites:** Existing dependencies only; no migrations, packages, environment variables or changes to spec 22. Installed PHP versions inspected during planning: Laravel 13.31.0, inertia-laravel 3.3.4, Wayfinder 0.1.21 and PHPUnit 12.5.35. `package.json` declares Inertia React ^3.0.0 / React ^19.2.0. Node runs through `vendor/bin/sail npm`. PostgreSQL concurrency proof uses only the disposable `testing` database, never the development database.

**Required guidance:** Read `.ai/rules/index.md` and matching rules before editing. Activate `laravel-best-practices`, `testing-best-practices`, `inertia-react-development` and `wayfinder-development` for their respective tasks. Reuse the existing page layout and controls; activate `tailwindcss-development` if changing utility classes. Boost `search-docs` was unavailable during planning; the installed Laravel pagination source and existing project call sites were inspected. Before execution, use Boost documentation search if available for `transactions`, `pessimistic locking`, `pagination`, `validation errors` and `forms`, scoped to the relevant installed packages. Do not change dependencies to obtain documentation.

## Files and ownership

| File | Responsibility |
| --- | --- |
| `app/Services/Finance/AllocationValidator.php` | Optional claim-aware validation under ordered bill locks |
| `app/Services/Finance/PaymentService.php` | Enable claim mode on upload/re-allocation; locked guardian cancellation |
| `app/Services/Finance/FeePortal.php` | Clamped display balances, shared payment rows, scoped pending pagination |
| `app/Http/Controllers/Parent/FeeController.php` | Parent capability, pending page prop, cancel response |
| `routes/parent.php` | Parent-only cancellation endpoint |
| `resources/js/pages/parent/fees.tsx` | Claim-aware selection, pending list/pagination and confirmation |
| `tests/Unit/Services/Finance/PaymentServiceTest.php` | Extend existing service behavior coverage |
| `tests/Feature/Parent/FeePortalTest.php` | Extend existing upload/read-model coverage |
| `tests/Feature/Parent/CancelTransferTest.php` | New HTTP cancellation boundary |
| `tests/Feature/fees/PaymentReturnConcurrencyTest.php` | New real PostgreSQL contention tests |
| `tests/Feature/fees/VerificationTest.php`, `tests/Feature/Student/FeeTest.php` | Extend existing consumer coverage only where behavior changes |
| `resources/js/actions/**`, `resources/js/routes/**`, `resources/js/wayfinder/**` | Generated output; regenerate, never hand-edit |

No new shared test superclass, global payment status, notification type, sidebar destination or persistence table is needed. `AuditLogger` gets the actor from `Auth::id()`: HTTP and service tests must authenticate the acting user, including worker processes.

## Task 1: Enforce pending claims inside the payment transaction

**Files:** Modify `app/Services/Finance/AllocationValidator.php` (`validate`) and `app/Services/Finance/PaymentService.php` (`submitTransfer`, `verify`). Test `tests/Unit/Services/Finance/PaymentServiceTest.php`, `tests/Feature/Parent/FeePortalTest.php`, `tests/Feature/fees/VerificationTest.php`.

**Dependencies:** None.

**What this does:** Stops an upload or explicit re-allocation from consuming another pending payment's claim while preserving ordinary verification and cash behavior.

**Verification:** Service tests establish arithmetic and mode selection; HTTP tests establish field feedback, privacy and proof cleanup.

- [x] **Step 1: Extend the validator signature and its existing ordered lock query.** Import `App\Enums\PaymentStatus` and `App\Models\PaymentAllocation`. Preserve `allowedStudentIds`, positive allocation, open-bill and total-equals-payment checks. Extend the signature exactly:

```php
public function validate(
    string $paymentAmount,
    array $allocations,
    array $allowedStudentIds,
    bool $respectPendingClaims = false,
    ?int $excludePaymentId = null,
): Collection
```

After the current bill query has acquired `ORDER BY id FOR UPDATE`, before iterating allocations, compute other pending claims with one query (a plain aggregate, not a payment-row lock):

```php
$pendingByBill = $respectPendingClaims
    ? PaymentAllocation::query()
        ->whereIn('bill_id', $bills->keys())
        ->when($excludePaymentId !== null, fn ($query) => $query->where('payment_id', '!=', $excludePaymentId))
        ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Pending))
        ->get(['bill_id', 'amount'])
        ->groupBy('bill_id')
        ->map(fn (Collection $rows) => $rows->reduce(
            fn (string $sum, PaymentAllocation $row) => Money::add($sum, $row->amount),
            '0.00',
        ))
    : collect();
```

Replace the existing remaining-balance comparison inside the loop with:

```php
$remaining = Money::sub($bill->netAmount(), $paid);
$pending = $pendingByBill->get($billId, '0.00');
$claimable = Money::sub($remaining, $pending);

if ($respectPendingClaims && Money::gt($amount, $claimable) && Money::isPositive($pending)) {
    $display = Money::isNegative($claimable) ? '0.00' : $claimable;
    throw new PaymentOperationException(
        'Alokasi untuk tagihan "'.$bill->title.'" melebihi jumlah yang dapat diklaim ('.Money::format($display).'). '
        .'Ada transfer menunggu verifikasi untuk tagihan ini. Periksa daftar transfer yang dapat Anda batalkan atau hubungi bendahara.'
    );
}

if (Money::gt($amount, $remaining)) {
    throw new PaymentOperationException("Alokasi untuk tagihan \"{$bill->title}\" melebihi sisa tagihan.");
}
```

Use decimal strings throughout; do not cast money to float. Change the validator PHPDoc to say the caller must hold an active transaction; an upload has no existing payment to lock. In `submitTransfer`, pass `respectPendingClaims: true`. In `verify`, pass `respectPendingClaims: $reallocation !== null, excludePaymentId: $payment->id`. Keep `createCash` unchanged. Do not apply claim mode to a null re-allocation or replace the bill locks with locking reads of payments.

- [x] **Step 2: Add this service regression to the existing class.** It uses the existing `bill`, `accountId`, `$user` and `$service` fixtures. Add the `PaymentOperationException` import if missing.

```php
public function test_pending_claims_allow_only_the_unclaimed_remainder(): void
{
    $bill = $this->bill();
    $submit = fn (string $amount) => $this->service->submitTransfer(
        $amount, [$bill->id => $amount], [$bill->student_id],
        $this->accountId('1-1200'), '2026-07-09', $this->user,
    );
    $first = $submit('200000.00');
    $before = Payment::count();

    try {
        $submit('150001.00');
        $this->fail('An upload consumed another pending claim.');
    } catch (PaymentOperationException $exception) {
        $this->assertStringContainsString(Money::format('150000.00'), $exception->getMessage());
    }

    $this->assertSame($before, Payment::count());
    $second = $submit('150000.00');
    $this->service->verify($second, null, $this->accountId('1-1200'), $this->user);
    $this->service->verify($first, null, $this->accountId('1-1200'), $this->user);
    $this->assertSame('350000.00', $bill->refresh()->paid_amount);
    $this->assertSame(BillStatus::Paid, $bill->status);
}
```

Import `App\Support\Money`. Add concrete cases using the same fixtures: verify the two disjoint claims in the opposite order; re-allocation of P to a bill with another full pending claim rejects and preserves P's original allocations/status; re-allocation to P's own bill excludes P and succeeds; school rejection releases the claim; cash succeeds despite a pending claim. These cases own AC-21-02…04 and 09…11; preserve existing immutability and ledger assertions.

In `FeePortalTest`, add the HTTP rejection test using its existing `billFor` and `payload` helpers:

```php
public function test_a_second_full_claim_has_field_feedback_and_no_orphan_proof(): void
{
    $bill = $this->billFor($this->child);
    $this->actingAs($this->parent)->post(route('parent.fees.transfers.store'), $this->payload($bill));
    $stored = Storage::disk('local')->allFiles();

    $this->post(route('parent.fees.transfers.store'), $this->payload($bill))
        ->assertSessionHasErrors('allocations');

    $this->assertSame(1, Payment::count());
    $this->assertSame($stored, Storage::disk('local')->allFiles());
    $this->assertSame(0, JournalEntry::count());
}
```

Also submit via `postJson` with a valid file and assert `assertUnprocessable()` plus `errors.allocations`; retain the first proof and no second payment. For AC-21-03/20, create a pending payment by a second guardian allocating to `$this->child` and `$this->stranger` (name the latter `PRIVATE_CHILD`). The current guardian links only to `$this->child`. Reject the current guardian's conflicting upload and assert the error contains the affected bill/zero amount and `hubungi bendahara`, but not `PRIVATE_CHILD` or the second guardian's name. Do not serialize the blocking payment to build this message.

- [x] **Step 3: Run the owner files before and after the change.**

```sh
php artisan test --compact tests/Unit/Services/Finance/PaymentServiceTest.php
php artisan test --compact tests/Feature/Parent/FeePortalTest.php
php artisan test --compact tests/Feature/fees/VerificationTest.php
```

Expected: new claim tests fail against the original code, then all owner cases pass; failed uploads add neither payments nor proof files. Record real counts, not a predicted count.

## Task 2: Add locked, authorized guardian cancellation

**Files:** Modify `app/Services/Finance/PaymentService.php`, `app/Http/Controllers/Parent/FeeController.php`, `routes/parent.php`. Create `tests/Feature/Parent/CancelTransferTest.php` with `php artisan make:test --phpunit Parent/CancelTransferTest --no-interaction`. Generated route output follows below.

**Dependencies:** Task 1.

**What this does:** Gives linked parents a cancellation operation that remains correct after concurrent verification or re-allocation.

**Verification:** HTTP tests assert persisted rejection, exactly one audit row with the actor, proof retention and no notification/journal writes; forbidden callers remain unchanged.

- [x] **Step 1: Add the following service method.** Import `App\Enums\UserRole`. Do not call `lockPending` before authorization: that helper reveals status first.

```php
public function cancelByGuardian(Payment $payment, User $user): Payment
{
    abort_unless($user->hasRole(UserRole::Parent), 403);

    return DB::transaction(function () use ($payment, $user) {
        $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
        $guardian = Guardian::query()->where('user_id', $user->id)->first();
        abort_unless($guardian !== null && $locked->payment_method === PaymentMethod::BankTransfer, 403);

        $studentIds = $locked->allocations()->join('bills', 'bills.id', '=', 'payment_allocations.bill_id')
            ->pluck('bills.student_id')->unique();
        $linkedCount = $guardian->students()->whereIn('students.id', $studentIds)->count();
        abort_unless($studentIds->isNotEmpty() && $linkedCount === $studentIds->count(), 403);

        if ($locked->status !== PaymentStatus::Pending) {
            throw new PaymentOperationException('Hanya pembayaran berstatus menunggu verifikasi yang dapat diproses.');
        }

        $bills = Bill::query()->whereIn('id', $locked->allocations()->select('bill_id'))
            ->orderBy('id')->lockForUpdate()->get();
        $locked->update(['status' => PaymentStatus::Rejected, 'reject_reason' => 'Dibatalkan oleh wali']);
        $bills->each(fn (Bill $bill) => $this->billService->recompute($bill));
        $this->auditLogger->record(
            $locked, 'cancelled_by_guardian', ['status' => PaymentStatus::Pending->value],
            ['status' => PaymentStatus::Rejected->value, 'reject_reason' => 'Dibatalkan oleh wali'],
        );

        return $locked;
    });
}
```

Do not change unrelated service authorization or introduce request state in a singleton.

Add this controller action (import `App\Models\Payment`):

```php
public function cancel(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
{
    $payments->cancelByGuardian($payment, $request->user());
    Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer berhasil dibatalkan.']);

    return to_route('parent.fees.index', [
        'transfers_page' => max(1, $request->integer('transfers_page', 1)),
    ]);
}
```

Register outside the existing `role:parent,admin` group so admin alone gains no capability:

```php
Route::post('my-fees/transfers/{payment}/cancel', [FeeController::class, 'cancel'])
    ->middleware(['auth', 'role:parent'])
    ->name('parent.fees.transfers.cancel');
```

- [x] **Step 2: Add this complete happy-path test to the scaffolded class.** Use `RefreshDatabase`; import the classes named in the code. Test-fixture storage is local/fake and no external notifier is called.

```php
public function test_linked_guardian_cancels_with_one_audit_and_keeps_the_proof(): void
{
    Storage::fake('local');
    $parent = User::factory()->parent()->create();
    $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
    $student = Student::factory()->create();
    $guardian->students()->attach($student->id, ['relationship_type' => 'father']);
    $bill = Bill::factory()->create(['student_id' => $student->id]);
    $payment = Payment::factory()->create(['proof_path' => 'payment-proofs/original.pdf']);
    PaymentAllocation::create(['payment_id' => $payment->id, 'bill_id' => $bill->id, 'amount' => '350000.00']);
    Storage::disk('local')->put($payment->proof_path, 'proof');
    $journals = JournalEntry::count();
    $notifications = DB::table('notifications')->count();

    $this->actingAs($parent)->post(route('parent.fees.transfers.cancel', $payment))
        ->assertRedirect(route('parent.fees.index', ['transfers_page' => 1]))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
    $this->assertSame('Dibatalkan oleh wali', $payment->reject_reason);
    $this->assertNull($payment->receipt_number);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => $payment->getMorphClass(), 'auditable_id' => $payment->id,
        'action' => 'cancelled_by_guardian', 'user_id' => $parent->id,
    ]);
    $this->assertSame(1, AuditLog::where('action', 'cancelled_by_guardian')->count());
    $this->assertSame($journals, JournalEntry::count());
    $this->assertSame($notifications, DB::table('notifications')->count());
    Storage::disk('local')->assertExists($payment->proof_path);
}
```

Extend that owner class with these exact fixture/outcome cases (use `UserFactory::withRoles` for role combinations):

| Case | Action/result and persisted assertions |
| --- | --- |
| Second guardian linked to the same child | Cancel payment submitted by the first guardian; success; audit actor is the second guardian |
| Each of admin/teacher/finance/principal + parent | Test with each active role in session; linked cancellation succeeds; actor remains the same |
| Each non-parent-only role, parent without profile | 403; payment, journal, notification and cancellation-audit counts unchanged |
| Parent linked only to one of two allocated students | 403 for pending and verified payments; no status-specific message |
| Cash payment or payment with zero allocations | 403; no changes |
| Linked parent, rejected/verified/void status | JSON 422; original status/reason/receipt retained; no audit or journal addition |
| Repeated successful cancellation | First succeeds; second JSON 422; exactly one cancellation audit |
| Guest or unknown payment | Guest redirects to login; authenticated parent with missing ID gets 404 |
| Claim released | Upload full amount, cancel, upload full amount again; second pending payment exists; cancellation posted nothing |

- [x] **Step 3: Regenerate routes and run boundary checks.**

```sh
vendor/bin/sail artisan wayfinder:generate --with-form --no-interaction
php artisan route:list --name=parent.fees
php artisan test --compact tests/Feature/Parent/CancelTransferTest.php
php artisan test --compact tests/Feature/fees/VerificationTest.php
```

Expected: POST cancellation route exists; only held parent capability grants it. School rejection still sends its existing notification; guardian cancellation does not.

## Task 3: Expose safe balances and all visible pending transfers

**Files:** Modify `app/Services/Finance/FeePortal.php`, `app/Http/Controllers/Parent/FeeController.php`. Extend `tests/Feature/Parent/FeePortalTest.php`, `tests/Feature/Student/FeeTest.php`.

**Dependencies:** Tasks 1–2.

**What this does:** Adds a non-negative display claim and a pending list independent of the latest-50 history.

**Verification:** Inertia prop assertions prove correct scope, pagination, multi-role capabilities and read-only student behavior.

- [x] **Step 1: Extend the read model using the following contracts.**

```php
public function forStudents(Collection|array $studentIds, bool $allowCancel = false): array
private function payments(array $ids, bool $allowCancel): array
private function visiblePayments(array $ids): \Illuminate\Database\Eloquent\Builder
private function paymentRow(Payment $payment, bool $allowCancel): array
public function pendingTransfers(array $ids, bool $allowCancel, int $page): \Illuminate\Pagination\LengthAwarePaginator
```

Use array-shape PHPDoc for payment rows, including the existing fields plus `can_cancel: bool`; type the builder as `Builder<Payment>` and paginator as `LengthAwarePaginator<int, array<string, mixed>>`. The visible-payment query is the current history query without ordering/limit:

```php
return Payment::query()
    ->whereHas('allocations.bill', fn ($query) => $query->whereIn('student_id', $ids))
    ->whereDoesntHave('allocations.bill', fn ($query) => $query->whereNotIn('student_id', $ids));
```

Extract the existing history mapper unchanged into `paymentRow`; append:

```php
'can_cancel' => $allowCancel
    && $payment->payment_method === PaymentMethod::BankTransfer
    && $payment->status === PaymentStatus::Pending,
```

The query already proves nonempty allocations/all-linked-student visibility. Do not query guardian links for every mapped row. In `forStudents`, keep summary/bill/history fields and ordering; add per bill:

```php
'claimable' => Money::isPositive($claimable = Money::sub(
    $bill->remainingAmount(), $pendingByBill->get($bill->id, '0.00'),
)) ? $claimable : '0.00',
```

Pass `$allowCancel` from `forStudents` into `$this->payments($ids, $allowCancel)`. History calls `visiblePayments($ids)->latest('id')->limit(self::PAYMENT_LIMIT)` and maps with `$allowCancel`. Add `PaymentMethod` import. Pending pagination uses:

```php
$query = $this->visiblePayments($ids)
    ->where('status', PaymentStatus::Pending)
    ->where('payment_method', PaymentMethod::BankTransfer)
    ->latest('id');
$paginator = (clone $query)->paginate(20, ['*'], 'transfers_page', max(1, $page));
if ($paginator->currentPage() > $paginator->lastPage()) {
    $paginator = $query->paginate(20, ['*'], 'transfers_page', $paginator->lastPage());
}

return $paginator->withQueryString()->through(
    fn (Payment $payment) => $this->paymentRow($payment, $allowCancel),
);
```

In parent `index`, compute `$allowCancel = $request->user()->hasRole(UserRole::Parent) && $request->user()->guardian !== null`; pass it to `forStudents`. Add a distinct `pendingTransfers` prop using the same linked IDs and `max(1, $request->integer('transfers_page', 1))`. Leave the student controller's call using the default false, and allow the shared React page's `pendingTransfers` prop to be optional. Do not add the pending list or cancellation capability to the student response. The default-false contract preserves read-only consumers.

- [x] **Step 2: Add this pagination/visibility regression using the existing portal fixture.**

```php
public function test_pending_list_reaches_transfers_outside_the_latest_fifty_history_rows(): void
{
    $bill = $this->billFor($this->child);
    $oldest = Payment::factory()->create();
    PaymentAllocation::create(['payment_id' => $oldest->id, 'bill_id' => $bill->id, 'amount' => '1.00']);
    for ($i = 0; $i < 50; $i++) {
        $newer = Payment::factory()->rejected()->create();
        PaymentAllocation::create(['payment_id' => $newer->id, 'bill_id' => $bill->id, 'amount' => '1.00']);
    }
    $this->actingAs($this->parent)->get(route('parent.fees.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('portal.payments', 50)
            ->where('pendingTransfers.total', 1)
            ->where('pendingTransfers.data.0.id', $oldest->id)
            ->where('pendingTransfers.data.0.can_cancel', true));
}
```

Add a separate fixture with 21 pending payments: page 1 has 20, page 2 has 1, no IDs repeat, and cancelling the page-2 row then GETting page 2 yields current page 1. With zero pending records, return an empty paginator on page 1. Include a cross-child payment to prove it is absent from every pending page and from history for a guardian linked to only one child. Given a remaining 350000 and pending 700000, assert `portal.bills.0.claimable === '0.00'`; after rejection/cancellation reduces claims, assert the new exact display value. Given remaining 350000/pending 200000, assert claimable 150000 and `has_pending === true`. In `Student/FeeTest`, assert every returned payment has `can_cancel === false` and the pending prop is absent.

- [x] **Step 3: Run owner tests.**

```sh
php artisan test --compact tests/Feature/Parent/FeePortalTest.php
php artisan test --compact tests/Feature/Parent/CancelTransferTest.php
php artisan test --compact tests/Feature/Student/FeeTest.php
```

Expected: pending transfers beyond history are accessible; hidden siblings never appear; page fallback and no-profile admin empty portal remain valid.

## Task 4: Wire the portal selection, pending pagination and cancellation dialog

**Files:** Modify `resources/js/pages/parent/fees.tsx`. Generated `FeeController.cancel` comes from Task 2; reuse existing `Button`, `Dialog`, `Spinner`, `InputError`, badges and money helpers.

**Dependencies:** Tasks 2–3.

**What this does:** Makes the new backend behavior reachable through the existing Biaya Sekolah sidebar entry.

**Verification:** Type/lint checks plus the door-to-door browser cases in Task 6. No frontend test dependency is introduced.

- [x] **Step 1: Extend the types and selection behavior.** Add `claimable: string` to `BillRow`, `can_cancel: boolean` to `PaymentRow`, and this optional prop (student pages omit it):

```tsx
pendingTransfers?: {
    data: PaymentRow[];
    current_page: number;
    last_page: number;
    total: number;
};
```

Import `router` from `@inertiajs/react` and destructure `pendingTransfers`. Change both `toggle` and `toggleAll` initial values from `bill.remaining` to `bill.claimable`. Use the same predicate everywhere, including the individual checkbox, select-all and upload button eligibility:

```tsx
const canSelectBill = (bill: BillRow) => isOpen(bill) && toCents(bill.claimable) > 0;
const selectableBills = useMemo(() => bills.filter(canSelectBill), [bills]);
```

Replace the checkbox's `disabled` expression with `!uploadEnabled || !canSelectBill(bill)`. Use `bill.claimable` for the modal's available amount and numeric input max. Preserve the existing remaining-balance table column and summary; pending badge remains based on `has_pending`. Do not use `!has_pending` to exclude partially claimed bills. Preserve field errors and entered values after a rejected upload; the existing controller removes the rejected upload's stored file. A stale selection still reaches server validation and cannot create an over-claim.

- [x] **Step 2: Add dedicated cancellation form state and submit handler.**

```tsx
const [cancelling, setCancelling] = useState<PaymentRow | null>(null);
const cancelForm = useForm({});
const cancelTransfer = () => {
    if (!cancelling || cancelForm.processing) return;
    cancelForm.post(
        FeeController.cancel.url(
            { payment: cancelling.id },
            { query: { transfers_page: pendingTransfers?.current_page ?? 1 } },
        ),
        { preserveScroll: true, onSuccess: () => setCancelling(null) },
    );
};
const visitPendingPage = (page: number) => router.get(
    FeeController.index.url({ query: { transfers_page: page } }),
    {},
    { only: ['pendingTransfers'], preserveScroll: true, preserveState: true },
);
```

Render a section only when `canUpload && pendingTransfers` is present. Its heading is `Transfer Menunggu Verifikasi`; show each payment's date, bank, amount and a `Batalkan` button only when `can_cancel` is true. Each button opens the same dialog, using this content:

```tsx
<Dialog open={cancelling !== null} onOpenChange={(open) => {
    if (!open && !cancelForm.processing) setCancelling(null);
}}>
    <DialogContent>
        <DialogTitle>Batalkan transfer?</DialogTitle>
        <DialogDescription>
            Transfer ini akan ditandai sebagai dibatalkan oleh wali. Bukti tetap disimpan.
        </DialogDescription>
        <DialogFooter>
            <Button variant="outline" disabled={cancelForm.processing}
                onClick={() => setCancelling(null)}>Kembali</Button>
            <Button variant="destructive" disabled={cancelForm.processing}
                onClick={cancelTransfer}>
                {cancelForm.processing && <Spinner />} Batalkan transfer
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

Show `Tidak ada transfer menunggu verifikasi.` for an empty pending list. Display `Halaman {current_page} dari {last_page}`, with `Sebelumnya` and `Berikutnya` buttons calling `visitPendingPage(current_page - 1)` and `visitPendingPage(current_page + 1)`; disable at the respective bounds. Put the same `can_cancel`-guarded button next to the existing receipt action in each history row. The cancellation visit refreshes all props, not just the pending list, so badges, claims, totals and history update together. The server clamps pages after cancellation. Student views have no pending section or cancellation controls. Keep global toast handling intact; do not read flash through `props.flash`.

- [x] **Step 3: Run the frontend gates and owner tests.**

```sh
vendor/bin/sail npm run check
vendor/bin/sail npm run types:check
php artisan test --compact tests/Feature/Parent/FeePortalTest.php
php artisan test --compact tests/Feature/Student/FeeTest.php
```

Expected: generated cancel action is typed; partial visits replace only `pendingTransfers`; student rendering stays read-only. Record any browser prerequisite missing rather than claiming UI proof from PHP tests.

## Task 5: Prove PostgreSQL contention and ledger preservation

**Files:** Create `tests/Feature/fees/PaymentReturnConcurrencyTest.php` with `php artisan make:test --phpunit fees/PaymentReturnConcurrencyTest --no-interaction`. Extend the existing service test for the ledger sequence below.

**Dependencies:** Tasks 1–3 (Task 4 is independent of this proof).

**What this does:** Proves locks against separate database sessions rather than merely executing two sequential service calls.

**Verification:** AC-21-12, 13 and 21 pass in PostgreSQL; SQLite explicitly skips the concurrency class.

- [x] **Step 1: Use committed fixtures and a guarded test setup.** This class must not use `RefreshDatabase` or an outer test transaction: child processes cannot see uncommitted fixtures. Use the following setup and ordinary `Tests\TestCase`. Import `Date`, `DB`, `ChartOfAccountsSeeder`, `SchoolSettings` and all models used in the case code.

```php
protected function setUp(): void
{
    parent::setUp();
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL row locks.');
    }
    $this->assertSame('testing', DB::connection()->getDatabaseName());
    $this->artisan('migrate:fresh', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
    Date::setTestNow('2026-07-10 03:00:00');
    app(SchoolSettings::class)->row();
    $this->seed(ChartOfAccountsSeeder::class);
    app(SchoolSettings::class)->refresh();
}

protected function tearDown(): void
{
    Date::setTestNow();
    parent::tearDown();
}
```

This resets only the explicitly asserted disposable `testing` database. It is not a command to run against the current development connection. Create a parent linked to two students and a second parent linked only to the first; the submitting parent has both children so verification may re-allocate to the second. Use recognized bills with amount `350000.00`, fee type accounts from seeded `1-1300`/`4-1100`, and deposit `1-1200`. Fixture writes commit before starting the contention transaction. Add this local fixture method; it deliberately does not post recognition journals, so the race tests count only payment journals:

```php
/** @return array{parent: User, otherParent: User, finance: User, bill: Bill, billForY: Bill, bankId: int} */
private function fixture(): array
{
    $parent = User::factory()->parent()->create();
    $otherParent = User::factory()->parent()->create();
    $finance = User::factory()->finance()->create();
    $x = Student::factory()->create();
    $y = Student::factory()->create();
    $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
    $guardian->students()->attach([$x->id => ['relationship_type' => 'father'], $y->id => ['relationship_type' => 'father']]);
    Guardian::factory()->create(['user_id' => $otherParent->id])->students()
        ->attach($x->id, ['relationship_type' => 'mother']);
    $type = FeeType::factory()->create([
        'receivable_account_id' => Account::where('code', '1-1300')->value('id'),
        'revenue_account_id' => Account::where('code', '4-1100')->value('id'),
        'discount_account_id' => Account::where('code', '4-1900')->value('id'),
    ]);
    $make = fn (Student $student) => Bill::factory()->recognized('2026-07-01')->create([
        'student_id' => $student->id, 'fee_type_id' => $type->id,
        'amount' => '350000.00', 'period_key' => '2026-07', 'due_date' => '2026-07-10',
    ]);

    return ['parent' => $parent, 'otherParent' => $otherParent, 'finance' => $finance,
        'bill' => $make($x), 'billForY' => $make($y),
        'bankId' => (int) Account::where('code', '1-1200')->value('id')];
}
```

The fixture uses seeded `4-1900` (Potongan & Beasiswa), confirmed in `ChartOfAccountsSeeder`.

- [x] **Step 2: Add this bounded worker helper to the same test class.** Import `Symfony\Component\Process\Process`. It boots the same app in another process, takes DB configuration through stdin, identifies its PostgreSQL PID, and waits on a lock held by the parent. Do not print DB configuration or credentials.

```php
private function contend(array $operation, \Closure $holdLock, \Closure $winner): array
{
    $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config(['database.default' => 'pgsql', 'database.connections.pgsql' => $input['connection'], 'queue.default' => 'database']);
Illuminate\Support\Facades\DB::purge('pgsql');
Illuminate\Support\Facades\Date::setTestNow('2026-07-10 03:00:00');
$user = App\Models\User::findOrFail($input['operation']['user']);
Illuminate\Support\Facades\Auth::login($user);
echo json_encode(['pid' => Illuminate\Support\Facades\DB::selectOne('select pg_backend_pid() as pid')->pid]).PHP_EOL;
flush();
try {
    $op = $input['operation'];
    $service = app(App\Services\Finance\PaymentService::class);
    if ($op['kind'] === 'upload') {
        $service->submitTransfer('350000.00', [$op['bill'] => '350000.00'], [$op['student']], $op['account'], '2026-07-09', $user);
    } elseif ($op['kind'] === 'verify') {
        $service->verify(App\Models\Payment::findOrFail($op['payment']), null, $op['account'], $user);
    } else {
        $service->cancelByGuardian(App\Models\Payment::findOrFail($op['payment']), $user);
    }
    echo json_encode(['status' => 200]).PHP_EOL;
} catch (App\Services\Finance\Exceptions\PaymentOperationException $exception) {
    echo json_encode(['status' => 422]).PHP_EOL;
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode()]).PHP_EOL;
}
PHP;
    $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['APP_ENV' => 'testing']);
    $process->setInput(json_encode([
        'connection' => DB::connection()->getConfig(), 'operation' => $operation,
    ], JSON_THROW_ON_ERROR));
    $process->setTimeout(15);
    DB::beginTransaction();
    try {
        $holdLock();
        $process->start();
        $deadline = microtime(true) + 10;
        $blocked = false;
        while (microtime(true) < $deadline && $process->isRunning()) {
            $first = strtok($process->getOutput(), "\n");
            $ready = $first === false ? null : json_decode($first, true);
            if (is_array($ready) && isset($ready['pid'])) {
                DB::select('select pg_stat_clear_snapshot()');
                $activity = DB::selectOne('select wait_event_type from pg_stat_activity where pid = ?', [$ready['pid']]);
                if ($activity?->wait_event_type === 'Lock') {
                    $blocked = true;
                    break;
                }
            }
            usleep(10000);
        }
        $this->assertTrue($blocked, 'Worker did not contend on the held row lock. '.$process->getErrorOutput());
        $winner();
        DB::commit();
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

        return json_decode($lines[count($lines) - 1], true, flags: JSON_THROW_ON_ERROR);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($process->isRunning()) {
            $process->stop(1);
        }
    }
}
```

Clear the statistics snapshot on each poll because PostgreSQL caches session activity inside a transaction ([PostgreSQL 18 monitoring documentation](https://www.postgresql.org/docs/18/monitoring-stats.html)). The short polling delay observes real database lock contention; correctness must depend on the observed `Lock`, never on an assumed sleep interval. A missing PostgreSQL session permission or worker failure fails this proof instead of silently skipping it. `JournalNumberConcurrencyTest` does not establish this property and must not be cited as evidence.

- [x] **Step 3: Exercise both terminal winners and changed allocation access.** This is the upload-race test body after creating `$bill`, linked `$parent`, seeded `$bankId`; use `$service = app(PaymentService::class)`:

```php
$result = $this->contend(
    ['kind' => 'upload', 'bill' => $bill->id, 'student' => $bill->student_id, 'account' => $bankId, 'user' => $parent->id],
    fn () => Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail(),
    fn () => $service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $bankId, '2026-07-09', $parent),
);
$this->assertSame(422, $result['status']);
$this->assertSame(1, Payment::where('status', PaymentStatus::Pending)->count());
$this->assertSame('350000.00', Money::toDecimal(PaymentAllocation::sum('amount')));
$this->assertSame(0, JournalEntry::count());
```

Wrap the upload body above in `test_two_uploads_serialize_on_the_bill_lock(): void`, starting with `['bill' => $bill, 'parent' => $parent, 'bankId' => $bankId] = $this->fixture();` and `$service = app(PaymentService::class);`. Add these complete terminal-race cases (import `PHPUnit\Framework\Attributes\DataProvider`):

```php
public static function terminalWinners(): array
{
    return ['verify first' => ['verify'], 'cancel first' => ['cancel']];
}

#[DataProvider('terminalWinners')]
public function test_cancel_and_verify_have_exactly_one_winner(string $kind): void
{
    ['bill' => $bill, 'parent' => $parent, 'finance' => $finance, 'bankId' => $bankId] = $this->fixture();
    $service = app(PaymentService::class);
    $payment = $service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $bankId, '2026-07-09', $parent);
    $actor = $kind === 'verify' ? $finance : $parent;
    $this->actingAs($actor);
    $result = $this->contend(
        ['kind' => $kind === 'verify' ? 'cancel' : 'verify', 'payment' => $payment->id,
            'account' => $bankId, 'user' => $kind === 'verify' ? $parent->id : $finance->id],
        fn () => Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail(),
        fn () => $kind === 'verify'
            ? $service->verify($payment, null, $bankId, $finance)
            : $service->cancelByGuardian($payment, $parent),
    );
    $this->assertSame(422, $result['status']);
    $payment->refresh();
    $this->assertSame($kind === 'verify' ? PaymentStatus::Verified : PaymentStatus::Rejected, $payment->status);
    $this->assertSame($kind === 'verify' ? 1 : 0, JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->count());
    $this->assertSame($kind === 'cancel' ? 1 : 0, AuditLog::where('action', 'cancelled_by_guardian')->count());
    if ($kind === 'verify') {
        $this->assertNotNull($payment->receipt_number);
    } else {
        $this->assertNull($payment->receipt_number);
        $this->assertSame('Dibatalkan oleh wali', $payment->reject_reason);
        $this->assertSame(0, JournalEntry::count());
    }
}

public function test_waiting_cancellation_authorizes_latest_allocations_before_status(): void
{
    ['bill' => $bill, 'billForY' => $billForY, 'parent' => $parent,
        'otherParent' => $otherParent, 'finance' => $finance, 'bankId' => $bankId] = $this->fixture();
    $service = app(PaymentService::class);
    $payment = $service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $bankId, '2026-07-09', $parent);
    $this->actingAs($finance);
    $result = $this->contend(
        ['kind' => 'cancel', 'payment' => $payment->id, 'user' => $otherParent->id],
        fn () => Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail(),
        fn () => $service->verify($payment, [$billForY->id => '350000.00'], $bankId, $finance),
    );
    $this->assertSame(403, $result['status']);
    $this->assertSame(PaymentStatus::Verified, $payment->refresh()->status);
    $this->assertSame($billForY->id, $payment->allocations()->sole()->bill_id);
    $this->assertSame(1, JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->count());
    $this->assertSame(0, AuditLog::where('action', 'cancelled_by_guardian')->count());
}
```

These cases use the helper and actual services, not direct status writes. The first fixture stamps recognition solely for the race assertions; the separate ledger sequence below posts real recognition entries.

Add this exact ledger sequence to `PaymentServiceTest`; import `ReceivablesReconciliation`, `JournalLine` and `AuditLog` along with the existing model imports. It uses real recognition entries rather than treating a factory stamp as a ledger entry:

```php
public function test_claim_cancellation_and_settlement_preserve_the_ledger(): void
{
    $parent = User::factory()->parent()->create();
    $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
    $bill = $this->bill(['recognized_at' => null]);
    $guardian->students()->attach($bill->student_id, ['relationship_type' => 'father']);
    $other = $this->bill(['recognized_at' => null, 'student_id' => $bill->student_id]);
    $this->actingAs($parent);
    app(BillService::class)->recognize($bill);
    app(BillService::class)->recognize($other);
    $baseline = JournalEntry::count();
    $submit = fn (Bill $target, string $amount) => $this->service->submitTransfer(
        $amount, [$target->id => $amount], [$target->student_id],
        $this->accountId('1-1200'), '2026-07-09', $parent,
    );
    $first = $submit($bill, '200000.00');
    $cancelled = $submit($bill, '150000.00');
    $this->service->cancelByGuardian($cancelled, $parent);
    $replacement = $submit($bill, '150000.00');
    $this->assertSame($baseline, JournalEntry::count());
    $this->service->verify($first, null, $this->accountId('1-1200'), $this->user);
    $this->service->verify($replacement, null, $this->accountId('1-1200'), $this->user);
    $beforeReject = JournalEntry::count();
    $this->service->reject($submit($other, '350000.00'), 'Bukti salah', $this->user);
    $this->assertSame($beforeReject, JournalEntry::count());
    $this->assertTrue(app(ReceivablesReconciliation::class)->asOf('2026-07-10')['is_balanced']);
    $lines = JournalLine::whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))->get();
    $debit = $lines->reduce(fn (string $sum, JournalLine $line) => Money::add($sum, $line->debit), '0.00');
    $credit = $lines->reduce(fn (string $sum, JournalLine $line) => Money::add($sum, $line->credit), '0.00');
    $this->assertSame($debit, $credit);
    $this->assertSame(1, AuditLog::where('action', 'cancelled_by_guardian')->count());
}
```

Keep this deterministic fixture small; no new randomized test framework is needed.

- [x] **Step 4: Run the database-specific gate.**

```sh
php artisan test --compact tests/Feature/fees/PaymentReturnConcurrencyTest.php
vendor/bin/sail exec -e APP_ENV=testing -e DB_CONNECTION=pgsql -e DB_DATABASE=testing -e DB_HOST=pgsql laravel.test php vendor/bin/phpunit tests/Feature/fees/PaymentReturnConcurrencyTest.php
php artisan test --compact tests/Unit/Services/Finance/PaymentServiceTest.php
```

Expected: SQLite explicitly skips the PostgreSQL class; the PostgreSQL run executes all contention cases with no skips. Report any unavailable Docker/testing DB prerequisite; a SQLite pass never substitutes for these cases.

## Task 6: Complete browser journeys and project gates

**Files:** Review all changed files; `resources/js/components/app-sidebar.tsx` and `routes/fees.php` are navigation references, not planned edits. Update `docs/specs/21-payment-return.md` and `docs/specs/README.md` only when implementation and required proof actually pass.

**Dependencies:** Tasks 1–5.

**What this does:** Confirms the feature is reachable for each persona and records actual completion evidence.

**Verification:** Walk the table below in the browser, inspect only fresh errors, then run the final checks.

- [x] **Step 1: Execute these paths on existing or deliberately created local test data.** Resolve the project URL through Boost `get-absolute-url` when available. Never use the development DB for the destructive concurrency class.

| Persona / data | Clickable journey | Required observation |
| --- | --- | --- |
| Parent, partial pending claim | Login → Dashboard → Biaya Sekolah → bill selection → upload | 350000 remaining/200000 pending selects 150000; select-all behaves identically; badge remains |
| Parent, full or legacy excessive claim | Same path → upload list | Claimable 0, selection disabled; stale submit shows field error and preserves recoverable input |
| Parent, older pending transfer | Biaya Sekolah → Transfer Menunggu Verifikasi → next pages → Batalkan → confirm | Old record beyond history remains reachable, disappears after cancel, totals/claims refresh |
| Parent, final row on last page | Same path → last page → cancel | Page falls back or shows empty state; no dead end |
| Parent with partial access to a shared transfer | Biaya Sekolah → conflicting upload | Generic pending guidance; private transfer/other child absent; contact treasurer remains possible |
| Admin/teacher/finance/principal + parent | Role switch → parent → Dashboard → Biaya Sekolah → cancel | Parent journey works; active role is UI-only; direct request results do not depend on it |
| Admin/finance without parent | Dashboard → Verifikasi Transfer / Payments | School reject stays available; guardian cancellation endpoint is forbidden |
| Student | Dashboard → Biaya Sekolah | Read-only bills/history, no pending cancellation list, upload or cancel controls |

Check confirmation dismissal, loading/disabled state, success/error toasts, no negative money display and no new browser/server errors. If tools/accounts/data prevent a journey, record the exact missing proof and leave close-out incomplete.

- [x] **Step 2: Run narrow checks, then the full gate once.**

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Parent/FeePortalTest.php
php artisan test --compact tests/Feature/Parent/CancelTransferTest.php
php artisan test --compact tests/Feature/fees/VerificationTest.php
php artisan test --compact tests/Feature/Student/FeeTest.php
php artisan test --compact tests/Unit/Services/Finance/PaymentServiceTest.php
vendor/bin/sail composer ci:check
```

If using the documented host equivalent, run `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, then `composer test`; if host PHPStan hits its memory limit, run `vendor/bin/phpstan analyse --memory-limit=1G` and record that separately. Rerun an owner test after formatting changes to it. Do not skip hooks or automatically commit/push/deploy.

- [x] **Step 3: Record honest completion status.** Only after the required gates and browser proof pass, replace spec 21's `Status:` with `Implemented` plus the actual date, update its README row, and capture the shipped direction in the Presensio MOC. Report the SQLite, PostgreSQL, frontend and browser results separately. Keep cashier warnings and all spec 22 work outside the final diff.

## Acceptance coverage map

| Spec criterion | Owner task |
| --- | --- |
| AC-21-01…04 | Task 1 HTTP/arithmetic tests; claim release also Task 2 |
| AC-21-05…08 | Task 2 cancellation boundary and persisted effects |
| AC-21-09…11 | Task 1 mode/exclusion/verification regressions |
| AC-21-12…13 | Task 5 real contention tests |
| AC-21-14 | Tasks 3–4 props/selection/dialog; Task 6 browser proof |
| AC-21-15 | Task 5 deterministic ledger sequence |
| AC-21-16 | Tasks 1/3 non-positive limit and display; Task 6 UI |
| AC-21-17 | Task 3 pending pagination; Task 4 actions; Task 6 old-transfer journey |
| AC-21-18 | Task 2 union-role tests; Task 6 role-switch journey |
| AC-21-19 | Task 1 stale-upload HTTP rejection; Tasks 3–4 partial claims/select-all |
| AC-21-20 | Task 1 privacy feedback, Task 2 denial, Task 3 hidden payment scope |
| AC-21-21 | Task 2 lock-before-status authorization; Task 5 re-allocation contention |

## Verification record — 2026-10-23

- Implemented all six tasks. No migration, new dependency, payment status or notification type was added.
- `vendor/bin/sail composer ci:check` passed: JS formatting/lint, TypeScript, Pint, PHPStan and the full PHP suite. PHPUnit reported 1,540 cases: 1,536 passed, 4 PostgreSQL-only cases skipped on SQLite; 8,006 assertions.
- The four PostgreSQL contention cases passed separately with 37 assertions on the disposable `testing` database. They observed a real blocked database session and covered double upload, both terminal winners and authorization after re-allocation.
- Browser verification used the built frontend and an isolated server backed by `testing`. Factories prepared 21 pending transfers and 51 newer rejected payments; the temporary fixture test and browser artifacts were moved out of the repository afterward. The isolated server was stopped. Existing development payment data was not modified.
- Verified login/role-switch → Dashboard → Biaya Sekolah for parent plus teacher/finance/principal/admin; select-all used 150,000 of a 350,000 bill with a 200,000 pending claim. The pending badge stayed visible.
- Reached and cancelled a pending transfer older than the latest-50 history on page 2; cancelling its last row returned valid page-1 data and refreshed totals. Confirm/dismiss and success feedback worked.
- A second guardian kept a form open while the first submitted a cross-child transfer. The stale upload was rejected with bill-specific, non-negative feedback and school-contact guidance; bank input/file were preserved, and the inaccessible shared transfer stayed hidden. Fully claimed checkboxes were disabled.
- Verified finance Dashboard → Verifikasi Transfer → Payments and the rejected filter; guardian cancellation controls were absent for finance-only users. Student Dashboard → Biaya Sekolah stayed read-only with no pending cancellation section or upload controls. No fresh browser console errors were found on these journeys.
- Full CI exposed two existing integration assumptions: the route matrix needed the new parent-only endpoint, and the seeded ledger generator needed to expect atomic rejection of overlapping claims. Both owner tests were updated without reducing their invariants. Two student-import assertions were changed from raw HTML substrings to Inertia component/prop assertions so they also work with static assets; no import behavior changed.
- Implementation adjustments to the illustrative snippets: explicit portal-payment array-shape typing for PHPStan, a distinct bill period in the ledger sequence to respect the existing unique key, and a pending-query closure for partial Inertia visits. Scope and spec behavior are unchanged.
- The cashier pending-claim warning and spec 22 remain deferred. No commit, push or deployment was performed.
