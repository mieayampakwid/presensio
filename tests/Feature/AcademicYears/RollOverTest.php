<?php

namespace Tests\Feature\AcademicYears;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RollOverTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $sourceYear;

    private AcademicYear $targetYear;

    private SchoolClass $class5a;

    private SchoolClass $class5b;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // The active 2026/2027 year is the source; two classes, two
        // students each.
        $this->sourceYear = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'starts_at' => '2026-07-01', 'ends_at' => '2027-06-30']);
        $this->admin = User::factory()->admin()->create();

        $this->class5a = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'grade_level' => 5, 'academic_year_id' => $this->sourceYear->id]);
        $this->class5b = SchoolClass::factory()->create(['name' => 'Kelas 5B', 'grade_level' => 5, 'academic_year_id' => $this->sourceYear->id]);

        Student::factory()->enrolledIn($this->class5a)->create(['full_name' => 'Ahmad Fauzi']);
        Student::factory()->enrolledIn($this->class5a)->create(['full_name' => 'Ayu Lestari']);
        Student::factory()->enrolledIn($this->class5b)->create(['full_name' => 'Dimas Saputra']);
        Student::factory()->enrolledIn($this->class5b)->create(['full_name' => 'Eka Putri']);

        $this->targetYear = AcademicYear::factory()->create([
            'name' => '2027/2028',
            'starts_at' => '2027-07-01',
            'ends_at' => '2028-06-30',
        ]);
    }

    /**
     * Map 5A onto an existing target class, 5B onto a newly created one.
     */
    private function mappings(int $existingTargetId): array
    {
        return [
            $this->class5a->id => [
                'mode' => 'existing',
                'target_class_id' => $existingTargetId,
            ],
            $this->class5b->id => [
                'mode' => 'new',
                'new_name' => 'Kelas 5B',
                'new_teacher_id' => null,
            ],
        ];
    }

    public function test_roll_over_reopens_mapped_students_and_activates_the_target_year(): void
    {
        $target5a = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'academic_year_id' => $this->targetYear->id]);
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('academic-years.roll-over.apply'), [
                'target_year_id' => $this->targetYear->id,
                'effective_on' => '2027-07-01',
                'mappings' => [
                    $this->class5a->id => ['mode' => 'existing', 'target_class_id' => $target5a->id],
                    $this->class5b->id => ['mode' => 'new', 'new_name' => 'Kelas 5B', 'new_teacher_id' => $teacher->id],
                ],
            ])
            ->assertRedirect(route('academic-years.index'));

        // Every source enrollment closed the day before the effective date.
        $this->assertSame(4, Enrollment::query()->where('ended_on', '2027-06-30')->count());

        // The two 5A students continue in the mapped target class.
        $promoted = Enrollment::query()
            ->whereNull('ended_on')
            ->where('class_id', $target5a->id)
            ->count();
        $this->assertSame(2, $promoted);

        // 5B got a fresh class row in the target year with the homeroom set.
        $target5b = SchoolClass::query()->where('academic_year_id', $this->targetYear->id)->where('name', 'Kelas 5B')->first();
        $this->assertNotNull($target5b);
        $this->assertSame($teacher->id, $target5b->teacher_id);
        $this->assertSame(2, Enrollment::query()->whereNull('ended_on')->where('class_id', $target5b->id)->count());
        $this->assertSame(6, $target5b->grade_level); // Source was grade 5, new class gets 5 + 1 = 6
        $this->assertSame('merdeka', $target5b->curriculum instanceof \BackedEnum ? $target5b->curriculum->value : $target5b->curriculum);

        // The target year is now the single active one.
        $this->assertTrue($this->targetYear->fresh()->is_active);
        $this->assertFalse($this->sourceYear->fresh()->is_active);
    }

    public function test_unmapped_classes_become_alumni(): void
    {
        $target5a = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'academic_year_id' => $this->targetYear->id]);

        $this->actingAs($this->admin)
            ->post(route('academic-years.roll-over.apply'), [
                'target_year_id' => $this->targetYear->id,
                'effective_on' => '2027-07-01',
                'mappings' => [
                    $this->class5a->id => ['mode' => 'existing', 'target_class_id' => $target5a->id],
                    $this->class5b->id => ['mode' => 'none'],
                ],
            ])
            ->assertRedirect(route('academic-years.index'));

        // 5B students have history but no open enrollment — they are alumni.
        $alumni = Student::query()->where('full_name', 'Dimas Saputra')->first();
        $this->assertNull($alumni->currentEnrollment);
        $this->assertSame(1, $alumni->enrollments()->count());
        $this->assertNull($alumni->classOn('2027-07-05'));
        // ...and they are still visible in their historical class.
        $this->assertSame($this->class5b->id, $alumni->classOn('2026-09-21')->id);
    }

    public function test_the_old_class_report_keeps_its_attribution_after_roll_over(): void
    {
        $reports = app(AttendanceReportService::class);

        $before = $reports->classReport($this->class5a, Date::parse('2026-09-01'), Date::parse('2026-09-30'), false);

        $target5a = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'academic_year_id' => $this->targetYear->id]);

        $this->actingAs($this->admin)
            ->post(route('academic-years.roll-over.apply'), [
                'target_year_id' => $this->targetYear->id,
                'effective_on' => '2027-07-01',
                'mappings' => [
                    $this->class5a->id => ['mode' => 'existing', 'target_class_id' => $target5a->id],
                    $this->class5b->id => ['mode' => 'none'],
                ],
            ])
            ->assertRedirect();

        $after = $reports->classReport($this->class5a, Date::parse('2026-09-01'), Date::parse('2026-09-30'), false);

        // History is immutable: same roster, same rows — even though every
        // student's current enrollment now sits in the new year.
        $this->assertSame($before['rows'], $after['rows']);

        // The NEW year's 5A contains exactly the promoted students.
        $newReport = $reports->classReport($target5a, Date::parse('2027-07-01'), Date::parse('2027-07-31'), false);
        $this->assertCount(2, $newReport['rows']);
    }

    public function test_re_applying_a_promoted_year_is_a_no_op(): void
    {
        $target5a = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'academic_year_id' => $this->targetYear->id]);

        $payload = [
            'target_year_id' => $this->targetYear->id,
            'effective_on' => '2027-07-01',
            'mappings' => [
                $this->class5a->id => ['mode' => 'existing', 'target_class_id' => $target5a->id],
                $this->class5b->id => ['mode' => 'none'],
            ],
        ];

        $this->actingAs($this->admin)->post(route('academic-years.roll-over.apply'), $payload)->assertRedirect();
        $snapshot = Enrollment::query()->orderBy('id')->get(['student_id', 'class_id', 'started_on', 'ended_on'])->toArray();

        $this->actingAs($this->admin)->post(route('academic-years.roll-over.apply'), $payload)->assertRedirect();

        $this->assertSame($snapshot, Enrollment::query()->orderBy('id')->get(['student_id', 'class_id', 'started_on', 'ended_on'])->toArray());
    }

    public function test_a_target_class_from_the_wrong_year_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('academic-years.roll-over.apply'), [
                'target_year_id' => $this->targetYear->id,
                'effective_on' => '2027-07-01',
                'mappings' => [
                    // 5A (source year) mapped onto a SOURCE-year class — never allowed.
                    $this->class5a->id => ['mode' => 'existing', 'target_class_id' => $this->class5b->id],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(0, Enrollment::query()->whereNotNull('ended_on')->count());
        $this->assertTrue($this->sourceYear->fresh()->is_active);
    }

    public function test_the_roll_over_screen_carries_the_mapping_material(): void
    {
        $this->actingAs($this->admin)
            ->get(route('academic-years.roll-over', ['target_year_id' => $this->targetYear->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academic-years/roll-over')
                ->where('source.name', '2026/2027')
                ->where('target_year_id', $this->targetYear->id)
                ->has('source_classes', 2)
                ->has('target_classes', 0)
                ->where('already_promoted', false));
    }

    /**
     * AC-09-07: Roll-over with "copy subject assignments" on maps 5A (2026/2027, 9 assignments)
     * to 6A (2027/2028) and creates 9 assignments on 6A; an assignment whose teacher is inactive
     * is skipped and reported.
     */
    public function test_ac_09_07_roll_over_copies_subject_assignments_and_reports_inactive(): void
    {
        $activeTeachers = Teacher::factory()->count(8)->create();
        $inactiveUser = User::factory()->teacher()->create(['is_active' => false]);
        $inactiveTeacher = Teacher::factory()->create(['user_id' => $inactiveUser->id]);

        $subjects = Subject::factory()->count(9)->create();

        // 8 active assignments
        foreach ($activeTeachers as $index => $teacher) {
            ClassSubject::factory()->create([
                'class_id' => $this->class5a->id,
                'subject_id' => $subjects[$index]->id,
                'teacher_id' => $teacher->id,
                'passing_threshold' => '75.00',
            ]);
        }

        // 1 assignment with inactive teacher
        ClassSubject::factory()->create([
            'class_id' => $this->class5a->id,
            'subject_id' => $subjects[8]->id,
            'teacher_id' => $inactiveTeacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->assertSame(9, ClassSubject::where('class_id', $this->class5a->id)->count());

        $existingTarget = SchoolClass::factory()->create([
            'name' => 'Kelas 6A',
            'grade_level' => 6,
            'academic_year_id' => $this->targetYear->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('academic-years.roll-over.apply'), [
            'target_year_id' => $this->targetYear->id,
            'effective_on' => '2027-07-01',
            'mappings' => [
                $this->class5a->id => [
                    'mode' => 'existing',
                    'target_class_id' => $existingTarget->id,
                    'copy_subjects' => true,
                ],
                $this->class5b->id => [
                    'mode' => 'none',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('academic-years.index'));

        // 8 copied, 1 skipped
        $this->assertSame(8, ClassSubject::where('class_id', $existingTarget->id)->count());

        /** @var array{copied: int, skipped: list<array{subject: string, reason: string}>} $summary */
        $summary = session('roll_over_summary');
        $this->assertNotNull($summary);
        $this->assertSame(8, $summary['copied']);
        $this->assertCount(1, $summary['skipped']);
        $this->assertSame($subjects[8]->name, $summary['skipped'][0]['subject']);
        $this->assertSame('Teacher is inactive', $summary['skipped'][0]['reason']);
    }
}
