<?php

namespace Tests\Unit\Models;

use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\ScanEvent;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_outcome_casts_to_the_enum(): void
    {
        $event = ScanEvent::factory()->make(['outcome' => 'ignored_debounce']);

        $this->assertSame(ScanOutcome::IgnoredDebounce, $event->outcome);
    }

    public function test_scan_method_only_knows_the_scan_stream_values(): void
    {
        $event = ScanEvent::factory()->make(['scan_method' => 'dynamic_qr']);

        $this->assertSame(ScanMethod::DynamicQr, $event->scan_method);
    }

    public function test_identifier_holds_the_raw_rfid_number(): void
    {
        $event = ScanEvent::factory()->make(['identifier' => '001234567890']);

        $this->assertSame('001234567890', $event->identifier);
    }

    public function test_student_relation_resolves(): void
    {
        $event = ScanEvent::factory()->for(Student::factory()->make(['id' => 3]), 'student')->make();

        $this->assertSame(3, $event->student_id);
    }
}
