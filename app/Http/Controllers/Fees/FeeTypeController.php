<?php

namespace App\Http\Controllers\Fees;

use App\Enums\AccountType;
use App\Enums\FeeKind;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SaveFeeTypeRequest;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeeTypeController extends Controller
{
    /**
     * Display the fee catalog.
     */
    public function index(Request $request): Response
    {
        $feeTypes = FeeType::query()
            ->withCount(['bills', 'discounts'])
            ->orderBy('code')
            ->get()
            ->map(fn (FeeType $feeType) => [
                'id' => $feeType->id,
                'name' => $feeType->name,
                'code' => $feeType->code,
                'type' => $feeType->type->value,
                'type_label' => $feeType->type->label(),
                'default_amount' => $feeType->default_amount,
                'academic_year_id' => $feeType->academic_year_id,
                'description' => $feeType->description,
                'receivable_account_id' => $feeType->receivable_account_id,
                'revenue_account_id' => $feeType->revenue_account_id,
                'discount_account_id' => $feeType->discount_account_id,
                'is_active' => $feeType->is_active,
                'is_in_use' => $feeType->bills_count > 0 || $feeType->discounts_count > 0,
            ]);

        return Inertia::render('fees/types', [
            'feeTypes' => $feeTypes,
            'kinds' => array_map(
                fn (FeeKind $kind) => ['value' => $kind->value, 'label' => $kind->label()],
                FeeKind::cases()
            ),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_at')->get(['id', 'name']),
            'receivableAccounts' => $this->accountOptions(AccountType::Asset, false),
            'revenueAccounts' => $this->accountOptions(AccountType::Revenue, false),
            'discountAccounts' => $this->accountOptions(AccountType::Revenue, true),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Store a newly created fee type.
     */
    public function store(SaveFeeTypeRequest $request): RedirectResponse
    {
        FeeType::create($this->attributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jenis biaya berhasil ditambahkan.',
        ]);

        return to_route('fees.types.index');
    }

    /**
     * Update the specified fee type. Existing bills keep their snapshot amounts.
     */
    public function update(SaveFeeTypeRequest $request, FeeType $feeType): RedirectResponse
    {
        $feeType->update($this->attributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jenis biaya berhasil diperbarui.',
        ]);

        return to_route('fees.types.index');
    }

    /**
     * Remove the specified fee type when nothing references it.
     */
    public function destroy(FeeType $feeType): RedirectResponse
    {
        if ($feeType->bills()->exists() || $feeType->discounts()->exists()) {
            abort(422, 'Jenis biaya yang sudah dipakai tagihan atau diskon tidak dapat dihapus. Nonaktifkan sebagai gantinya.');
        }

        $feeType->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jenis biaya berhasil dihapus.',
        ]);

        return to_route('fees.types.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveFeeTypeRequest $request): array
    {
        return [...$request->validated(), 'is_active' => $request->boolean('is_active')];
    }

    /**
     * @return Collection<int, Account>
     */
    private function accountOptions(AccountType $type, bool $isContra): Collection
    {
        return Account::query()
            ->where('type', $type)
            ->where('is_contra', $isContra)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
