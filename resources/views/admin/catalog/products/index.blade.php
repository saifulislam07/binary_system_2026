@extends('adminlte::page')

@php $money = fn (int $poysha) => \App\Support\Money::format($poysha); @endphp

@section('title', 'Products')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h1 class="mb-0">Products</h1>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New product</a>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="GET" class="card mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label for="q" class="form-label small">Search</label>
                <input id="q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="Name, SKU or brand">
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label small">Category</label>
                <select id="category" name="category" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['category'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="brand" class="form-label small">Brand</label>
                <select id="brand" name="brand" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($brands as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['brand'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label small">Status</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>In the shop</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Hidden</option>
                    <option value="featured" @selected(($filters['status'] ?? '') === 'featured')>Featured</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th style="min-width: 13rem">Product</th><th class="d-none d-md-table-cell">Category</th><th class="text-end">Price</th><th class="text-end d-none d-md-table-cell">In packages</th><th class="d-none d-sm-table-cell">Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr data-product="{{ $product->slug }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($product->imageUrl())
                                        <img src="{{ $product->imageUrl() }}" alt="" width="56" height="42" class="thumb">
                                    @else
                                        <span class="thumb-placeholder" style="width: 56px; height: 42px"><i class="bi bi-image text-body-secondary"></i></span>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $product->name }}</div>
                                        <div class="small text-body-secondary">{{ $product->sku }}@if ($product->brand) · {{ $product->brand->name }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">{{ $product->category->name ?? '—' }}</td>
                            <td class="text-end tabular-nums text-nowrap">
                                {{ $money($product->price) }}
                                @if ($product->compare_at_price)<div class="small text-body-secondary text-decoration-line-through">{{ $money($product->compare_at_price) }}</div>@endif
                            </td>
                            <td class="text-end tabular-nums d-none d-md-table-cell">{{ $product->packages_count }}</td>
                            <td class="d-none d-sm-table-cell">
                                @if ($product->is_active)<span class="badge text-bg-success">In shop</span>@else<span class="badge text-bg-secondary">Hidden</span>@endif
                                @if ($product->is_featured)<span class="badge text-bg-warning">Featured</span>@endif
                            </td>
                            <td class="text-end"><a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-search"></i>No products match.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-footer">{{ $products->links() }}</div>
        @endif
    </div>
@stop
