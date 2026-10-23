<?php

namespace App\Services\Finance\Reports;

use App\Services\Finance\GuardianDepositReconciliation;
use App\Services\Finance\ReceivablesReconciliation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesReconciliationReport
{
    public function __construct(
        private readonly ReceivablesReconciliation $reconciliation,
        private readonly GuardianDepositReconciliation $deposits,
    ) {}

    /**
     * Generate the Receivables Reconciliation (Rekonsiliasi Piutang) report data.
     *
     * @return array<string, mixed>
     */
    public function generate(string $asOf): array
    {
        return $this->reconciliation->asOf($asOf) + ['guardian_deposits' => $this->deposits->asOf($asOf)];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(string $asOf): StreamedResponse
    {
        $data = $this->generate($asOf);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Akun', 'Nama Akun', 'Saldo Buku Besar', 'Saldo Subledger', 'Selisih']);

            foreach ([...$data['receivables'], $data['prepaid']] as $row) {
                fputcsv($out, [$row['code'], $row['name'], $row['gl_balance'], $row['subledger_balance'], $row['difference']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['ID Tagihan', 'Siswa', 'Tagihan', 'Akun', 'Jenis', 'Seharusnya', 'Buku Besar']);

            foreach ($data['mismatches'] as $row) {
                fputcsv($out, [$row['bill_id'], $row['student_name'], $row['title'], $row['account_code'], $row['kind'], $row['expected'], $row['actual']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Titipan Wali', 'Nama Akun', 'Saldo Buku Besar', 'Saldo Subledger', 'Selisih']);
            foreach ($data['guardian_deposits']['accounts'] as $row) {
                fputcsv($out, [$row['code'], $row['name'], $row['gl_balance'], $row['subledger_balance'], $row['difference']]);
            }

            fclose($out);
        }, 'rekonsiliasi-piutang-per-'.$data['as_of'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
