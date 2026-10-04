@extends('adminlte::page')

@php
    $editing = $target->exists;
    $isMe = $editing && auth('admin')->user()?->is($target);
@endphp

@section('title', $editing ? 'Edit '.$target->name : 'New admin')

@section('content_header')
    <h1>{{ $editing ? 'Edit admin' : 'New admin' }} @if ($editing)<small class="text-body-secondary">{{ $target->email }}</small>@endif</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ $editing ? route('admin.admins.update', $target) : route('admin.admins.store') }}" class="card" style="max-width: 640px" autocomplete="off">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $target->name) }}" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email (sign-in)</label>
                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $target->email) }}" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" @disabled($isMe)>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', $currentRole ?? 'support') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                    @if ($isMe)<input type="hidden" name="role" value="{{ $currentRole }}">@endif
                </div>
                @if ($editing)
                    <div class="col-md-6">
                        <label for="is_active" class="form-label">Status</label>
                        <select id="is_active" name="is_active" class="form-select" @disabled($isMe)>
                            <option value="1" @selected((string) old('is_active', (int) $target->is_active) === '1')>Active</option>
                            <option value="0" @selected((string) old('is_active', (int) $target->is_active) === '0')>Deactivated — can't sign in</option>
                        </select>
                        @if ($isMe)<input type="hidden" name="is_active" value="1">@endif
                    </div>
                @endif
            </div>
            @if ($isMe)
                <p class="small text-body-secondary">You can't change your own role or deactivate yourself — ask another admin.</p>
            @endif

            <fieldset class="border-top pt-3">
                <legend class="fs-6">{{ $editing ? 'Set a new password (optional)' : 'Password' }}</legend>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" @required(! $editing)>
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Repeat password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="form-control" @required(! $editing)>
                    </div>
                </div>
                <div class="form-text">Share it privately; they can change it under “My account”.</div>
            </fieldset>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Save admin' : 'Create admin' }}</button>
            <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    @if ($editing && ! $isMe)
        <div class="card" style="max-width: 640px">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0">Two-factor sign-in</h3>
                <span class="badge {{ $target->hasTwoFactorEnabled() ? 'text-bg-success' : 'text-bg-secondary' }} ms-auto">{{ $target->hasTwoFactorEnabled() ? 'On' : 'Off' }}</span>
            </div>
            @if ($target->hasTwoFactorEnabled())
                <form method="POST" action="{{ route('admin.admins.two-factor.reset', $target) }}" class="card-body d-flex flex-wrap align-items-center gap-2"
                      onsubmit="return confirm('Turn off two-factor sign-in for this admin? Only do this after checking who is asking.')">
                    @csrf
                    @method('DELETE')
                    <span class="me-auto small">Lost their phone and recovery codes? Reset it so they can sign in with their password and set it up again.</span>
                    <button class="btn btn-outline-danger">Reset two-factor</button>
                </form>
            @else
                <div class="card-body small text-body-secondary">They can turn it on under “My account”.</div>
            @endif
        </div>
    @endif
@stop
