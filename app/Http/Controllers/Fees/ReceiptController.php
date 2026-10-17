<?php

namespace App\Http\Controllers\Fees;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\ReceiptPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    /**
     * Inline Kuitansi PDF of a verified or voided payment.
     */
    public function show(Request $request, Payment $payment, ReceiptPdf $receipt): Response
    {
        abort_unless(in_array($payment->status, [PaymentStatus::Verified, PaymentStatus::Void], true), 404);
        abort_unless($this->canView($request->user(), $payment), 403);

        return response($receipt->pdf($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="kuitansi-'.$payment->receipt_number.'.pdf"',
        ]);
    }

    /**
     * Staff and principal see any receipt; a guardian only when every student on it is theirs.
     */
    private function canView(?User $user, Payment $payment): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole(UserRole::Admin, UserRole::Finance, UserRole::Principal)) {
            return true;
        }

        $guardian = $user->hasRole(UserRole::Parent) ? $user->guardian : null;

        if ($guardian === null) {
            return false;
        }

        $studentIds = $payment->allocations()->join('bills', 'bills.id', '=', 'payment_allocations.bill_id')->pluck('bills.student_id')->unique();
        $linked = $guardian->students()->whereIn('students.id', $studentIds)->count();

        return $studentIds->isNotEmpty() && $linked === $studentIds->count();
    }
}
