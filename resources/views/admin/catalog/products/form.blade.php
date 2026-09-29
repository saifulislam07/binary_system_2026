@extends('adminlte::page')

@php
    $editing = $product->exists;
    $plain = fn (?int $scaled) => $scaled === null ? '' : \App\Support\Money::toInputString($scaled);
    $features = array_values(array_filter((array) old('highlights', $product->highlightList()), fn ($f) => is_string($f)));
    $photos = $editing ? $product->getMedia('images') : collect();
    $newBrand = old('new_brand');
@endphp

@section('title', $editing ? 'Edit '.$product->name : 'New product')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1>{{ $editing ? 'Edit product' : 'New product' }} @if ($editing)<small>{{ $product->name }}</small>@endif</h1>
            <p class="page-lead">Products are sold inside packages — add this one to a package so members can buy it.</p>
        </div>
        @if ($editing && $product->is_active)
            <a href="{{ route('shop.show', $product) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> View in shop</a>
        @endif
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data" id="product-form" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row">
            <div class="col-xl-8">
                {{-- Basics --}}
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Product details</h3></div>
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
                            <div class="col-md-6" data-brand-picker>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <label for="brand_id" class="form-label">Brand</label>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-brand-new-toggle @if ($newBrand) hidden @endif><i class="bi bi-plus-lg"></i> New brand</button>
                                </div>
                                <select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror" @if ($newBrand) hidden @endif>
                                    <option value="">— No brand —</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brand->id)>{{ $brand->name }}{{ $brand->is_active ? '' : ' (hidden)' }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group" data-brand-new @unless ($newBrand) hidden @endunless>
                                    <input id="new_brand" name="new_brand" maxlength="100" class="form-control @error('new_brand') is-invalid @enderror" value="{{ $newBrand }}" placeholder="New brand name" aria-label="New brand name">
                                    <button type="button" class="btn btn-outline-secondary" data-brand-new-cancel>Pick existing</button>
                                </div>
                                <div class="form-text">Manage logos under <a href="{{ route('admin.brands.index') }}">Brands</a>.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Description</h3></div>
                    <div class="card-body">
                        <label for="description" class="visually-hidden">Description</label>
                        <textarea id="description" name="description" rows="8" class="form-control @error('description') is-invalid @enderror" data-rich-text placeholder="Describe the product: what it is, who it's for, what's in the box.">{{ old('description', $product->descriptionHtml()) }}</textarea>
                        <div class="form-text">Headings, bold, lists and links are kept; anything else is removed when saved.</div>
                    </div>
                </div>

                {{-- Key features --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Key features</h3>
                        <span class="small text-body-secondary" data-feature-count></span>
                    </div>
                    <div class="card-body">
                        <p class="form-section-help mb-3">Short points shown with a check mark on the product page. Drag <i class="bi bi-grip-vertical"></i> to reorder; press Enter to add the next one.</p>
                        <div class="feature-list" data-feature-list data-max="20">
                            @foreach ($features ?: [''] as $i => $feature)
                                <div class="feature-row" data-feature-row>
                                    <span class="feature-handle" aria-hidden="true" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                                    <i class="bi bi-check-circle-fill feature-check" aria-hidden="true"></i>
                                    <input name="highlights[]" maxlength="200" class="form-control @error('highlights.'.$i) is-invalid @enderror" value="{{ $feature }}" aria-label="Key feature {{ $i + 1 }}">
                                    <button type="button" class="btn btn-outline-secondary" data-feature-remove aria-label="Remove this feature"><i class="bi bi-x-lg"></i></button>
                                </div>
                            @endforeach
                        </div>
                        <template data-feature-template>
                            <div class="feature-row" data-feature-row>
                                <span class="feature-handle" aria-hidden="true" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                                <i class="bi bi-check-circle-fill feature-check" aria-hidden="true"></i>
                                <input name="highlights[]" maxlength="200" class="form-control" placeholder="e.g. Up to 30 hours battery" aria-label="Key feature">
                                <button type="button" class="btn btn-outline-secondary" data-feature-remove aria-label="Remove this feature"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </template>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-3" data-feature-add><i class="bi bi-plus-lg"></i> Add feature</button>
                        @error('highlights')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Photos --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Photos</h3>
                        <span class="small text-body-secondary" data-photo-count></span>
                    </div>
                    <div class="card-body" data-uploader data-max-new="12" data-max-bytes="3145728">
                        <div class="uploader-grid" data-uploader-grid>
                            @foreach ($photos as $media)
                                <div class="uploader-item" data-media-id="{{ $media->id }}" data-url="{{ $media->getUrl('card') }}">
                                    <img src="{{ $media->getUrl('card') }}" alt="">
                                    <label class="uploader-actions small">
                                        <input type="checkbox" name="remove_images[]" value="{{ $media->id }}" class="form-check-input" @checked(in_array((string) $media->id, (array) old('remove_images', []), true))> Remove
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="uploader-drop" data-uploader-drop tabindex="0" role="button" aria-controls="images">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <div class="fw-semibold mt-1">Drop photos here or click to choose</div>
                            <div class="small">Pick several at once, or add more in batches. JPG, PNG or WebP, at least 400×300 px, up to 3 MB each.</div>
                        </div>
                        <input id="images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="form-control mt-2 @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" data-uploader-input aria-label="Add photos">
                        <div data-uploader-order></div>
                        <div class="text-danger small mt-2" data-uploader-error role="alert">@error('images'){{ $message }}@enderror @error('images.*'){{ $message }}@enderror</div>
                        <div class="form-text">The first photo is the cover on shop cards (cropped to 4:3). Drag photos to reorder.</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Pricing</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="price" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input id="price" name="price" type="number" step="0.01" min="0.01" inputmode="decimal" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plain($product->price)) }}" required>
                            </div>
                        </div>
                        <div>
                            <label for="compare_at_price" class="form-label">"Was" price <span class="text-body-secondary small fw-normal">(optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input id="compare_at_price" name="compare_at_price" type="number" step="0.01" min="0.01" inputmode="decimal" class="form-control @error('compare_at_price') is-invalid @enderror" value="{{ old('compare_at_price', $plain($product->compare_at_price)) }}">
                            </div>
                            <div class="form-text">Only a real previous price — shown struck through with the saving. <span class="fw-semibold text-success" data-discount></span></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Visibility</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked((string) old('is_active', (int) $product->is_active) === '1')>
                            <label class="form-check-label" for="is_active"><span class="fw-semibold">Shown in the shop</span><br><span class="small text-body-secondary">Hidden products stay in packages and past orders.</span></label>
                        </div>
                        <input type="hidden" name="is_featured" value="0">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" @checked((string) old('is_featured', (int) $product->is_featured) === '1')>
                            <label class="form-check-label" for="is_featured"><span class="fw-semibold">Featured</span><br><span class="small text-body-secondary">Shown on the home page and first in the shop.</span></label>
                        </div>
                        <label for="sort_order" class="form-label">Display order</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="1000" class="form-control" value="{{ old('sort_order', $product->sort_order) }}" required>
                        <div class="form-text">Lower numbers come first.</div>
                    </div>
                </div>

                @if ($editing)
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Sold in packages</h3></div>
                        <div class="card-body">
                            @forelse ($product->packages as $package)
                                <span class="badge text-bg-primary me-1 mb-1">{{ $package->name }} × {{ $package->pivot->quantity }}</span>
                            @empty
                                <p class="small text-body-secondary mb-0">Not in any package yet — add it from Settings → Packages so members can buy it.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card sticky-actions">
            <div class="card-body d-flex gap-2 py-3">
                <button class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ $editing ? 'Save product' : 'Create product' }}</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
@stop

@push('js')
    @vite('resources/js/admin/product-form.ts')
@endpush
