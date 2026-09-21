<?php

namespace Tests\Feature;

use App\Models\AbsenceNotification;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\NonSchoolDay;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        // Factory default role: a teacher with no linked profile.
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_admin_sees_presence_board_numbers_and_per_class_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedBoard();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('is_school_day', true)
                // Same numbers as the Presence Board for the same moment.
                ->where('board.totals.enrolled', 6)
                ->where('board.totals.in_building', 2)
                ->where('board.totals.checked_out', 1)
                ->where('board.totals.not_checked_in', 3)
                ->where('board.classes.0.name', 'Kelas 5A')
                ->where('board.classes.0.enrolled', 4)
                ->where('board.classes.0.in_building', 2)
                ->where('board.classes.0.checked_out', 1)
                ->where('board.classes.0.not_checked_in', 1)
                ->where('board.classes.1.name', 'Kelas 5B')
                ->where('board.classes.1.enrolled', 2)
                ->where('board.classes.1.not_checked_in', 2));
    }

    public function test_admin_dashboard_carries_no_student_names(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedBoard();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('board.classes.0.not_in')
                ->missing('board.as_of'));
    }

    public function test_admin_pending_excuse_count_equals_all_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $first = Student::factory()->enrolledIn($class)->create();
        $second = Student::factory()->enrolledIn($class)->create();
        $third = Student::factory()->enrolledIn($class)->create();

        Excuse::factory()->create(['student_id' => $first->id]);
        Excuse::factory()->create(['student_id' => $second->id]);
        Excuse::factory()->approved()->create(['student_id' => $third->id]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pending_excuses', 2));
    }

    public function test_teacher_sees_only_homeroom_class_counts(): void
    {
        $user = $this->teacherUser();
        SchoolClass::factory()->create(['name' => 'Kelas 4A', 'teacher_id' => $user->teacher->id]);
        $this->seedBoard();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.classes', 1)
                ->where('board.classes.0.name', 'Kelas 4A')
                ->where('board.classes.0.enrolled', 0)
                ->where('board.totals', null));
    }

    public function test_teacher_pending_count_is_scoped_to_their_classes(): void
    {
        $user = $this->teacherUser();
        $mine = SchoolClass::factory()->create(['teacher_id' => $user->teacher->id]);
        $other = SchoolClass::factory()->create();
        $myStudent = Student::factory()->enrolledIn($mine)->create();
        $otherStudent = Student::factory()->enrolledIn($other)->create();

        Excuse::factory()->create(['student_id' => $myStudent->id]);
        Excuse::factory()->create(['student_id' => $otherStudent->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pending_excuses', 1));
    }

    public function test_teacher_without_a_linked_profile_gets_an_empty_board(): void
    {
        $user = User::factory()->teacher()->create(); // no Teacher profile

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.classes', 0)
                ->where('pending_excuses', 0));
    }

    public function test_parent_sees_one_card_per_linked_child_across_classes(): void
    {
        $classA = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $classB = SchoolClass::factory()->create(['name' => 'Kelas 5B']);
        $childA = Student::factory()->enrolledIn($classA)->create(['full_name' => 'Alpha One']);
        $childB = Student::factory()->enrolledIn($classB)->create(['full_name' => 'Echo Five']);

        $this->actingAs($this->parentUser($childA, $childB))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('children', 2)
                ->where('children.0.full_name', 'Alpha One')
                ->where('children.0.class_name', 'Kelas 5A')
                ->where('children.1.full_name', 'Echo Five')
                ->where('children.1.class_name', 'Kelas 5B'));
    }

    public function test_parent_child_without_a_record_shows_no_record_status(): void
    {
        $class = SchoolClass::factory()->create();
        $absent = Student::factory()->enrolledIn($class)->create(['full_name' => 'Alpha One']);
        $quiet = Student::factory()->enrolledIn($class)->create(['full_name' => 'Echo Five']);
        Attendance::factory()->absent()->create(['student_id' => $absent->id, 'date' => '2026-09-21']);

        $this->actingAs($this->parentUser($absent, $quiet))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // A swept absence is an honest record...
                ->where('children.0.today_status', 'absent')
                // ...but no record at all is "no record yet", never "absent".
                ->where('children.1.today_status', null));
    }

    public function test_parent_sees_latest_excuse_per_child(): void
    {
        $class = SchoolClass::factory()->create();
        $child = Student::factory()->enrolledIn($class)->create();

        $older = Excuse::factory()->create(['student_id' => $child->id]);
        $older->created_at = Date::parse('2026-09-19 10:00:00', 'UTC');
        $older->save();

        $newer = Excuse::factory()->leave()->create(['student_id' => $child->id]);
        $newer->created_at = Date::parse('2026-09-20 10:00:00', 'UTC');
        $newer->save();

        $this->actingAs($this->parentUser($child))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('children.0.latest_excuse.type', 'leave')
                ->where('children.0.latest_excuse.status', 'pending')
                ->where('children.0.latest_excuse.start_date', $newer->start_date->toDateString())
                ->where('children.0.latest_excuse.end_date', $newer->end_date->toDateString())
                // Summary fields only — no reason or review note.
                ->missing('children.0.latest_excuse.reason')
                ->missing('children.0.latest_excuse.review_note'));
    }

    public function test_parent_never_sees_other_guardians_children(): void
    {
        $class = SchoolClass::factory()->create();
        $mine = Student::factory()->enrolledIn($class)->create(['full_name' => 'Alpha One']);
        $theirs = Student::factory()->enrolledIn($class)->create(['full_name' => 'Echo Five']);

        Guardian::factory()->create()->students()->attach($theirs->id);

        $this->actingAs($this->parentUser($mine))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('children', 1)
                ->where('children.0.full_name', 'Alpha One'));
    }

    public function test_parent_without_a_guardian_profile_sees_the_empty_state(): void
    {
        $this->actingAs(User::factory()->parent()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('children', null));
    }

    public function test_student_sees_their_today_status_card(): void
    {
        $class = SchoolClass::factory()->create();
        $user = User::factory()->student()->create();
        $student = Student::factory()->enrolledIn($class)->create(['user_id' => $user->id]);
        Attendance::factory()->checkedOut()->create(['student_id' => $student->id, 'date' => '2026-09-21']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Pre-formatted school-tz times (UTC + 7): the factory's
                // 07:00/13:00 stamps are UTC, so they display as 14:00/20:00.
                ->where('student.today.status', 'present')
                ->where('student.today.checked_in_at', '14:00')
                ->where('student.today.checked_out_at', '20:00')
                ->where('student.today.scan_method', 'rfid'));
    }

    public function test_student_without_a_record_sees_no_record_yet(): void
    {
        $class = SchoolClass::factory()->create();
        $user = User::factory()->student()->create();
        Student::factory()->enrolledIn($class)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('student.today', null));
    }

    public function test_student_without_a_profile_sees_the_empty_state(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('student', null));
    }

    public function test_roles_get_no_cross_role_widgets(): void
    {
        $class = SchoolClass::factory()->create();

        $studentUser = User::factory()->student()->create();
        Student::factory()->enrolledIn($class)->create(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board', null)
                ->where('pending_excuses', null)
                ->where('children', null));

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board', null)
                ->where('pending_excuses', null)
                ->where('student', null));
    }

    public function test_weekend_shows_the_not_a_school_day_flag_for_every_role(): void
    {
        // School-tz today = Saturday 2026-09-20.
        Date::setTestNow('2026-09-19 18:30:00', 'UTC');

        $class = SchoolClass::factory()->create();

        $studentUser = User::factory()->student()->create();
        Student::factory()->enrolledIn($class)->create(['user_id' => $studentUser->id]);

        $teacherUser = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $teacherUser->id]);

        foreach ([
            User::factory()->admin()->create(),
            $teacherUser,
            $this->parentUser(),
            $studentUser,
        ] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('is_school_day', false));
        }
    }

    public function test_a_seeded_non_school_day_shows_the_banner_flag(): void
    {
        NonSchoolDay::factory()->create(['date' => '2026-09-21']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_school_day', false));
    }

    public function test_loading_the_dashboard_writes_nothing(): void
    {
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->enrolledIn($class)->create();

        $studentUser = User::factory()->student()->create();
        $student->update(['user_id' => $studentUser->id]);
        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-09-21']);

        $this->seedBoard();

        $attendance = Attendance::count();
        $excuses = Excuse::count();
        $notifications = AbsenceNotification::count();

        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertOk();
        $this->actingAs($this->teacherUser())->get(route('dashboard'))->assertOk();
        $this->actingAs($this->parentUser($student))->get(route('dashboard'))->assertOk();
        $this->actingAs($studentUser)->get(route('dashboard'))->assertOk();

        $this->assertSame($attendance, Attendance::count());
        $this->assertSame($excuses, Excuse::count());
        $this->assertSame($notifications, AbsenceNotification::count());
    }

    /**
     * Two classes, six students, one of today's every bucket — the same
     * world as PresenceBoardTest::seedBoard, so the numbers must match.
     */
    private function seedBoard(): void
    {
        $classA = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $classB = SchoolClass::factory()->create(['name' => 'Kelas 5B']);

        $this->todayRecord(Student::factory()->enrolledIn($classA)->create([
            'full_name' => 'Alpha One',
        ]));
        $this->todayRecord(Student::factory()->enrolledIn($classA)->create([
            'full_name' => 'Bravo Two',
        ]), 'late');
        $this->todayRecord(Student::factory()->enrolledIn($classA)->create([
            'full_name' => 'Charlie Three',
        ]), 'checkedOut');
        $this->todayRecord(Student::factory()->enrolledIn($classA)->create([
            'full_name' => 'Delta Four',
        ]), 'sick');

        Student::factory()->enrolledIn($classB)->create(['full_name' => 'Echo Five']);
        $this->todayRecord(Student::factory()->enrolledIn($classB)->create([
            'full_name' => 'Foxtrot Six',
        ]), 'absent');
    }

    private function teacherUser(): User
    {
        $user = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function parentUser(Student ...$children): User
    {
        $user = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $user->id])->students()->attach(
            collect($children)->pluck('id')->all(),
        );

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
}
