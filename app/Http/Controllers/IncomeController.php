<?php

namespace App\Http\Controllers;

use App\Enums\CommissionType;
use App\Models\Commission;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    /**
     * Tab => commission types shown on it.
     *
     * @var array<string, list<CommissionType>>
     */
    public const TABS = [
        'referral' => [CommissionType::Referral],
        'binary' => [CommissionType::Binary],
        'rank' => [CommissionType::Rank],
        'other' => [CommissionType::Leadership, CommissionType::Sales, CommissionType::Performance],
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $tab = $filters['tab'] ?? 'referral';
        $member = $request->user('web')->member;

        $query = Commission::query()
            ->with(['sourceSale.member:id,member_code', 'cycle:id,cycle_date'])
            ->where('member_id', $member->id)
            ->whereIn('type', self::TABS[$tab])
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('cycle_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('cycle_date', '<=', $to));

        $net = (int) (clone $query)->whereIn('status', ['paid', 'reversed'])->sum('amount');

        return Inertia::render('income/Index', [
            'tab' => $tab,
            'tabs' => [
                'referral' => 'Referral · রেফারেল',
                'binary' => 'Binary · বাইনারি',
                'rank' => 'Rank bonus · র‍্যাংক',
                'other' => 'Other bonus · অন্যান্য',
            ],
            'filters' => ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null],
            'net' => Money::format($net),
            'rows' => $query->orderByDesc('cycle_date')->orderByDesc('id')->paginate(20)->withQueryString()
                ->through(fn (Commission $c) => [
                    'id' => $c->id,
                    'date' => $c->cycle_date->format('d M Y'),
                    'type' => ucfirst($c->type->value),
                    'amount' => Money::format($c->amount),
                    'negative' => $c->amount < 0,
                    'status' => $c->status->value,
                    'source' => match (true) {
                        $c->sourceSale !== null => 'Sale by '.($c->sourceSale->member->member_code ?? '#'.$c->sourceSale->member_id),
                        $c->cycle !== null => 'Cycle '.$c->cycle->cycle_date->toDateString(),
                        default => null,
                    },
                    'description' => $c->description,
                ]),
        ]);
    }
}
