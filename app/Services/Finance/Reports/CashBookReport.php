<?php

namespace App\Services\Finance\Reports;

use App\Models\Account;
use App\Models\JournalLine;
use App\Support\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashBookReport
{
    /**
     * Generate Cash/Bank Book (Buku Kas & Bank) report data for one cash account.
     *
     * @return array{
     *     account: array<string, mixed>|null,
     *     cash_accounts: array<int, array<string, mixed>>,
     *     opening_balance: string,
     *     period_debits: string,
     *     period_credits: string,
     *     closing_balance: string,
     *     lines: list<array<string, mixed>>,
     *     from: string|null,
     *     to: string|null
     * }
     */
    public function generate(?int $accountId = null, ?string $from = null, ?string $to = null): array
    {
        $cashAccounts = Account::query()
            ->where('is_cash', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        /** @var Account|null $account */
        $account = $accountId
            ? $cashAccounts->firstWhere('id', $accountId)
            : $cashAccounts->first();

        if (! $account && $accountId) {
            $account = Account::where('id', $accountId)->where('is_cash', true)->first();
        }

        if (! $account) {
            return [
                'account' => null,
                'cash_accounts' => [],
                'opening_balance' => '0.00',
                'period_debits' => '0.00',
                'period_credits' => '0.00',
                'closing_balance' => '0.00',
                'lines' => [],
                'from' => $from,
                'to' => $to,
            ];
        }

        // Calculate opening balance prior to $from
        $priorDebits = '0.00';
        $priorCredits = '0.00';

        if ($from) {
            $priorLines = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($from) {
                    $q->where('status', 'posted')
                        ->where('entry_date', '<', $from);
                })
                ->get(['debit', 'credit']);

            foreach ($priorLines as $pLine) {
                $priorDebits = Money::add($priorDebits, $pLine->debit);
                $priorCredits = Money::add($priorCredits, $pLine->credit);
            }
        }

        $openingBalance = Money::sub($priorDebits, $priorCredits);

        // Fetch period lines
        $periodLines = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                $q->where('status', 'posted')
                    ->when($from, fn ($sub) => $sub->where('entry_date', '>=', $from))
                    ->when($to, fn ($sub) => $sub->where('entry_date', '<=', $to));
            })
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_lines.*')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->with(['journalEntry:id,number,entry_date,description,source_type,source_id', 'student:id,full_name'])
            ->get();

        $runningBalance = $openingBalance;
        $periodDebits = '0.00';
        $periodCredits = '0.00';
        $mappedLines = [];

        foreach ($periodLines as $line) {
            $periodDebits = Money::add($periodDebits, $line->debit);
            $periodCredits = Money::add($periodCredits, $line->credit);
            $runningBalance = Money::add($runningBalance, Money::sub($line->debit, $line->credit));

            $mappedLines[] = [
                'id' => $line->id,
                'entry_date' => $line->journalEntry->entry_date->toDateString(),
                'number' => $line->journalEntry->number,
                'journal_entry_id' => $line->journalEntry->id,
                'overpayment_id' => $line->journalEntry->source_type->isOverpayment() ? $line->journalEntry->source_id : null,
                'description' => $line->journalEntry->description,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => $runningBalance,
                'student_name' => $line->student?->full_name,
                'memo' => $line->memo,
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ],
            'cash_accounts' => array_values($cashAccounts->map(fn (Account $acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'label' => "{$acc->code} - {$acc->name}",
            ])->all()),
            'opening_balance' => $openingBalance,
            'period_debits' => $periodDebits,
            'period_credits' => $periodCredits,
            'closing_balance' => $runningBalance,
            'lines' => $mappedLines,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(?int $accountId = null, ?string $from = null, ?string $to = null): StreamedResponse
    {
        $data = $this->generate($accountId, $from, $to);
        $accountCode = $data['account']['code'] ?? 'kas';
        $filename = "buku-kas-{$accountCode}-".($from ?? 'awal').'-sd-'.($to ?? 'akhir').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Akun Kas/Bank',
                ($data['account']['code'] ?? '').' - '.($data['account']['name'] ?? ''),
            ]);
            fputcsv($out, [
                'Periode',
                ($data['from'] ?? 'Awal').' s.d. '.($data['to'] ?? 'Akhir'),
            ]);
            fputcsv($out, []);

            fputcsv($out, [
                'Tanggal',
                'Nomor Jurnal',
                'Keterangan',
                'Penerimaan (Debit)',
                'Pengeluaran (Kredit)',
                'Saldo Kas',
                'Siswa',
                'Memo',
            ]);

            // Opening balance row
            fputcsv($out, [
                $data['from'] ?? '-',
                '',
                'SALDO AWAL',
                '',
                '',
                $data['opening_balance'],
                '',
                '',
            ]);

            foreach ($data['lines'] as $line) {
                fputcsv($out, [
                    $line['entry_date'],
                    $line['number'],
                    $line['description'],
                    $line['debit'],
                    $line['credit'],
                    $line['running_balance'],
                    $line['student_name'] ?? '',
                    $line['memo'] ?? '',
                ]);
            }

            // Closing balance row
            fputcsv($out, [
                $data['to'] ?? '-',
                '',
                'SALDO AKHIR',
                $data['period_debits'],
                $data['period_credits'],
                $data['closing_balance'],
                '',
                '',
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
