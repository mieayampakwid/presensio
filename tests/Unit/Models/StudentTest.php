<?php

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\RfidCard;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class StudentTest extends TestCase
{
    public function test_enrollment_relations_resolve_class_membership(): void
    {
        $student = Student::factory()->make();

        $this->assertInstanceOf(HasMany::class, $student->enrollments());
        $this->assertInstanceOf(Enrollment::class, $student->enrollments()->getRelated());
        $this->assertInstanceOf(HasOne::class, $student->currentEnrollment());
    }

    public function test_guardians_relation_resolves_linked_guardians(): void
    {
        $student = Student::factory()->make();

        $this->assertInstanceOf(BelongsToMany::class, $student->guardians());
        $this->assertInstanceOf(Guardian::class, $student->guardians()->getRelated());
    }

    public function test_rfid_cards_relation_resolves_assigned_cards(): void
    {
        $student = Student::factory()->make();

        $this->assertInstanceOf(HasMany::class, $student->rfidCards());
        $this->assertInstanceOf(RfidCard::class, $student->rfidCards()->getRelated());
    }

    public function test_user_relation_links_to_the_login_account(): void
    {
        $student = Student::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $student->user());
        $this->assertInstanceOf(User::class, $student->user()->getRelated());
    }

    public function test_dob_is_cast_to_a_carbon_date(): void
    {
        $student = Student::factory()->make(['dob' => '2012-12-25']);

        $this->assertInstanceOf(CarbonImmutable::class, $student->dob);
        $this->assertSame('2012-12-25', $student->dob->toDateString());
    }

    public function test_unnumbered_state_clears_the_student_number(): void
    {
        $student = Student::factory()->unnumbered()->make();

        $this->assertNull($student->student_number);
    }
}
