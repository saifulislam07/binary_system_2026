@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
    $bv = fn (int $centi) => \App\Support\Money::format($centi, symbol: false);
@endphp

@section('title', 'Packages')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h1 class="mb-0">Packages</h1>
        <a href="{{ route('admin.packages.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New package</a>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <p class="text-body-secondary small">
        Price and BV changes apply to new orders only — every sale keeps the amount and BV it was made at.
        Packages are never deleted; deactivate one to take it off sale.
    </p>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Order</th><th>Package</th><th class="text-end">Price</th><th class="text-end">BV</th>
                        <th class="text-end">Cost of goods</th><th>Referral bonus</th><th>Status</th>
                        <th class="text-end">Members</th><th class="text-end">Sales</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($packages as $package)
                        <tr data-package="{{ $package->slug }}">
                            <td>{{ $package->sort_order }}</td>
                            <td>
                                <div class="fw-semibold">{{ $package->name }}</div>
                                @if ($package->description)<div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($package->description, 80) }}</div>@endif
                            </td>
                            <td class="text-end tabular-nums">{{ $money($package->price) }}</td>
                            <td class="text-end tabular-nums">{{ $bv($package->bv_value) }}</td>
                            <td class="text-end tabular-nums">{{ $money($package->cost_of_goods) }}</td>
                            <td>
                                @if ($package->is_qualifying)<span class="badge text-bg-info">Qualifying</span>@else<span class="badge text-bg-secondary">No</span>@endif
                            </td>
                            <td>
                                @if ($package->is_active)<span class="badge text-bg-success">On sale</span>@else<span class="badge text-bg-secondary">Inactive</span>@endif
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($package->members_count) }}</td>
                            <td class="text-end tabular-nums">{{ number_format($package->sales_count) }}</td>
                            <td class="text-end"><a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop
