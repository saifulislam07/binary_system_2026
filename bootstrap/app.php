<?php

use App\Http\Middleware\EnsureActiveMember;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleAuthEndpoints;
use App\Http\Middleware\UseAdminGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$isAdminRequest = fn (Request $request): bool => $request->is('admin', 'admin/*');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', UseAdminGuard::class])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) use ($isAdminRequest): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Payment gateways POST back cross-site; those requests are verified with the gateway instead.
        $middleware->validateCsrfTokens(except: ['payments/*/callback']);

        $middleware->alias(['member.active' => EnsureActiveMember::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ThrottleAuthEndpoints::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $isAdminRequest($request)
            ? route('admin.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request) => $isAdminRequest($request)
            ? route('admin.dashboard')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
