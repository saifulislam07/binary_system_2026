@extends('adminlte::page')

@section('title', 'Edit '.$member->user->name)

@section('content_header')
    <h1>Edit member <small class="text-body-secondary">{{ $member->member_code ?? '' }}</small></h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.members.update', $member) }}" class="card" style="max-width: 720px">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $member->user->name) }}" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $member->user->email) }}" required>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Mobile</label>
                <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $member->user->phone) }}" required>
            </div>
            <div class="mb-3">
                <label for="nid" class="form-label">NID</label>
                <input id="nid" name="nid" class="form-control @error('nid') is-invalid @enderror" value="{{ old('nid', $member->nid) }}" required>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $member->address) }}</textarea>
            </div>
            <p class="small text-body-secondary mb-0">Sponsor and tree position can't be changed here — use the tree's placement adjustment tool.</p>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">Save</button>
            <a href="{{ route('admin.members.show', $member) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@stop
