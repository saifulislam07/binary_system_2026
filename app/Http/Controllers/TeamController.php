<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, TeamService $team): Response
    {
        $member = $request->user('web')->member;
        $sponsor = $member->sponsor()->with('user:id,name')->first();
        $legs = $team->legCounts($member);

        return Inertia::render('team/Index', [
            'sponsor' => $sponsor === null ? null : [
                'code' => $sponsor->member_code,
                'name' => $sponsor->user->name,
            ],
            'legs' => $legs,
            'directReferrals' => $member->sponsoredMembers()->count(),
            'tree' => $team->subtree($member, 2),
        ]);
    }

    /**
     * Lazy-loads a branch of the tree (JSON). Only nodes in the viewer's
     * own downline may be opened.
     */
    public function tree(Request $request, Member $member, TeamService $team): JsonResponse
    {
        $viewer = $request->user('web')->member;

        abort_unless($team->isInDownline($viewer, $member), 403);

        $depth = (int) $request->query('depth', '2');

        return response()->json($team->subtree($member, $depth));
    }
}
