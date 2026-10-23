<?php

namespace App\Http\Controllers\Fees;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\CancelOverpaymentRequest;
use App\Http\Requests\Fees\RefundOverpaymentRequest;
use App\Http\Requests\Fees\StoreOverpaymentRequest;
use App\Models\Overpayment;
use App\Services\Finance\OverpaymentReadModel;
use App\Services\Finance\OverpaymentService;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OverpaymentController extends Controller
{
    public function index(Request $request, OverpaymentReadModel $reader): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:held,refunded,cancelled'], 'guardian_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return Inertia::render('fees/overpayments', $reader->listing($filters) + [
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
            'canConfigure' => $request->user()->hasRole(UserRole::Admin),
        ]);
    }

    public function show(Request $request, Overpayment $overpayment, OverpaymentReadModel $reader): Response
    {
        return Inertia::render('fees/overpayment', [
            'today' => app(SchoolSettings::class)->todayDate(),
            'overpayment' => $reader->detail($overpayment), 'cashAccounts' => $reader->cashAccounts(),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    public function store(StoreOverpaymentRequest $request, OverpaymentService $service): RedirectResponse
    {
        $file = $request->file('proof');
        $path = $file->store('overpayments', 'local');
        abort_unless(is_string($path), 500, 'Bukti tidak berhasil disimpan.');
        try {
            $record = $service->record([
                'guardian_id' => $request->integer('guardian_id'),
                'student_id' => $request->filled('student_id') ? $request->integer('student_id') : null,
                'related_payment_id' => $request->filled('related_payment_id') ? $request->integer('related_payment_id') : null,
                'amount' => $request->string('amount')->toString(),
                'bank_mutation_date' => $request->string('bank_mutation_date')->toString(),
                'posting_date' => $request->string('posting_date')->toString(),
                'deposit_account_id' => $request->integer('deposit_account_id'),
                'reason' => $request->string('reason')->toString(),
                'note' => $request->string('note')->toString(),
                'proof_path' => $path, 'proof_hash' => $this->proofHash($file),
            ], $request->user());
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kelebihan pembayaran berhasil dicatat.']);

        return to_route('fees.overpayments.show', $record);
    }

    public function refund(RefundOverpaymentRequest $request, Overpayment $overpayment, OverpaymentService $service): RedirectResponse
    {
        $file = $request->file('proof');
        $path = $file?->store('overpayments', 'local');
        abort_if($path === false, 500, 'Bukti tidak berhasil disimpan.');
        try {
            $data = [
                'refund_date' => $request->string('refund_date')->toString(),
                'refund_account_id' => $request->integer('refund_account_id'),
                'bank_fee' => $request->string('bank_fee', '0')->toString(),
                'bank_reference' => $request->string('bank_reference')->toString(),
                'refund_proof_path' => $path, 'refund_proof_hash' => $file ? $this->proofHash($file) : null,
            ];
            foreach (['net_refund', 'bank_total'] as $key) {
                if ($request->has($key)) {
                    $data[$key] = $request->string($key)->toString();
                }
            }
            $service->refund($overpayment, $data, $request->user());
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengembalian berhasil dicatat.']);

        return to_route('fees.overpayments.show', $overpayment);
    }

    public function cancel(CancelOverpaymentRequest $request, Overpayment $overpayment, OverpaymentService $service): RedirectResponse
    {
        $service->cancel($overpayment, $request->validated('date'), $request->validated('reason'), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penerimaan kelebihan pembayaran dibatalkan.']);

        return to_route('fees.overpayments.show', $overpayment);
    }

    private function proofHash(UploadedFile $file): string
    {
        $hash = hash_file('sha256', $file->getRealPath());
        if ($hash === false) {
            throw new RuntimeException('Bukti tidak dapat dibaca. Silakan unggah kembali.');
        }

        return $hash;
    }

    public function proof(Overpayment $overpayment, string $kind): StreamedResponse
    {
        $path = $kind === 'receipt' ? $overpayment->proof_path : $overpayment->refund_proof_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
