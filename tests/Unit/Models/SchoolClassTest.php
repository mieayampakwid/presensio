<?php

namespace Tests\Unit\Models;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
