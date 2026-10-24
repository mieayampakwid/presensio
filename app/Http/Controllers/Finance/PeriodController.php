<?php

namespace App\Http\Controllers\Finance;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Services\Finance\PeriodService;
use App\Services\Finance\YearEndCloser;
use App\Services\SchoolSettings;
use App\Support\Finance\RejectionResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PeriodController extends Controller
{
    /**
     * Display listing of monthly accounting periods and their status.
     */
    public function index(Request $request, PeriodService $periodService, SchoolSettings $settings, YearEndCloser $yearEndCloser): Response
    {
        $currentMonth = Carbon::parse($settings->todayDate())->startOfMonth();

        // Ensure current month and past 6 months exist in DB for visibility
        for ($i = 0; $i < 12; $i++) {
            $key = $currentMonth->copy()->subMonths($i)->format('Y-m');
            $periodService->ensure($key);
        }

        $periods = AccountingPeriod::query()
            ->with(['closedBy:id,username'])
            ->orderByDesc('period')
            ->limit(24)
            ->get()
            ->map(fn (AccountingPeriod $p) => [
                'period' => $p->period,
                'status' => $p->status,
                'is_open' => $p->isOpen(),
                'closed_at' => $p->closed_at?->toIso8601String(),
                'closed_by' => $p->closedBy?->username,
            ]);

        $canManage = $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance);

        return Inertia::render('finance/periods', [
            'periods' => $periods,
            'canManage' => $canManage,
            'closableYears' => $canManage ? $yearEndCloser->closableYears() : [],
        ]);
    }

    /**
     * Close an open accounting period.
     */
    public function close(Request $request, string $period, PeriodService $periodService): RedirectResponse
    {
        try {
            $periodService->close($period, (int) auth()->id());
        } catch (RuntimeException $e) {
            return RejectionResponse::make($request, $e->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Periode {$period} berhasil ditutup.",
        ]);

        return to_route('finance.periods.index');
    }

    /**
     * Reopen a closed accounting period.
     */
    public function reopen(Request $request, string $period, PeriodService $periodService): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $periodService->reopen($period, $validated['reason'], (int) auth()->id());
        } catch (RuntimeException $e) {
            return RejectionResponse::make($request, $e->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Periode {$period} berhasil dibuka kembali.",
        ]);

        return to_route('finance.periods.index');
    }

    /**
     * Post the year-end closing entry and lock the fiscal year's periods.
     */
    public function closeYear(Request $request, YearEndCloser $yearEndCloser): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_start' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $yearEndCloser->close($validated['fiscal_year_start'], (int) auth()->id());
        } catch (RuntimeException $e) {
            return RejectionResponse::make($request, $e->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tahun buku berhasil ditutup dan seluruh periodenya dikunci.',
        ]);

        return to_route('finance.periods.index');
    }
}
