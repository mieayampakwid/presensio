<?php

namespace Tests\Feature\timetable;

use App\Models\AcademicYear;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\CreatesBellSchedules;
use Tests\TestCase;

class TimetableViewTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBellSchedules;

    private User $admin;

    private AcademicYear $year;

    private SchoolClass $class;

    private Semester $semester;

    private BellSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        // School-tz Monday 2026-09-21 08:00, inside Jam ke-2 (07:45-08:20),
        // so the teacher schedule has both a current and a next slot.
        Date::setTestNow('2026-09-21 01:00:00', 'UTC');

        $this->admin = User::factory()->admin()->create();
        $this->year = AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5A']);
        $this->semester = $this->year->semesters()->firstOrFail();
        $this->schedule = $this->createSchedule('Reguler', [1, 2, 3, 4], 3, breakAfterLesson: 2);
        $this->createSchedule('Jumat', [5], 2);

        // Distinct times so the current/next resolution is unambiguous.
        $this->setPeriodTimes(1, '07:00', '07:35');
        $this->setPeriodTimes(2, '07:45', '08:20');
        $this->setPeriodTimes(3, '08:30', '09:05');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }
    public function test_admin_and_principal_can_view_any_class_timetable(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        TimetableSlot::factory()->forClassSubject($subject)->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
        ]);

        $principal = User::factory()->principal()->create();
        foreach ([$this->admin, $principal] as $user) {
            $this->actingAs($user)
                ->get(route('classes.timetable.view', $this->class))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('timetable/week')
                    ->where('school_class.name', '5A')
                    ->where('semester_id', $this->semester->id)
                    ->has('days', 5)
                    ->has('days.0.periods', 4)
                    ->has('days.0.slots', 1)
                    ->where('days.0.slots.0.title', $subject->subject->name)
                    ->where('days.0.slots.0.start_time', '07:00')
                    ->where('days.0.slots.0.end_time', '07:35'));
        }
    }

    public function test_teacher_in_scope_can_view_the_class_timetable(): void
    {
        $budiUser = User::factory()->teacher()->create();
        $budi = Teacher::factory()->create(['user_id' => $budiUser->id]);
        $assignment = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $budi->id]);
        TimetableSlot::factory()->forClassSubject($assignment)->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
        ]);

        $this->actingAs($budiUser)
            ->get(route('classes.timetable.view', $this->class))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/week')
                ->where('days.0.slots.0.teacher_name', $budi->employee->name)
                ->has('days.0.slots', 1));
    }

    public function test_teacher_out_of_scope_gets_403(): void
    {
        $otherUser = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->get(route('classes.timetable.view', $this->class))
            ->assertForbidden();
    }

    public function test_student_can_view_their_current_class_only(): void
    {
        $otherClass = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $studentUser = User::factory()->student()->create();
        Student::factory()->enrolledIn($this->class)->create(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser)
            ->get(route('classes.timetable.view', $this->class))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/week'));

        $this->actingAs($studentUser)
            ->get(route('classes.timetable.view', $otherClass))
            ->assertForbidden();
    }

    public function test_guardian_can_view_their_childs_class_only(): void
    {
        $otherClass = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $guardianUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
        $child = Student::factory()->enrolledIn($this->class)->create();
        $guardian->students()->attach($child->id, ['relationship_type' => 'father']);

        $this->actingAs($guardianUser)
            ->get(route('classes.timetable.view', $this->class))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/week'));

        $this->actingAs($guardianUser)
            ->get(route('classes.timetable.view', $otherClass))
            ->assertForbidden();
    }

    /**
     * AC-13-06: times live only in the bell schedule; changing Jam ke-1 to
     * 07:15-07:50 shows the new derived times in every class's view.
     */
    public function test_time_change_propagates_to_the_weekly_view(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        TimetableSlot::factory()->forClassSubject($subject)->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
        ]);

        $this->lesson(1)->update(['start_time' => '07:15', 'end_time' => '07:50']);

        $this->actingAs($this->admin)
            ->get(route('classes.timetable.view', $this->class))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/week')
                ->where('days.0.slots.0.start_time', '07:15')
                ->where('days.0.slots.0.end_time', '07:50'));
    }

    /**
     * AC-13-09: the schedule lists the teacher's slots in 5A and 6A for the
     * current semester; another teacher's slots are absent.
     */
    public function test_teacher_schedule_lists_their_classes_slots(): void
    {
        $budiUser = User::factory()->teacher()->create();
        $budi = Teacher::factory()->create(['user_id' => $budiUser->id]);
        $matematika = Subject::factory()->create(['name' => 'Matematika']);
        $assignment5A = ClassSubject::factory()->create(['class_id' => $this->class->id, 'subject_id' => $matematika->id, 'teacher_id' => $budi->id]);
        $sixA = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '6A']);
        $assignment6A = ClassSubject::factory()->create(['class_id' => $sixA->id, 'subject_id' => $matematika->id, 'teacher_id' => $budi->id]);
        $otherUser = User::factory()->teacher()->create();
        $other = Teacher::factory()->create(['user_id' => $otherUser->id]);
        $otherAssignment = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $other->id]);

        TimetableSlot::factory()->forClassSubject($assignment5A)->create([
            'semester_id' => $this->semester->id, 'class_id' => $this->class->id, 'day_of_week' => 1, 'lesson_period_id' => $this->lesson(1)->id,
        ]);
        TimetableSlot::factory()->forClassSubject($assignment5A)->create([
            'semester_id' => $this->semester->id, 'class_id' => $this->class->id, 'day_of_week' => 1, 'lesson_period_id' => $this->lesson(2)->id,
        ]);
        TimetableSlot::factory()->forClassSubject($assignment6A)->create([
            'semester_id' => $this->semester->id, 'class_id' => $sixA->id, 'day_of_week' => 1, 'lesson_period_id' => $this->lesson(3)->id,
        ]);
        // Another teacher's slot: absent from Budi's schedule.
        TimetableSlot::factory()->forClassSubject($otherAssignment)->create([
            'semester_id' => $this->semester->id, 'class_id' => $this->class->id, 'day_of_week' => 2, 'lesson_period_id' => $this->lesson(1)->id,
        ]);

        $this->actingAs($budiUser)
            ->get(route('schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('teacher/schedule')
                ->has('days', 5)
                ->has('days.0.slots', 3)
                ->where('days.0.slots.0.class_name', '5A')
                ->where('days.0.slots.1.class_name', '5A')
                ->where('days.0.slots.2.class_name', '6A')
                ->where('days.0.slots.2.start_time', '08:30')
                ->where('days.0.slots.2.end_time', '09:05')
                ->where('days.1.slots', []));
    }

    public function test_teacher_schedule_marks_current_and_next_slot(): void
    {
        $budiUser = User::factory()->teacher()->create();
        $budi = Teacher::factory()->create(['user_id' => $budiUser->id]);
        $assignment = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $budi->id]);

        foreach ([1, 2, 3] as $lessonNumber) {
            TimetableSlot::factory()->forClassSubject($assignment)->create([
                'semester_id' => $this->semester->id,
                'class_id' => $this->class->id,
                'day_of_week' => 1,
                'lesson_period_id' => $this->lesson($lessonNumber)->id,
            ]);
        }

        // Frozen now (08:00) sits inside Jam ke-2 and before Jam ke-3.
        $this->actingAs($budiUser)
            ->get(route('schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('teacher/schedule')
                ->where('days.0.slots.0.status', null)
                ->where('days.0.slots.1.status', 'current')
                ->where('days.0.slots.2.status', 'next'));
    }

    private function setPeriodTimes(int $number, string $start, string $end): void
    {
        $this->lesson($number)->update(['start_time' => $start, 'end_time' => $end]);
    }

    private function lesson(int $number): LessonPeriod
    {
        return $this->schedule->periods->firstOrFail(
            fn (LessonPeriod $period): bool => $period->number === $number
        );
    }
}
