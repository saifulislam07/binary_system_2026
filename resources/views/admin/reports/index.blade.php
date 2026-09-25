@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
    $cell = fn (int $value, int $i) => $data['columns'][$i]['money'] ? $money($value) : number_format($value);
    $query = ['report' => $report, 'granularity' => $granularity, 'from' => $from, 'to' => $to];
@endphp

@section('title', 'Reports')

@section('content_header')
    <h1>Reports</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="report" class="form-label">Report</label>
                <select id="report" name="report" class="form-select">
                    @foreach ($reports as $key => $label)
                        <option value="{{ $key }}" @selected($key === $report)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="granularity" class="form-label">Group by</label>
                <select id="granularity" name="granularity" class="form-select">
                    @foreach ($granularities as $key => $label)
                        <option value="{{ $key }}" @selected($key === $granularity)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label">From</label>
                <input id="from" type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label">To</label>
                <input id="to" type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-primary">Show</button>
                <a href="{{ route('admin.reports.export', $query) }}" class="btn btn-outline-success"><i class="bi bi-download"></i> CSV</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ $data['title'] }} · {{ $granularities[$granularity] }} · {{ $from }} → {{ $to }}</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped mb-0" style="font-variant-numeric: tabular-nums">
                <thead>
                    <tr>
                        <th>Period</th>
                        @foreach ($data['columns'] as $column)
                            <th class="text-end text-nowrap">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['rows'] as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['period'] }}</td>
                            @foreach ($row['values'] as $i => $value)
                                @if ($value === 0)
                                    <td class="text-end text-body-tertiary">—</td>
                                @else
                                    <td class="text-end text-nowrap {{ $value < 0 ? 'text-danger' : '' }}">{{ $cell($value, $i) }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($data['columns']) + 1 }}" class="text-center text-body-secondary py-3">No periods in this range.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="fw-semibold">
                    <tr>
                        <td>Total</td>
                        @foreach ($data['totals'] as $i => $value)
                            <td class="text-end text-nowrap {{ $value < 0 ? 'text-danger' : '' }}">{{ $cell($value, $i) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        @if ($report === 'pnl')
            <div class="card-footer small text-body-secondary">Uses the same definitions as the dashboard: revenue excludes refunded sales; expenses exclude the product-cost and commission categories.</div>
        @endif
    </div>
@stop
