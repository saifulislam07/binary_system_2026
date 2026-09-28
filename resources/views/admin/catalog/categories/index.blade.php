@extends('adminlte::page')

@section('title', 'Categories')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h1 class="mb-0">Categories</h1>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New category</a>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <p class="text-body-secondary small">Categories group products in the shop. Deactivating one hides it and its products from the shop; nothing is deleted.</p>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Order</th><th>Category</th><th class="text-end">Products</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr data-category="{{ $category->slug }}">
                            <td>{{ $category->sort_order }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($category->imageUrl())<img src="{{ $category->imageUrl() }}" alt="" width="48" height="36" class="rounded border" style="object-fit: cover">@endif
                                    <div>
                                        <div class="fw-semibold">{{ $category->name }} @if ($category->name_bn)<span class="text-body-secondary fw-normal">· {{ $category->name_bn }}</span>@endif</div>
                                        @if ($category->description)<div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($category->description, 90) }}</div>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($category->products_count) }}</td>
                            <td>@if ($category->is_active)<span class="badge text-bg-success">Shown</span>@else<span class="badge text-bg-secondary">Hidden</span>@endif</td>
                            <td class="text-end">
                                <a href="{{ route('admin.products.index', ['category' => $category->id]) }}" class="btn btn-sm btn-outline-secondary">Products</a>
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
