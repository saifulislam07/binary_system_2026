@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
    $labels = ['paid' => 'Completed', 'pending' => 'Pending', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'];
@endphp

@section('title', 'Sales')

@section('content_header')
    <h1>Sales</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="row">
        @foreach ($labels as $status => $label)
            <div class="col-6 col-md">
                <div class="info-box" data-total="{{ $status }}">
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $money($totals[$status]['amount'] ?? 0) }}</span>
                        <span class="small text-body-secondary">{{ number_format($totals[$status]['orders'] ?? 0) }} orders</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Any</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $labels[$status->value] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="package_id" class="form-label">Package</label>
                <select id="package_id" name="package_id" class="form-select">
                    <option value="">Any</option>
                    @foreach ($packages as $id => $name)
                        <option value="{{ $id }}" @selected((int) ($filters['package_id'] ?? 0) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="member" class="form-label">Member code</label>
                <input id="member" name="member" value="{{ $filters['member'] ?? '' }}" class="form-control" placeholder="MBR-100001">
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('admin.sales.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Member</th>
                        <th>Package</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">BV</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                @if ($order->sale)
                                    <a href="{{ route('admin.sales.show', $order->sale) }}">{{ $order->order_number }}</a>
                                @else
                                    {{ $order->order_number }}
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.members.show', $order->member) }}">{{ $order->member->member_code ?? '(pending)' }}</a>
                                <div class="small text-body-secondary">{{ $order->member->user->name }}</div>
                            </td>
                            <td>{{ $order->package->name }}</td>
                            <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($order->amount) }}</td>
                            <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $order->sale ? number_format(intdiv($order->sale->bv_value, 100)) : '—' }}</td>
                            <td>@include('admin.partials.status-badge', ['status' => $order->status->value === 'paid' ? 'completed' : $order->status->value])</td>
                            <td class="text-nowrap">{{ $order->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">No orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer">{{ $orders->links() }}</div>
        @endif
    </div>
@stop
