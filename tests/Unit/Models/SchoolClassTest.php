<?php

namespace Tests\Unit\Models;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\MerdekaPhase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchoolClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_relation_resolves_the_homeroom_teacher(): void
    {
        $class = SchoolClass::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $class->teacher());
        $this->assertInstanceOf(Teacher::class, $class->teacher()->getRelated());
    }

    public function test_students_relation_resolves_currently_enrolled_students(): void
    {
        $class = SchoolClass::factory()->make();

        $this->assertInstanceOf(HasManyThrough::class, $class->students());
        $this->assertInstanceOf(Student::class, $class->students()->getRelated());
        // Through the enrollments table (open enrollment filter lives in
        // the relation).
        $this->assertSame('enrollments.class_id', $class->students()->getQualifiedFirstKeyName());
    }

    public function test_with_teacher_state_assigns_the_homeroom_teacher(): void
    {
        $teacher = Teacher::factory()->make(['id' => 7]);
        $class = SchoolClass::factory()->withTeacher($teacher)->make();

        $this->assertSame(7, $class->teacher_id);
    }

    /**
     * AC-15-09: A class with grade_level = 5 resolves Merdeka phase C.
     */
    #[DataProvider('gradeLevelPhaseProvider')]
    public function test_merdeka_phase_resolves_correctly_for_all_grade_levels(int $gradeLevel, ?MerdekaPhase $expectedPhase): void
    {
        $class = SchoolClass::factory()->make(['grade_level' => $gradeLevel]);

        $this->assertSame($expectedPhase, $class->phase());
    }

    public static function gradeLevelPhaseProvider(): array
    {
        return [
            'sentinel 0 (belum diatur)' => [0, null],
            'grade 1 phase A' => [1, MerdekaPhase::A],
            'grade 2 phase A' => [2, MerdekaPhase::A],
            'grade 3 phase B' => [3, MerdekaPhase::B],
            'grade 4 phase B' => [4, MerdekaPhase::B],
            'grade 5 phase C (AC-15-09)' => [5, MerdekaPhase::C],
            'grade 6 phase C' => [6, MerdekaPhase::C],
            'grade 7 phase D' => [7, MerdekaPhase::D],
            'grade 8 phase D' => [8, MerdekaPhase::D],
            'grade 9 phase D' => [9, MerdekaPhase::D],
            'grade 10 phase E' => [10, MerdekaPhase::E],
            'grade 11 phase F' => [11, MerdekaPhase::F],
            'grade 12 phase F' => [12, MerdekaPhase::F],
            'out of range 13' => [13, null],
        ];
    }

    public function test_curriculum_locked_returns_false(): void
    {
        $class = SchoolClass::factory()->make();

        $this->assertFalse($class->curriculumLocked());
    }
}
