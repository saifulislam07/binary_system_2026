<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With `business.admin_two_factor_required` on, an admin without two-factor
 * sign-in can reach only "My account" (to set it up) and sign out.
 */
class EnsureAdminHasTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (
            config('business.admin_two_factor_required')
            && $admin instanceof Admin
            && ! $admin->hasTwoFactorEnabled()
            && ! $request->routeIs('admin.account.*', 'admin.logout')
        ) {
            return redirect()->route('admin.account.edit')
                ->with('error', 'Turn on two-factor sign-in to use the admin panel.');
        }

        return $next($request);
    }
}
