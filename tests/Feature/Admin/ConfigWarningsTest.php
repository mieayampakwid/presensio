<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConfigWarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_pending_config_warnings_when_configs_need_data(): void
    {
        $admin = User::factory()->admin()->create();

        // Ensure settings are unconfigured
        $setting = app(SchoolSettings::class)->row();
        $setting->update([
            'school_name' => '',
            'cashier_default_cash_account_id' => null,
            'default_transfer_account_id' => null,
            'guardian_deposit_account_id' => null,
        ]);
        app(SchoolSettings::class)->refresh();

        // Create class with grade_level = 0
        SchoolClass::factory()->create(['grade_level' => 0]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pending_config_warnings')
                ->where('pending_config_warnings', function ($warnings) {
                    $ids = collect($warnings)->pluck('id')->all();

                    return in_array('school_name', $ids)
                        && in_array('cashier_default_cash_account', $ids)
                        && in_array('default_transfer_account', $ids)
                        && in_array('guardian_deposit_account', $ids)
                        && in_array('incomplete_classes', $ids);
                })
            );
    }

    public function test_admin_receives_no_warnings_when_everything_is_properly_configured(): void
    {
        $admin = User::factory()->admin()->create();

        $cash = Account::factory()->cash()->create();
        $transfer = Account::factory()->cash()->create();
        $deposit = Account::factory()->create([
            'type' => 'liability',
            'is_cash' => false,
            'is_contra' => false,
            'is_active' => true,
            'is_postable' => true,
        ]);

        $year = AcademicYear::factory()->create(['is_active' => true]);
        SchoolSetting::first()->update([
            'school_name' => 'SMP Presensio',
            'cashier_default_cash_account_id' => $cash->id,
            'default_transfer_account_id' => $transfer->id,
            'guardian_deposit_account_id' => $deposit->id,
        ]);
        app(SchoolSettings::class)->refresh();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pending_config_warnings', 0)
            );
    }

    public function test_non_admin_users_do_not_receive_config_warnings(): void
    {
        $teacher = User::factory()->teacher()->create();
        $parent = User::factory()->parent()->create();

        // Leave configs unconfigured
        SchoolSetting::first()->update([
            'cashier_default_cash_account_id' => null,
            'default_transfer_account_id' => null,
        ]);
        app(SchoolSettings::class)->refresh();

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pending_config_warnings', 0)
            );

        $this->actingAs($parent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pending_config_warnings', 0)
            );
    }
}
