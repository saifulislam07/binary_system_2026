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
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
    ->withExceptions(function (Exceptions $exceptions) use ($isAdminRequest): void {
        // Error tracking: a no-op until SENTRY_LARAVEL_DSN is set.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Member app (Inertia): never show a raw error page inside a modal.
        // An expired form goes back with a toast; outside debug mode a failed
        // page visit reloads fully (showing resources/views/errors/*) and a
        // failed form submission goes back with a toast.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) use ($isAdminRequest) {
            if (! $request->header('X-Inertia') || $isAdminRequest($request)) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'The page expired — please try again. · পাতার মেয়াদ শেষ, আবার চেষ্টা করুন।']);

                return back();
            }

            if (config('app.debug') || $status < 400 || $status === 409) {
                return $response;
            }

            if ($request->isMethod('GET')) {
                return Inertia::location($request->fullUrl());
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $status === 429
                ? 'Too many attempts — wait a minute. · অনেকবার চেষ্টা হয়েছে, এক মিনিট অপেক্ষা করুন।'
                : 'That didn’t work. Please try again. · কাজটি হয়নি, আবার চেষ্টা করুন।']);

            return back();
        });
    })->create();
