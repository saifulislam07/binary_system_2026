<?php

namespace App\Http\Controllers;

use App\Models\CommissionRule;
use App\Models\Rank;
use App\Models\Setting;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Membership & earnings" page: the full, honest description of the
 * sponsor-based membership and how commission works, with the live rates.
 * The shop front stays product-first; this page is linked from its footer
 * and from registration, so nobody joins without seeing it.
 */
class MembershipController extends Controller
{
    public function __invoke(): Response
    {
        $percent = fn (int $bps) => rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.').'%';
        $cap = fn (int $poysha) => $poysha > 0 ? Money::format($poysha) : null;

        return Inertia::render('membership/Index', [
            'rates' => [
                'referral' => $percent(CommissionRule::int(CommissionRule::REFERRAL_RATE_BPS)),
                'binary' => $percent(CommissionRule::int(CommissionRule::BINARY_RATE_BPS)),
                'dailyCap' => $cap(CommissionRule::int(CommissionRule::DAILY_CAP)),
                'weeklyCap' => $cap(CommissionRule::int(CommissionRule::WEEKLY_CAP)),
                'monthlyCap' => $cap(CommissionRule::int(CommissionRule::MONTHLY_CAP)),
                'carryForward' => CommissionRule::bool(CommissionRule::CARRY_FORWARD_ENABLED),
                'minWithdrawal' => Money::format(Setting::int(Setting::MIN_WITHDRAWAL, 100_000)),
            ],
            'ranks' => Rank::query()->orderBy('sort_order')->pluck('name')->values(),
        ]);
    }
}
