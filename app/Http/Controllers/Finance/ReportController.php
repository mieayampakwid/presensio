<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Finance\Reports\CashBookReport;
use App\Services\Finance\Reports\CollectionsByClassReport;
use App\Services\Finance\Reports\GeneralJournalReport;
use App\Services\Finance\Reports\GeneralLedgerReport;
use App\Services\Finance\Reports\ReceivablesAgingReport;
use App\Services\Finance\Reports\ReceivablesReconciliationReport;
use App\Services\Finance\Reports\StudentReceivableCardReport;
use App\Services\Finance\Reports\TrialBalanceReport;
use App\Services\SchoolSettings;
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

    /**
     * Display Receivables Reconciliation (Rekonsiliasi Piutang) report or download CSV.
     */
    public function receivablesReconciliation(Request $request, ReceivablesReconciliationReport $report, SchoolSettings $settings): Response|StreamedResponse
    {
        $validated = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);
        $asOf = $validated['as_of'] ?? $settings->todayDate();

        if ($request->query('format') === 'csv') {
            return $report->toCsv($asOf);
        }

        return Inertia::render('finance/reports/receivables-reconciliation', [
            'report' => $report->generate($asOf),
        ]);
    }

    /**
     * Display the Student Receivable Card (Kartu Piutang Siswa) or download its CSV.
     */
    public function studentReceivableCard(Request $request, StudentReceivableCardReport $report, SchoolSettings $settings): Response|StreamedResponse
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $asOf = $validated['as_of'] ?? $settings->todayDate();
        $studentId = isset($validated['student_id']) ? (int) $validated['student_id'] : null;

        if ($request->query('format') === 'csv' && $studentId !== null) {
            return $report->toCsv($studentId, $asOf);
        }

        return Inertia::render('finance/reports/student-receivable-card', [
            'report' => $report->generate($studentId, $asOf, $validated['q'] ?? ''),
        ]);
    }

    /**
     * Display Receivables Aging (Umur Piutang) or download CSV.
     */
    public function receivablesAging(Request $request, ReceivablesAgingReport $report, SchoolSettings $settings): Response|StreamedResponse
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);
        $asOf = $validated['as_of'] ?? $settings->todayDate();
        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;

        if ($request->query('format') === 'csv') {
            return $report->toCsv($asOf, $classId);
        }

        return Inertia::render('finance/reports/receivables-aging', [
            'report' => $report->generate($asOf, $classId),
        ]);
    }

    /**
     * Display Collections by Class (Penerimaan per Kelas) or download CSV.
     */
    public function collectionsByClass(Request $request, CollectionsByClassReport $report): Response|StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $range = [...$report->defaultRange(), ...array_filter($validated)];

        if ($request->query('format') === 'csv') {
            return $report->toCsv($range['from'], $range['to']);
        }

        return Inertia::render('finance/reports/collections-by-class', [
            'report' => $report->generate($range['from'], $range['to']),
        ]);
    }
}
