<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AccountType;
use App\Enums\NetAssetRestriction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display chart of accounts tree / list.
     */
    public function index(Request $request): Response
    {
        $accounts = Account::query()
            ->with(['parent:id,name,code'])
            ->withCount(['journalLines', 'children'])
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'normal_balance' => $account->normalBalance(),
                'is_contra' => $account->is_contra,
                'restriction' => $account->restriction?->value,
                'restriction_label' => $account->restriction?->label(),
                'parent_id' => $account->parent_id,
                'parent' => $account->parent ? [
                    'id' => $account->parent->id,
                    'code' => $account->parent->code,
                    'name' => $account->parent->name,
                ] : null,
                'is_postable' => $account->is_postable,
                'is_cash' => $account->is_cash,
                'is_active' => $account->is_active,
                'has_posted_lines' => $account->hasPostedLines(),
                'journal_lines_count' => $account->journal_lines_count,
                'children_count' => $account->children_count,
            ]);

        $types = array_map(
            fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'normal_balance' => $type->normalBalance(),
            ],
            AccountType::cases()
        );

        $restrictions = array_map(
            fn (NetAssetRestriction $restriction) => [
                'value' => $restriction->value,
                'label' => $restriction->label(),
            ],
            NetAssetRestriction::cases()
        );

        return Inertia::render('finance/accounts', [
            'accounts' => $accounts,
            'types' => $types,
            'restrictions' => $restrictions,
            'canManage' => $request->user()->hasRole(UserRole::Admin),
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(SaveAccountRequest $request): RedirectResponse
    {
        $data = $request->sanitized();

        $account = Account::create($data);

        if ($account->parent_id) {
            Account::where('id', $account->parent_id)->update(['is_postable' => false]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Akun berhasil ditambahkan.',
        ]);

        return to_route('finance.accounts.index');
    }

    /**
     * Update the specified account.
     */
    public function update(SaveAccountRequest $request, Account $account): RedirectResponse
    {
        $data = $request->sanitized();

        $account->update($data);

        if ($account->parent_id) {
            Account::where('id', $account->parent_id)->update(['is_postable' => false]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Akun berhasil diperbarui.',
        ]);

        return to_route('finance.accounts.index');
    }

    /**
     * Remove the specified account from storage.
     */
    public function destroy(Account $account): RedirectResponse
    {
        if ($account->journalLines()->exists()) {
            abort(422, 'Akun dengan transaksi tidak dapat dihapus. Nonaktifkan akun sebagai gantinya.');
        }

        if ($account->children()->exists()) {
            abort(422, 'Akun dengan sub-akun tidak dapat dihapus.');
        }

        $parentId = $account->parent_id;

        $account->delete();

        if ($parentId && ! Account::where('parent_id', $parentId)->exists()) {
            // Parent no longer has children, can be made postable if desired
            Account::where('id', $parentId)->update(['is_postable' => true]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Akun berhasil dihapus.',
        ]);

        return to_route('finance.accounts.index');
    }
}
