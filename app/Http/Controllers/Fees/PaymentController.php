<?php

namespace App\Http\Controllers\Fees;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Display payments with the students they cover and a Kuitansi link once verified.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'method' => $request->string('method')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $payments = Payment::query()
            ->with('allocations.bill.student:id,full_name')
            ->when($filters['date_from'] !== '', fn (Builder $query) => $query->whereDate('payment_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn (Builder $query) => $query->whereDate('payment_date', '<=', $filters['date_to']))
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('payment_method', $filters['method']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'payment_date' => $payment->payment_date->toDateString(),
                'students' => $payment->allocations
                    ->map(fn (PaymentAllocation $allocation) => $allocation->bill->student->full_name)
                    ->unique()
                    ->values()
                    ->all(),
                'amount' => $payment->amount,
                'method_label' => $payment->payment_method->label(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'has_receipt' => in_array($payment->status, [PaymentStatus::Verified, PaymentStatus::Void], true),
            ]);

        return Inertia::render('fees/payments', [
            'payments' => $payments,
            'filters' => $filters,
            'methods' => array_map(
                fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                PaymentMethod::cases()
            ),
            'statuses' => array_map(
                fn (PaymentStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                PaymentStatus::cases()
            ),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }
}
