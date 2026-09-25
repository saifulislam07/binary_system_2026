@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
    $bv = fn (int $centi) => number_format(intdiv($centi, 100));
@endphp

@section('title', 'Sale '.$sale->order->order_number)

@section('content_header')
    <h1>
        Sale {{ $sale->order->order_number }}
        <span class="fs-6 align-middle">@include('admin.partials.status-badge', ['status' => $sale->status->value])</span>
    </h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Sale</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Member</dt>
                        <dd class="col-sm-8"><a href="{{ route('admin.members.show', $sale->member) }}">{{ $sale->member->member_code }}</a> · {{ $sale->member->user->name }}</dd>
                        <dt class="col-sm-4">Package</dt><dd class="col-sm-8">{{ $sale->package->name }}</dd>
                        <dt class="col-sm-4">Amount</dt><dd class="col-sm-8">{{ $money($sale->amount) }}</dd>
                        <dt class="col-sm-4">BV</dt><dd class="col-sm-8">{{ $bv($sale->bv_value) }}</dd>
                        <dt class="col-sm-4">Date</dt><dd class="col-sm-8">{{ $sale->created_at?->format('d M Y H:i') }}</dd>
                        @foreach ($sale->order->payments as $payment)
                            <dt class="col-sm-4">Payment</dt>
                            <dd class="col-sm-8">{{ $payment->gateway->label() }} · {{ $payment->gateway_ref ?? '—' }} · @include('admin.partials.status-badge', ['status' => $payment->status->value])</dd>
                        @endforeach
                        @if ($sale->refund)
                            <dt class="col-sm-4">Refunded</dt>
                            <dd class="col-sm-8">{{ $sale->refunded_at?->format('d M Y H:i') }} by {{ $sale->refund->processedBy?->name ?? 'system' }} — {{ $sale->refund->reason }}</dd>
                            <dt class="col-sm-4">Reversal</dt>
                            <dd class="col-sm-8">{{ $sale->reversed_at ? 'Completed '.$sale->reversed_at->format('d M Y H:i') : 'Queued' }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @if ($sale->status->value === 'completed')
                <div class="card card-outline card-danger">
                    <div class="card-header"><h3 class="card-title">Refund</h3></div>
                    <form method="POST" action="{{ route('admin.sales.refund', $sale) }}" onsubmit="return confirm('Refund this sale and reverse its BV and commissions?')">
                        @csrf
                        <div class="card-body">
                            <p class="small">
                                Marks the sale refunded and reverses its BV up the tree plus every commission it generated
                                (reversal entries — originals are kept). Return the money to the customer through the gateway separately.
                            </p>
                            <label for="reason" class="form-label">Reason</label>
                            <textarea id="reason" name="reason" rows="2" class="form-control" required minlength="5"></textarea>
                        </div>
                        <div class="card-footer"><button class="btn btn-danger btn-sm">Refund sale</button></div>
                    </form>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">Commissions from this sale</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0" style="font-variant-numeric: tabular-nums">
                        <thead><tr><th>Member</th><th>Type</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($commissions as $c)
                                <tr>
                                    <td>{{ $c->member->member_code }}</td>
                                    <td>{{ ucfirst($c->type->value) }}</td>
                                    <td class="text-end">{{ $money($c->amount) }}</td>
                                    <td>@include('admin.partials.status-badge', ['status' => $c->status->value])</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">None.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-body-secondary">Binary commission is earned per cycle from pooled volume; see the BV flow below for the cycles that matched this sale.</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">BV flow up the placement tree</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0" style="font-variant-numeric: tabular-nums">
                <thead>
                    <tr><th>Upline member</th><th>Side</th><th class="text-end">BV</th><th class="text-end">Still unmatched</th><th>What happened</th></tr>
                </thead>
                <tbody>
                    @forelse ($lots as $lot)
                        <tr>
                            <td><a href="{{ route('admin.members.show', $lot->member) }}">{{ $lot->member->member_code }}</a></td>
                            <td>{{ ucfirst($lot->side->value) }}</td>
                            <td class="text-end">{{ $bv($lot->bv) }}</td>
                            <td class="text-end">{{ $bv($lot->remaining) }}</td>
                            <td class="small">
                                @forelse ($consumptions[$lot->id] ?? [] as $c)
                                    <div>{{ ucfirst($c->kind) }} {{ $bv($c->bv) }} BV @if ($c->teamVolume?->cycle) in cycle {{ $c->teamVolume->cycle->cycle_date->toDateString() }} @endif</div>
                                @empty
                                    <span class="text-body-secondary">Waiting to be matched</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">No upline (top of the tree) or no BV.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
