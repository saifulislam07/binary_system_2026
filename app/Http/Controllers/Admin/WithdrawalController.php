<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Exceptions\WithdrawalException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\WithdrawalController as MemberWithdrawalController;
use App\Models\Admin;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $status = WithdrawalStatus::tryFrom((string) $request->query('status', 'pending')) ?? WithdrawalStatus::Pending;

        $counts = Withdrawal::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) AS n, SUM(amount) AS total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->status => ['n' => (int) $row->n, 'total' => (int) $row->total]]);

        return view('admin.withdrawals.index', [
            'status' => $status,
            'statuses' => WithdrawalStatus::cases(),
            'counts' => $counts,
            'withdrawals' => Withdrawal::query()
                ->with(['member:id,member_code,user_id', 'member.user:id,name,phone', 'admin:id,name'])
                ->where('status', $status)
                ->oldest('id') // first come, first served
                ->paginate(25)
                ->withQueryString(),
            'describe' => fn (Withdrawal $w) => MemberWithdrawalController::describe($w->method, $w->account_details),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        return $this->attempt(fn (Admin $admin) => $service->approve($withdrawal, $admin), $request, 'approved');
    }

    public function startProcessing(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        return $this->attempt(fn (Admin $admin) => $service->startProcessing($withdrawal, $admin), $request, 'marked as processing');
    }

    public function markPaid(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate(['payout_reference' => ['required', 'string', 'max:100']]);

        return $this->attempt(fn (Admin $admin) => $service->markPaid($withdrawal, $admin, $data['payout_reference']), $request, 'marked as paid');
    }

    public function reject(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn (Admin $admin) => $service->reject($withdrawal, $admin, $data['reason']), $request, 'rejected — the amount is back in the member\'s wallet');
    }

    /**
     * @param  callable(Admin): Withdrawal  $action
     */
    private function attempt(callable $action, Request $request, string $done): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        try {
            $withdrawal = $action($admin);
        } catch (WithdrawalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Withdrawal #{$withdrawal->id} {$done}.");
    }
}
