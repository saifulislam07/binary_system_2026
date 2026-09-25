<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlacementSide;
use App\Exceptions\PlacementException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Member;
use App\Models\TeamVolume;
use App\Services\PlacementAdjustmentService;
use App\Services\TeamService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreeController extends Controller
{
    /**
     * Tree browser. Starts at ?member=CODE, or at the top of the tree.
     */
    public function index(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('member', '')));

        $root = $code !== ''
            ? Member::query()->where('member_code', $code)->whereHas('binaryNode')->first()
            : Member::query()->whereNull('placement_parent_id')->whereHas('binaryNode')->first();

        return view('admin.tree.index', [
            'root' => $root,
            'searched' => $code,
            'history' => $root === null ? collect() : TeamVolume::query()
                ->with('cycle:id,cycle_date')
                ->where('member_id', $root->id)
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    /**
     * JSON branch for the lazy tree (any member — admins aren't limited to a downline).
     */
    public function node(Request $request, Member $member, TeamService $team): JsonResponse
    {
        abort_if($member->binaryNode()->doesntExist(), 404);

        return response()->json($team->subtree($member, (int) $request->query('depth', '2')));
    }

    public function adjust(Request $request, Member $member, PlacementAdjustmentService $adjustments): RedirectResponse
    {
        $data = $request->validate([
            'new_parent' => ['required', 'string', Rule::exists('members', 'member_code')],
            'side' => ['required', Rule::enum(PlacementSide::class)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        try {
            $adjustments->move(
                $member,
                Member::query()->where('member_code', strtoupper($data['new_parent']))->firstOrFail(),
                PlacementSide::from($data['side']),
                $admin,
                $data['reason'],
            );
        } catch (PlacementException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.tree.index', ['member' => $member->member_code])
            ->with('success', "{$member->member_code} was moved under {$data['new_parent']} ({$data['side']}).");
    }
}
