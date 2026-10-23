<?php

namespace App\Services\Finance;

use App\Enums\OverpaymentReason;
use App\Models\Account;
use App\Models\Guardian;
use App\Models\Overpayment;
use App\Models\Payment;
use App\Services\SchoolSettings;
use Illuminate\Database\Eloquent\Collection;

class OverpaymentReadModel
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listing(array $filters): array
    {
        return [
            'filters' => $filters,
            'today' => $this->settings->todayDate(),
            'configuration' => ['account_id' => $this->settings->guardianDepositAccountId()],
            'overpayments' => Overpayment::query()->with(['guardian:id,name', 'student:id,full_name'])
                ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
                ->when($filters['guardian_id'] ?? null, fn ($q, $value) => $q->where('guardian_id', $value))
                ->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate('posting_date', '>=', $value))
                ->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate('posting_date', '<=', $value))
                ->when($filters['search'] ?? null, fn ($q, $value) => $q->whereHas('guardian', fn ($g) => $g->whereLike('name', "%{$value}%")))
                ->orderByDesc('posting_date')->orderByDesc('id')->paginate(20)->withQueryString()
                ->through(fn (Overpayment $record) => $this->summary($record)),
            'guardians' => Guardian::query()->with('students:id,full_name')->orderBy('name')->get(['id', 'name']),
            'cashAccounts' => $this->cashAccounts(),
            'reasons' => array_map(fn (OverpaymentReason $reason) => ['value' => $reason->value, 'label' => $reason->label()], OverpaymentReason::cases()),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Overpayment $record): array
    {
        $record->load(['guardian', 'student', 'relatedPayment', 'liabilityAccount', 'depositAccount', 'refundAccount', 'receiptEntry', 'refundEntry', 'cancellationEntry', 'recordedBy', 'refundedBy', 'cancelledBy']);
        $duplicates = Overpayment::query()->whereKeyNot($record->id)
            ->where(fn ($q) => $q->where('proof_hash', $record->proof_hash)->orWhere('refund_proof_hash', $record->proof_hash))
            ->pluck('id');
        $paymentDuplicates = Payment::query()->where('proof_hash', $record->proof_hash)->pluck('id');

        return $this->summary($record) + [
            'bank_mutation_date' => $record->bank_mutation_date->toDateString(),
            'reason_label' => $record->reason->label(), 'note' => $record->note,
            'related_payment_id' => $record->related_payment_id,
            'liability_account' => $record->liabilityAccount->only(['id', 'code', 'name', 'is_active']),
            'deposit_account' => $record->depositAccount->only(['id', 'code', 'name']),
            'refund_account' => $record->refundAccount?->only(['id', 'code', 'name']),
            'receipt_entry' => $record->receiptEntry?->only(['id', 'number', 'entry_date']),
            'refund_entry' => $record->refundEntry?->only(['id', 'number', 'entry_date']),
            'cancellation_entry' => $record->cancellationEntry?->only(['id', 'number', 'entry_date']),
            'refund_date' => $record->refund_date?->toDateString(), 'bank_reference' => $record->bank_reference,
            'has_refund_proof' => $record->refund_proof_path !== null,
            'requested_cancel_date' => $record->requested_cancel_date?->toDateString(), 'cancel_reason' => $record->cancel_reason,
            'recorded_by' => $record->recordedBy?->username, 'refunded_by' => $record->refundedBy?->username, 'cancelled_by' => $record->cancelledBy?->username,
            'duplicate_overpayment_ids' => $duplicates, 'duplicate_payment_ids' => $paymentDuplicates,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(Overpayment $record): array
    {
        return [
            'id' => $record->id, 'guardian_id' => $record->guardian_id, 'guardian_name' => $record->guardian?->name,
            'student_name' => $record->student?->full_name, 'amount' => $record->amount,
            'posting_date' => $record->posting_date->toDateString(), 'status' => $record->status->value,
            'status_label' => $record->status->label(), 'bank_fee' => $record->bank_fee, 'net_refund' => $record->net_refund,
            'bank_total' => $record->refund_entry_id === null ? null : $record->amount,
            'age_days' => (int) $record->posting_date->diffInDays($this->settings->todayDate(), false),
        ];
    }

    /** @return Collection<int, Account> */
    public function cashAccounts(): Collection
    {
        return Account::query()->where('type', 'asset')->where('is_cash', true)->where('is_active', true)
            ->where('is_postable', true)->where('is_contra', false)->orderBy('code')->get(['id', 'code', 'name']);
    }
}
