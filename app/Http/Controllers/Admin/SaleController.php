<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\RefundException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Sale;
use App\Models\VolumeConsumption;
use App\Models\VolumeLot;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'package_id' => ['nullable', 'integer'],
            'member' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $base = fn (): Builder => Order::query()
            ->when($filters['package_id'] ?? null, fn ($q, $id) => $q->where('package_id', $id))
            ->when($filters['member'] ?? null, fn ($q, $code) => $q->whereHas('member', fn ($m) => $m->where('member_code', strtoupper($code))))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        // Totals by status for the current filters (ignoring the status filter itself).
        $totals = $base()
            ->toBase()
            ->selectRaw('status, COUNT(*) AS orders, SUM(amount) AS amount')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->status => ['orders' => (int) $row->orders, 'amount' => (int) $row->amount]]);

        $orders = $base()
            ->with(['member:id,member_code,user_id', 'member.user:id,name', 'package:id,name', 'sale:id,order_id,bv_value,status'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.sales.index', [
            'orders' => $orders,
            'totals' => $totals,
            'filters' => $filters,
            'statuses' => OrderStatus::cases(),
            'packages' => Package::query()->orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    /**
     * One sale: where its BV went and every commission it produced.
     */
    public function show(Sale $sale): View
    {
        $sale->load(['order.payments', 'member.user:id,name', 'package', 'refund.processedBy:id,name']);

        $lots = VolumeLot::query()
            ->with('member:id,member_code')
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        $consumptions = VolumeConsumption::query()
            ->with('teamVolume.cycle:id,cycle_date')
            ->whereIn('volume_lot_id', $lots->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('volume_lot_id');

        return view('admin.sales.show', [
            'sale' => $sale,
            'lots' => $lots,
            'consumptions' => $consumptions,
            // Commissions attributed to this sale directly (referral + reversals).
            'commissions' => Commission::query()
                ->with('member:id,member_code')
                ->where('source_sale_id', $sale->id)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function refund(Request $request, Sale $sale, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        try {
            $refunds->refund($sale, $data['reason'], $admin);
        } catch (RefundException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sales.show', $sale)
            ->with('success', 'Sale refunded. Its BV and commissions are being reversed; return the money to the customer through the gateway.');
    }
}
