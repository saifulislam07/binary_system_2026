@extends('adminlte::page')

@php $editing = $brand->exists; @endphp

@section('title', $editing ? 'Edit '.$brand->name : 'New brand')

@section('content_header')
    <h1>{{ $editing ? 'Edit brand' : 'New brand' }} @if ($editing)<small>{{ $brand->name }}</small>@endif</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" class="card" style="max-width: 720px" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" maxlength="100" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $brand->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="1000" class="form-control" value="{{ old('sort_order', $brand->sort_order) }}" required>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Short description <span class="text-body-secondary small fw-normal">(optional)</span></label>
                    <input id="description" name="description" maxlength="500" class="form-control" value="{{ old('description', $brand->description) }}">
                </div>
                <div class="col-md-6">
                    <label for="is_active" class="form-label">In the shop</label>
                    <select id="is_active" name="is_active" class="form-select">
                        <option value="1" @selected((string) old('is_active', (int) $brand->is_active) === '1')>Shown</option>
                        <option value="0" @selected((string) old('is_active', (int) $brand->is_active) === '0')>Hidden from the brand list</option>
                    </select>
                    <div class="form-text">Hiding a brand doesn't hide its products.</div>
                </div>
                <div class="col-12">
                    <label for="logo" class="form-label">Logo <span class="text-body-secondary small fw-normal">(optional; PNG with a transparent background works best)</span></label>
                    @if ($editing && $brand->logoUrl())
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="{{ $brand->logoUrl() }}" alt="" width="140" height="70" class="thumb bg-white" style="object-fit: contain; padding: 6px">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label" for="remove_logo">Remove logo</label>
                            </div>
                        </div>
                    @endif
                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('logo') is-invalid @enderror">
                    <div class="form-text">JPG, PNG or WebP, at least 80×40 px, up to 1 MB.</div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Save brand' : 'Create brand' }}</button>
            <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@stop
