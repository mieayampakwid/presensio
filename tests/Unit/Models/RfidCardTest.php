<?php

namespace Tests\Unit\Models;

use App\Models\RfidCard;
use App\Models\Student;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class RfidCardTest extends TestCase
{
    public function test_student_relation_resolves_the_card_owner(): void
    {
        $card = RfidCard::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $card->student());
        $this->assertInstanceOf(Student::class, $card->student()->getRelated());
    }

    public function test_factory_defaults_to_a_spare_card(): void
    {
        $this->assertNull(RfidCard::factory()->make()->student_id);
    }

    public function test_assigned_state_points_at_the_given_student(): void
    {
        $student = Student::factory()->make(['id' => 5]);
        $card = RfidCard::factory()->assigned($student)->make();

        $this->assertSame(5, $card->student_id);
    }
}
