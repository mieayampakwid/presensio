<?php

namespace App\Http\Controllers\Fees;

use App\Enums\BillStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\StoreCashPaymentRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Student;
use App\Services\Finance\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashierController extends Controller
{
    private const SEARCH_LIMIT = 10;

    private const KAS_TUNAI_CODE = '1-1100';

    /**
     * Cashier screen: student search (`q`), selected students (`students[]`) with their open
     * bills, and siblings of the selection that can join the same transaction.
     */
    public function index(Request $request): Response
    {
        $term = trim($request->string('q')->toString());
        $selectedIds = array_values(array_unique(array_map('intval', (array) $request->input('students', []))));

        $cashAccounts = Account::query()
            ->where('is_cash', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('fees/cashier', [
            'term' => $term,
            'results' => mb_strlen($term) >= 2
                ? Student::query()
                    ->where(fn ($query) => $query->where('full_name', 'like', "%{$term}%")->orWhere('student_number', 'like', "%{$term}%"))
                    ->with('currentEnrollment.schoolClass')
                    ->orderBy('full_name')
                    ->limit(self::SEARCH_LIMIT)
                    ->get()
                    ->map(fn (Student $student) => $this->summary($student))
                : [],
            'selectedStudents' => $selectedIds === [] ? [] : Student::query()
                ->whereIn('id', $selectedIds)
                ->with([
                    'currentEnrollment.schoolClass',
                    'bills' => fn ($query) => $query
                        ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
                        ->orderBy('due_date')
                        ->orderBy('id'),
                ])
                ->orderBy('full_name')
                ->get()
                ->map(fn (Student $student) => $this->withOpenBills($student)),
            'siblings' => $selectedIds === [] ? [] : Student::query()
                ->whereNotIn('id', $selectedIds)
                ->whereHas('guardians.students', fn ($query) => $query->whereIn('students.id', $selectedIds))
                ->with('currentEnrollment.schoolClass')
                ->orderBy('full_name')
                ->get()
                ->map(fn (Student $student) => $this->summary($student)),
            'cashAccounts' => $cashAccounts,
            'defaultAccountId' => ($cashAccounts->where('code', self::KAS_TUNAI_CODE)->first() ?? $cashAccounts->first())?->id,
        ]);
    }

    /**
     * Record a cash payment, verified immediately.
     */
    public function store(StoreCashPaymentRequest $request, PaymentService $payments): RedirectResponse
    {
        $allocations = [];

        foreach ($request->validated('allocations') as $row) {
            $allocations[(int) $row['bill_id']] = (string) $row['amount'];
        }

        $payment = $payments->createCash(
            $allocations,
            array_values(array_map('intval', $request->validated('student_ids'))),
            (int) $request->validated('deposit_account_id'),
            $request->user(),
            $request->validated('notes'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pembayaran tercatat. Kuitansi {$payment->receipt_number}."]);
        Inertia::flash('payment', ['id' => $payment->id, 'receipt_number' => $payment->receipt_number]);

        return to_route('fees.cashier.index');
    }

    /**
     * @return array{id: int, full_name: string, student_number: string|null, class_name: string|null, bills: array<int, array{id: int, title: string, due_date: string, remaining: string}>}
     */
    private function withOpenBills(Student $student): array
    {
        return [
            ...$this->summary($student),
            'bills' => $student->bills->map(fn (Bill $bill) => [
                'id' => $bill->id,
                'title' => $bill->title,
                'due_date' => $bill->due_date->toDateString(),
                'remaining' => $bill->remainingAmount(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{id: int, full_name: string, student_number: string|null, class_name: string|null}
     */
    private function summary(Student $student): array
    {
        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'student_number' => $student->student_number,
            'class_name' => $student->school_class?->name,
        ];
    }
}
