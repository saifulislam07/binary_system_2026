<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes `admin` the default guard for the request, so Gate/`@can`, Spatie
 * permission checks and AdminLTE's menu `can` filter all resolve the Admin.
 */
class UseAdminGuard
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('admin');

        return $next($request);
    }
}
