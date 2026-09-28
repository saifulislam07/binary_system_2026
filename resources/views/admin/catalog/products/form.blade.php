@extends('adminlte::page')

@php
    $editing = $product->exists;
    $plain = fn (?int $scaled) => $scaled === null ? '' : \App\Support\Money::toInputString($scaled);
@endphp

@section('title', $editing ? 'Edit '.$product->name : 'New product')

@section('content_header')
    <h1>{{ $editing ? 'Edit product' : 'New product' }} @if ($editing)<small class="text-body-secondary">{{ $product->name }}</small>@endif</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card" style="max-width: 900px" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="sku" class="form-label">SKU</label>
                    <input id="sku" name="sku" maxlength="60" class="form-control text-uppercase @error('sku') is-invalid @enderror" value="{{ old('sku', $product->sku) }}" required>
                </div>
                <div class="col-md-6">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('category_id', $product->category_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="brand" class="form-label">Brand <span class="text-body-secondary small">(optional)</span></label>
                    <input id="brand" name="brand" maxlength="100" class="form-control" value="{{ old('brand', $product->brand) }}">
                </div>
                <div class="col-md-4">
                    <label for="price" class="form-label">Price (৳)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0.01" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plain($product->price)) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="compare_at_price" class="form-label">"Was" price (৳) <span class="text-body-secondary small">(optional)</span></label>
                    <input id="compare_at_price" name="compare_at_price" type="number" step="0.01" min="0.01" class="form-control @error('compare_at_price') is-invalid @enderror" value="{{ old('compare_at_price', $plain($product->compare_at_price)) }}">
                    <div class="form-text">Only a real previous price — shown struck through with the saving.</div>
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="1000" class="form-control" value="{{ old('sort_order', $product->sort_order) }}" required>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="4" maxlength="5000" class="form-control">{{ old('description', $product->description) }}</textarea>
                </div>
                <div class="col-12">
                    <label for="highlights" class="form-label">Key features <span class="text-body-secondary small">(one per line)</span></label>
                    <textarea id="highlights" name="highlights" rows="4" maxlength="2000" class="form-control" placeholder="Up to 30 hours battery&#10;Bluetooth 5.3">{{ old('highlights', $product->highlights) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="is_active" class="form-label">In the shop</label>
                    <select id="is_active" name="is_active" class="form-select">
                        <option value="1" @selected((string) old('is_active', (int) $product->is_active) === '1')>Shown</option>
                        <option value="0" @selected((string) old('is_active', (int) $product->is_active) === '0')>Hidden</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="is_featured" class="form-label">Featured on the home page</label>
                    <select id="is_featured" name="is_featured" class="form-select">
                        <option value="0" @selected((string) old('is_featured', (int) $product->is_featured) === '0')>No</option>
                        <option value="1" @selected((string) old('is_featured', (int) $product->is_featured) === '1')>Yes</option>
                    </select>
                </div>

                <div class="col-12">
                    <span class="form-label d-block">Photos <span class="text-body-secondary small">(the first is the cover; cards are cropped to 4:3)</span></span>
                    @if ($editing && $product->getMedia('images')->isNotEmpty())
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            @foreach ($product->getMedia('images') as $media)
                                <label class="text-center small" style="width: 128px">
                                    <img src="{{ $media->getUrl('card') }}" alt="" width="128" height="96" class="rounded border d-block mb-1" style="object-fit: cover">
                                    <input type="checkbox" name="remove_images[]" value="{{ $media->id }}" class="form-check-input"> Remove
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input id="images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="form-control @error('images.*') is-invalid @enderror" aria-label="Add photos">
                    <div class="form-text">JPG, PNG or WebP, at least 400×300 px, up to 3 MB each, 8 at a time.</div>
                </div>

                @if ($editing)
                    <div class="col-12">
                        <span class="form-label d-block">Sold in packages</span>
                        @forelse ($product->packages as $package)
                            <span class="badge text-bg-light border">{{ $package->name }} × {{ $package->pivot->quantity }}</span>
                        @empty
                            <span class="small text-body-secondary">Not in any package yet — add it from Settings → Packages so members can buy it.</span>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Save product' : 'Create product' }}</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@stop
