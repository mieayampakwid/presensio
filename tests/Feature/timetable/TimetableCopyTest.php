<?php

namespace Tests\Feature\timetable;

use App\Models\AcademicYear;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesBellSchedules;
use Tests\TestCase;

class TimetableCopyTest extends TestCase
{
    use CreatesBellSchedules;
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $year;

    private SchoolClass $class;

    private Semester $ganjil;

    private Semester $genap;

    private BellSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->year = AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $this->year->id, 'name' => '5A']);
        $this->ganjil = $this->year->semesters()->where('number', 1)->firstOrFail();
        $this->genap = $this->year->semesters()->where('number', 2)->firstOrFail();
        $this->schedule = $this->createSchedule('Reguler', [1, 2, 3, 4], 3, breakAfterLesson: 2);
        $this->createSchedule('Jumat', [5], 2);
    }

    /**
     * AC-13-07: the class's Ganjil slots are duplicated into Genap.
     */
    public function test_copy_into_genap_pulls_from_ganjil_of_the_same_year(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        TimetableSlot::factory()->forClassSubject($subject)->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'room' => 'R-101',
        ]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 2,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Upacara Bendera',
        ]);

        $this->actingAs($this->admin)
            ->post(route('classes.timetable.copy', $this->class), ['semester_id' => $this->genap->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->genap->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'period_count' => 1,
            'class_subject_id' => $subject->id,
            'activity_name' => null,
            'room' => 'R-101',
        ]);

        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->genap->id,
            'class_id' => $this->class->id,
            'day_of_week' => 2,
            'lesson_period_id' => $this->lesson(1)->id,
            'class_subject_id' => null,
            'activity_name' => 'Upacara Bendera',
        ]);
    }

    /**
     * AC-13-07: a source slot whose class_subjects row no longer exists is
     * skipped and reported, while the rest of the grid is copied.
     */
    public function test_copy_skips_slots_whose_subject_row_was_removed(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        $slot = TimetableSlot::factory()->forClassSubject($subject)->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
        ]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 2,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Literasi',
        ]);
        // Simulate the class_subjects row vanishing for this class (the UI
        // blocks deleting a subject with slots): point the slot at another
        // class's assignment so the copier's class check fails.
        $foreign = ClassSubject::factory()->create();
        TimetableSlot::query()->whereKey($slot->id)->update(['class_subject_id' => $foreign->id]);

        $this->copyToGenap(1, 1);

        $this->assertDatabaseMissing('timetable_slots', [
            'semester_id' => $this->genap->id,
            'class_subject_id' => $foreign->id,
        ]);
        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->genap->id,
            'class_id' => $this->class->id,
            'day_of_week' => 2,
            'activity_name' => 'Literasi',
        ]);
    }

    /**
     * AC-13-07: an occupied target position is never overwritten; the
     * source slot there is reported as skipped.
     */
    public function test_copy_skips_positions_already_occupied_in_the_target(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        TimetableSlot::factory()->forClassSubject($subject)->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
        ]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 2,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Literasi',
        ]);
        TimetableSlot::factory()->create([
            'semester_id' => $this->genap->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Upacara Bendera',
        ]);

        $this->copyToGenap(1, 1);

        // The pre-existing Genap slot survives untouched and the Tuesday
        // Ganjil slot is copied beside it.
        $this->assertSame(2, TimetableSlot::query()->where('semester_id', $this->genap->id)->count());
        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->genap->id,
            'day_of_week' => 1,
            'activity_name' => 'Upacara Bendera',
        ]);
        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->genap->id,
            'day_of_week' => 2,
            'activity_name' => 'Literasi',
        ]);
    }

    /**
     * Semester 1 of a year copies from semester 2 of the previous academic
     * year (ordering, not dates).
     */
    public function test_copy_into_ganjil_pulls_from_the_previous_year_genap(): void
    {
        $previousYear = AcademicYear::factory()->create([
            'name' => '2025/2026',
            'starts_at' => '2025-07-01',
            'ends_at' => '2026-06-30',
        ]);
        $previousGenap = $previousYear->semesters()->where('number', 2)->firstOrFail();

        TimetableSlot::factory()->create([
            'semester_id' => $previousGenap->id,
            'class_id' => $this->class->id,
            'day_of_week' => 3,
            'lesson_period_id' => $this->lesson(1)->id,
            'activity_name' => 'Literasi',
        ]);

        $this->actingAs($this->admin)
            ->post(route('classes.timetable.copy', $this->class), ['semester_id' => $this->ganjil->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 3,
            'activity_name' => 'Literasi',
        ]);
    }

    public function test_copy_without_a_previous_semester_fails_with_422(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.copy', $this->class), ['semester_id' => $this->ganjil->id])
            ->assertJsonValidationErrors('semester_id');

        $this->assertSame(0, TimetableSlot::count());
    }

    public function test_editor_page_defaults_to_the_current_semester_and_reports_semester_slots(): void
    {
        $subject = ClassSubject::factory()->create(['class_id' => $this->class->id]);
        TimetableSlot::factory()->forClassSubject($subject)->create([
            'semester_id' => $this->ganjil->id,
            'class_id' => $this->class->id,
            'day_of_week' => 1,
            'lesson_period_id' => $this->lesson(1)->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('classes.timetable.edit', $this->class))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/editor')
                ->where('school_class.name', '5A')
                ->where('semester_id', $this->ganjil->id)
                ->where('read_only', false)
                ->has('semesters', 2)
                ->has('class_subjects', 1)
                ->has('days', 5)
                ->has('days.0.periods')
                ->has('days.0.slots', 1)
                ->where('days.0.slots.0.title', $subject->subject->name)
                ->has('days.0.slots.0.teacher_name')
                ->where('days.1.slots', []));
    }

    public function test_editor_page_shows_the_requested_semester_via_the_query_param(): void
    {
        $this->actingAs($this->admin)
            ->get(route('classes.timetable.edit', ['school_class' => $this->class, 'semester' => $this->genap->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/editor')
                ->where('semester_id', $this->genap->id));
    }

    /**
     * Classes of a past academic year are read-only (spec 13 Decisions).
     */
    public function test_editor_page_is_read_only_for_a_past_year_class(): void
    {
        $pastYear = AcademicYear::factory()->create();
        $pastClass = SchoolClass::factory()->create(['academic_year_id' => $pastYear->id, 'name' => '4A 2029/2030']);

        $this->actingAs($this->admin)
            ->get(route('classes.timetable.edit', $pastClass))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/editor')
                ->where('read_only', true));
    }

    public function test_copy_on_a_past_year_class_is_rejected(): void
    {
        $pastYear = AcademicYear::factory()->create();
        $pastClass = SchoolClass::factory()->create(['academic_year_id' => $pastYear->id]);
        $pastSemester = $pastYear->semesters()->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('classes.timetable.copy', $pastClass), ['semester_id' => $pastSemester->id])
            ->assertJsonValidationErrors('class_id');

        $this->assertSame(0, TimetableSlot::count());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_open_the_editor_or_copy(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('classes.timetable.edit', $this->class))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('classes.timetable.copy', $this->class), ['semester_id' => $this->genap->id])
            ->assertForbidden();
    }

    /**
     * Copy into Genap and assert the flash toast reports the skip counts
     * (AC-13-07 skipped list).
     */
    private function copyToGenap(int $copied, int $skipped): void
    {
        $this->actingAs($this->admin)
            ->post(route('classes.timetable.copy', $this->class), ['semester_id' => $this->genap->id])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', __(
                'timetable.toast.copied',
                ['copied' => $copied, 'skipped' => $skipped],
            ));
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
