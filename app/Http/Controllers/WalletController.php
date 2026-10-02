<?php

namespace App\Http\Controllers;

use App\Enums\CommissionType;
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
                'date' => $t->created_at?->translatedFormat('d M Y, h:i A'),
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
            'period' => __($summary['period']),
            'referral' => Money::format($summary['referral']),
            'binary' => Money::format($summary['binary']),
            'binaryCycle' => $summary['binary_cycle'],
            'lifetime' => Money::format($summary['lifetime']),
        ];
    }

    private static function describeReference(WalletTransaction $transaction): ?string
    {
        $reference = $transaction->reference;

        if ($reference === null) {
            return null;
        }

        return match (true) {
            $reference instanceof Commission => __(':type commission #:id', ['type' => self::commissionType($reference->type), 'id' => $reference->id]),
            $reference instanceof Withdrawal => __('Withdrawal #:id', ['id' => $reference->id]),
            $reference instanceof Order => __('Order :number', ['number' => $reference->order_number]),
            $reference instanceof Sale => __('Sale #:id', ['id' => $reference->id]),
            default => class_basename($reference).' #'.$reference->getKey(),
        };
    }

    private static function commissionType(CommissionType $type): string
    {
        return match ($type) {
            CommissionType::Referral => __('Referral'),
            CommissionType::Binary => __('Binary'),
            CommissionType::Rank => __('Rank'),
            CommissionType::Leadership => __('Leadership'),
            CommissionType::Sales => __('Sales'),
            CommissionType::Performance => __('Performance'),
        };
    }
}
