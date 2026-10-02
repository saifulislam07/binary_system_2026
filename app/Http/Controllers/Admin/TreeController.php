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
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreeController extends Controller
{
    /** Most matches listed when a search is ambiguous. */
    private const MATCH_LIMIT = 20;

    /**
     * Tree browser. Starts at the member found by ?member= (code, name, email
     * or phone), or at the top of the tree. Several matches → a pick list.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('member', ''));
        $matches = new Collection;

        if ($search === '') {
            $root = Member::query()->with('user:id,name')->whereNull('placement_parent_id')->whereHas('binaryNode')->first();
        } else {
            $matches = $this->placedMatching($search);
            $root = $matches->count() === 1 ? $matches->first() : null;
        }

        return view('admin.tree.index', [
            'root' => $root,
            'searched' => $search,
            'matches' => $root === null ? $matches : new Collection,
            'matchLimit' => self::MATCH_LIMIT,
            'history' => $root === null ? collect() : TeamVolume::query()
                ->with('cycle:id,cycle_date')
                ->where('member_id', $root->id)
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    /**
     * Members in the tree matching a search. A full code — "MBR-100004",
     * "mbr100004" or just "100004" — wins outright; otherwise a partial
     * code, name, email or phone.
     *
     * @return Collection<int, Member>
     */
    private function placedMatching(string $search): Collection
    {
        $placed = fn () => Member::query()->with('user:id,name')->whereHas('binaryNode');

        if (preg_match('/^(?:MBR)?[\s-]*(\d+)$/i', $search, $digits) === 1) {
            $exact = $placed()->where('member_code', 'MBR-'.$digits[1])->first();

            if ($exact !== null) {
                return new Collection([$exact]);
            }
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $phone = PhoneNumber::normalize($search);

        return $placed()
            ->where(fn ($query) => $query
                ->where('member_code', 'like', $like)
                ->orWhereHas('user', fn ($user) => $user
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->when(PhoneNumber::isValid($phone), fn ($q) => $q->orWhere('phone', $phone))))
            ->orderBy('member_code')
            ->limit(self::MATCH_LIMIT)
            ->get();
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
