<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\Overpayment;
use App\Services\SchoolSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GuardianDepositReconciliation
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /** @return array{as_of: string, accounts: list<array<string, mixed>>, is_balanced: bool, total_difference: string} */
    public function asOf(CarbonInterface|string $date): array
    {
        $asOf = is_string($date) ? $date : $date->toDateString();
        $ids = Overpayment::query()->distinct()->pluck('liability_account_id')
            ->push($this->settings->guardianDepositAccountId())->filter()->unique()->values();
        $gl = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->whereIn('journal_lines.account_id', $ids)->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.credit) - SUM(journal_lines.debit) as balance')->pluck('balance', 'account_id');
        $subledger = Overpayment::query()
            ->whereHas('receiptEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf))
            ->whereDoesntHave('refundEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf))
            ->whereDoesntHave('cancellationEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf))
            ->groupBy('liability_account_id')->selectRaw('liability_account_id, SUM(amount) as balance')->pluck('balance', 'liability_account_id');
        $rows = [];
        $totalDifference = '0.00';
        foreach (Account::whereIn('id', $ids)->orderBy('code')->get() as $account) {
            $glBalance = Money::toDecimal($gl[$account->id] ?? '0');
            $subBalance = Money::toDecimal($subledger[$account->id] ?? '0');
            $difference = Money::sub($glBalance, $subBalance);
            $totalDifference = Money::add($totalDifference, Money::abs($difference));
            $rows[] = ['account_id' => $account->id, 'code' => $account->code, 'name' => $account->name,
                'gl_balance' => $glBalance, 'subledger_balance' => $subBalance, 'difference' => $difference, 'is_balanced' => Money::isZero($difference)];
        }

        return ['as_of' => $asOf, 'accounts' => $rows, 'is_balanced' => Money::isZero($totalDifference), 'total_difference' => $totalDifference];
    }
}
