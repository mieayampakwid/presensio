<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicYear;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\NonSchoolDay;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\EnrollmentService;
use App\Services\Timetable\TodaySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\CreatesBellSchedules;
use Tests\TestCase;

class TodayScheduleTest extends TestCase
{
    use CreatesBellSchedules;
    use RefreshDatabase;

    private TodaySchedule $service;

    private AcademicYear $year;

    private SchoolClass $class;

    private Semester $semester;

    private BellSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        // School-tz Monday 2026-09-21 08:00, inside Jam ke-2 (07:45-08:20),
        // so today's slots get both a current and a next marking.
        Date::setTestNow('2026-09-21 01:00:00', 'UTC');

        $this->service = app(TodaySchedule::class);
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

    public function test_returns_todays_slots_in_period_order_with_current_and_next_marked(): void
    {
        $budi = Teacher::factory()->create();
        $matematika = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $budi->id]);
        $this->createSlot(1);
        $this->createSlot(2, $matematika, 'R-102');
        $this->createSlot(3, $matematika);

        $student = Student::factory()->enrolledIn($this->class)->create();

        $slots = $this->service->forStudent($student);

        $this->assertSame(
            ['Upacara Bendera', $matematika->subject->name, $matematika->subject->name],
            array_column($slots, 'title'),
        );
        $this->assertSame(['07:00', '07:45', '08:30'], array_column($slots, 'start_time'));
        $this->assertSame(['07:35', '08:20', '09:05'], array_column($slots, 'end_time'));
        $this->assertSame([null, 'current', 'next'], array_column($slots, 'status'));
        // AC-13-05: an activity slot carries no teacher name.
        $this->assertNull($slots[0]['teacher_name']);
        $this->assertSame($budi->employee->name, $slots[1]['teacher_name']);
        $this->assertSame('R-102', $slots[1]['room']);
    }

    /**
     * AC-13-08: a student who moved from 5A to 5B yesterday sees 5B's slots.
     */
    public function test_a_student_moved_to_a_new_class_yesterday_sees_the_new_classs_slots(): void
    {
        $classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $oldSubject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        $newSubject = ClassSubject::factory()->create(['class_id' => $classB->id]);
        $this->createSlot(1, $oldSubject);
        TimetableSlot::factory()->forClassSubject($newSubject)->create([
            'semester_id' => $this->semester->id,
            'class_id' => $classB->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(2)->id,
        ]);

        $student = Student::factory()->enrolledIn($this->class)->create();
        app(EnrollmentService::class)->assign($student, $classB, '2026-09-20');

        $slots = $this->service->forStudent($student);

        $this->assertCount(1, $slots);
        $this->assertSame($newSubject->subject->name, $slots[0]['title']);
        $this->assertSame('07:45', $slots[0]['start_time']);
    }

    /**
     * AC-13-10: a seeded non-school day yields an empty schedule even
     * though the class has slots on that weekday.
     */
    public function test_a_seeded_non_school_day_returns_no_slots(): void
    {
        $this->createSlot(1);
        $student = Student::factory()->enrolledIn($this->class)->create();
        NonSchoolDay::factory()->create(['date' => '2026-09-21']);

        $this->assertSame([], $this->service->forStudent($student));
    }

    public function test_a_non_operational_weekday_returns_no_slots(): void
    {
        // School-tz Saturday 2026-09-19.
        Date::setTestNow('2026-09-18 18:30:00', 'UTC');

        $this->createSlot(1);
        $student = Student::factory()->enrolledIn($this->class)->create();

        $this->assertSame([], $this->service->forStudent($student));
    }

    public function test_a_student_without_a_class_gets_no_slots(): void
    {
        $this->createSlot(1);

        $this->assertSame([], $this->service->forStudent(Student::factory()->create()));
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

    private function createSlot(int $lessonNumber, ?ClassSubject $subject = null, ?string $room = null): TimetableSlot
    {
        $factory = $subject !== null
            ? TimetableSlot::factory()->forClassSubject($subject)
            : TimetableSlot::factory();

        return $factory->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson($lessonNumber)->id,
            'room' => $room,
        ]);
    }
}
