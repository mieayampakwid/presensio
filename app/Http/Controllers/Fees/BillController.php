<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\AdjustBillDiscountRequest;
use App\Http\Requests\Fees\BillReasonRequest;
use App\Models\Bill;
use App\Services\Finance\BillService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BillController extends Controller
{
    /**
     * Change a bill's discount; a recognized bill gets an adjusting entry.
     */
    public function discount(AdjustBillDiscountRequest $request, Bill $bill, BillService $billService): RedirectResponse
    {
        $billService->adjustDiscount($bill, $request->string('discount_amount')->toString(), $request->string('reason')->toString());

        return $this->done('Diskon tagihan berhasil diubah.');
    }

    /**
     * Cancel a bill that has no verified payments.
     */
    public function cancel(BillReasonRequest $request, Bill $bill, BillService $billService): RedirectResponse
    {
        $billService->cancel($bill, $request->string('reason')->toString());

        return $this->done('Tagihan berhasil dibatalkan.');
    }

    /**
     * Write off the outstanding receivable of a recognized bill.
     */
    public function writeOff(BillReasonRequest $request, Bill $bill, BillService $billService): RedirectResponse
    {
        $billService->writeOff($bill, $request->string('reason')->toString());

        return $this->done('Piutang berhasil dihapusbukukan.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
