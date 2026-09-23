<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Services\IncomeSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, IncomeSummaryService $income): Response
    {
        $member = $request->user('web')?->member()->with('package:id,name')->first();

        return Inertia::render('Dashboard', [
            'member' => $member === null ? null : [
                'code' => $member->member_code,
                'status' => $member->status->value,
                'package' => $member->package?->name,
            ],
            'walletSummary' => $member !== null && $member->status === MemberStatus::Active
                ? WalletController::formatSummary($income->for($member))
                : null,
        ]);
    }
}
