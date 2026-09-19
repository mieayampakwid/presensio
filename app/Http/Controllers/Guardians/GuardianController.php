<?php

namespace App\Http\Controllers\Guardians;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\StoreGuardianRequest;
use App\Http\Requests\Guardians\UpdateGuardianRequest;
use App\Models\Guardian;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    /**
     * Display a listing of the guardians.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $guardians = Guardian::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('guardians/index', [
            'guardians' => $guardians,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new guardian.
     */
    public function create(): Response
    {
        return Inertia::render('guardians/create');
    }

    /**
     * Store a newly created guardian.
     */
    public function store(StoreGuardianRequest $request): RedirectResponse
    {
        Guardian::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardian created.']);

        return to_route('guardians.index');
    }

    /**
     * Show the form for editing the specified guardian.
     */
    public function edit(Guardian $guardian): Response
    {
        return Inertia::render('guardians/edit', [
            'guardian' => $guardian,
            // Relations stay admin-managed; shown read-only on this side.
            'students' => $guardian->students()
                ->orderBy('full_name')
                ->get(['students.id', 'full_name']),
        ]);
    }

    /**
     * Update the specified guardian.
     */
    public function update(UpdateGuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $guardian->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardian updated.']);

        return to_route('guardians.edit', $guardian);
    }

    /**
     * Remove the specified guardian.
     */
    public function destroy(Guardian $guardian): RedirectResponse
    {
        if ($guardian->user_id !== null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete: linked to a user account. Unlink it first.',
            ]);

            return back();
        }

        // The pivot has no DB-level foreign keys, so orphan rows must be
        // removed explicitly.
        DB::transaction(function () use ($guardian): void {
            $guardian->students()->detach();
            $guardian->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardian deleted.']);

        return to_route('guardians.index');
    }
}
