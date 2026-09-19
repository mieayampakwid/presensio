<?php

namespace Tests\Unit\Models;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_casts_to_the_enum(): void
    {
        $record = Attendance::factory()->make(['status' => 'late']);

        $this->assertSame(AttendanceStatus::Late, $record->status);
        $this->assertSame('late', $record->status->value);
    }

    public function test_scan_method_casts_to_the_enum_and_stays_nullable(): void
    {
        $methoded = Attendance::factory()->make(['scan_method' => 'dynamic_qr']);
        $sweep = Attendance::factory()->absent()->make();

        $this->assertSame(ScanMethod::DynamicQr, $methoded->scan_method);
        $this->assertNull($sweep->scan_method);
    }

    public function test_student_relation_resolves(): void
    {
        $record = Attendance::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $record->student());
        $this->assertInstanceOf(Student::class, $record->student()->getRelated());
    }

    public function test_override_by_relation_points_at_users(): void
    {
        $record = Attendance::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $record->overrideBy());
        $this->assertInstanceOf(User::class, $record->overrideBy()->getRelated());
    }

    public function test_student_and_date_are_unique_together(): void
    {
        $student = Student::factory()->create();

        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-09-21']);

        $this->expectException(UniqueConstraintViolationException::class);

        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-09-21']);
    }

    public function test_same_date_is_allowed_for_a_different_student(): void
    {
        Attendance::factory()->create(['date' => '2026-09-21']);
        Attendance::factory()->create(['date' => '2026-09-21']);

        $this->assertSame(2, Attendance::where('date', '2026-09-21')->count());
    }
}
