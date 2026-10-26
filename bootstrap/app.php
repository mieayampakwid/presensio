<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RestrictScannerOperatorAccess;
use App\Http\Middleware\SetLocale;
use App\Services\Finance\Exceptions\BillOperationException;
use App\Services\Finance\Exceptions\ClosedPeriodException;
use App\Services\Finance\Exceptions\InvalidLineException;
use App\Services\Finance\Exceptions\OverpaymentOperationException;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Services\Finance\Exceptions\UnbalancedEntryException;
use App\Support\Finance\RejectionResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // No 'auth' middleware group exists in Laravel; appending there would
        // shadow the framework's `auth` route alias. Run in `web` instead —
        // it no-ops for guests and covers every authenticated route.
        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            EnsureUserIsActive::class,
            RestrictScannerOperatorAccess::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(
            fn (UnbalancedEntryException $e, Request $request) => RejectionResponse::make($request, $e->getMessage()),
        );

        $exceptions->render(
            fn (ClosedPeriodException $e, Request $request) => RejectionResponse::make($request, $e->getMessage()),
        );

        $exceptions->render(
            fn (BillOperationException $e, Request $request) => RejectionResponse::make($request, $e->getMessage()),
        );

        $exceptions->render(
            fn (PaymentOperationException $e, Request $request) => RejectionResponse::make($request, $e->getMessage()),
        );

        $exceptions->render(
            fn (OverpaymentOperationException $e, Request $request) => RejectionResponse::make($request, $e->getMessage())->withErrors(['operation' => $e->getMessage()]),
        );

        $exceptions->render(
            fn (InvalidLineException $e, Request $request) => RejectionResponse::make($request, $e->getMessage()),
        );
    })->create();
