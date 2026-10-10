<?php

namespace App\Http\Controllers\RfidCards;

use App\Http\Controllers\Controller;
use App\Http\Requests\RfidCards\StoreRfidCardRequest;
use App\Http\Requests\RfidCards\UpdateRfidCardRequest;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Student;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RfidCardController extends Controller
{
    /**
     * Display a listing of the RFID cards.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $cards = RfidCard::query()
            ->with(['student:id,full_name', 'employee:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('rfid_number', 'like', "%{$search}%")
                        ->orWhereHas('student', function (Builder $query) use ($search) {
                            $query->where('full_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('employee', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('rfid-cards/index', [
            'cards' => $cards,
            'students' => Inertia::optional(fn () => $this->studentOptions()),
            'employees' => Inertia::optional(fn () => $this->employeeOptions()),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created RFID card.
     */
    public function store(StoreRfidCardRequest $request): RedirectResponse
    {
        RfidCard::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card created.']);

        return to_route('rfid-cards.index');
    }

    /**
     * Update the specified RFID card.
     */
    public function update(UpdateRfidCardRequest $request, RfidCard $rfidCard): RedirectResponse
    {
        $rfidCard->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card updated.']);

        return to_route('rfid-cards.index');
    }

    /**
     * Detach the card from its student or employee, returning it to the spare pool.
     */
    public function revoke(RfidCard $rfidCard): RedirectResponse
    {
        $rfidCard->update(['student_id' => null, 'employee_id' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card revoked.']);

        return back();
    }

    /**
     * Remove the specified RFID card.
     */
    public function destroy(RfidCard $rfidCard): RedirectResponse
    {
        $rfidCard->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Card deleted.']);

        return to_route('rfid-cards.index');
    }

    /**
     * Student options for the assign select.
     *
     * @return Collection<int, Student>
     */
    private function studentOptions(): Collection
    {
        return Student::query()->orderBy('full_name')->get(['id', 'full_name']);
    }

    /**
     * Employee options for the assign select.
     *
     * @return Collection<int, Employee>
     */
    private function employeeOptions(): Collection
    {
        return Employee::query()->active()->orderBy('name')->get(['id', 'name']);
    }
}
