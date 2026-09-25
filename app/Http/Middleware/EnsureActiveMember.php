<?php

namespace App\Http\Middleware;

use App\Enums\MemberStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages that only make sense once a member is placed in the tree
 * (team, income, referral link). Pending members are sent to pay first.
 */
class EnsureActiveMember
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user('web')?->member;

        if ($member === null) {
            abort(403);
        }

        if ($member->status !== MemberStatus::Active) {
            return $member->status === MemberStatus::Pending
                ? redirect()->route('checkout.index')
                : abort(403, 'Your account is suspended.');
        }

        return $next($request);
    }
}
