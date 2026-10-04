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

    <div class="card" style="max-width: 520px">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title mb-0">Two-factor sign-in</h3>
            @if ($admin->hasTwoFactorEnabled())
                <span class="badge text-bg-success ms-auto">On</span>
            @else
                <span class="badge {{ $required ? 'text-bg-warning' : 'text-bg-secondary' }} ms-auto">{{ $required ? 'Required' : 'Off' }}</span>
            @endif
        </div>

        @if (session('recoveryCodes'))
            <div class="card-body border-bottom">
                <p class="mb-2"><strong>Recovery codes.</strong> Each one signs you in once if you lose your phone. Store them somewhere safe — they won't be shown again.</p>
                <ul class="list-unstyled font-monospace row row-cols-2 g-1 mb-0">
                    @foreach (session('recoveryCodes') as $recoveryCode)
                        <li class="col">{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($admin->hasTwoFactorEnabled())
            <div class="card-body">
                <p class="mb-1">Signing in asks for a code from your authenticator app after your password.</p>
                <p class="small text-body-secondary mb-0">
                    On since {{ $admin->two_factor_confirmed_at?->format('j M Y') }} ·
                    {{ count($admin->two_factor_recovery_codes ?? []) }} recovery codes left
                </p>
            </div>
            <div class="card-footer">
                <form method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12">
                        <label for="tf_current_password" class="form-label">Current password</label>
                        <input id="tf_current_password" type="password" name="current_password" autocomplete="current-password" class="form-control" required>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-secondary" formaction="{{ route('admin.account.two-factor.recovery-codes') }}">New recovery codes</button>
                        <button class="btn btn-outline-danger" formaction="{{ route('admin.account.two-factor.destroy') }}" name="_method" value="DELETE">Turn off</button>
                    </div>
                </form>
            </div>
        @elseif ($settingUp)
            <div class="card-body">
                <ol class="ps-3 mb-3">
                    <li>Open an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password …) and scan this code.</li>
                    <li>Enter the 6-digit code the app shows.</li>
                </ol>
                <div class="d-inline-block bg-white p-2 rounded border mb-2" aria-label="QR code for your authenticator app">{!! $qrCode !!}</div>
                <p class="small text-body-secondary">Can't scan? Enter this key: <code class="user-select-all">{{ $admin->two_factor_secret }}</code></p>
                <form method="POST" action="{{ route('admin.account.two-factor.confirm') }}" class="d-flex gap-2" style="max-width: 320px">
                    @csrf
                    <label for="code" class="visually-hidden">Code from the app</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="form-control @error('code') is-invalid @enderror" required autofocus>
                    <button class="btn btn-primary text-nowrap">Turn on</button>
                </form>
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('admin.account.two-factor.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-link px-0">Cancel setup</button>
                </form>
            </div>
        @else
            <div class="card-body">
                <p class="mb-0">
                    Add a second step to signing in: a code from an authenticator app on your phone. Someone who learns your password still can't get in.
                    @if ($required) <strong>It is required for every admin on this system.</strong> @endif
                </p>
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('admin.account.two-factor.store') }}">
                    @csrf
                    <button class="btn btn-primary">Set up two-factor sign-in</button>
                </form>
            </div>
        @endif
    </div>
@stop
