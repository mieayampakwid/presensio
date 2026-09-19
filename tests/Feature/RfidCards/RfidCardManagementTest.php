<?php

namespace Tests\Feature\RfidCards;

use App\Models\RfidCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RfidCardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_cards_list(): void
    {
        $admin = User::factory()->admin()->create();
        RfidCard::factory()->create(['rfid_number' => 'AB-1234']);

        $this->actingAs($admin)
            ->get(route('rfid-cards.index'))
            ->assertOk()
            ->assertSee('AB-1234');
    }

    public function test_admin_can_search_cards_by_student_name(): void
    {
        $admin = User::factory()->admin()->create();
        $assigned = Student::factory()->create(['full_name' => 'Ayu Lestari']);
        RfidCard::factory()->assigned($assigned)->create(['rfid_number' => 'AA-0001']);
        RfidCard::factory()->create(['rfid_number' => 'ZZ-9999']);

        $this->actingAs($admin)
            ->get(route('rfid-cards.index', ['search' => 'Ayu']))
            ->assertOk()
            ->assertSee('AA-0001')
            ->assertDontSee('ZZ-9999');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_card_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('rfid-cards.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_card_assigned_to_a_student(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->post(route('rfid-cards.store'), [
                'rfid_number' => 'AB-1234',
                'student_id' => $student->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseHas('rfid_cards', [
            'rfid_number' => 'AB-1234',
            'student_id' => $student->id,
        ]);
    }

    public function test_card_creation_requires_a_unique_number(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = RfidCard::factory()->create();

        $this->actingAs($admin)
            ->post(route('rfid-cards.store'), [
                'rfid_number' => $existing->rfid_number,
            ])
            ->assertSessionHasErrors('rfid_number');
    }

    public function test_admin_can_update_a_card_keeping_its_own_number(): void
    {
        $admin = User::factory()->admin()->create();
        $card = RfidCard::factory()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->put(route('rfid-cards.update', $card), [
                'rfid_number' => $card->rfid_number,
                'student_id' => $student->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($student->id, $card->fresh()->student_id);
    }

    public function test_revoke_returns_the_card_to_the_spare_pool(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $card = RfidCard::factory()->assigned($student)->create();

        $this->actingAs($admin)
            ->from(route('rfid-cards.index'))
            ->put(route('rfid-cards.revoke', $card))
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertNull($card->fresh()->student_id);
    }

    public function test_admin_can_delete_a_card(): void
    {
        $admin = User::factory()->admin()->create();
        $card = RfidCard::factory()->create();

        $this->actingAs($admin)
            ->from(route('rfid-cards.index'))
            ->delete(route('rfid-cards.destroy', $card))
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseMissing('rfid_cards', ['id' => $card->id]);
    }

    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'student' => ['student'],
            'parent' => ['parent'],
        ];
    }
}
