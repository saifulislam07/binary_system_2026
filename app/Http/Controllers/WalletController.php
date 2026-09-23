<?php

namespace App\Http\Controllers;

use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionType;
use App\Http\Requests\WalletHistoryRequest;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Sale;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\IncomeSummaryService;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(WalletHistoryRequest $request, WalletService $wallets, IncomeSummaryService $summary): Response
    {
        $member = $request->user('web')->member;
        $filters = $request->validated();

        $history = $wallets->history(
            $member,
            isset($filters['type']) ? WalletTransactionType::from($filters['type']) : null,
            isset($filters['from']) ? Carbon::parse($filters['from']) : null,
            isset($filters['to']) ? Carbon::parse($filters['to']) : null,
        );

        return Inertia::render('wallet/Index', [
            'summary' => self::formatSummary($summary->for($member)),
            'transactions' => $history->through(fn (WalletTransaction $t) => [
                'id' => $t->id,
                'date' => $t->created_at?->format('d M Y, h:i A'),
                'type' => $t->type->value,
                'typeLabel' => $t->type->label(),
                'credit' => $t->direction === TransactionDirection::Credit,
                'amount' => ($t->direction === TransactionDirection::Credit ? '+' : '−').Money::format($t->amount),
                'balanceAfter' => $t->balance_after === null ? null : Money::format($t->balance_after),
                'reference' => self::describeReference($t),
                'description' => $t->description,
                'status' => $t->status->value,
            ]),
            'filters' => [
                'type' => $filters['type'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'types' => array_map(fn (WalletTransactionType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ], WalletTransactionType::cases()),
        ]);
    }

    /**
     * @param  array{available: int, period: string, period_start: string, referral: int, binary: int, binary_cycle: string|null, lifetime: int}  $summary
     * @return array<string, string|null>
     */
    public static function formatSummary(array $summary): array
    {
        return [
            'available' => Money::format($summary['available']),
            'period' => $summary['period'],
            'referral' => Money::format($summary['referral']),
            'binary' => Money::format($summary['binary']),
            'binaryCycle' => $summary['binary_cycle'],
            'lifetime' => Money::format($summary['lifetime']),
        ];
    }

    private static function describeReference(WalletTransaction $transaction): ?string
    {
        $reference = $transaction->reference;

        return match (true) {
            $reference instanceof Commission => ucfirst($reference->type->value).' commission #'.$reference->id,
            $reference instanceof Withdrawal => 'Withdrawal #'.$reference->id,
            $reference instanceof Order => 'Order '.$reference->order_number,
            $reference instanceof Sale => 'Sale #'.$reference->id,
            $reference === null => null,
            default => class_basename($reference).' #'.$reference->getKey(),
        };
    }
}
