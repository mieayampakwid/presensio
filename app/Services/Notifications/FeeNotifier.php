<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Bill;
use App\Models\Guardian;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Guardian notifications for school fees (spec 17): payment outcomes and the
 * daily bill reminder. The reject reason is deliberately never sent; it is
 * shown only in the portal (AC-17-10).
 */
class FeeNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Notify the submitting guardian that a transfer was verified.
     */
    public function paymentVerified(Payment $payment): void
    {
        $this->notifySubmitter(
            type: NotificationType::PaymentVerified,
            payment: $payment,
            title: 'Pembayaran Terverifikasi',
            body: 'Pembayaran '.Money::format($payment->amount)." telah diverifikasi. Nomor kuitansi: {$payment->receipt_number}.",
        );
    }

    /**
     * Notify the submitting guardian that a transfer was rejected, without the reason.
     */
    public function paymentRejected(Payment $payment): void
    {
        $this->notifySubmitter(
            type: NotificationType::PaymentRejected,
            payment: $payment,
            title: 'Transfer Ditolak',
            body: 'Bukti transfer sebesar '.Money::format($payment->amount).' ditolak. Buka portal pembayaran untuk melihat alasannya.',
        );
    }

    /**
     * Send one reminder per guardian covering every given bill of all their children (AC-17-06).
     * The dedupe base is the date, so the key is unique per guardian per day.
     *
     * @param  Collection<int, Bill>  $bills  with student.guardians.user loaded
     */
    public function billsDue(Collection $bills, string $date): void
    {
        $perGuardian = [];

        foreach ($bills as $bill) {
            foreach ($bill->student->guardians as $guardian) {
                if ($guardian->user_id === null && $guardian->phone_number === '') {
                    continue;
                }

                $perGuardian[$guardian->id]['guardian'] = $guardian;
                $perGuardian[$guardian->id]['bills'][] = $bill;
            }
        }

        foreach ($perGuardian as $entry) {
            $guardianBills = collect($entry['bills']);
            $total = $guardianBills->reduce(fn (string $sum, Bill $bill) => Money::add($sum, $bill->remainingAmount()), '0.00');
            $details = $guardianBills->map(fn (Bill $bill) => "{$bill->student->full_name} ({$bill->title})")->implode(', ');

            $this->dispatcher->dispatch(
                type: NotificationType::BillDueReminder,
                recipients: collect([Recipient::fromGuardian($entry['guardian'])]),
                message: new Message(
                    title: 'Pengingat Tagihan',
                    body: "Ada {$guardianBills->count()} tagihan jatuh tempo pada ".$guardianBills->first()->due_date->format('d/m/Y').' dengan total '.Money::format($total).": {$details}.",
                    route: 'parent.fees.index',
                ),
                dedupeBase: $date,
            );
        }
    }

    private function notifySubmitter(NotificationType $type, Payment $payment, string $title, string $body): void
    {
        $guardian = Guardian::query()->where('user_id', $payment->submitted_by_user_id)->first();

        if ($guardian === null) {
            return;
        }

        $this->dispatcher->dispatch(
            type: $type,
            recipients: collect([Recipient::fromGuardian($guardian)]),
            message: new Message(title: $title, body: $body, route: 'parent.fees.index'),
            dedupeBase: "payment:{$payment->id}",
        );
    }
}
