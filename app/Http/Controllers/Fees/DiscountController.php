<?php

namespace App\Http\Controllers\Fees;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SaveDiscountRequest;
use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentFeeDiscount;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    /**
     * Display student fee discounts and scholarships.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $discounts = StudentFeeDiscount::query()
            ->with(['student:id,full_name,student_number', 'feeType:id,name,code', 'academicYear:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->whereHas('student', function (Builder $query) use ($search) {
                    $query->whereLike('full_name', "%{$search}%")
                        ->orWhereLike('student_number', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (StudentFeeDiscount $discount) => [
                'id' => $discount->id,
                'student_id' => $discount->student_id,
                'student_name' => $discount->student->full_name,
                'student_number' => $discount->student->student_number,
                'fee_type_id' => $discount->fee_type_id,
                'fee_type_name' => $discount->feeType->name,
                'academic_year_id' => $discount->academic_year_id,
                'academic_year_name' => $discount->academicYear->name,
                'percent' => $discount->percent,
                'fixed_amount' => $discount->fixed_amount,
                'reason' => $discount->reason,
            ]);

        return Inertia::render('fees/discounts', [
            'discounts' => $discounts,
            'filters' => ['search' => $search],
            'feeTypes' => FeeType::query()->orderBy('name')->get(['id', 'name', 'code']),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_at')->get(['id', 'name']),
            // Full student list in a <select>; upgrade to a server-side search chooser once rosters outgrow it.
            'students' => Student::query()->orderBy('full_name')->get(['id', 'full_name', 'student_number']),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Store a newly created discount. Applies to bills generated afterwards only.
     */
    public function store(SaveDiscountRequest $request): RedirectResponse
    {
        StudentFeeDiscount::create($this->attributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Diskon berhasil ditambahkan.',
        ]);

        return to_route('fees.discounts.index');
    }

    /**
     * Update the specified discount. Existing bills are not touched.
     */
    public function update(SaveDiscountRequest $request, StudentFeeDiscount $discount): RedirectResponse
    {
        $discount->update($this->attributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Diskon berhasil diperbarui.',
        ]);

        return to_route('fees.discounts.index');
    }

    /**
     * Remove the specified discount.
     */
    public function destroy(StudentFeeDiscount $discount): RedirectResponse
    {
        $discount->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Diskon berhasil dihapus.',
        ]);

        return to_route('fees.discounts.index');
    }

    /**
     * Unsent amount fields become null so an edit can switch between percent and fixed.
     *
     * @return array<string, mixed>
     */
    private function attributes(SaveDiscountRequest $request): array
    {
        return [...['percent' => null, 'fixed_amount' => null], ...$request->validated()];
    }
}
