<?php

namespace App\Http\Controllers\Fees;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\RejectTransferRequest;
use App\Http\Requests\Fees\VerifyTransferRequest;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use App\Services\Finance\VerificationQueue;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    private const DISK = 'local';

    /**
     * Pending bank transfers awaiting verification.
     */
    public function index(Request $request, VerificationQueue $queue, SchoolSettings $settings): Response
    {
        return Inertia::render('fees/verifications', [
            'payments' => $queue->pending(),
            'cashAccounts' => $queue->cashAccounts(),
            'defaultAccountId' => $settings->defaultTransferAccountId(),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Verify a pending transfer, optionally replacing its allocations.
     */
    public function verify(VerifyTransferRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $reallocation = null;

        foreach ($request->validated('allocations') ?? [] as $row) {
            $reallocation[(int) $row['bill_id']] = (string) $row['amount'];
        }

        $verified = $payments->verify($payment, $reallocation, (int) $request->validated('deposit_account_id'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pembayaran terverifikasi. Kuitansi {$verified->receipt_number}."]);

        return to_route('fees.verifications.index');
    }

    /**
     * Reject a pending transfer with a reason.
     */
    public function reject(RejectTransferRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $payments->reject($payment, $request->string('reason')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer ditolak.']);

        return to_route('fees.verifications.index');
    }

    /**
     * Stream the uploaded transfer proof inline.
     */
    public function proof(Payment $payment): StreamedResponse
    {
        abort_if($payment->proof_path === null || ! Storage::disk(self::DISK)->exists($payment->proof_path), 404);

        return Storage::disk(self::DISK)->response($payment->proof_path);
    }
}
