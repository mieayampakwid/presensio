<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillStatus;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Jobs\DeliverNotification;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $spp;

    private User $finance;

    private User $parent;

    private Guardian $guardian;

    private Student $budi;

    private Student $sari;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Date::setTestNow('2026-09-12 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->spp = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
        $this->finance = User::factory()->finance()->create();
        $this->parent = User::factory()->parent()->create();
        $this->guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $this->budi = Student::factory()->create(['full_name' => 'Budi Santoso']);
        $this->sari = Student::factory()->create(['full_name' => 'Sari Santoso']);
        $this->guardian->students()->attach([
            $this->budi->id => ['relationship_type' => 'father'],
            $this->sari->id => ['relationship_type' => 'father'],
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function accountId(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }

    private function billFor(Student $student, string $dueDate, ?BillStatus $status = null, array $state = []): Bill
    {
        return Bill::factory()->recognized('2026-09-01')->create([
            'student_id' => $student->id,
            'fee_type_id' => $this->spp->id,
            'academic_year_id' => AcademicYear::query()->value('id') ?? AcademicYear::factory()->create()->id,
            'title' => 'SPP '.$student->full_name,
            'amount' => '350000.00',
            'due_date' => $dueDate,
            'status' => $status ?? BillStatus::Unpaid,
            ...$state,
        ]);
    }

    private function pendingTransfer(?User $submitter = null): Payment
    {
        $bill = $this->billFor($this->budi, '2026-09-20');

        return app(PaymentService::class)->submitTransfer(
            '350000.00',
            [$bill->id => '350000.00'],
            [$this->budi->id],
            $this->accountId('1-1200'),
            '2026-09-11',
            $submitter ?? $this->parent,
            'Mandiri',
            null,
            null,
        );
    }

    public function test_verifying_notifies_the_submitting_guardian_once(): void
    {
        $payment = $this->pendingTransfer();

        app(PaymentService::class)->verify($payment, null, $this->accountId('1-1200'), $this->finance);

        $payment->refresh();
        $notification = $this->parent->notifications()->where('data->type', NotificationType::PaymentVerified->value)->sole();
        $this->assertSame("payment_verified:payment:{$payment->id}:guardian:{$this->guardian->id}", $notification->dedupe_key);
        $this->assertStringContainsString($payment->receipt_number, $notification->data['body']);
        $this->assertSame('/my-fees', $notification->data['url']);
    }

    public function test_rejecting_notifies_without_the_reason_and_queues_whatsapp_immediately_during_quiet_hours(): void
    {
        Date::setTestNow(Date::parse('2026-09-12 22:00:00', 'Asia/Jakarta'));
        Queue::fake();
        $payment = $this->pendingTransfer();

        app(PaymentService::class)->reject($payment, 'Nominal tidak cocok rahasia', $this->finance);

        $notification = $this->parent->notifications()->sole();
        $this->assertSame(NotificationType::PaymentRejected->value, $notification->data['type']);
        $this->assertStringNotContainsString('rahasia', json_encode($notification->data));
        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => "payment_rejected:payment:{$payment->id}:guardian:{$this->guardian->id}",
            'channel' => 'whatsapp',
            'status' => DeliveryStatus::Pending->value,
            'scheduled_for' => null,
        ]);
        Queue::assertPushed(DeliverNotification::class);
    }

    public function test_payments_without_a_guardian_submitter_send_nothing(): void
    {
        $payment = $this->pendingTransfer($this->finance);

        app(PaymentService::class)->verify($payment, null, $this->accountId('1-1200'), $this->finance);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_cashier_payment_sends_no_notification(): void
    {
        $bill = $this->billFor($this->budi, '2026-09-20');

        app(PaymentService::class)->createCash([$bill->id => '350000.00'], [$this->budi->id], $this->accountId('1-1100'), $this->finance);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_reminder_sends_one_notification_per_guardian_for_bills_due_exactly_n_days_out(): void
    {
        $this->billFor($this->budi, '2026-09-15');
        $this->billFor($this->sari, '2026-09-15', BillStatus::PartiallyPaid, ['paid_amount' => '100000.00']);
        $this->billFor($this->budi, '2026-09-16', state: ['title' => 'Terlalu jauh']);
        $this->billFor($this->budi, '2026-09-15', BillStatus::Paid, ['title' => 'Sudah lunas']);
        $this->billFor($this->sari, '2026-09-15', BillStatus::Cancelled, ['title' => 'Dibatalkan', 'cancelled_at' => now()]);
        $this->billFor($this->sari, '2026-09-15', BillStatus::WrittenOff, ['title' => 'Dihapusbukukan', 'written_off_at' => now()]);

        $this->artisan('fees:send-bill-reminders')->assertSuccessful();

        $notification = $this->parent->notifications()->sole();
        $this->assertSame(NotificationType::BillDueReminder->value, $notification->data['type']);
        $this->assertStringContainsString('2 tagihan', $notification->data['body']);
        $this->assertStringContainsString('Rp 600.000', $notification->data['body']);
        $this->assertStringContainsString('Budi Santoso', $notification->data['body']);
        $this->assertStringContainsString('Sari Santoso', $notification->data['body']);
        foreach (['Terlalu jauh', 'Sudah lunas', 'Dibatalkan', 'Dihapusbukukan'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $notification->data['body']);
        }
    }

    public function test_running_the_reminder_twice_or_with_nothing_due_creates_no_extra_notification(): void
    {
        $this->artisan('fees:send-bill-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);

        $this->billFor($this->budi, '2026-09-15');
        $this->artisan('fees:send-bill-reminders');
        $this->artisan('fees:send-bill-reminders');

        $this->assertDatabaseCount('notifications', 1);
    }
}
