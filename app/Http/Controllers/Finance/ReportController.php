<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Finance\Reports\CashBookReport;
use App\Services\Finance\Reports\GeneralJournalReport;
use App\Services\Finance\Reports\GeneralLedgerReport;
use App\Services\Finance\Reports\TrialBalanceReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display General Journal (Jurnal Umum) report or download CSV.
     */
    public function generalJournal(Request $request, GeneralJournalReport $report): Response|StreamedResponse
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        if ($request->query('format') === 'csv') {
            return $report->toCsv($from, $to);
        }

        return Inertia::render('finance/reports/general-journal', [
            'report' => $report->generate($from, $to),
        ]);
    }

    /**
     * Display General Ledger (Buku Besar) report or download CSV.
     */
    public function generalLedger(Request $request, GeneralLedgerReport $report): Response|StreamedResponse
    {
        $accountId = $request->integer('account_id') ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        if ($request->query('format') === 'csv') {
            return $report->toCsv($accountId, $from, $to);
        }

        $accountOptions = Account::query()
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'label' => "{$acc->code} - {$acc->name}",
            ]);

        return Inertia::render('finance/reports/general-ledger', [
            'report' => $report->generate($accountId, $from, $to),
            'account_options' => $accountOptions,
        ]);
    }

    /**
     * Display Trial Balance (Neraca Saldo) report or download CSV.
     */
    public function trialBalance(Request $request, TrialBalanceReport $report): Response|StreamedResponse
    {
        $asOf = $request->string('as_of')->toString() ?: null;

        if ($request->query('format') === 'csv') {
            return $report->toCsv($asOf);
        }

        return Inertia::render('finance/reports/trial-balance', [
            'report' => $report->generate($asOf),
        ]);
    }

    /**
     * Display Cash/Bank Book (Buku Kas & Bank) report or download CSV.
     */
    public function cashBook(Request $request, CashBookReport $report): Response|StreamedResponse
    {
        $accountId = $request->integer('account_id') ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        if ($request->query('format') === 'csv') {
            return $report->toCsv($accountId, $from, $to);
        }

        return Inertia::render('finance/reports/cash-book', [
            'report' => $report->generate($accountId, $from, $to),
        ]);
    }
}
