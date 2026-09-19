<?php

namespace App\Http\Controllers\Calendar;

use App\Enums\NonSchoolDaySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreNonSchoolDayRequest;
use App\Models\NonSchoolDay;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD over the shared no-school calendar (spec 03 §Requirements 7).
 * No editing: the weekly sync owns synced-row content, so admins create
 * manual dates or delete rows — never rewrite a synced one.
 */
class NonSchoolDayController extends Controller
{
    public function index(): Response
    {
        $days = NonSchoolDay::query()
            ->orderBy('date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('non-school-days/index', [
            'days' => $days,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('non-school-days/create');
    }

    public function store(StoreNonSchoolDayRequest $request): RedirectResponse
    {
        NonSchoolDay::create([
            ...$request->validated(),
            'source' => NonSchoolDaySource::Manual,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Non-school day added.']);

        return to_route('non-school-days.index');
    }

    public function destroy(NonSchoolDay $nonSchoolDay): RedirectResponse
    {
        $nonSchoolDay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Non-school day removed.']);

        return to_route('non-school-days.index');
    }
}
