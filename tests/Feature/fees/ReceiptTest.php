<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\ReceiptPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Student $budi;

    private Student $sari;

    /**
     * @param  array<string, mixed>  $state
     */
    private function paymentFor(array $state = [], Student ...$students): Payment
    {
        $payment = Payment::factory()->cash()->verified()->create([
            'amount' => (string) (350000 * count($students)),
            'payment_date' => '2026-09-05',
            ...$state,
        ]);

        foreach ($students as $student) {
            PaymentAllocation::factory()->create([
                'payment_id' => $payment->id,
                'bill_id' => Bill::factory()->create(['student_id' => $student->id, 'title' => 'SPP September 2026'])->id,
                'amount' => '350000.00',
            ]);
        }

        return $payment;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::factory()->create();
        $class = SchoolClass::factory()->create(['academic_year_id' => $year->id, 'name' => '5A']);
        $this->budi = Student::factory()->create(['full_name' => 'Budi Santoso', 'student_number' => '2026001']);
        $this->sari = Student::factory()->create(['full_name' => 'Sari Dewi']);
        Enrollment::factory()->create([
            'student_id' => $this->budi->id,
            'class_id' => $class->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
    }

    public function test_receipt_html_has_number_lines_total_words_method_and_staff(): void
    {
        $staff = User::factory()->finance()->create();
        Employee::factory()->forUser($staff)->create(['name' => 'Bu Keuangan']);
        $payment = $this->paymentFor(['verified_by_user_id' => $staff->id, 'receipt_number' => 'KWT-202609-00007'], $this->budi, $this->sari);

        $html = app(ReceiptPdf::class)->html($payment);

        foreach (['KWT-202609-00007', 'Budi Santoso', '2026001', '5A', 'SPP September 2026', 'Rp 700.000', 'Tujuh Ratus Ribu Rupiah', 'Tunai', 'Bu Keuangan', 'Sari Dewi'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('BATAL', $html);
    }

    public function test_voided_receipt_is_watermarked(): void
    {
        $payment = $this->paymentFor(['status' => 'void', 'void_reason' => 'Salah input', 'voided_at' => now()], $this->budi);

        $this->assertStringContainsString('BATAL', app(ReceiptPdf::class)->html($payment));
    }

    public function test_finance_streams_inline_pdf(): void
    {
        $response = $this->actingAs(User::factory()->finance()->create())
            ->get(route('fees.payments.receipt', $this->paymentFor([], $this->budi)))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_pending_and_rejected_payments_have_no_receipt(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('fees.payments.receipt', Payment::factory()->create()))->assertNotFound();
        $this->actingAs($admin)->get(route('fees.payments.receipt', Payment::factory()->rejected()->create()))->assertNotFound();
    }

    public function test_access_matrix(): void
    {
        $payment = $this->paymentFor([], $this->budi);

        $this->actingAs(User::factory()->admin()->create())->get(route('fees.payments.receipt', $payment))->assertOk();
        $this->actingAs(User::factory()->principal()->create())->get(route('fees.payments.receipt', $payment))->assertOk();
        $this->actingAs(User::factory()->teacher()->create())->get(route('fees.payments.receipt', $payment))->assertForbidden();
        $this->actingAs(User::factory()->student()->create())->get(route('fees.payments.receipt', $payment))->assertForbidden();
    }

    public function test_guardian_sees_only_receipts_covering_own_children(): void
    {
        $parent = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $parent->id])->students()->attach($this->budi);

        $own = $this->paymentFor([], $this->budi);
        $withOther = $this->paymentFor([], $this->budi, $this->sari);
        $other = $this->paymentFor([], $this->sari);

        $this->actingAs($parent)->get(route('fees.payments.receipt', $own))->assertOk();
        $this->actingAs($parent)->get(route('fees.payments.receipt', $withOther))->assertForbidden();
        $this->actingAs($parent)->get(route('fees.payments.receipt', $other))->assertForbidden();
    }
}
