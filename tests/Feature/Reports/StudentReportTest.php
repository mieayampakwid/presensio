<?php

namespace Tests\Feature\Reports;

use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jakarta is already on Tuesday the 21st while UTC still says the
        // 20th — pins the school-tz business date and the September month
        // defaults.
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
     * 4 present + 2 late + 1 absent + 1 sick + 1 leave, all inside the
     * default September range. (student_id, date) is unique — each record
     * gets its own day.
     */
    private function seedRecords(Student $student): void
    {
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
                'student_id' => $student->id,
                'date' => sprintf('2026-09-%02d', $day),
            ]);
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.student'))->assertRedirect(route('login'));
    }

    public function test_picker_renders_without_a_student_selected(): void
    {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['full_name' => 'Ahmad Fauzi']);

        $this->actingAs($admin)
            ->get(route('reports.student'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/student-report')
                ->has('students', 1)
                ->where('students.0.full_name', 'Ahmad Fauzi')
                ->where('filters.student_id', null)
                ->where('filters.from', '2026-09-01')
                ->where('filters.to', '2026-09-30')
                ->where('filters.include_excused', false)
                ->where('summary', null)
                ->where('records', null));
    }

    public function test_an_invalid_or_inverted_range_falls_back_to_the_current_month(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id, 'from' => 'garbage']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-09-01')
                ->where('filters.to', '2026-09-30'));

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id, 'from' => '2026-09-10', 'to' => '2026-09-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-09-01')
                ->where('filters.to', '2026-09-30'));
    }

    public function test_ranges_wider_than_366_days_are_clamped(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id, 'from' => '2025-01-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2025-01-01')
                ->where('filters.to', '2026-01-01'));
    }

    public function test_counts_and_rate_exclude_sick_and_leave_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $this->seedRecords($student);

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.counts.present', 4)
                ->where('summary.counts.late', 2)
                ->where('summary.counts.absent', 1)
                ->where('summary.counts.sick', 1)
                ->where('summary.counts.leave', 1)
                ->where('summary.rate', 85.7));

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id, 'include_excused' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.include_excused', true)
                ->where('summary.counts.sick', 1)
                ->where('summary.rate', 66.7));
    }

    public function test_a_student_without_records_has_a_null_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.rate', null)
                ->has('records', 0));
    }

    public function test_notes_and_override_attribution_never_reach_the_report(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        Attendance::factory()->create([
            'student_id' => $student->id,
            'notes' => 'Card damaged — internal note',
            'override_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => $student->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 1)
                ->missing('records.0.notes')
                ->missing('records.0.override_by_user_id'));
    }

    public function test_a_teacher_sees_only_homeroom_students(): void
    {
        $user = $this->teacherUser();
        $own = SchoolClass::factory()->create(['teacher_id' => $user->teacher->id]);
        $other = SchoolClass::factory()->create();
        $ownStudent = Student::factory()->enrolledIn($own)->create();
        $otherStudent = Student::factory()->enrolledIn($other)->create();
        $unclassed = Student::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.student'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students', 1)
                ->where('students.0.id', $ownStudent->id));

        $this->actingAs($user)
            ->get(route('reports.student', ['student_id' => $ownStudent->id]))
            ->assertOk();

        foreach ([$otherStudent, $unclassed] as $forbidden) {
            $this->actingAs($user)
                ->get(route('reports.student', ['student_id' => $forbidden->id]))
                ->assertForbidden();
        }
    }

    public function test_a_parent_sees_only_linked_children(): void
    {
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $stranger = Student::factory()->create();
        $guardian->students()->attach($child->id);

        $this->actingAs($user)
            ->get(route('reports.student'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students', 1)
                ->where('students.0.id', $child->id));

        $this->actingAs($user)
            ->get(route('reports.student', ['student_id' => $child->id]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('reports.student', ['student_id' => $stranger->id]))
            ->assertForbidden();
    }

    public function test_a_student_sees_only_self(): void
    {
        $user = User::factory()->student()->create();
        $self = Student::factory()->create(['user_id' => $user->id]);
        $other = Student::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.student', ['student_id' => $self->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students', 1)
                ->where('students.0.id', $self->id));

        $this->actingAs($user)
            ->get(route('reports.student', ['student_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_unlinked_student_and_parent_profiles_get_the_empty_state(): void
    {
        $studentUser = User::factory()->student()->create();
        $parentUser = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $parentUser->id]);

        foreach ([$studentUser, $parentUser] as $user) {
            $this->actingAs($user)
                ->get(route('reports.student'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('students', 0)
                    ->where('summary', null)
                    ->where('records', null));
        }
    }

    public function test_unknown_student_id_404s(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.student', ['student_id' => 999]))
            ->assertNotFound();
    }

    public function test_export_downloads_the_csv_matching_the_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['full_name' => 'Ahmad Fauzi', 'student_number' => '2410001']);
        $this->seedRecords($student);

        $route = fn (array $query = []) => route('reports.student.export', array_merge([
            'student_id' => $student->id,
        ], $query));

        $default = $this->actingAs($admin)->get($route());

        $default->assertOk()
            ->assertDownload('student-report-2410001-2026-09-01-to-2026-09-30.csv');

        $content = $default->streamedContent();
        $this->assertStringContainsString('"Student Report","Ahmad Fauzi"', $content);
        $this->assertStringContainsString('"Include sick/leave in rate",No', $content);
        $this->assertStringContainsString('"Attendance rate",85.7%', $content);
        $this->assertStringContainsString('2026-09-11,Late', $content);

        $excused = $this->actingAs($admin)->get($route(['include_excused' => 1]));

        $excused->assertOk()
            ->assertDownload('student-report-2410001-2026-09-01-to-2026-09-30.csv');

        $content = $excused->streamedContent();
        $this->assertStringContainsString('"Include sick/leave in rate",Yes', $content);
        $this->assertStringContainsString('"Attendance rate",66.7%', $content);
    }

    public function test_export_without_a_student_404s(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.student.export'))
            ->assertNotFound();
    }
}
