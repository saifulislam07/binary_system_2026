@extends('adminlte::page')

@section('title', 'Brands')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1>Brands</h1>
            <p class="page-lead">Pick a brand on each product; the shop lets customers browse and filter by it.</p>
        </div>
        <a href="{{ route('admin.brands.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New brand</a>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>Brand</th><th class="text-end">Products</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr data-brand="{{ $brand->slug }}">
                            <td class="tabular-nums">{{ $brand->sort_order }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($brand->logoUrl())
                                        <img src="{{ $brand->logoUrl() }}" alt="" width="72" height="36" class="thumb bg-white" style="object-fit: contain; padding: 4px">
                                    @else
                                        <span class="thumb-placeholder fw-bold" style="width: 72px; height: 36px">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($brand->name, 0, 2)) }}</span>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $brand->name }}</div>
                                        @if ($brand->description)<div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($brand->description, 90) }}</div>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($brand->products_count) }}</td>
                            <td>@if ($brand->is_active)<span class="badge text-bg-success">Shown</span>@else<span class="badge text-bg-secondary">Hidden</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.products.index', ['brand' => $brand->id]) }}" class="btn btn-sm btn-outline-secondary">Products</a>
                                <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-award"></i>No brands yet. Add one here, or type a new brand on a product.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
