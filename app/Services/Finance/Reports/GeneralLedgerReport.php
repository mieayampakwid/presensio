<?php

namespace App\Services\Finance\Reports;

use App\Models\Account;
use App\Models\JournalLine;
use App\Support\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerReport
{
    /**
     * Generate General Ledger (Buku Besar) report data.
     *
     * @return array{
     *     accounts: list<array<string, mixed>>,
     *     from: string|null,
     *     to: string|null,
     *     account_id: int|null
     * }
     */
    public function generate(?int $accountId = null, ?string $from = null, ?string $to = null): array
    {
        $accountsQuery = Account::query()
            ->where('is_postable', true)
            ->when($accountId, fn ($q) => $q->where('id', $accountId))
            ->orderBy('code');

        $accounts = $accountsQuery->get();
        $ledgerData = [];

        foreach ($accounts as $account) {
            $normalBalance = $account->normalBalance();

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

            $openingBalance = $normalBalance === 'debit'
                ? Money::sub($priorDebits, $priorCredits)
                : Money::sub($priorCredits, $priorDebits);

            // Fetch movements during the period
            $linesQuery = JournalLine::query()
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
                ->with(['journalEntry:id,number,entry_date,description', 'student:id,name']);

            $periodLines = $linesQuery->get();

            $runningBalance = $openingBalance;
            $periodDebits = '0.00';
            $periodCredits = '0.00';
            $mappedLines = [];

            foreach ($periodLines as $line) {
                $periodDebits = Money::add($periodDebits, $line->debit);
                $periodCredits = Money::add($periodCredits, $line->credit);

                if ($normalBalance === 'debit') {
                    $runningBalance = Money::add($runningBalance, Money::sub($line->debit, $line->credit));
                } else {
                    $runningBalance = Money::add($runningBalance, Money::sub($line->credit, $line->debit));
                }

                $mappedLines[] = [
                    'id' => $line->id,
                    'entry_date' => $line->journalEntry->entry_date->toDateString(),
                    'number' => $line->journalEntry->number,
                    'description' => $line->journalEntry->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'running_balance' => $runningBalance,
                    'student_name' => $line->student?->full_name,
                    'memo' => $line->memo,
                ];
            }

            $ledgerData[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'normal_balance' => $normalBalance,
                'is_contra' => $account->is_contra,
                'is_cash' => $account->is_cash,
                'opening_balance' => $openingBalance,
                'period_debits' => $periodDebits,
                'period_credits' => $periodCredits,
                'closing_balance' => $runningBalance,
                'lines' => $mappedLines,
            ];
        }

        return [
            'accounts' => $ledgerData,
            'from' => $from,
            'to' => $to,
            'account_id' => $accountId,
        ];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(?int $accountId = null, ?string $from = null, ?string $to = null): StreamedResponse
    {
        $data = $this->generate($accountId, $from, $to);
        $filename = 'buku-besar-'.($from ?? 'awal').'-sd-'.($to ?? 'akhir').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Kode Akun',
                'Nama Akun',
                'Tanggal',
                'Nomor Jurnal',
                'Keterangan',
                'Debit',
                'Kredit',
                'Saldo Berjalan',
                'Siswa',
                'Memo',
            ]);

            foreach ($data['accounts'] as $acc) {
                fputcsv($out, [
                    $acc['code'],
                    $acc['name'],
                    $data['from'] ?? '-',
                    '',
                    'SALDO AWAL',
                    '',
                    '',
                    $acc['opening_balance'],
                    '',
                    '',
                ]);

                foreach ($acc['lines'] as $line) {
                    fputcsv($out, [
                        $acc['code'],
                        $acc['name'],
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

                fputcsv($out, [
                    $acc['code'],
                    $acc['name'],
                    $data['to'] ?? '-',
                    '',
                    'SALDO AKHIR',
                    $acc['period_debits'],
                    $acc['period_credits'],
                    $acc['closing_balance'],
                    '',
                    '',
                ]);

                fputcsv($out, []);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
