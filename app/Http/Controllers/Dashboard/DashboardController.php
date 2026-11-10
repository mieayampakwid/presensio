<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role landing page (spec 08): a read-only summary of today per role.
 * Numbers, statuses, and deep links only — every widget's drill-down
 * lives on its own page, and loading this page never writes anything.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardBuilder $builder,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($user->hasRole(UserRole::ScannerOperator)) {
            return redirect()->route('scanner.index');
        }

        return Inertia::render('dashboard', $this->builder->buildForUser($user));
    }
}
