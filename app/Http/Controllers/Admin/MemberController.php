<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Exceptions\MemberAdminException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMemberRequest;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Package;
use App\Models\WalletTransaction;
use App\Services\MemberAdminService;
use App\Services\TeamService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(MemberStatus::class)],
            'package_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $members = Member::query()
            ->with(['user:id,name,email,phone', 'package:id,name', 'sponsor:id,member_code'])
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $query->where(fn ($w) => $w
                    ->where('member_code', 'like', "%{$q}%")
                    ->orWhere('nid', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['package_id'] ?? null, fn ($query, $id) => $query->where('package_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.members.index', [
            'members' => $members,
            'filters' => $filters,
            'packages' => Package::query()->orderBy('sort_order')->pluck('name', 'id'),
            'statuses' => MemberStatus::cases(),
        ]);
    }

    public function show(Member $member, TeamService $team, WalletService $wallets): View
    {
        $member->load(['user', 'package', 'sponsor.user:id,name', 'placementParent:id,member_code', 'binaryNode', 'currentRank:id,name']);

        return view('admin.members.show', [
            'member' => $member,
            'legs' => $member->binaryNode ? $team->legCounts($member) : null,
            'balance' => $wallets->balance($member),
            'transactions' => WalletTransaction::query()
                ->whereHas('wallet', fn ($q) => $q->where('member_id', $member->id))
                ->latest('id')
                ->limit(20)
                ->get(),
            'personalSales' => (int) $member->sales()->where('status', 'completed')->sum('amount'),
            'directReferrals' => $member->sponsoredMembers()->count(),
            'activity' => Activity::query()
                ->where('subject_type', $member->getMorphClass())
                ->where('subject_id', $member->id)
                ->with('causer')
                ->latest('id')
                ->limit(20)
                ->get(),
            'packages' => Package::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function edit(Member $member): View
    {
        return view('admin.members.edit', ['member' => $member->load('user')]);
    }

    public function update(UpdateMemberRequest $request, Member $member, MemberAdminService $service): RedirectResponse
    {
        $service->update($member, [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString(),
            'nid' => $request->string('nid')->toString(),
            'address' => $request->string('address')->toString(),
        ], $this->admin($request));

        return redirect()->route('admin.members.show', $member)->with('success', 'Member updated.');
    }

    public function suspend(Request $request, Member $member, MemberAdminService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->suspend($member, $this->admin($request), $data['reason']), $member, 'Member suspended.');
    }

    public function reinstate(Request $request, Member $member, MemberAdminService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->reinstate($member, $this->admin($request), $data['reason']), $member, 'Member reinstated.');
    }

    public function changePackage(Request $request, Member $member, MemberAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->attempt(
            fn () => $service->changePackage($member, Package::query()->whereKey($data['package_id'])->firstOrFail(), $this->admin($request), $data['reason']),
            $member,
            'Package changed.',
        );
    }

    private function attempt(callable $action, Member $member, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (MemberAdminException $e) {
            return redirect()->route('admin.members.show', $member)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.members.show', $member)->with('success', $success);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
