<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\VoidPaymentRequest;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PaymentVoidController extends Controller
{
    /**
     * Void a verified payment, reversing its journal entry.
     */
    public function __invoke(VoidPaymentRequest $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        $paymentService->void($payment, $request->string('reason')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran berhasil dibatalkan.']);

        return back();
    }
}
