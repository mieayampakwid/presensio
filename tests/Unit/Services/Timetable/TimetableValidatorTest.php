<?php

namespace Tests\Unit\Services\Timetable;

use App\Enums\PeriodKind;
use App\Models\AcademicYear;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableValidator;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableValidatorTest extends TestCase
{
    use RefreshDatabase;

    private TimetableValidator $validator;

    private AcademicYear $year;

    private BellSchedule $schedule;

    private SchoolClass $class;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(TimetableValidator::class);
        $this->year = AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5A']);
        $this->semester = $this->year->semesters()->firstOrFail();
        $this->schedule = $this->createSchedule('Reguler', [1, 2, 3, 4], 3, breakAfterLesson: 2);
        $this->createSchedule('Jumat', [5], 2);
    }

    public function test_valid_placement_has_no_errors_or_conflicts(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);

        $this->assertSame([], $this->validator->errors($this->class, $this->semester, 1, $this->lesson(1), 1));
        $this->assertSame([], $this->validator->conflicts($this->class, $this->semester, 1, $this->lesson(1), 1, $subject, null));
    }

    /**
     * AC-13-02: a slot at Jam ke-1 with period_count 2 overlaps the existing
     * Jam ke-2 slot in the same class, semester and day.
     */
    public function test_overlapping_range_in_the_same_class_semester_and_day_is_an_error(): void
    {
        $this->createSlot($this->class, $this->lesson(2), day: 1);

        $errors = $this->validator->errors($this->class, $this->semester, 1, $this->lesson(1), 2);

        $this->assertSame([__('timetable.errors.slot_overlap')], $errors);
    }

    public function test_updating_a_slot_ignores_itself_in_the_overlap_scan(): void
    {
        $slot = $this->createSlot($this->class, $this->lesson(3), day: 1);

        $this->assertSame([], $this->validator->errors($this->class, $this->semester, 1, $this->lesson(3), 1, $slot->id));
        $this->assertNotSame([], $this->validator->errors($this->class, $this->semester, 1, $this->lesson(3), 1));
    }

    public function test_overlap_only_applies_within_the_same_semester_and_class(): void
    {
        $this->createSlot($this->class, $this->lesson(2), day: 1);
        $genap = $this->year->semesters()->where('number', 2)->firstOrFail();
        $classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);

        $this->assertSame([], $this->validator->errors($this->class, $genap, 1, $this->lesson(1), 2));
        $this->assertSame([], $this->validator->errors($classB, $this->semester, 1, $this->lesson(1), 2));
    }

    /**
     * AC-13-03: a slot spanning across "Istirahat 1" is an error.
     */
    public function test_span_crossing_a_break_is_an_error(): void
    {
        $errors = $this->validator->errors($this->class, $this->semester, 1, $this->lesson(2), 2);

        $this->assertSame([__('timetable.errors.slot_span_invalid')], $errors);
    }

    public function test_span_running_past_the_last_lesson_is_an_error(): void
    {
        $errors = $this->validator->errors($this->class, $this->semester, 1, $this->lesson(3), 2);

        $this->assertSame([__('timetable.errors.slot_span_invalid')], $errors);
    }

    public function test_non_operational_day_is_an_error(): void
    {
        $errors = $this->validator->errors($this->class, $this->semester, 7, $this->lesson(1), 1);

        $this->assertSame(
            [__('timetable.errors.slot_day_not_operational', ['day' => __('timetable.weekdays.7')])],
            $errors,
        );
    }

    public function test_starting_on_a_break_is_an_error(): void
    {
        $break = $this->schedule->periods->firstWhere('kind', PeriodKind::Break);
        $this->assertNotNull($break);

        $errors = $this->validator->errors($this->class, $this->semester, 1, $break, 1);

        $this->assertSame([__('timetable.errors.slot_start_not_lesson')], $errors);
    }

    public function test_starting_period_from_another_days_schedule_is_an_error(): void
    {
        $jumatLesson = BellSchedule::query()->where('name', 'Jumat')->firstOrFail()->periods->firstOrFail();

        $errors = $this->validator->errors($this->class, $this->semester, 1, $jumatLesson, 1);

        $this->assertSame([__('timetable.errors.slot_schedule_mismatch')], $errors);
    }

    /**
     * AC-13-04: the same teacher in another class at an overlapping range is
     * a warning, not a hard error.
     */
    public function test_same_teacher_in_another_class_is_a_warning_not_an_error(): void
    {
        $teacher = Teacher::factory()->create();
        $subjectA = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $teacher->id]);
        $this->createSlot($this->class, $this->lesson(2), subject: $subjectA);
        $classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $subjectB = ClassSubject::factory()->create(['class_id' => $classB->id, 'teacher_id' => $teacher->id]);

        $errors = $this->validator->errors($classB, $this->semester, 1, $this->lesson(2), 1);
        $conflicts = $this->validator->conflicts($classB, $this->semester, 1, $this->lesson(2), 1, $subjectB, null);

        $this->assertSame([], $errors);
        $this->assertSame([
            ['type' => 'teacher', 'message' => __('timetable.warnings.teacher', ['class' => '5A'])],
        ], $conflicts);
    }

    public function test_same_room_in_another_class_is_a_warning(): void
    {
        $this->createSlot($this->class, $this->lesson(2), room: 'R-101');
        $classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $subjectB = ClassSubject::factory()->create(['class_id' => $classB->id]);

        $conflicts = $this->validator->conflicts($classB, $this->semester, 1, $this->lesson(2), 1, $subjectB, 'R-101');

        $this->assertSame([
            ['type' => 'room', 'message' => __('timetable.warnings.room', ['class' => '5A', 'room' => 'R-101'])],
        ], $conflicts);
    }

    public function test_conflicts_do_not_fire_for_other_days_periods_semesters_or_blank_rooms(): void
    {
        $teacher = Teacher::factory()->create();
        $subjectA = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $teacher->id]);
        $this->createSlot($this->class, $this->lesson(2), subject: $subjectA, room: 'R-101');
        $classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $subjectB = ClassSubject::factory()->create(['class_id' => $classB->id, 'teacher_id' => $teacher->id]);

        // Different day.
        $this->assertSame([], $this->validator->conflicts($classB, $this->semester, 2, $this->lesson(2), 1, $subjectB, 'R-101'));

        // Different semester.
        $genap = $this->year->semesters()->where('number', 2)->firstOrFail();
        $this->assertSame([], $this->validator->conflicts($classB, $genap, 1, $this->lesson(2), 1, $subjectB, 'R-101'));

        // Non-overlapping period.
        $this->assertSame([], $this->validator->conflicts($classB, $this->semester, 1, $this->lesson(3), 1, $subjectB, 'R-101'));

        // The class being edited is excluded.
        $this->assertSame([], $this->validator->conflicts($this->class, $this->semester, 1, $this->lesson(2), 1, $subjectA, 'R-101'));

        // An activity slot without a room has nothing to conflict.
        $this->assertSame([], $this->validator->conflicts($classB, $this->semester, 1, $this->lesson(2), 1, null, null));
    }

    private function lesson(int $number): LessonPeriod
    {
        return $this->schedule->periods->firstOrFail(
            fn (LessonPeriod $period): bool => $period->number === $number
        );
    }

    private function createSlot(
        SchoolClass $class,
        LessonPeriod $period,
        int $day = 1,
        int $count = 1,
        ?ClassSubject $subject = null,
        ?string $room = null,
    ): TimetableSlot {
        return TimetableSlot::factory()->create([
            'semester_id' => $this->semester->id,
            'class_id' => $class->id,
            'day_of_week' => $day,
            'lesson_period_id' => $period->id,
            'period_count' => $count,
            'class_subject_id' => $subject?->id,
            'activity_name' => $subject === null ? 'Upacara Bendera' : null,
            'room' => $room,
        ]);
    }

    private function createSchedule(string $name, array $weekdays, int $lessons, ?int $breakAfterLesson = null): BellSchedule
    {
        return BellSchedule::factory()
            ->has(
                LessonPeriod::factory()
                    ->count($breakAfterLesson === null ? $lessons : $lessons + 1)
                    ->sequence(function (Sequence $sequence) use ($breakAfterLesson): array {
                        $position = $sequence->index + 1;
                        $isBreak = $breakAfterLesson !== null && $position === $breakAfterLesson + 1;

                        if ($isBreak) {
                            return [
                                'kind' => PeriodKind::Break,
                                'number' => null,
                                'name' => 'Istirahat',
                                'sort_order' => $position,
                            ];
                        }

                        return [
                            'kind' => PeriodKind::Lesson,
                            'number' => $breakAfterLesson !== null && $position > $breakAfterLesson
                                ? $position - 1
                                : $position,
                            'name' => null,
                            'sort_order' => $position,
                        ];
                    }),
                'periods'
            )
            ->create(['name' => $name, 'weekdays' => $weekdays]);
    }
}
