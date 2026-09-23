<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['package:id,name', 'member:id,member_code,status']);

        return Inertia::render('orders/Show', [
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status->value,
                'amount' => Money::format($order->amount),
                'package' => $order->package->name,
                'paidAt' => $order->paid_at?->toDayDateTimeString(),
                'memberCode' => $order->member->member_code,
                'memberStatus' => $order->member->status->value,
            ],
        ]);
    }
}
