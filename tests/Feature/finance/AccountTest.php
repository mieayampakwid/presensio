<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_finance_can_view_accounts_list(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = User::factory()->finance()->create();
        Account::factory()->create(['code' => '1-1100', 'name' => 'Kas Tunai']);

        $this->actingAs($admin)
            ->get(route('finance.accounts.index'))
            ->assertOk();

        $this->actingAs($finance)
            ->get(route('finance.accounts.index'))
            ->assertOk();
    }

    public function test_principal_can_view_accounts_list_read_only(): void
    {
        $principal = User::factory()->principal()->create();
        Account::factory()->create(['code' => '1-1100', 'name' => 'Kas Tunai']);

        $this->actingAs($principal)
            ->get(route('finance.accounts.index'))
            ->assertOk();
    }

    public function test_unauthorized_roles_cannot_view_accounts(): void
    {
        $teacher = User::factory()->teacher()->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs($teacher)
            ->get(route('finance.accounts.index'))
            ->assertForbidden();

        $this->actingAs($parent)
            ->get(route('finance.accounts.index'))
            ->assertForbidden();
    }

    public function test_principal_and_finance_cannot_create_update_or_delete_account(): void
    {
        $account = Account::factory()->create(['code' => '1-1100']);

        foreach ([User::factory()->principal()->create(), User::factory()->finance()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('finance.accounts.store'), [
                    'code' => '1-1200',
                    'name' => 'Bank',
                    'type' => 'asset',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('finance.accounts.update', $account), [
                    'code' => '1-1100',
                    'name' => 'Kas Baru',
                    'type' => 'asset',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('finance.accounts.destroy', $account))
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('accounts', ['code' => '1-1200']);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => $account->name]);
    }

    public function test_only_admin_can_manage_accounts_on_index(): void
    {
        foreach (['admin' => true, 'finance' => false, 'principal' => false] as $role => $canManage) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('finance.accounts.index'))
                ->assertInertia(fn (Assert $page) => $page->where('canManage', $canManage));
        }
    }

    public function test_admin_can_create_new_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('finance.accounts.store'), [
            'code' => '1-1500',
            'name' => 'Perlengkapan Sekolah',
            'type' => 'asset',
            'is_cash' => false,
            'is_postable' => true,
        ]);

        $response->assertRedirect(route('finance.accounts.index'));
        $this->assertDatabaseHas('accounts', [
            'code' => '1-1500',
            'name' => 'Perlengkapan Sekolah',
            'type' => 'asset',
            'is_postable' => true,
        ]);
    }

    public function test_code_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Account::factory()->create(['code' => '1-1100']);

        $this->actingAs($admin)->post(route('finance.accounts.store'), [
            'code' => '1-1100',
            'name' => 'Kas Duplikat',
            'type' => 'asset',
        ])->assertSessionHasErrors(['code']);
    }

    public function test_restriction_only_allowed_for_net_assets(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('finance.accounts.store'), [
            'code' => '4-1100',
            'name' => 'Pendapatan Salah',
            'type' => 'revenue',
            'restriction' => 'with_restriction',
        ])->assertSessionHasErrors(['restriction']);

        // Allowed for net assets
        $this->actingAs($admin)->post(route('finance.accounts.store'), [
            'code' => '3-1000',
            'name' => 'Aset Neto Valid',
            'type' => 'net_assets',
            'restriction' => 'with_restriction',
        ])->assertRedirect(route('finance.accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'code' => '3-1000',
            'restriction' => 'with_restriction',
        ]);
    }

    public function test_cannot_delete_account_with_posted_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $account = Account::factory()->create(['code' => '1-1100']);
        $entry = JournalEntry::factory()->create(['status' => JournalStatus::Posted]);

        JournalLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => '50000.00',
            'credit' => '0.00',
        ]);

        $this->actingAs($admin)
            ->delete(route('finance.accounts.destroy', $account))
            ->assertStatus(422);

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_cannot_change_type_of_account_with_posted_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $account = Account::factory()->create(['code' => '1-1100', 'type' => AccountType::Asset]);
        $entry = JournalEntry::factory()->create(['status' => JournalStatus::Posted]);

        JournalLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => '50000.00',
            'credit' => '0.00',
        ]);

        $this->actingAs($admin)
            ->put(route('finance.accounts.update', $account), [
                'code' => '1-1100',
                'name' => 'Kas Tunai Updated',
                'type' => 'liability',
            ])
            ->assertSessionHasErrors(['type']);
    }

    public function test_parent_account_loses_postable_when_child_is_added(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Account::factory()->create([
            'code' => '1-1000',
            'name' => 'Kas & Bank',
            'is_postable' => true,
        ]);

        $this->actingAs($admin)->post(route('finance.accounts.store'), [
            'code' => '1-1100',
            'name' => 'Kas Tunai',
            'type' => 'asset',
            'parent_id' => $parent->id,
            'is_postable' => true,
        ])->assertRedirect(route('finance.accounts.index'));

        $this->assertFalse($parent->fresh()->is_postable);
    }

    public function test_chart_of_accounts_seeder_creates_default_accounts_and_wires_settings(): void
    {
        SchoolSetting::query()->firstOrCreate([], [
            'school_timezone' => 'Asia/Jakarta',
            'school_start_time' => '07:30:00',
        ]);

        $this->seed(ChartOfAccountsSeeder::class);

        $this->assertDatabaseHas('accounts', ['code' => '1-1100', 'name' => 'Kas Tunai', 'is_cash' => true]);
        $this->assertDatabaseHas('accounts', ['code' => '1-1300', 'name' => 'Piutang SPP']);
        $this->assertDatabaseHas('accounts', ['code' => '2-1100', 'name' => 'Pendapatan Diterima di Muka']);
        $this->assertDatabaseHas('accounts', ['code' => '3-1000', 'name' => 'Aset Neto Tanpa Pembatasan']);
        $this->assertDatabaseHas('accounts', ['code' => '4-1900', 'is_contra' => true]);
        $this->assertDatabaseHas('accounts', ['code' => '5-9100', 'name' => 'Beban Penghapusan Piutang']);

        $settings = SchoolSetting::first();
        $this->assertNotNull($settings->prepaid_revenue_account_id);
        $this->assertNotNull($settings->bad_debt_expense_account_id);
        $this->assertNotNull($settings->net_assets_closing_account_id);
        $this->assertSame('1-1200', $settings->defaultTransferAccount->code);
    }
}
