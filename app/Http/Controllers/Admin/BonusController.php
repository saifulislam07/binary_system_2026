<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MemberAdminException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Member;
use App\Services\BonusService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BonusController extends Controller
{
    /**
     * One-off performance bonus. Pays company money at an admin's discretion,
     * so it is gated with manage-settings (super admin) and always logged.
     */
    public function performance(Request $request, Member $member, BonusService $bonuses): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        try {
            $amount = Money::fromTaka($data['amount']);
            $bonus = $bonuses->awardPerformance($member, $amount, $data['reason'], $admin);
        } catch (InvalidArgumentException|MemberAdminException $e) {
            return back()->withInput()->with('error', $e instanceof InvalidArgumentException ? 'Enter an amount in taka, e.g. 1500.' : $e->getMessage());
        }

        return redirect()->route('admin.members.show', $member)
            ->with('success', 'Performance bonus of '.Money::format($bonus->amount).' paid to the member\'s wallet.');
    }
}
