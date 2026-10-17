<?php

namespace App\Http\Controllers\Fees;

use App\Enums\FeeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\GenerateBillsRequest;
use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Services\Finance\BillGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillGenerationController extends Controller
{
    /**
     * Show the bulk bill generator form.
     */
    public function index(): Response
    {
        $year = AcademicYear::active();

        return Inertia::render('fees/generate', [
            'academicYear' => $year?->only(['id', 'name']),
            'feeTypes' => FeeType::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn (FeeType $feeType) => [
                    'id' => $feeType->id,
                    'name' => $feeType->name,
                    'code' => $feeType->code,
                    'is_recurring' => $feeType->type === FeeKind::MonthlyRecurring,
                    'default_amount' => $feeType->default_amount,
                ]),
            'classes' => $year
                ? SchoolClass::query()->where('academic_year_id', $year->id)->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'grade_level'])
                : [],
        ]);
    }

    /**
     * Dry run: report created / skipped / discounted counts without writing.
     */
    public function preview(GenerateBillsRequest $request, BillGenerator $generator): JsonResponse
    {
        return response()->json($generator->preview($request->validated()));
    }

    /**
     * Create the bills and post recognition for those already due.
     */
    public function store(GenerateBillsRequest $request, BillGenerator $generator): RedirectResponse
    {
        $summary = $generator->commit($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$summary['created']} tagihan dibuat, {$summary['skipped']} dilewati, {$summary['discounted']} berdiskon.",
        ]);

        return to_route('fees.generate.index');
    }
}
