<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Exceptions\PlacementException;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * TEMPORARY (Phase 3): manual activation for testing until the Phase 4
 * payment callback activates members automatically.
 */
class PendingMemberController extends Controller
{
    public function index(): View
    {
        $members = Member::query()
            ->with(['user:id,name,email,phone', 'sponsor:id,member_code', 'package:id,name'])
            ->where('status', MemberStatus::Pending)
            ->latest()
            ->paginate(25);

        return view('admin.members.pending', ['members' => $members]);
    }

    public function activate(Member $member, PlacementService $placement): RedirectResponse
    {
        try {
            $member = $placement->activateMember($member);
        } catch (PlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Activated {$member->user->name} as {$member->member_code}.");
    }
}
