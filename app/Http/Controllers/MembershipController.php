<?php

namespace App\Http\Controllers;

use App\Models\BonusRule;
use App\Models\CommissionRule;
use App\Models\MembershipSection;
use App\Models\Package;
use App\Models\Rank;
use App\Models\Setting;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Membership & earnings" page: the full, honest description of the
 * sponsor-based membership and how commission works. Everything on it is
 * live: admin-written text sections (MembershipSection, in the visitor's
 * language) and the current packages, rates, caps, ranks and bonus rules.
 * The earnings disclaimer is part of the page itself and not editable. The
 * shop front stays product-first; this page is linked from its footer and
 * from registration, so nobody joins without seeing it.
 */
class MembershipController extends Controller
{
    public function __invoke(): Response
    {
        $referralBps = CommissionRule::int(CommissionRule::REFERRAL_RATE_BPS);
        $cap = fn (int $poysha) => $poysha > 0 ? Money::format($poysha) : null;
        $bv = fn (int $centiBv) => number_format(intdiv($centiBv, 100));

        return Inertia::render('membership/Index', [
            'sections' => MembershipSection::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (MembershipSection $section) => [
                    'id' => $section->id,
                    'title' => $section->localTitle(),
                    'body' => $section->localBody(),
                ])
                ->values(),
            'rates' => [
                'referral' => self::percent($referralBps),
                'binary' => self::percent(CommissionRule::int(CommissionRule::BINARY_RATE_BPS)),
                'dailyCap' => $cap(CommissionRule::int(CommissionRule::DAILY_CAP)),
                'weeklyCap' => $cap(CommissionRule::int(CommissionRule::WEEKLY_CAP)),
                'monthlyCap' => $cap(CommissionRule::int(CommissionRule::MONTHLY_CAP)),
                'overCap' => CommissionRule::raw(CommissionRule::CAP_OVERFLOW_BEHAVIOR) === 'carry_forward' ? 'carry_forward' : 'void',
                'carryForward' => CommissionRule::bool(CommissionRule::CARRY_FORWARD_ENABLED),
                'minWithdrawal' => Money::format(Setting::int(Setting::MIN_WITHDRAWAL, 100_000)),
            ],
            'packages' => Package::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => Money::format($package->price),
                    'bv' => $bv($package->bv_value),
                    'referralBonus' => $package->is_qualifying ? Money::format(Money::percentOf($package->price, $referralBps)) : null,
                ])
                ->values(),
            'ranks' => Rank::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Rank $rank) => [
                    'name' => $rank->name,
                    'personalSales' => $rank->min_personal_sales > 0 ? Money::format($rank->min_personal_sales) : null,
                    'teamBv' => $rank->min_team_sales > 0 ? $bv($rank->min_team_sales) : null,
                    'activeTeam' => $rank->min_active_team > 0 ? $rank->min_active_team : null,
                    'bonus' => $rank->bonus_amount > 0 ? Money::format($rank->bonus_amount) : null,
                ])
                ->values(),
            'bonusRules' => BonusRule::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('threshold')
                ->get()
                ->map(fn (BonusRule $rule) => [
                    'id' => $rule->id,
                    'type' => $rule->type,
                    'name' => $rule->name,
                    'threshold' => $rule->type === BonusRule::SALES ? Money::format($rule->threshold) : number_format($rule->threshold),
                    'amount' => Money::format($rule->amount),
                ])
                ->values(),
        ]);
    }

    private static function percent(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.').'%';
    }
}
