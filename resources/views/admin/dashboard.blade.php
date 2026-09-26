@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
@endphp

@section('title', 'Dashboard')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="m-0">Dashboard</h1>
        <div class="btn-group btn-group-sm" role="group" aria-label="Period">
            @foreach ($periods as $key => $label)
                <a href="{{ route('admin.dashboard', ['period' => $key]) }}"
                   class="btn {{ $key === $period ? 'btn-primary' : 'btn-outline-secondary' }}"
                   @if ($key === $period) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
    </div>
@stop

@section('content')
    <p class="text-body-secondary small mb-3">
        Welcome, {{ auth('admin')->user()->name }}. Period figures: <strong>{{ $periods[$period] }}</strong>.
        Member totals and open withdrawals are always current.
    </p>

    <h2 class="h6 text-uppercase text-body-secondary">Members</h2>
    <div class="row">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="members_total">
                <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-people-fill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total members</span>
                    <span class="info-box-number">{{ number_format($m['members_total']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="members_active">
                <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-person-check-fill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active members</span>
                    <span class="info-box-number">{{ number_format($m['members_active']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="members_new">
                <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-person-plus-fill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">New members ({{ strtolower($periods[$period]) }})</span>
                    <span class="info-box-number">{{ number_format($m['members_new']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="members_pending">
                <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Awaiting payment</span>
                    <span class="info-box-number">{{ number_format($m['members_pending']) }}</span>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h6 text-uppercase text-body-secondary mt-2">Sales &amp; payouts</h2>
    <div class="row">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="sales_amount">
                <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-bag-check-fill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Sales ({{ strtolower($periods[$period]) }})</span>
                    <span class="info-box-number">{{ $money($m['sales_amount']) }}</span>
                    <span class="small text-body-secondary">{{ number_format($m['sales_count']) }} orders</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="sales_today">
                <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-calendar-day"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Today's sales</span>
                    <span class="info-box-number">{{ $money($m['sales_today_amount']) }}</span>
                    <span class="small text-body-secondary">{{ number_format($m['sales_today_count']) }} orders</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="commission_paid">
                <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-diagram-3-fill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Commission &amp; bonuses (net)</span>
                    <span class="info-box-number">{{ $money($m['commission_paid']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="info-box" data-metric="withdrawals_open">
                <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-box-arrow-up-right"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending withdrawals</span>
                    <span class="info-box-number">{{ $money($m['withdrawals_open_amount']) }}</span>
                    <span class="small text-body-secondary">{{ number_format($m['withdrawals_open_count']) }} open requests</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12 col-lg-6">
            <div class="card" data-metric="pnl">
                <div class="card-header">
                    <h3 class="card-title">Profit &amp; loss — {{ $periods[$period] }}</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody>
                            <tr>
                                <td>Company revenue</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($m['revenue']) }}</td>
                            </tr>
                            <tr class="small text-body-secondary">
                                <td class="ps-4">of which other income</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($m['other_income']) }}</td>
                            </tr>
                            <tr>
                                <td>− Cost of goods</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($m['cost_of_goods']) }}</td>
                            </tr>
                            <tr class="fw-semibold">
                                <td>Gross profit</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums" data-value="gross_profit">{{ $money($m['gross_profit']) }}</td>
                            </tr>
                            <tr>
                                <td>− Commission &amp; bonuses (net)</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($m['commission_paid']) }}</td>
                            </tr>
                            <tr>
                                <td>− Company expenses</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($m['expenses']) }}</td>
                            </tr>
                            <tr class="fw-bold {{ $m['net_profit'] < 0 ? 'text-danger' : '' }}">
                                <td>Estimated net profit</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums" data-value="net_profit">{{ $money($m['net_profit']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-body-secondary">
                    Cost of goods uses each package's configured cost. Expenses exclude the
                    "product cost" and "commission" categories, which are already counted above.
                    Refunded sales ({{ $money($m['refunded_amount']) }} this period) are excluded from revenue.
                </div>
            </div>
        </div>
    </div>
@stop
