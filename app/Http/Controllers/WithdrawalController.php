<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\WithdrawalMethodType;
use App\Exceptions\WithdrawalException;
use App\Http\Requests\WithdrawalRequest;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function index(Request $request, WalletService $wallets, WithdrawalService $withdrawals): Response
    {
        $member = $request->user('web')?->member;

        abort_if($member === null, 403);

        return Inertia::render('withdrawals/Index', [
            'canRequest' => $member->status === MemberStatus::Active,
            'balance' => Money::format($wallets->balance($member)),
            'minimum' => Money::format($withdrawals->minimumAmount()),
            'providers' => WithdrawalRequest::MOBILE_PROVIDERS,
            'savedMethods' => $member->withdrawalMethods()
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
                ->map(fn (WithdrawalMethod $m) => [
                    'id' => $m->id,
                    'label' => self::describe($m->type, $m->details),
                    'isDefault' => $m->is_default,
                ]),
            'withdrawals' => $member->withdrawals()
                ->latest('id')
                ->paginate(15)
                ->through(fn (Withdrawal $w) => [
                    'id' => $w->id,
                    'date' => $w->created_at?->format('d M Y, h:i A'),
                    'amount' => Money::format($w->amount),
                    'account' => self::describe($w->method, $w->account_details),
                    'status' => $w->status->value,
                    'statusLabel' => $w->status->label(),
                    'rejectionReason' => $w->rejection_reason,
                    'processedAt' => $w->processed_at?->format('d M Y'),
                ]),
        ]);
    }

    public function store(WithdrawalRequest $request, WithdrawalService $withdrawals): RedirectResponse
    {
        $member = $request->member();

        if ($request->filled('withdrawal_method_id')) {
            $saved = $member->withdrawalMethods()->findOrFail($request->integer('withdrawal_method_id'));
            $method = $saved->type;
            $details = $saved->details;
        } else {
            $method = WithdrawalMethodType::from($request->string('method')->toString());
            $details = $request->newMethodDetails();

            if ($request->boolean('save_method')) {
                $member->withdrawalMethods()->create([
                    'type' => $method,
                    'details' => $details,
                    'is_default' => ! $member->withdrawalMethods()->exists(),
                ]);
            }
        }

        try {
            $withdrawals->request($member, $request->amountInPoysha(), $method, $details);
        } catch (WithdrawalException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Withdrawal requested. The amount is on hold until it is processed.')]);

        return to_route('withdrawals.index');
    }

    /**
     * "bKash · 01712***678" / "Sonali Bank · ****4321" — never the full number.
     *
     * @param  array<string, string>  $details
     */
    public static function describe(WithdrawalMethodType $type, array $details): string
    {
        if ($type === WithdrawalMethodType::MobileBanking) {
            $number = $details['mobile_number'] ?? '';
            $local = str_starts_with($number, '+88') ? substr($number, 3) : $number;
            $provider = WithdrawalRequest::MOBILE_PROVIDERS[$details['provider'] ?? ''] ?? 'Mobile banking';

            return $provider.' · '.substr($local, 0, 5).'***'.substr($local, -3);
        }

        return ($details['bank_name'] ?? 'Bank').' · ****'.substr($details['account_number'] ?? '', -4);
    }
}
