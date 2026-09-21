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

class ClassReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * A class of two, with one student carrying 4 present + 2 late + 1
     * absent + 1 sick + 1 leave across September school days (rate 85.7%
     * / 66.7% with the switch on) and one with no records.
     */
    private function classWithRecords(): SchoolClass
    {
        $class = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        $busy = Student::factory()->enrolledIn($class)->create([
            'full_name' => 'Ahmad Fauzi',
            'student_number' => '2410001',
        ]);
        Student::factory()->enrolledIn($class)->create(['full_name' => 'Ayu Lestari']);

        $plan = [
            ['present', 1], ['present', 2], ['present', 3], ['present', 4],
            ['late', 11], ['late', 14],
            ['absent', 16],
            ['sick', 17],
            ['leave', 18],
        ];

        foreach ($plan as [$state, $day]) {
            $factory = Attendance::factory();

            if ($state !== 'present') {
                $factory = $factory->{$state}();
            }

            $factory->create([
                'student_id' => $busy->id,
                'date' => sprintf('2026-09-%02d', $day),
            ]);
        }

        return $class;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.class'))->assertRedirect(route('login'));
    }

    public function test_students_and_parents_are_forbidden(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('reports.class'))
            ->assertForbidden();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('reports.class'))
            ->assertForbidden();
    }

    public function test_a_teacher_without_linked_profile_sees_an_empty_report(): void
    {
        $user = User::factory()->teacher()->create();

        $this->actingAs($user)
            ->get(route('reports.class'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/class-report')
                ->has('classes', 0)
                ->where('report', null));
    }

    public function test_a_teacher_sees_only_their_own_class(): void
    {
        $user = $this->teacherUser();
        $own = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'teacher_id' => $user->teacher->id]);
        SchoolClass::factory()->create(['name' => 'Kelas 5B']);

        $this->actingAs($user)
            ->get(route('reports.class'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('classes', 1)
                ->where('classes.0.name', 'Kelas 5A')
                ->where('filters.class_id', $own->id));

        // An out-of-scope class_id falls back to the first scoped class on
        // the page — the same silent default as the exception dashboard.
        $this->actingAs($user)
            ->get(route('reports.class', ['class_id' => SchoolClass::query()->where('name', 'Kelas 5B')->value('id')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.class_id', $own->id));
    }

    public function test_the_grid_carries_statuses_and_non_school_dates(): void
    {
        $admin = User::factory()->admin()->create();
        $class = $this->classWithRecords();
        NonSchoolDay::factory()->create(['date' => '2026-09-15']);

        $this->actingAs($admin)
            ->get(route('reports.class', ['class_id' => $class->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.dates.0', '2026-09-01')
                ->where('report.dates.29', '2026-09-30')
                ->has('report.dates', 30)
                ->has('report.non_school_dates', 9) // Sep 2026: 8 weekend days + the holiday
                ->where('report.rows.0.full_name', 'Ahmad Fauzi')
                ->where('report.rows.0.statuses.2026-09-01', 'present')
                ->where('report.rows.0.statuses.2026-09-16', 'absent')
                ->where('report.rows.0.statuses.2026-09-17', 'sick')
                // Days without a record are simply absent from the map.
                ->missing('report.rows.0.statuses.2026-09-08')
                ->missing('report.rows.1.statuses.2026-09-01')
                ->where('report.rows.1.rate', null));
    }

    public function test_per_student_rate_reflects_the_include_excused_switch(): void
    {
        $admin = User::factory()->admin()->create();
        $class = $this->classWithRecords();

        $this->actingAs($admin)
            ->get(route('reports.class', ['class_id' => $class->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.rows.0.counts.present', 4)
                ->where('report.rows.0.counts.leave', 1)
                ->where('report.rows.0.rate', 85.7));

        $this->actingAs($admin)
            ->get(route('reports.class', ['class_id' => $class->id, 'include_excused' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.include_excused', true)
                ->where('report.rows.0.rate', 66.7));
    }

    public function test_export_downloads_the_grid_as_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $class = $this->classWithRecords();

        $response = $this->actingAs($admin)
            ->get(route('reports.class.export', ['class_id' => $class->id]))
            ->assertOk()
            ->assertDownload('class-report-kelas-5a-2026-09-01-to-2026-09-30.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"Class Report","Kelas 5A"', $content);
        $this->assertStringContainsString('"Include sick/leave in rate",No', $content);
        $this->assertStringContainsString('NIS,Name,Present,Late,Absent,Sick,Leave,"Rate %",1/9,2/9', $content);
        $this->assertStringContainsString('2410001,"Ahmad Fauzi",4,2,1,1,1,85.7%', $content);
        // Blank cells (not counted) separate the summary from the grid columns.
        $this->assertStringContainsString(',"Ayu Lestari",0,0,0,0,0,', $content);
    }

    public function test_export_rejects_out_of_scope_classes(): void
    {
        $user = $this->teacherUser();
        $other = SchoolClass::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.class.export', ['class_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_export_without_a_class_404s(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.class.export'))
            ->assertNotFound();
    }
}
