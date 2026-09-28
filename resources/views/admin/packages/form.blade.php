@extends('adminlte::page')

@php
    $editing = $package->exists;
    $plain = fn (?int $scaled) => $scaled === null ? '' : \App\Support\Money::toInputString($scaled);
@endphp

@section('title', $editing ? 'Edit '.$package->name : 'New package')

@section('content_header')
    <h1>{{ $editing ? 'Edit package' : 'New package' }} @if ($editing)<small class="text-body-secondary">{{ $package->name }}</small>@endif</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.packages.update', $package) : route('admin.packages.store') }}" class="card" style="max-width: 760px" enctype="multipart/form-data"
          @if ($editing) onsubmit="return confirm('Save this package? New prices and BV apply to future orders only. The change is logged.')" @endif>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" maxlength="100" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $package->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="1000" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $package->sort_order) }}" required>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description <span class="text-body-secondary small">(shown to members)</span></label>
                    <textarea id="description" name="description" rows="2" maxlength="1000" class="form-control @error('description') is-invalid @enderror">{{ old('description', $package->description) }}</textarea>
                </div>
                <div class="col-md-4">
                    <label for="price" class="form-label">Price (৳)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0.01" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plain($package->price)) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="bv_value" class="form-label">Business volume (BV)</label>
                    <input id="bv_value" name="bv_value" type="number" step="0.01" min="0" class="form-control @error('bv_value') is-invalid @enderror" value="{{ old('bv_value', $plain($package->bv_value)) }}" required>
                    <div class="form-text">What flows up the tree and gets matched.</div>
                </div>
                <div class="col-md-4">
                    <label for="cost_of_goods" class="form-label">Cost of goods (৳)</label>
                    <input id="cost_of_goods" name="cost_of_goods" type="number" step="0.01" min="0" class="form-control @error('cost_of_goods') is-invalid @enderror" value="{{ old('cost_of_goods', $plain($package->cost_of_goods ?? 0)) }}" required>
                    <div class="form-text">For the profit reports.</div>
                </div>
                <div class="col-12">
                    <label for="image" class="form-label">Product photo <span class="text-body-secondary small">(shop card, cropped to 4:3)</span></label>
                    @if ($editing && $package->imageUrl())
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="{{ $package->imageUrl() }}" alt="" width="160" height="120" class="rounded border" style="object-fit: cover">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                                <label class="form-check-label" for="remove_image">Remove photo</label>
                            </div>
                        </div>
                    @endif
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror">
                    <div class="form-text">JPG, PNG or WebP, at least 400×300 px, up to 2 MB. Leave empty to keep the current photo.</div>
                </div>
                <div class="col-md-6">
                    <label for="is_qualifying" class="form-label">Referral bonus</label>
                    <select id="is_qualifying" name="is_qualifying" class="form-select">
                        <option value="1" @selected((string) old('is_qualifying', (int) $package->is_qualifying) === '1')>Qualifying — the sponsor earns a referral bonus</option>
                        <option value="0" @selected((string) old('is_qualifying', (int) $package->is_qualifying) === '0')>Not qualifying — no referral bonus</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="is_active" class="form-label">Availability</label>
                    <select id="is_active" name="is_active" class="form-select">
                        <option value="1" @selected((string) old('is_active', (int) $package->is_active) === '1')>On sale</option>
                        <option value="0" @selected((string) old('is_active', (int) $package->is_active) === '0')>Inactive — hidden at sign-up and checkout</option>
                    </select>
                </div>

                @if ($products->isNotEmpty())
                    <fieldset class="col-12">
                        <legend class="form-label fs-6 mb-1">What's inside <span class="text-body-secondary small">(quantity; leave blank for products not in this package)</span></legend>
                        <p class="form-text mt-0">The shop shows each product's packages, so members can buy it through them.</p>
                        <div class="row g-2">
                            @foreach ($products as $categoryName => $group)
                                <div class="col-12"><div class="small fw-semibold text-body-secondary mt-2">{{ $categoryName }}</div></div>
                                @foreach ($group as $item)
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text flex-grow-1 text-truncate" style="max-width: 80%">
                                                {{ $item->name }} @unless ($item->is_active)<span class="badge text-bg-secondary ms-1">hidden</span>@endunless
                                            </span>
                                            <input type="number" name="products[{{ $item->id }}]" min="0" max="99" class="form-control" style="max-width: 5rem"
                                                   value="{{ old('products.'.$item->id, $included[$item->id] ?? '') }}" aria-label="Quantity of {{ $item->name }}">
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Save package' : 'Create package' }}</button>
            <a href="{{ route('admin.packages.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@stop
