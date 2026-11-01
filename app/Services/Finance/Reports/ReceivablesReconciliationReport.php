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

            fputcsv($out, [
                __('toasts.csv.common.account'),
                __('toasts.csv.common.account_name'),
                __('toasts.csv.receivables_reconciliation.gl_balance'),
                __('toasts.csv.receivables_reconciliation.subledger_balance'),
                __('toasts.csv.receivables_reconciliation.difference'),
            ]);

            foreach ([...$data['receivables'], $data['prepaid']] as $row) {
                fputcsv($out, [$row['code'], $row['name'], $row['gl_balance'], $row['subledger_balance'], $row['difference']]);
            }

            fputcsv($out, []);
            fputcsv($out, [
                __('toasts.csv.receivables_reconciliation.bill_id'),
                __('toasts.csv.common.student'),
                __('toasts.csv.receivables_aging.bill'),
                __('toasts.csv.common.account'),
                __('toasts.csv.receivables_reconciliation.kind'),
                __('toasts.csv.receivables_reconciliation.expected'),
                __('toasts.csv.receivables_reconciliation.actual'),
            ]);

            foreach ($data['mismatches'] as $row) {
                fputcsv($out, [$row['bill_id'], $row['student_name'], $row['title'], $row['account_code'], $row['kind'], $row['expected'], $row['actual']]);
            }

            fputcsv($out, []);
            fputcsv($out, [
                __('toasts.csv.receivables_reconciliation.guardian_deposits'),
                __('toasts.csv.common.account_name'),
                __('toasts.csv.receivables_reconciliation.gl_balance'),
                __('toasts.csv.receivables_reconciliation.subledger_balance'),
                __('toasts.csv.receivables_reconciliation.difference'),
            ]);
            foreach ($data['guardian_deposits']['accounts'] as $row) {
                fputcsv($out, [$row['code'], $row['name'], $row['gl_balance'], $row['subledger_balance'], $row['difference']]);
            }

            fclose($out);
        }, 'rekonsiliasi-piutang-per-'.$data['as_of'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
