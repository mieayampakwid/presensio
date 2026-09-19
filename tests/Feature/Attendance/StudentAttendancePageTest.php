<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentAttendancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-09-20 18:30 UTC = 2026-09-21 01:30 in Jakarta — the school
        // business date differs from the UTC date.
        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_student_sees_today_record_and_paginated_history(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);

        Attendance::factory()->create([
            'student_id' => $student->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Late,
            'checked_in_at' => '2026-09-21 00:45:00', // 07:45 Jakarta
        ]);
        Attendance::factory()->late()->create([
            'student_id' => $student->id,
            'date' => '2026-09-18',
            'checked_in_at' => '2026-09-18 00:50:00', // 07:50 Jakarta
        ]);
        Attendance::factory()->absent()->create([
            'student_id' => $student->id,
            'date' => '2026-09-17',
        ]);

        $this->actingAs($user)
            ->get(route('attendance.my-attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/my-attendance')
                ->where('school_timezone', 'Asia/Jakarta')
                ->where('today.status', 'late')
                ->where('today.checked_in_at', '07:45')
                ->where('today.checked_out_at', null)
                ->has('records.data', 2)
                ->where('records.current_page', 1)
                ->where('records.data.0.date', '2026-09-18')
                ->where('records.data.0.checked_in_at', '07:50')
                ->where('records.data.0.scan_method', 'rfid')
                ->where('records.data.1.date', '2026-09-17')
                ->where('records.data.1.checked_in_at', null)
                ->where('records.data.1.scan_method', null)
                ->etc());
    }

    public function test_today_is_excluded_from_history_rows(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);

        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-09-21']);
        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-09-18']);

        $this->actingAs($user)
            ->get(route('attendance.my-attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.status', 'present')
                ->has('records.data', 1)
                ->where('records.data.0.date', '2026-09-18'));
    }

    public function test_student_without_records_gets_an_empty_today_card(): void
    {
        $user = User::factory()->student()->create();
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('attendance.my-attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('today', null)
                ->has('records.data', 0));
    }

    public function test_student_without_linked_profile_gets_an_empty_state(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->get(route('attendance.my-attendance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('today', null)
                ->where('records', null));
    }

    #[DataProvider('nonStudentRoles')]
    public function test_non_student_roles_are_forbidden(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('attendance.my-attendance'))
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
        $this->get(route('attendance.my-attendance'))->assertRedirect(route('login'));
    }
}
