<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillIndexTest extends TestCase
{
    use RefreshDatabase;

    private function enroll(Student $student, SchoolClass $class): void
    {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'started_on' => '2020-01-01',
            'ended_on' => null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rowsByTitle(User $user, array $query = []): array
    {
        $rows = [];

        $this->actingAs($user)->get(route('fees.bills.index', $query))->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = collect($page->toArray()['props']['bills']['data'])->keyBy('title')->all();
            });

        return $rows;
    }

    public function test_filters_by_class_student_fee_type_period_and_status(): void
    {
        $year = AcademicYear::factory()->create();
        $classA = SchoolClass::factory()->create(['academic_year_id' => $year->id, 'name' => '5A']);
        $classB = SchoolClass::factory()->create(['academic_year_id' => $year->id, 'name' => '5B']);
        $budi = Student::factory()->create(['full_name' => 'Budi Santoso', 'student_number' => '2026001']);
        $sari = Student::factory()->create(['full_name' => 'Sari Dewi']);
        $this->enroll($budi, $classA);
        $this->enroll($sari, $classB);
        $spp = FeeType::factory()->create();
        $uniform = FeeType::factory()->oneTime()->create();

        Bill::factory()->create(['student_id' => $budi->id, 'fee_type_id' => $spp->id, 'title' => 'budi-spp-sep', 'period_key' => '2026-09']);
        Bill::factory()->paid()->create(['student_id' => $budi->id, 'fee_type_id' => $uniform->id, 'title' => 'budi-uniform', 'period_key' => '2026-07']);
        Bill::factory()->create(['student_id' => $sari->id, 'fee_type_id' => $spp->id, 'title' => 'sari-spp-sep', 'period_key' => '2026-09']);
        $admin = User::factory()->admin()->create();

        $this->assertSame(['budi-spp-sep', 'budi-uniform', 'sari-spp-sep'], collect($this->rowsByTitle($admin))->keys()->sort()->values()->all());
        $this->assertSame(['budi-spp-sep', 'budi-uniform'], collect($this->rowsByTitle($admin, ['class_id' => $classA->id]))->keys()->sort()->values()->all());
        $this->assertSame(['budi-spp-sep', 'budi-uniform'], collect($this->rowsByTitle($admin, ['search' => '2026001']))->keys()->sort()->values()->all());
        $this->assertSame(['sari-spp-sep'], array_keys($this->rowsByTitle($admin, ['search' => 'Sari'])));
        $this->assertSame(['budi-uniform'], array_keys($this->rowsByTitle($admin, ['fee_type_id' => $uniform->id])));
        $this->assertSame(['budi-uniform'], array_keys($this->rowsByTitle($admin, ['period_key' => '2026-07'])));
        $this->assertSame(['budi-uniform'], array_keys($this->rowsByTitle($admin, ['status' => 'paid'])));
        $this->assertSame('5A', $this->rowsByTitle($admin, ['search' => 'Budi'])['budi-spp-sep']['class_name']);
    }

    public function test_row_action_flags_follow_bill_state(): void
    {
        $paid = Bill::factory()->recognized()->paid()->create(['title' => 'paid']);
        $unrecognized = Bill::factory()->create(['title' => 'unrecognized']);
        $partial = Bill::factory()->recognized()->partiallyPaid()->create(['title' => 'partial']);
        $cancelled = Bill::factory()->cancelled()->create(['title' => 'cancelled']);
        $writtenOff = Bill::factory()->recognized()->create(['title' => 'written-off', 'written_off_at' => now()]);

        $rows = $this->rowsByTitle(User::factory()->finance()->create());

        $flags = fn (string $title) => [$rows[$title]['can_adjust_discount'], $rows[$title]['can_cancel'], $rows[$title]['can_write_off']];
        $this->assertSame([true, false, false], $flags('paid'));
        $this->assertSame([true, true, false], $flags('unrecognized'));
        $this->assertSame([true, false, true], $flags('partial'));
        $this->assertSame([false, false, false], $flags('cancelled'));
        $this->assertSame([false, false, false], $flags('written-off'));
        $this->assertSame('250000.00', $rows['partial']['max_discount']);
    }

    public function test_principal_sees_read_only_list_and_cannot_write(): void
    {
        $bill = Bill::factory()->create();
        $principal = User::factory()->principal()->create();

        $this->actingAs($principal)->get(route('fees.bills.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('fees/bills')->where('canManage', false)->has('bills.data', 1));
        $this->actingAs($principal)->post(route('fees.bills.cancel', $bill), ['reason' => 'x'])->assertForbidden();
        $this->actingAs(User::factory()->finance()->create())->get(route('fees.bills.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
    }

    public function test_teacher_parent_and_student_are_forbidden(): void
    {
        foreach ([User::factory()->teacher(), User::factory()->parent(), User::factory()->student()] as $user) {
            $this->actingAs($user->create())->get(route('fees.bills.index'))->assertForbidden();
        }
    }

    public function test_listing_does_not_lazy_load_relations(): void
    {
        $class = SchoolClass::factory()->create();
        Bill::factory()->count(3)->create()->each(fn (Bill $bill) => $this->enroll($bill->student, $class));

        $this->actingAs(User::factory()->admin()->create())->get(route('fees.bills.index'))->assertOk();
    }
}
