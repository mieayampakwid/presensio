<?php

namespace App\Http\Controllers\Parent;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Parent\StoreTransferRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Services\Finance\FeePortal;
use App\Services\Finance\PaymentService;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FeeController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'payment-proofs';

    /**
     * Guardian's fee portal: bills, payments and the transfer upload for linked children.
     */
    public function index(Request $request, FeePortal $portal, SchoolSettings $settings): Response
    {
        $studentIds = $this->linkedStudentIds($request->user());
        $school = $settings->row();
        $allowCancel = $request->user()->hasRole(UserRole::Parent) && $request->user()->guardian !== null;

        return Inertia::render('parent/fees', [
            'portal' => fn () => $portal->forStudents($studentIds, $allowCancel),
            'pendingTransfers' => fn () => $portal->pendingTransfers($studentIds, $allowCancel, max(1, $request->integer('transfers_page', 1))),
            'students' => $portal->children($studentIds),
            'schoolBank' => [
                'bank_name' => $school->bank_name,
                'bank_account_number' => $school->bank_account_number,
                'bank_account_holder' => $school->bank_account_holder,
            ],
            'canUpload' => true,
            'transferAccountConfigured' => $settings->defaultTransferAccountId() !== null,
        ]);
    }

    /**
     * Upload a bank-transfer proof as a pending payment awaiting verification.
     */
    public function store(StoreTransferRequest $request, PaymentService $payments, SchoolSettings $settings): RedirectResponse
    {
        $user = $request->user();
        $allocations = [];

        foreach ($request->validated('allocations') as $row) {
            $allocations[(int) $row['bill_id']] = (string) $row['amount'];
        }

        $file = $request->file('proof');
        $proofHash = hash_file('sha256', $file->getRealPath()) ?: throw new RuntimeException('Could not hash the payment proof.');
        $proofPath = $file->storeAs(
            self::DIRECTORY,
            bin2hex(random_bytes(20)).'.'.strtolower((string) $file->getClientOriginalExtension()),
            self::DISK,
        );

        if ($proofPath === false) {
            throw new RuntimeException('Could not persist the payment proof.');
        }

        try {
            $payments->submitTransfer(
                (string) $request->validated('amount'),
                $allocations,
                $this->linkedStudentIds($user),
                $settings->defaultTransferAccountId(),
                $request->validated('payment_date'),
                $user,
                $request->validated('origin_bank'),
                $proofPath,
                $proofHash,
                $request->validated('notes'),
            );
        } catch (PaymentOperationException $exception) {
            Storage::disk(self::DISK)->delete($proofPath);

            throw ValidationException::withMessages(['allocations' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bukti transfer terkirim, menunggu verifikasi.']);

        return to_route('parent.fees.index');
    }

    public function cancel(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $payments->cancelByGuardian($payment, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer berhasil dibatalkan.']);

        return to_route('parent.fees.index', [
            'transfers_page' => max(1, $request->integer('transfers_page', 1)),
        ]);
    }

    /**
     * Students linked to the signed-in guardian; an admin without a guardian profile sees an empty portal.
     *
     * @return list<int>
     */
    private function linkedStudentIds(?User $user): array
    {
        abort_if($user === null, 401);

        $guardian = $user->guardian;
        abort_if($guardian === null && ! $user->hasRole(UserRole::Admin), 403);

        return array_values($guardian?->students()->pluck('students.id')->map(fn ($id) => (int) $id)->all() ?? []);
    }
}
