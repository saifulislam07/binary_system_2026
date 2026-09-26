<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify only rate-limits login. Registration and the password-reset
 * endpoints are registered by Fortify too, so their named limiters (see
 * FortifyServiceProvider) are applied here, by route name — this works
 * with cached routes, unlike patching the route objects at boot.
 */
class ThrottleAuthEndpoints
{
    private const LIMITERS = [
        'register.store' => 'registration',
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName() ?? ''] ?? null;

        if ($limiter === null || $request->isMethodSafe()) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
