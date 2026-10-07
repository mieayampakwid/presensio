<?php

namespace App\Services\Finance\Reports;

use App\Models\Account;
use App\Models\JournalLine;
use App\Support\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalanceReport
{
    /**
     * Generate Trial Balance (Neraca Saldo) report data.
     *
     * @return array{
     *     accounts: list<array<string, mixed>>,
     *     total_debit: string,
     *     total_credit: string,
     *     difference: string,
     *     is_balanced: bool,
     *     as_of: string|null
     * }
     */
    public function generate(?string $asOf = null): array
    {
        $accounts = Account::query()
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($accounts as $account) {
            $lines = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($asOf) {
                    $q->where('status', 'posted')
                        ->when($asOf, fn ($sub) => $sub->where('entry_date', '<=', $asOf));
                })
                ->get(['debit', 'credit']);

            $sumDebit = '0.00';
            $sumCredit = '0.00';

            foreach ($lines as $line) {
                $sumDebit = Money::add($sumDebit, $line->debit);
                $sumCredit = Money::add($sumCredit, $line->credit);
            }

            // Net position: debits - credits
            $net = Money::sub($sumDebit, $sumCredit);

            if (Money::gt($net, '0.00')) {
                $debitBalance = $net;
                $creditBalance = '0.00';
            } elseif (Money::lt($net, '0.00')) {
                $debitBalance = '0.00';
                $creditBalance = Money::abs($net);
            } else {
                $debitBalance = '0.00';
                $creditBalance = '0.00';
            }

            $totalDebit = Money::add($totalDebit, $debitBalance);
            $totalCredit = Money::add($totalCredit, $creditBalance);

            $rows[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'normal_balance' => $account->normalBalance(),
                'sum_debit' => $sumDebit,
                'sum_credit' => $sumCredit,
                'debit_balance' => $debitBalance,
                'credit_balance' => $creditBalance,
            ];
        }

        $diff = Money::abs(Money::sub($totalDebit, $totalCredit));
        $isBalanced = Money::cmp($totalDebit, $totalCredit) === 0;

        return [
            'accounts' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $diff,
            'is_balanced' => $isBalanced,
            'as_of' => $asOf,
        ];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(?string $asOf = null): StreamedResponse
    {
        $data = $this->generate($asOf);
        $filename = 'neraca-saldo-per-'.($asOf ?? 'hari-ini').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Kode Akun',
                'Nama Akun',
                'Klasifikasi',
                'Saldo Normal',
                'Saldo Debit',
                'Saldo Kredit',
            ]);

            foreach ($data['accounts'] as $acc) {
                fputcsv($out, [
                    $acc['code'],
                    $acc['name'],
                    $acc['type'],
                    $acc['normal_balance'],
                    $acc['debit_balance'],
                    $acc['credit_balance'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, [
                '',
                'TOTAL',
                '',
                '',
                $data['total_debit'],
                $data['total_credit'],
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
