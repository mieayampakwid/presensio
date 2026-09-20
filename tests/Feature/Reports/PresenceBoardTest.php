<?php

namespace Tests\Feature\Reports;

use App\Models\Attendance;
use App\Models\NonSchoolDay;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PresenceBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // School-tz today = Monday 2026-09-21 while UTC still says the 20th.
        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function teacherUser(): User
    {
        $user = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function todayRecord(Student $student, string $state = 'default'): Attendance
    {
        $factory = Attendance::factory();

        if ($state !== 'default') {
            $factory = $factory->{$state}();
        }

        return $factory->create([
            'student_id' => $student->id,
            'date' => '2026-09-21',
        ]);
    }

    /**
     * Two classes, six students, one of today's every bucket:
     * 5A — 1 present without times (bulk-marked), 1 late (both
     * in-building), 1 checked out, 1 sick; 5B — 1 with no record yet,
     * 1 absent.
     */
    private function seedBoard(): void
    {
        $classA = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $classB = SchoolClass::factory()->create(['name' => 'Kelas 5B']);

        $this->todayRecord(Student::factory()->create([
            'class_id' => $classA->id, 'full_name' => 'Alpha One',
        ]));
        $this->todayRecord(Student::factory()->create([
            'class_id' => $classA->id, 'full_name' => 'Bravo Two',
        ]), 'late');
        $this->todayRecord(Student::factory()->create([
            'class_id' => $classA->id, 'full_name' => 'Charlie Three',
        ]), 'checkedOut');
        $this->todayRecord(Student::factory()->create([
            'class_id' => $classA->id, 'full_name' => 'Delta Four',
        ]), 'sick');

        Student::factory()->create(['class_id' => $classB->id, 'full_name' => 'Echo Five']);
        $this->todayRecord(Student::factory()->create([
            'class_id' => $classB->id, 'full_name' => 'Foxtrot Six',
        ]), 'absent');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('presence-board.index'))->assertRedirect(route('login'));
    }

    public function test_students_and_parents_are_forbidden(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('presence-board.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('presence-board.index'))
            ->assertForbidden();
    }

    public function test_a_teacher_sees_only_their_class_and_no_totals(): void
    {
        $user = $this->teacherUser();
        SchoolClass::factory()->create(['name' => 'Kelas 5A', 'teacher_id' => $user->teacher->id]);
        $this->seedBoard();

        $this->actingAs($user)
            ->get(route('presence-board.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/presence-board')
                ->has('board.classes', 1)
                ->where('board.classes.0.name', 'Kelas 5A')
                ->where('board.totals', null));
    }

    public function test_the_admin_sees_school_wide_totals_and_the_right_buckets(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedBoard();

        $this->actingAs($admin)
            ->get(route('presence-board.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.classes', 2)
                ->where('board.classes.0.in_building', 2) // present without times + late
                ->where('board.classes.0.checked_out', 1)
                ->where('board.classes.0.not_checked_in', 1)
                ->where('board.classes.0.not_in.0.full_name', 'Delta Four')
                ->where('board.classes.0.not_in.0.status', 'sick')
                ->where('board.classes.1.not_in.0.full_name', 'Echo Five')
                ->where('board.classes.1.not_in.0.status', null)
                ->where('board.classes.1.not_in.1.status', 'absent')
                ->where('board.totals.enrolled', 6)
                ->where('board.totals.in_building', 2)
                ->where('board.totals.checked_out', 1)
                ->where('board.totals.not_checked_in', 3)
                ->where('board.as_of', '01:30') // school tz (UTC + 7)
                ->where('is_school_day', true));
    }

    public function test_records_from_yesterday_do_not_count(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);
        Attendance::factory()->checkedOut()->create([
            'student_id' => $student->id,
            'date' => '2026-09-18', // last Friday, not today
        ]);

        $this->actingAs($admin)
            ->get(route('presence-board.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.classes.0.enrolled', 1)
                ->where('board.classes.0.in_building', 0)
                ->where('board.classes.0.checked_out', 0)
                ->where('board.classes.0.not_checked_in', 1)
                ->where('board.classes.0.not_in.0.status', null));
    }

    public function test_a_non_school_day_is_flagged(): void
    {
        $admin = User::factory()->admin()->create();
        NonSchoolDay::factory()->create(['date' => '2026-09-21']);

        $this->actingAs($admin)
            ->get(route('presence-board.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_school_day', false));
    }

    public function test_viewing_the_board_writes_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedBoard();
        $records = Attendance::count();

        $this->actingAs($admin)->get(route('presence-board.index'))->assertOk();

        $this->assertSame($records, Attendance::count());
    }
}
