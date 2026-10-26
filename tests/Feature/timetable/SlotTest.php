<?php

namespace Tests\Feature\timetable;

use App\Enums\PeriodKind;
use App\Models\AcademicYear;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesBellSchedules;
use Tests\TestCase;

class SlotTest extends TestCase
{
    use CreatesBellSchedules;
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $year;

    private BellSchedule $schedule;

    private SchoolClass $class;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->year = AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5A']);
        $this->semester = $this->year->semesters()->firstOrFail();
        $this->schedule = $this->createSchedule('Reguler', [1, 2, 3, 4], 3, breakAfterLesson: 2);
        $this->createSchedule('Jumat', [5], 2);
    }

    public function test_admin_can_store_a_subject_slot(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'class_subject_id' => $subject->id,
                'room' => 'R-101',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'period_count' => 1,
            'class_subject_id' => $subject->id,
            'activity_name' => null,
            'room' => 'R-101',
        ]);
    }

    /**
     * AC-13-05: an activity slot persists with a null class_subject_id.
     */
    public function test_admin_can_store_an_activity_slot_without_a_subject(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'activity_name' => 'Upacara Bendera',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'class_subject_id' => null,
            'activity_name' => 'Upacara Bendera',
        ]);
    }

    public function test_exactly_one_of_subject_or_activity_is_required(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);

        // Both.
        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'class_subject_id' => $subject->id,
                'activity_name' => 'Upacara Bendera',
            ]))
            ->assertJsonValidationErrors('class_subject_id');

        // Neither.
        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload())
            ->assertJsonValidationErrors('class_subject_id');

        $this->assertSame(0, TimetableSlot::count());
    }

    public function test_subject_from_another_class_is_rejected(): void
    {
        $otherClass = SchoolClass::factory()->create(['academic_year_id' => $this->year->id]);
        $foreignSubject = ClassSubject::factory()->create(['class_id' => $otherClass->id]);

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'class_subject_id' => $foreignSubject->id,
            ]))
            ->assertJsonValidationErrors('class_subject_id');

        $this->assertSame(0, TimetableSlot::count());
    }

    public function test_semester_from_another_academic_year_is_rejected(): void
    {
        $pastSemester = AcademicYear::factory()->create()->semesters()->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'activity_name' => 'Upacara Bendera',
                'semester_id' => $pastSemester->id,
            ]))
            ->assertJsonValidationErrors('semester_id');

        $this->assertSame(0, TimetableSlot::count());
    }

    /**
     * AC-13-02 via HTTP: overlapping an existing slot of the same class in
     * the same semester and day returns 422.
     */
    public function test_overlapping_slot_returns_422(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(3)->id,
            'class_subject_id' => $subject->id,
            'activity_name' => null,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'class_subject_id' => $subject->id,
                'lesson_period_id' => $this->lesson(2)->id,
                'period_count' => 2,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slot');

        $this->assertSame(1, TimetableSlot::count());
    }

    /**
     * AC-13-03 via HTTP: a slot spanning across "Istirahat 1" returns 422.
     */
    public function test_span_crossing_a_break_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'activity_name' => 'Literasi',
                'lesson_period_id' => $this->lesson(2)->id,
                'period_count' => 2,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slot');

        $this->assertSame(0, TimetableSlot::count());
    }

    public function test_starting_on_a_break_is_rejected(): void
    {
        $break = $this->schedule->periods->firstWhere('kind', PeriodKind::Break);
        $this->assertNotNull($break);

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'activity_name' => 'Upacara Bendera',
                'lesson_period_id' => $break->id,
            ]))
            ->assertJsonValidationErrors('lesson_period_id');

        $this->assertSame(0, TimetableSlot::count());
    }

    /**
     * AC-13-04: the teacher conflict responds 409 with the Indonesian warning
     * text and nothing is saved; resubmitting with confirm_conflicts saves.
     */
    public function test_conflicts_return_409_and_confirm_conflicts_saves(): void
    {
        app()->setLocale('id');

        $teacher = Teacher::factory()->create();
        $subjectA = ClassSubject::factory()->create(['class_id' => $this->class->id, 'teacher_id' => $teacher->id]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(2)->id,
            'class_subject_id' => $subjectA->id,
            'activity_name' => null,
        ]);

        $classB = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5B']);
        $subjectB = ClassSubject::factory()->create(['class_id' => $classB->id, 'teacher_id' => $teacher->id]);

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $classB), $this->slotPayload([
                'class_subject_id' => $subjectB->id,
                'lesson_period_id' => $this->lesson(2)->id,
            ]))
            ->assertStatus(409)
            ->assertJsonPath('conflicts.0.type', 'teacher')
            ->assertJsonPath('conflicts.0.message', 'Peringatan: Guru bersangkutan telah dijadwalkan di Kelas 5A pada jam tersebut.');

        $this->assertSame(1, TimetableSlot::count());

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $classB), $this->slotPayload([
                'class_subject_id' => $subjectB->id,
                'lesson_period_id' => $this->lesson(2)->id,
                'confirm_conflicts' => true,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'class_id' => $classB->id,
            'class_subject_id' => $subjectB->id,
            'lesson_period_id' => $this->lesson(2)->id,
        ]);
    }

    public function test_admin_can_move_a_slot_but_not_onto_its_sibling(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        $slot = TimetableSlot::factory()->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'class_subject_id' => $subject->id,
            'activity_name' => null,
        ]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(3)->id,
            'activity_name' => 'Literasi',
        ]);

        // Move to Jam ke-2 (free): saves.
        $this->actingAs($this->admin)
            ->putJson(route('classes.timetable.slots.update', [$this->class, $slot]), $this->slotPayload([
                'class_subject_id' => $subject->id,
                'lesson_period_id' => $this->lesson(2)->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'id' => $slot->id,
            'lesson_period_id' => $this->lesson(2)->id,
        ]);

        // Span Jam ke-2..3 would overlap the sibling at Jam ke-3: 422.
        $this->actingAs($this->admin)
            ->putJson(route('classes.timetable.slots.update', [$this->class, $slot]), $this->slotPayload([
                'class_subject_id' => $subject->id,
                'lesson_period_id' => $this->lesson(2)->id,
                'period_count' => 2,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slot');

        $this->assertDatabaseHas('timetable_slots', [
            'id' => $slot->id,
            'lesson_period_id' => $this->lesson(2)->id,
            'period_count' => 1,
        ]);
    }

    public function test_admin_can_delete_a_slot(): void
    {
        $slot = TimetableSlot::factory()->create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Upacara Bendera',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('classes.timetable.slots.destroy', [$this->class, $slot]))
            ->assertRedirect();

        $this->assertDatabaseMissing('timetable_slots', ['id' => $slot->id]);
    }

    public function test_slot_writes_on_a_past_year_class_are_rejected_with_422(): void
    {
        $pastYear = AcademicYear::factory()->create();
        $pastClass = SchoolClass::factory()->create(['academic_year_id' => $pastYear->id, 'name' => '4A 2029/2030']);
        $pastSemester = $pastYear->semesters()->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.slots.store', $pastClass), $this->slotPayload([
                'activity_name' => 'Upacara Bendera',
                'semester_id' => $pastSemester->id,
            ]))
            ->assertJsonValidationErrors('class_id');

        $slot = TimetableSlot::factory()->create([
            'semester_id' => $pastSemester->id,
            'class_id' => $pastClass->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Upacara Bendera',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('classes.timetable.slots.destroy', [$pastClass, $slot]))
            ->assertStatus(422);

        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_manage_slots(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);

        $this->actingAs($user)
            ->postJson(route('classes.timetable.slots.store', $this->class), $this->slotPayload([
                'class_subject_id' => $subject->id,
            ]))
            ->assertForbidden();

        $this->assertSame(0, TimetableSlot::count());
    }

    /**
     * The base slot payload: Monday, Jam ke-1, single period, in the class's
     * own semester.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slotPayload(array $overrides = []): array
    {
        return array_merge([
            'semester_id' => $this->semester->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'period_count' => 1,
        ], $overrides);
    }

    private function lesson(int $number): LessonPeriod
    {
        return $this->schedule->periods->firstOrFail(
            fn (LessonPeriod $period): bool => $period->number === $number
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'principal' => ['principal'],
            'teacher' => ['teacher'],
            'counselor' => ['counselor'],
            'finance' => ['finance'],
            'staff' => ['staff'],
            'parent' => ['parent'],
            'student' => ['student'],
        ];
    }
}
