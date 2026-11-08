# Cashier Default Cash Account Implementation Plan

> **For implementer agents:** Read each task's full description. Execute in order. Each task is independently testable.

**Goal:** Make the cashier's pre-selected cash account on `/fees/cashier` a configurable school setting on `/settings/school` instead of a hard-coded account code (`1-1100`), with an automatic fallback to the first active cash account when unset.

**Architecture:** Add a nullable foreign key `cashier_default_cash_account_id` on the `settings` table, matching the pattern of `default_transfer_account_id`. Expose it via `SchoolSetting` and `SchoolSettings`, validate in `UpdateSchoolProfileRequest`, allow editing by admins on the school profile page with audit logging, and consume it in `CashierController::index()` to determine `defaultAccountId` while removing the `KAS_TUNAI_CODE` constant.

**Repos:** Single repository (`presensio`).

**Prerequisites:** None. Database is pre-production; migrations run additive changes.

---

### Task 1: Database Migration & Domain Model Storage

**Files:**
- Create: `database/migrations/2026_11_08_000001_add_cashier_default_cash_account_id_to_settings_table.php`
- Modify: `app/Models/SchoolSetting.php:40-165`
- Modify: `app/Services/SchoolSettings.php:280-330`
- Test: `tests/Feature/Settings/SchoolProfileTest.php`

**Dependencies:** None

**What this does:** Adds `cashier_default_cash_account_id` nullable column to `settings`, configures the `SchoolSetting` model with relation/fillable/cast, and adds the accessor to the `SchoolSettings` service.

**Verification:** Run migration and verify that `SchoolSettings::cashierDefaultCashAccountId()` returns null by default and returns an integer when configured.

- [ ] **Step 1: Create migration**
  Create file `database/migrations/2026_11_08_000001_add_cashier_default_cash_account_id_to_settings_table.php`:

  ```php
  <?php

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration
  {
      public function up(): void
      {
          Schema::table('settings', function (Blueprint $table) {
              $table->foreignId('cashier_default_cash_account_id')->nullable()->constrained('accounts')->nullOnDelete();
          });
      }

      public function down(): void
      {
          Schema::table('settings', function (Blueprint $table) {
              $table->dropConstrainedForeignId('cashier_default_cash_account_id');
          });
      }
  };
  ```

- [ ] **Step 2: Update `SchoolSetting` model**
  In `app/Models/SchoolSetting.php`:
  1. Add property annotation to class docblock:
     ```php
      * @property int|null $cashier_default_cash_account_id
      * @property-read Account|null $cashierDefaultCashAccount
     ```
  2. Add `'cashier_default_cash_account_id'` to the `#[Fillable([...])]` attribute array (around line 93, alongside `default_transfer_account_id`).
  3. Add relationship method:
     ```php
      /** @return BelongsTo<Account, $this> */
      public function cashierDefaultCashAccount(): BelongsTo
      {
          return $this->belongsTo(Account::class, 'cashier_default_cash_account_id');
      }
     ```
  4. Add `'cashier_default_cash_account_id' => 'integer'` to `casts()` array (around line 160).

- [ ] **Step 3: Update `SchoolSettings` service**
  In `app/Services/SchoolSettings.php`:
  1. Add getter method around line 287 (adjacent to `defaultTransferAccountId()`):
     ```php
      /**
       * Cash account pre-selected on the cashier screen.
       */
      public function cashierDefaultCashAccountId(): ?int
      {
          $id = $this->row()->cashier_default_cash_account_id;

          return $id !== null ? (int) $id : null;
      }
     ```
  2. Add `'cashier_default_cash_account_id' => null` to the `defaults()` method array around line 328.

- [ ] **Step 4: Run migration and verify model state**
  Run: `php artisan migrate --no-interaction`
  Run: `php artisan test --compact tests/Feature/Settings/SchoolProfileTest.php`
  Expected: Migrations succeed and existing profile tests pass.

---

### Task 2: School Profile Settings Form & Validation (Admin UI & Audit)

**Files:**
- Modify: `app/Http/Requests/Settings/UpdateSchoolProfileRequest.php:40-58`
- Modify: `app/Http/Controllers/Settings/SchoolProfileController.php:30-60`
- Modify: `resources/js/pages/settings/school.tsx:20-60,350-385`
- Test: `tests/Feature/Settings/SchoolProfileTest.php`

**Dependencies:** Task 1

**What this does:** Validates and persists `cashier_default_cash_account_id` via admin school profile update, records changes in `audit_logs`, and renders the selector in the settings page.

**Verification:** Feature test asserting acceptance of active postable cash account, rejection of invalid accounts, clearing to null, and audit log recording (AC 23-01, 23-02, 23-05).

- [ ] **Step 1: Add validation rule in `UpdateSchoolProfileRequest`**
  In `app/Http/Requests/Settings/UpdateSchoolProfileRequest.php`, add to `rules()`:
  ```php
              'cashier_default_cash_account_id' => [
                  'nullable',
                  'integer',
                  Rule::exists('accounts', 'id')->where(fn ($query) => $query
                      ->where('is_cash', true)
                      ->where('is_active', true)
                      ->where('is_postable', true)),
              ],
  ```

- [ ] **Step 2: Expose `cashier_default_cash_account_id` in `SchoolProfileController`**
  In `app/Http/Controllers/Settings/SchoolProfileController.php`, update `edit()` `profile` array:
  ```php
              'profile' => [
                  'school_name' => $setting->school_name,
                  'npsn' => $setting->npsn,
                  'school_address' => $setting->school_address,
                  'school_phone' => $setting->school_phone,
                  'school_email' => $setting->school_email,
                  'has_logo' => $setting->logo_path !== null,
                  'principal_name' => $setting->principal_name,
                  'principal_nip' => $setting->principal_nip,
                  'bank_name' => $setting->bank_name,
                  'bank_account_number' => $setting->bank_account_number,
                  'bank_account_holder' => $setting->bank_account_holder,
                  'default_curriculum' => $setting->default_curriculum,
                  'default_passing_threshold' => (string) ($setting->default_passing_threshold ?? '75.00'),
                  'default_transfer_account_id' => $setting->default_transfer_account_id,
                  'guardian_deposit_account_id' => $setting->guardian_deposit_account_id,
                  'cashier_default_cash_account_id' => $setting->cashier_default_cash_account_id,
              ],
  ```

- [ ] **Step 3: Update `resources/js/pages/settings/school.tsx`**
  1. In `SchoolProfileData` type definition (around line 27):
     ```ts
         default_transfer_account_id: number | null;
         guardian_deposit_account_id: number | null;
         cashier_default_cash_account_id: number | null;
     ```
  2. Right after the `default_transfer_account_id` input block (around line 380), add:
     ```tsx
                                     <div className="grid gap-2">
                                         <Label htmlFor="cashier_default_cash_account_id">
                                             Akun kas default kasir
                                         </Label>
                                         <select
                                             id="cashier_default_cash_account_id"
                                             name="cashier_default_cash_account_id"
                                             defaultValue={
                                                 profile.cashier_default_cash_account_id ??
                                                 ''
                                             }
                                             className={SELECT_CLASS}
                                         >
                                             <option value="">
                                                 Otomatis (akun kas pertama)
                                             </option>
                                             {cashAccounts.map((account) => (
                                                 <option
                                                     key={account.id}
                                                     value={account.id}
                                                 >
                                                     {account.code} -{' '}
                                                     {account.name}
                                                 </option>
                                             ))}
                                         </select>
                                         <p className="text-muted-foreground text-sm">
                                             Akun kas yang otomatis dipilih saat kasir membuka sesi pembayaran tunai baru.
                                         </p>
                                         <InputError
                                             message={
                                                 errors.cashier_default_cash_account_id
                                             }
                                         />
                                     </div>
     ```

- [ ] **Step 4: Add feature test in `tests/Feature/Settings/SchoolProfileTest.php`**
  Add test method to `tests/Feature/Settings/SchoolProfileTest.php`:
  ```php
      public function test_cashier_default_cash_account_accepts_valid_cash_account_rejects_others_and_can_be_cleared(): void
      {
          $admin = User::factory()->admin()->create();
          $validCash = Account::factory()->create([
              'type' => 'asset',
              'is_cash' => true,
              'is_active' => true,
              'is_postable' => true,
          ]);
          $nonCash = Account::factory()->create([
              'type' => 'asset',
              'is_cash' => false,
              'is_active' => true,
              'is_postable' => true,
          ]);
          $inactiveCash = Account::factory()->create([
              'type' => 'asset',
              'is_cash' => true,
              'is_active' => false,
              'is_postable' => true,
          ]);
          $nonPostableCash = Account::factory()->create([
              'type' => 'asset',
              'is_cash' => true,
              'is_active' => true,
              'is_postable' => false,
          ]);

          $payload = [
              'school_name' => 'School',
              'default_curriculum' => 'merdeka',
          ];

          // 1. Valid cash account accepted and logged in audit log (AC 23-01, 23-02)
          $this->actingAs($admin)
              ->put(route('school-profile.update'), $payload + ['cashier_default_cash_account_id' => $validCash->id])
              ->assertSessionHasNoErrors();
          $this->assertSame($validCash->id, SchoolSetting::first()->cashier_default_cash_account_id);

          $this->assertDatabaseHas('audit_logs', [
              'user_id' => $admin->id,
              'action' => 'updated',
              'auditable_type' => (new SchoolSetting)->getMorphClass(),
          ]);

          // 2. Invalid accounts fail validation (AC 23-02)
          foreach ([$nonCash, $inactiveCash, $nonPostableCash] as $invalidAccount) {
              $this->actingAs($admin)
                  ->put(route('school-profile.update'), $payload + ['cashier_default_cash_account_id' => $invalidAccount->id])
                  ->assertSessionHasErrors('cashier_default_cash_account_id');
              $this->assertSame($validCash->id, SchoolSetting::first()->cashier_default_cash_account_id);
          }

          // 3. Can be cleared to null (AC 23-01)
          $this->actingAs($admin)
              ->put(route('school-profile.update'), $payload + ['cashier_default_cash_account_id' => null])
              ->assertSessionHasNoErrors();
          $this->assertNull(SchoolSetting::first()->cashier_default_cash_account_id);
      }
  ```

- [ ] **Step 5: Verify types and ratchet i18n baseline**
  Run: `sail npm run types:check`
  Run: `sail npm run test:i18n -- --update`
  Run: `php artisan test --compact tests/Feature/Settings/SchoolProfileTest.php`
  Expected: All checks pass; baseline text file is ratcheted clean.

---

### Task 3: Cashier Screen Pre-Selection & Constant Removal

**Files:**
- Modify: `app/Http/Controllers/Fees/CashierController.php:15-75`
- Modify: `tests/Feature/Fees/CashierTest.php`

**Dependencies:** Task 1, Task 2

**What this does:** Removes `KAS_TUNAI_CODE` constant from `CashierController`, injects `SchoolSettings`, resolves `defaultAccountId` following `setting → first active cash account by code → null`, while leaving payment processing and validation untouched.

**Verification:** Feature tests in `tests/Feature/Fees/CashierTest.php` covering AC 23-03, 23-04, 23-06, and reflection check proving constant removal.

- [ ] **Step 1: Update `CashierController`**
  In `app/Http/Controllers/Fees/CashierController.php`:
  1. Add import:
     ```php
     use App\Services\SchoolSettings;
     ```
  2. Remove line 21:
     ```php
     // DELETE: private const KAS_TUNAI_CODE = '1-1100';
     ```
  3. Inject `SchoolSettings $settings` in `index()` method signature:
     ```php
     public function index(Request $request, SchoolSettings $settings): Response
     ```
  4. Update `defaultAccountId` resolution around line 69:
     ```php
         $cashAccounts = Account::query()
             ->where('is_cash', true)
             ->where('is_active', true)
             ->orderBy('code')
             ->get(['id', 'code', 'name']);

         $defaultSettingId = $settings->cashierDefaultCashAccountId();
         $defaultAccountId = ($defaultSettingId !== null ? $cashAccounts->firstWhere('id', $defaultSettingId) : null)?->id ?? $cashAccounts->first()?->id;
     ```
     And pass `'defaultAccountId' => $defaultAccountId` in the Inertia payload.

- [ ] **Step 2: Add tests in `tests/Feature/Fees/CashierTest.php`**
  Add the following tests to `tests/Feature/Fees/CashierTest.php`:

  ```php
      public function test_cashier_screen_preselects_configured_default_cash_account_ac_23_03(): void
      {
          $secondCash = Account::factory()->create([
              'code' => '1-1109',
              'name' => 'Kas Khusus',
              'is_cash' => true,
              'is_active' => true,
              'is_postable' => true,
          ]);

          app(SchoolSettings::class)->row()->update([
              'cashier_default_cash_account_id' => $secondCash->id,
          ]);
          app(SchoolSettings::class)->refresh();

          $this->actingAs($this->finance)
              ->get(route('fees.cashier.index'))
              ->assertInertia(fn (Assert $page) => $page
                  ->component('fees/cashier')
                  ->where('defaultAccountId', $secondCash->id));
      }

      public function test_cashier_screen_falls_back_to_first_active_cash_account_when_setting_is_null_or_invalid_ac_23_04(): void
      {
          // 1. Setting is null -> falls back to first active cash account by code (1-1100)
          app(SchoolSettings::class)->row()->update([
              'cashier_default_cash_account_id' => null,
          ]);
          app(SchoolSettings::class)->refresh();

          $this->actingAs($this->finance)
              ->get(route('fees.cashier.index'))
              ->assertInertia(fn (Assert $page) => $page
                  ->component('fees/cashier')
                  ->where('defaultAccountId', $this->accountId('1-1100')));

          // 2. Setting points to an inactive cash account -> falls back to first active cash account by code (1-1100)
          $inactiveCash = Account::factory()->create([
              'code' => '1-1105',
              'name' => 'Kas Nonaktif',
              'is_cash' => true,
              'is_active' => false,
              'is_postable' => true,
          ]);

          app(SchoolSettings::class)->row()->update([
              'cashier_default_cash_account_id' => $inactiveCash->id,
          ]);
          app(SchoolSettings::class)->refresh();

          $this->actingAs($this->finance)
              ->get(route('fees.cashier.index'))
              ->assertInertia(fn (Assert $page) => $page
                  ->component('fees/cashier')
                  ->where('defaultAccountId', $this->accountId('1-1100')));

          // 3. Confirm CashierController contains no KAS_TUNAI_CODE constant
          $reflection = new \ReflectionClass(\App\Http\Controllers\Fees\CashierController::class);
          $this->assertFalse($reflection->hasConstant('KAS_TUNAI_CODE'));
      }

      public function test_changing_cashier_default_account_does_not_alter_past_payments_ac_23_06(): void
      {
          $student = Student::factory()->create();
          $bill = $this->septemberBill($student);
          $originalCashId = $this->accountId('1-1100');

          $payload = $this->payload('350000', [$bill->id => '350000'], [$student->id], [
              'deposit_account_id' => $originalCashId,
          ]);

          $this->actingAs($this->finance)
              ->post(route('fees.cashier.store'), $payload)
              ->assertRedirect();

          $payment = Payment::firstOrFail();
          $this->assertSame($originalCashId, $payment->deposit_account_id);

          // Change cashier setting to a different cash account
          $newCash = Account::factory()->create([
              'code' => '1-1109',
              'name' => 'Kas Lain',
              'is_cash' => true,
              'is_active' => true,
              'is_postable' => true,
          ]);

          app(SchoolSettings::class)->row()->update([
              'cashier_default_cash_account_id' => $newCash->id,
          ]);
          app(SchoolSettings::class)->refresh();

          // Existing payment remains unchanged
          $payment->refresh();
          $this->assertSame($originalCashId, $payment->deposit_account_id);
      }
  ```

- [ ] **Step 3: Run Cashier tests**
  Run: `php artisan test --compact tests/Feature/Fees/CashierTest.php`
  Expected: All tests pass (including existing and newly added ones).

---

### Task 4: Full Verification & Status Update

**Files:**
- Modify: `docs/specs/23-cashier-default-account.md:1-5`
- Modify: `docs/specs/README.md:63`
- Modify: `CLAUDE.md:230`

**Dependencies:** Task 1, Task 2, Task 3

**What this does:** Runs code formatting, type checking, linter, test suite, executes door-to-door browser check, and flips status to implemented.

**Verification:** Full CI check passes (`composer ci:check`) and browser journey verifies configured default on cashier screen.

- [ ] **Step 1: Run code formatters and static analysis**
  Run: `vendor/bin/pint --dirty --format agent`
  Run: `sail npm run check`
  Run: `sail npm run types:check`
  Run: `sail npm run test:i18n`

- [ ] **Step 2: Run targeted tests and full CI check**
  Run: `php artisan test --compact tests/Feature/Settings/SchoolProfileTest.php tests/Feature/Fees/CashierTest.php`
  Run: `composer ci:check`
  Expected: Clean pass across all checks.

- [ ] **Step 3: Door-to-door browser verification**
  1. Login as Admin (`/login`).
  2. Navigate to Settings -> School Profile (`/settings/school`).
  3. Verify "Akun kas default kasir" selector is present alongside "Rekening tujuan transfer".
  4. Select a specific cash account (e.g., "1-1100 - Kas Tunai" or alternative cash account) and click "Save School Profile". Verify success toast and audit record in `/admin/audit-logs`.
  5. Navigate to Cashier (`/fees/cashier`) with Finance or Admin persona. Verify that the deposit account selector pre-selects the configured account.
  6. Return to Settings -> School Profile (`/settings/school`), select "Otomatis (akun kas pertama)", and save.
  7. Return to Cashier (`/fees/cashier`). Verify that the first active cash account by code is pre-selected.

- [ ] **Step 4: Update status in spec and index**
  1. In `docs/specs/23-cashier-default-account.md`:
     Update `Status: draft v1.0 (2026-11-01)` to:
     `Status: implemented 2026-11-08 (v1.0)`
  2. In `docs/specs/README.md`:
     Update row 23:
     `| 23 | [Cashier Default Cash Account](23-cashier-default-account.md) | Implemented 2026-11-08 | Configurable cashier cash-account pre-selection (amends 14) |`
  3. In `CLAUDE.md`:
     Update status block to record Spec 23 completion.
