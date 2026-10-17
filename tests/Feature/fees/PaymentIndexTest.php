<?php

namespace Tests\Feature\Fees;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, array<string, mixed>>
     */
    private function rowsByAmount(array $query = []): array
    {
        $rows = [];

        $this->actingAs(User::factory()->admin()->create())->get(route('fees.payments.index', $query))->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = collect($page->toArray()['props']['payments']['data'])->keyBy('amount')->all();
            });

        return $rows;
    }

    public function test_filters_by_date_range_method_and_status(): void
    {
        Payment::factory()->cash()->verified()->create(['amount' => '1000.00', 'payment_date' => '2026-09-01']);
        Payment::factory()->verified()->create(['amount' => '2000.00', 'payment_date' => '2026-09-10']);
        Payment::factory()->cash()->void()->create(['amount' => '3000.00', 'payment_date' => '2026-10-01']);
        Payment::factory()->create(['amount' => '4000.00', 'payment_date' => '2026-10-02']);

        $this->assertCount(4, $this->rowsByAmount());
        $this->assertSame(['3000.00', '2000.00'], array_keys($this->rowsByAmount(['date_from' => '2026-09-05', 'date_to' => '2026-10-01'])));
        $this->assertSame(['3000.00', '1000.00'], array_keys($this->rowsByAmount(['method' => 'cash'])));
        $this->assertSame(['3000.00'], array_keys($this->rowsByAmount(['status' => 'void'])));
    }

    public function test_rows_show_students_and_receipt_only_for_verified_or_void(): void
    {
        $payment = Payment::factory()->cash()->verified()->create(['amount' => '700000.00']);

        foreach (['Budi Santoso', 'Sari Dewi'] as $name) {
            PaymentAllocation::factory()->create([
                'payment_id' => $payment->id,
                'bill_id' => Bill::factory()->create(['student_id' => Student::factory()->create(['full_name' => $name])->id])->id,
                'amount' => '350000.00',
            ]);
        }
        Payment::factory()->create(['amount' => '5000.00']);
        Payment::factory()->rejected()->create(['amount' => '6000.00']);
        Payment::factory()->cash()->void()->create(['amount' => '7000.00']);

        $rows = $this->rowsByAmount();

        $this->assertEqualsCanonicalizing(['Budi Santoso', 'Sari Dewi'], $rows['700000.00']['students']);
        $this->assertSame('Tunai', $rows['700000.00']['method_label']);
        $this->assertSame(
            [true, false, false, true],
            [$rows['700000.00']['has_receipt'], $rows['5000.00']['has_receipt'], $rows['6000.00']['has_receipt'], $rows['7000.00']['has_receipt']],
        );
    }

    public function test_principal_sees_list_with_can_manage_false(): void
    {
        $this->actingAs(User::factory()->principal()->create())->get(route('fees.payments.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('fees/payments')->where('canManage', false));
        $this->actingAs(User::factory()->finance()->create())->get(route('fees.payments.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
    }

    public function test_teacher_parent_and_student_are_forbidden(): void
    {
        foreach ([User::factory()->teacher(), User::factory()->parent(), User::factory()->student()] as $user) {
            $this->actingAs($user->create())->get(route('fees.payments.index'))->assertForbidden();
        }
    }

    public function test_listing_does_not_lazy_load_relations(): void
    {
        Payment::factory()->count(3)->cash()->verified()->create()->each(
            fn (Payment $payment) => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_id' => Bill::factory()->create()->id])
        );

        $this->actingAs(User::factory()->admin()->create())->get(route('fees.payments.index'))->assertOk();
    }
}
