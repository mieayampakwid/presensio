<?php

namespace Tests\Feature\Attendance;

use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentQrPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_linked_profile_receives_a_fresh_qr_payload(): void
    {
        $user = User::factory()->student()->create();
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('attendance.my-qr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/my-qr')
                ->has('qr')
                ->where('qr.svg', fn (string $svg) => str_starts_with($svg, '<svg'))
                ->where('qr.expires_at', fn (string $expiresAt) => Date::parse($expiresAt)->isFuture())
                ->where('qr.token', function (string $token) {
                    return (new QrTokenService)->verify($token)->verified();
                })
                ->where('qr.school_timezone', 'Asia/Jakarta'));
    }

    public function test_student_without_linked_profile_gets_an_empty_state(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->get(route('attendance.my-qr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/my-qr')
                ->where('qr', null));
    }

    #[DataProvider('nonStudentRoles')]
    public function test_non_student_roles_are_forbidden(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('attendance.my-qr'))
            ->assertForbidden();
    }

    public static function nonStudentRoles(): array
    {
        return [
            ['admin'],
            ['teacher'],
            ['parent'],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('attendance.my-qr'))->assertRedirect(route('login'));
    }
}
