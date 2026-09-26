<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Services\IncomeSummaryService;
use App\Services\MemberOverviewService;
use App\Services\RankService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, IncomeSummaryService $income, MemberOverviewService $overview, RankService $ranks): Response
    {
        $member = $request->user('web')?->member()->with('package:id,name')->first();
        $active = $member !== null && $member->status === MemberStatus::Active;
        $stats = $active ? $overview->for($member) : null;

        return Inertia::render('Dashboard', [
            'member' => $member === null ? null : [
                'code' => $member->member_code,
                'status' => $member->status->value,
                'package' => $member->package?->name,
            ],
            'walletSummary' => $active ? WalletController::formatSummary($income->for($member)) : null,
            'overview' => $stats === null ? null : [
                'totalIncome' => Money::format($stats['total_income']),
                'available' => Money::format($stats['available']),
                'personalSales' => Money::format($stats['personal_sales']),
                'leftTeamBv' => number_format($stats['left_team_bv']),
                'rightTeamBv' => number_format($stats['right_team_bv']),
                'teamSize' => $stats['team_size'],
                'activeTeam' => $stats['active_team'],
                'leftTeam' => $stats['left_team'],
                'rightTeam' => $stats['right_team'],
                'chart' => array_map(fn (array $p) => [
                    'label' => $p['label'],
                    'from' => $p['from'],
                    'to' => $p['to'],
                    'amount' => intdiv($p['amount'], 100), // whole taka for plotting
                    'formatted' => Money::format($p['amount']),
                ], $stats['chart']),
            ],
            'rank' => $active ? self::formatRank($ranks->progress($member->load('currentRank'))) : null,
        ]);
    }

    /**
     * @param  array{current: string, next: string|null, progress: list<array{label: string, have: int, need: int, money: bool}>}  $progress
     * @return array<string, mixed>
     */
    private static function formatRank(array $progress): array
    {
        $format = fn (int $value, bool $money) => $money ? Money::format($value) : number_format($value);

        return [
            'current' => $progress['current'],
            'next' => $progress['next'],
            'requirements' => array_map(fn (array $r) => [
                'label' => $r['label'],
                'have' => $format($r['have'], $r['money']),
                'need' => $format($r['need'], $r['money']),
                'percent' => $r['need'] <= 0 ? 100 : min(100, intdiv($r['have'] * 100, $r['need'])),
                'met' => $r['have'] >= $r['need'],
            ], $progress['progress']),
        ];
    }
}
