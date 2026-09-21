<?php

namespace Tests\Feature\Excuses;

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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExcuseReviewPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jakarta is already past midnight on Monday the 21st while UTC
        // still says Sunday the 20th — pins the school-tz default date.
        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function homeroomTeacher(): array
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        return [$user, $class];
    }

    public function test_admin_sees_all_classes_and_pending_rows_float_to_the_top(): void
    {
        $admin = User::factory()->admin()->create();
        // Pending created FIRST: identical created_at + id-desc ordering
        // would bury it, so first place proves the pending-first sort.
        $pending = Excuse::factory()->create();
        $resolved = Excuse::factory()->rejected()->create();

        $this->actingAs($admin)
            ->get(route('excuses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('excuses/index')
                ->has('excuses.data', 2)
                ->where('excuses.data.0.id', $pending->id)
                ->where('excuses.data.0.status', 'pending')
                ->where('excuses.data.0.student_name', $pending->student->full_name)
                ->where('excuses.data.1.id', $resolved->id)
                ->where('excuses.data.1.status', 'rejected')
                ->where('can_review', true));
    }

    public function test_teacher_sees_only_their_homeroom_classes(): void
    {
        [$user, $class] = $this->homeroomTeacher();
        $ownStudent = Student::factory()->enrolledIn($class)->create();
        $own = Excuse::factory()->create(['student_id' => $ownStudent->id]);
        Excuse::factory()->create(); // default student, no homeroom match

        $this->actingAs($user)
            ->get(route('excuses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('excuses.data', 1)
                ->where('excuses.data.0.id', $own->id)
                ->where('can_review', false));
    }

    public function test_teacher_without_a_homeroom_sees_an_empty_queue(): void
    {
        $user = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $user->id]);
        Excuse::factory()->create();

        $this->actingAs($user)
            ->get(route('excuses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('excuses.data', 0)
                ->where('can_review', false));
    }

    public function test_days_listing_skips_weekends(): void
    {
        $admin = User::factory()->admin()->create();
        // Friday the 25th → Monday the 28th: only Fri and Mon are school days.
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-25',
            'end_date' => '2026-09-28',
        ]);

        $this->actingAs($admin)
            ->get(route('excuses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('excuses.data.0.days', [
                    ['date' => '2026-09-25', 'status' => null],
                    ['date' => '2026-09-28', 'status' => null],
                ]));
    }

    public function test_days_listing_skips_non_school_days_and_carries_attendance_status(): void
    {
        $admin = User::factory()->admin()->create();
        NonSchoolDay::factory()->create(['date' => '2026-09-22', 'name' => 'Idul Fitri']);
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-23',
        ]);

        // Monday already has a swept absence — the reviewer sees what an
        // approval would overwrite.
        Attendance::factory()->absent()->create([
            'student_id' => $excuse->student_id,
            'date' => '2026-09-21',
        ]);

        $this->actingAs($admin)
            ->get(route('excuses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('excuses.data.0.days', [
                    ['date' => '2026-09-21', 'status' => 'absent'],
                    ['date' => '2026-09-23', 'status' => null],
                ]));
    }

    #[DataProvider('forbiddenRoles')]
    public function test_parents_and_students_cannot_open_the_review_queue(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        if ($factoryState === 'parent') {
            Guardian::factory()->create(['user_id' => $user->id]);
        }

        $this->actingAs($user)
            ->get(route('excuses.index'))
            ->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [
            ['parent'],
            ['student'],
        ];
    }
}
