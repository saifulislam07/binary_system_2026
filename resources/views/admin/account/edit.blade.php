@extends('adminlte::page')

@section('title', 'My account')

@section('content_header')
    <h1>My account <small class="text-body-secondary">{{ $admin->email }}</small></h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.account.password') }}" class="card" style="max-width: 520px">
        @csrf
        @method('PUT')
        <div class="card-header"><h3 class="card-title">Change password</h3></div>
        <div class="card-body">
            <div class="mb-3">
                <label for="current_password" class="form-label">Current password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">New password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" required>
            </div>
            <div class="mb-0">
                <label for="password_confirmation" class="form-label">Repeat new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="form-control" required>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary">Change password</button></div>
    </form>
@stop
