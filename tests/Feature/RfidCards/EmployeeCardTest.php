<?php

namespace Tests\Feature\RfidCards;

use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_card_assigned_to_an_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['name' => 'Pak Joko Widodo']);

        $this->actingAs($admin)
            ->post(route('rfid-cards.store'), [
                'rfid_number' => 'EMP-1234567890',
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rfid-cards.index'));

        $card = RfidCard::where('rfid_number', 'EMP-1234567890')->first();
        $this->assertNotNull($card);
        $this->assertSame($employee->id, $card->employee_id);
        $this->assertNull($card->student_id);
        $this->assertTrue($card->isEmployee());
    }

    public function test_admin_can_update_a_card_assigning_it_to_an_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $card = RfidCard::factory()->spare()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->put(route('rfid-cards.update', $card), [
                'rfid_number' => $card->rfid_number,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rfid-cards.edit', $card));

        $this->assertSame($employee->id, $card->fresh()->employee_id);
    }

    public function test_ac_16_04_card_cannot_be_assigned_to_an_employee_while_assigned_to_a_student(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $employee = Employee::factory()->create();

        // 1. On create with both student_id and employee_id -> 422
        $this->actingAs($admin)
            ->post(route('rfid-cards.store'), [
                'rfid_number' => 'CONFLICT-001',
                'student_id' => $student->id,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasErrors('employee_id');

        // 2. On update of existing student card trying to add employee_id without clearing student_id -> 422
        $card = RfidCard::factory()->assigned($student)->create();

        $this->actingAs($admin)
            ->put(route('rfid-cards.update', $card), [
                'rfid_number' => $card->rfid_number,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertSame($student->id, $card->fresh()->student_id);
        $this->assertNull($card->fresh()->employee_id);
    }

    public function test_revoking_an_employee_card_returns_it_to_spare_pool(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $card = RfidCard::factory()->assignedToEmployee($employee)->create();

        $this->actingAs($admin)
            ->put(route('rfid-cards.revoke', $card))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($card->fresh()->employee_id);
        $this->assertNull($card->fresh()->student_id);
        $this->assertFalse($card->fresh()->isAssigned());
    }

    public function test_admin_can_search_cards_by_employee_name(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['name' => 'Budi Sudarsono']);
        $card = RfidCard::factory()->assignedToEmployee($employee)->create();
        $otherCard = RfidCard::factory()->spare()->create();

        $this->actingAs($admin)
            ->get(route('rfid-cards.index', ['search' => 'Sudarsono']))
            ->assertOk()
            ->assertSee($card->rfid_number)
            ->assertDontSee($otherCard->rfid_number);
    }
}
