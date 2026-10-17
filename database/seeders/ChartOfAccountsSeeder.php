<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\NetAssetRestriction;
use App\Models\Account;
use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Aset (1-xxxx)
            [
                'code' => '1-1100',
                'name' => 'Kas Tunai',
                'type' => AccountType::Asset,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => true,
                'is_active' => true,
            ],
            [
                'code' => '1-1200',
                'name' => 'Bank',
                'type' => AccountType::Asset,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => true,
                'is_active' => true,
            ],
            [
                'code' => '1-1300',
                'name' => 'Piutang SPP',
                'type' => AccountType::Asset,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '1-1310',
                'name' => 'Piutang Biaya Lainnya',
                'type' => AccountType::Asset,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],

            // Liabilitas (2-xxxx)
            [
                'code' => '2-1100',
                'name' => 'Pendapatan Diterima di Muka',
                'type' => AccountType::Liability,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],

            // Aset Neto (3-xxxx)
            [
                'code' => '3-1000',
                'name' => 'Aset Neto Tanpa Pembatasan',
                'type' => AccountType::NetAssets,
                'is_contra' => false,
                'restriction' => NetAssetRestriction::WithoutRestriction,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '3-2000',
                'name' => 'Aset Neto Dengan Pembatasan',
                'type' => AccountType::NetAssets,
                'is_contra' => false,
                'restriction' => NetAssetRestriction::WithRestriction,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],

            // Pendapatan (4-xxxx)
            [
                'code' => '4-1100',
                'name' => 'Pendapatan SPP',
                'type' => AccountType::Revenue,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '4-1200',
                'name' => 'Pendapatan Uang Pangkal',
                'type' => AccountType::Revenue,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '4-1900',
                'name' => 'Potongan & Beasiswa',
                'type' => AccountType::Revenue,
                'is_contra' => true,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],

            // Beban (5-xxxx)
            [
                'code' => '5-1100',
                'name' => 'Beban Gaji & Honorarium',
                'type' => AccountType::Expense,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '5-1200',
                'name' => 'Beban Operasional Pendidikan',
                'type' => AccountType::Expense,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '5-1300',
                'name' => 'Beban Listrik, Air & Internet',
                'type' => AccountType::Expense,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => '5-9100',
                'name' => 'Beban Penghapusan Piutang',
                'type' => AccountType::Expense,
                'is_contra' => false,
                'restriction' => null,
                'is_postable' => true,
                'is_cash' => false,
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $data) {
            Account::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        // Wire ledger settings default accounts
        $prepaid = Account::where('code', '2-1100')->first();
        $badDebt = Account::where('code', '5-9100')->first();
        $netAssetsClosing = Account::where('code', '3-1000')->first();
        $transfer = Account::where('code', '1-1200')->first();

        $settings = SchoolSetting::first();
        if ($settings) {
            $settings->update([
                'prepaid_revenue_account_id' => $prepaid?->id,
                'bad_debt_expense_account_id' => $badDebt?->id,
                'net_assets_closing_account_id' => $netAssetsClosing?->id,
                'default_transfer_account_id' => $transfer?->id,
            ]);
        }
    }
}
