<?php

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_cannot_hold_two_open_enrollments(): void
    {
        $student = Student::factory()->create();

        Enrollment::factory()->create(['student_id' => $student->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        Enrollment::factory()->create(['student_id' => $student->id]);
    }

    public function test_a_student_may_hold_sequential_enrollments(): void
    {
        $student = Student::factory()->create();
        $first = SchoolClass::factory()->create();
        $second = SchoolClass::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $first->id,
            'started_on' => '2026-07-01',
            'ended_on' => '2027-06-30',
        ]);

        $secondEnrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $second->id,
            'started_on' => '2027-07-01',
        ]);

        $this->assertSame($second->id, $secondEnrollment->class_id);
        $this->assertSame(2, $student->enrollments()->count());
    }

    public function test_class_on_resolves_the_enrollment_active_on_each_date(): void
    {
        $student = Student::factory()->create();
        $old = SchoolClass::factory()->create();
        $new = SchoolClass::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $old->id,
            'started_on' => '2026-07-01',
            'ended_on' => '2026-09-30',
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $new->id,
            'started_on' => '2026-10-01',
        ]);

        $this->assertSame($old->id, $student->classOn('2026-09-15')->id);
        // Boundary dates belong to the enrollment that touches them.
        $this->assertSame($old->id, $student->classOn('2026-09-30')->id);
        $this->assertSame($new->id, $student->classOn('2026-10-01')->id);
        $this->assertSame($new->id, $student->classOn('2027-01-31')->id);
        $this->assertNull($student->classOn('2026-06-30'));
    }

    public function test_active_on_scope_covers_open_and_bounded_enrollments(): void
    {
        $open = Enrollment::factory()->create(['started_on' => '2026-07-01']);
        $bounded = Enrollment::factory()->create([
            'started_on' => '2026-07-01',
            'ended_on' => '2026-09-30',
        ]);

        $active = Enrollment::query()->activeOn('2026-09-15')->pluck('id');

        $this->assertTrue($active->contains($open->id));
        $this->assertTrue($active->contains($bounded->id));

        $later = Enrollment::query()->activeOn('2026-10-15')->pluck('id');

        $this->assertTrue($later->contains($open->id));
        $this->assertFalse($later->contains($bounded->id));
    }
}
