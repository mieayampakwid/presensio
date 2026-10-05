<?php

namespace Tests\Unit\Models;

use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    public function test_user_relation_links_to_the_login_account(): void
    {
        $employee = Employee::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $employee->user());
        $this->assertInstanceOf(User::class, $employee->user()->getRelated());
    }

    public function test_teacher_relation_resolves_teaching_extension(): void
    {
        $employee = Employee::factory()->make();

        $this->assertInstanceOf(HasOne::class, $employee->teacher());
        $this->assertInstanceOf(Teacher::class, $employee->teacher()->getRelated());
    }

    public function test_rfid_cards_relation(): void
    {
        $employee = Employee::factory()->make();

        $this->assertInstanceOf(HasMany::class, $employee->rfidCards());
        $this->assertInstanceOf(RfidCard::class, $employee->rfidCards()->getRelated());
    }

    public function test_casts_and_defaults(): void
    {
        $employee = Employee::factory()->make([
            'employment_type' => EmploymentType::Pns,
            'working_days' => [1, 2, 3],
            'is_active' => true,
        ]);

        $this->assertSame(EmploymentType::Pns, $employee->employment_type);
        $this->assertSame([1, 2, 3], $employee->working_days);
        $this->assertTrue($employee->is_active);
    }
}
