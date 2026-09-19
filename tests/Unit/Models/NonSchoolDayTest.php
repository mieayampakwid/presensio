<?php

namespace Tests\Unit\Models;

use App\Enums\NonSchoolDaySource;
use App\Models\NonSchoolDay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonSchoolDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_casts_to_the_enum(): void
    {
        $day = NonSchoolDay::factory()->make(['source' => 'sync']);

        $this->assertSame(NonSchoolDaySource::Sync, $day->source);
    }

    public function test_factory_defaults_to_a_manual_entry(): void
    {
        $this->assertSame(NonSchoolDaySource::Manual, NonSchoolDay::factory()->make()->source);
    }

    public function test_dates_are_unique(): void
    {
        NonSchoolDay::factory()->create(['date' => '2026-12-25']);

        $this->expectException(UniqueConstraintViolationException::class);

        NonSchoolDay::factory()->create(['date' => '2026-12-25']);
    }
}
