@extends('adminlte::page')

@php $editing = $section->exists; @endphp

@section('title', $editing ? 'Edit section' : 'New section')

@section('content_header')
    <h1>{{ $editing ? 'Edit section' : 'New section' }} @if ($editing)<small>{{ $section->title_en }}</small>@endif</h1>
    <p class="page-lead">Shown on the public membership page. Leave the Bangla side empty to show the English text to Bangla readers.</p>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.membership.update', $section) : route('admin.membership.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">English</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title_en" class="form-label">Title</label>
                            <input id="title_en" name="title_en" maxlength="150" class="form-control @error('title_en') is-invalid @enderror" value="{{ old('title_en', $section->title_en) }}" required>
                        </div>
                        <label for="body_en" class="form-label">Text</label>
                        <textarea id="body_en" name="body_en" rows="10" class="form-control @error('body_en') is-invalid @enderror" data-rich-text placeholder="What members should know…">{{ old('body_en', $section->body_en) }}</textarea>
                        @error('body_en')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">বাংলা</h3></div>
                    <div class="card-body" lang="bn">
                        <div class="mb-3">
                            <label for="title_bn" class="form-label">শিরোনাম <span class="text-body-secondary small fw-normal">(optional)</span></label>
                            <input id="title_bn" name="title_bn" maxlength="150" class="form-control @error('title_bn') is-invalid @enderror" value="{{ old('title_bn', $section->title_bn) }}">
                        </div>
                        <label for="body_bn" class="form-label">লেখা <span class="text-body-secondary small fw-normal">(optional)</span></label>
                        <textarea id="body_bn" name="body_bn" rows="10" class="form-control @error('body_bn') is-invalid @enderror" data-rich-text placeholder="সদস্যদের যা জানা দরকার…">{{ old('body_bn', $section->body_bn) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="max-width: 720px">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Display order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="1000" class="form-control" value="{{ old('sort_order', $section->sort_order) }}" required>
                </div>
                <div class="col-md-8">
                    <label for="is_active" class="form-label">On the page</label>
                    <select id="is_active" name="is_active" class="form-select">
                        <option value="1" @selected((string) old('is_active', (int) $section->is_active) === '1')>Shown</option>
                        <option value="0" @selected((string) old('is_active', (int) $section->is_active) === '0')>Hidden</option>
                    </select>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ $editing ? 'Save section' : 'Add section' }}</button>
                <a href="{{ route('admin.membership.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
@stop

@push('js')
    @vite('resources/js/admin/rich-text.ts')
@endpush
