@extends('adminlte::page')

@php $editing = $category->exists; @endphp

@section('title', $editing ? 'Edit '.$category->name : 'New category')

@section('content_header')
    <h1>{{ $editing ? 'Edit category' : 'New category' }} @if ($editing)<small class="text-body-secondary">{{ $category->name }}</small>@endif</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card" style="max-width: 720px" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name (English)</label>
                    <input id="name" name="name" maxlength="100" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label for="name_bn" class="form-label">নাম (বাংলা)</label>
                    <input id="name_bn" name="name_bn" maxlength="100" class="form-control @error('name_bn') is-invalid @enderror" value="{{ old('name_bn', $category->name_bn) }}" lang="bn">
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Short description</label>
                    <input id="description" name="description" maxlength="500" class="form-control" value="{{ old('description', $category->description) }}">
                </div>
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="1000" class="form-control" value="{{ old('sort_order', $category->sort_order) }}" required>
                </div>
                <div class="col-md-8">
                    <label for="is_active" class="form-label">In the shop</label>
                    <select id="is_active" name="is_active" class="form-select">
                        <option value="1" @selected((string) old('is_active', (int) $category->is_active) === '1')>Shown</option>
                        <option value="0" @selected((string) old('is_active', (int) $category->is_active) === '0')>Hidden (with its products)</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="image" class="form-label">Tile image <span class="text-body-secondary small">(optional; otherwise the first product photo is used)</span></label>
                    @if ($editing && $category->imageUrl())
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="{{ $category->imageUrl() }}" alt="" width="120" height="90" class="rounded border" style="object-fit: cover">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                                <label class="form-check-label" for="remove_image">Remove image</label>
                            </div>
                        </div>
                    @endif
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Save category' : 'Create category' }}</button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@stop
