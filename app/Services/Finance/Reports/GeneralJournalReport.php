<?php

namespace App\Services\Finance\Reports;

use App\Models\JournalEntry;
use App\Support\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralJournalReport
{
    /**
     * Generate General Journal (Jurnal Umum) report data.
     *
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     total_debit: string,
     *     total_credit: string,
     *     from: string|null,
     *     to: string|null
     * }
     */
    public function generate(?string $from = null, ?string $to = null): array
    {
        $query = JournalEntry::query()
            ->where('status', 'posted')
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->with(['lines.account', 'lines.student', 'author:id,username'])
            ->orderBy('entry_date')
            ->orderBy('id');

        $entries = $query->get();

        $totalDebit = '0.00';
        $totalCredit = '0.00';

        $mappedEntries = [];
        foreach ($entries as $entry) {
            $mappedLines = [];
            foreach ($entry->lines as $line) {
                $totalDebit = Money::add($totalDebit, $line->debit);
                $totalCredit = Money::add($totalCredit, $line->credit);

                $mappedLines[] = [
                    'id' => $line->id,
                    'account_code' => $line->account->code,
                    'account_name' => $line->account->name,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'student_name' => $line->student?->name,
                    'memo' => $line->memo,
                ];
            }

            $mappedEntries[] = [
                'id' => $entry->id,
                'number' => $entry->number,
                'entry_date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'source_type' => $entry->source_type->value,
                'source_type_label' => $entry->source_type->label(),
                'author' => $entry->author?->username,
                'total_debit' => $entry->totalDebit(),
                'total_credit' => $entry->totalCredit(),
                'lines' => $mappedLines,
            ];
        }

        return [
            'entries' => $mappedEntries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(?string $from = null, ?string $to = null): StreamedResponse
    {
        $data = $this->generate($from, $to);
        $filename = 'jurnal-umum-'.($from ?? 'awal').'-sd-'.($to ?? 'akhir').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($out, [
                'Tanggal',
                'Nomor Jurnal',
                'Deskripsi',
                'Kode Akun',
                'Nama Akun',
                'Debit',
                'Kredit',
                'Siswa',
                'Memo',
            ]);

            foreach ($data['entries'] as $entry) {
                foreach ($entry['lines'] as $line) {
                    fputcsv($out, [
                        $entry['entry_date'],
                        $entry['number'],
                        $entry['description'],
                        $line['account_code'],
                        $line['account_name'],
                        $line['debit'],
                        $line['credit'],
                        $line['student_name'] ?? '',
                        $line['memo'] ?? '',
                    ]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['', '', 'TOTAL', '', '', $data['total_debit'], $data['total_credit']]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
