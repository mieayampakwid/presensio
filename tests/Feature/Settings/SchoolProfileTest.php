<?php

namespace Tests\Feature\Settings;

use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('school-profile.edit'))->assertRedirect(route('login'));
        $this->put(route('school-profile.update'), [])->assertRedirect(route('login'));
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        foreach (['teacher', 'student', 'parent'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->get(route('school-profile.edit'))
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('school-profile.update'), [])
                ->assertForbidden();
        }
    }

    public function test_admin_can_view_school_profile_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('school-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/school')
                ->has('profile')
            );
    }

    public function test_admin_can_update_school_profile_and_it_records_audit_log(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('school-profile.update'), [
                'school_name' => 'SMP Negeri 1 Surabaya',
                'npsn' => '20532100',
                'school_address' => 'Jl. Genteng Kali No. 10',
                'school_phone' => '031-5345678',
                'school_email' => 'info@smpn1sby.sch.id',
                'principal_name' => 'Dr. H. Sulaiman, M.Pd.',
                'principal_nip' => '197001011995011001',
                'bank_name' => 'Bank Mandiri',
                'bank_account_number' => '1420012345678',
                'bank_account_holder' => 'SMPN 1 Surabaya',
                'default_curriculum' => 'merdeka',
            ])
            ->assertRedirect(route('school-profile.edit'));

        $this->assertDatabaseHas('settings', [
            'school_name' => 'SMP Negeri 1 Surabaya',
            'npsn' => '20532100',
            'principal_name' => 'Dr. H. Sulaiman, M.Pd.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'updated',
            'auditable_type' => (new SchoolSetting)->getMorphClass(),
        ]);
    }

    public function test_logo_upload_validates_png_and_jpg_and_max_size(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        // 1. Valid PNG <= 1MB
        $file = UploadedFile::fake()->image('logo.png', 200, 200)->size(500);
        $this->actingAs($admin)
            ->put(route('school-profile.update'), [
                'school_name' => 'SMP Negeri 1 Surabaya',
                'default_curriculum' => 'merdeka',
                'logo' => $file,
            ])
            ->assertSessionHasNoErrors();

        $setting = SchoolSetting::first();
        $this->assertNotNull($setting->logo_path);
        Storage::disk('local')->assertExists($setting->logo_path);

        // 2. File size > 1024 KB rejected with 422
        $tooLarge = UploadedFile::fake()->image('huge.png', 800, 800)->size(2048);
        $this->actingAs($admin)
            ->put(route('school-profile.update'), [
                'school_name' => 'SMP Negeri 1 Surabaya',
                'default_curriculum' => 'merdeka',
                'logo' => $tooLarge,
            ])
            ->assertSessionHasErrors('logo');

        // 3. GIF format rejected with 422
        $gif = UploadedFile::fake()->create('logo.gif', 200, 'image/gif');
        $this->actingAs($admin)
            ->put(route('school-profile.update'), [
                'school_name' => 'SMP Negeri 1 Surabaya',
                'default_curriculum' => 'merdeka',
                'logo' => $gif,
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_old_logo_is_deleted_on_replacement(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $firstLogo = UploadedFile::fake()->image('first.png', 100, 100)->size(100);
        $this->actingAs($admin)
            ->put(route('school-profile.update'), [
                'school_name' => 'School',
                'default_curriculum' => 'merdeka',
                'logo' => $firstLogo,
            ]);

        $firstPath = SchoolSetting::first()->logo_path;
        Storage::disk('local')->assertExists($firstPath);

        $secondLogo = UploadedFile::fake()->image('second.jpg', 100, 100)->size(100);
        $this->actingAs($admin)
            ->put(route('school-profile.update'), [
                'school_name' => 'School',
                'default_curriculum' => 'merdeka',
                'logo' => $secondLogo,
            ]);

        $secondPath = SchoolSetting::first()->logo_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
    }

    public function test_logo_stream_route_returns_image_for_any_authenticated_user(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->putFile('school', UploadedFile::fake()->image('logo.png'));
        SchoolSetting::first()->update(['logo_path' => $path]);

        // Teacher can view
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)
            ->get(route('school-logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Parent can view
        $parent = User::factory()->parent()->create();
        $this->actingAs($parent)
            ->get(route('school-logo'))
            ->assertOk();

        // Guest cannot
        auth()->logout();
        $this->get(route('school-logo'))->assertRedirect(route('login'));
    }

    public function test_logo_stream_route_returns_404_when_unset_or_missing(): void
    {
        SchoolSetting::first()->update(['logo_path' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('school-logo'))
            ->assertNotFound();
    }
}
