@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Two-factor sign-in')

@section('auth_header', 'Enter your sign-in code')

@section('auth_body')
    <form action="{{ route('admin.two-factor.verify') }}" method="post">
        @csrf

        <p class="text-body-secondary small">Open your authenticator app and enter the 6-digit code for this account.</p>

        <label for="code" class="visually-hidden">Authenticator code</label>
        <div class="input-group mb-3">
            <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20"
                class="form-control @error('code') is-invalid @enderror" placeholder="123456" autofocus>
            <div class="input-group-text"><span class="bi bi-shield-lock"></span></div>
            @error('code')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
            @enderror
        </div>

        <details class="mb-3" @if (old('recovery_code')) open @endif>
            <summary class="small">Lost your phone? Use a recovery code</summary>
            <label for="recovery_code" class="visually-hidden">Recovery code</label>
            <input type="text" name="recovery_code" id="recovery_code" autocomplete="off" maxlength="30"
                class="form-control mt-2" placeholder="abcde-fghij">
            <div class="form-text">Leave the code above empty. Each recovery code works once.</div>
        </details>

        <div class="d-grid">
            <button type="submit" class="btn {{ config('adminlte.classes_auth_btn', 'btn-primary') }}">
                <i class="bi bi-box-arrow-in-right me-1"></i> Verify
            </button>
        </div>
    </form>
@stop

@section('auth_footer')
    <p class="my-0 small">No codes left? Ask an admin who manages admin accounts to reset your two-factor sign-in.</p>
    <p class="my-0"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@stop
