@extends('adminlte::page')

@php
    use App\Models\CommissionRule as R;
    $pct = fn (?string $bps) => rtrim(rtrim(number_format(((int) $bps) / 100, 2, '.', ''), '0'), '.');
    $taka = fn (int|string|null $poysha) => rtrim(rtrim(number_format(((int) $poysha) / 100, 2, '.', ''), '0'), '.');
@endphp

@section('title', 'Settings')

@section('content_header')
    <h1>Settings</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.settings.update') }}" class="card" style="max-width: 760px" onsubmit="return confirm('Save these business settings? The change is logged.')">
        @csrf
        @method('PUT')
        <div class="card-header"><h3 class="card-title">Commission rules</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="binary_rate" class="form-label">Binary commission rate (%)</label>
                    <input id="binary_rate" name="binary_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ old('binary_rate', $pct($rules[R::BINARY_RATE_BPS] ?? '0')) }}" required>
                    <div class="form-text">Of matched BV each cycle.</div>
                </div>
                <div class="col-md-6">
                    <label for="referral_rate" class="form-label">Referral bonus rate (%)</label>
                    <input id="referral_rate" name="referral_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ old('referral_rate', $pct($rules[R::REFERRAL_RATE_BPS] ?? '0')) }}" required>
                    <div class="form-text">Of each qualifying sale, paid to the sponsor.</div>
                </div>
                <div class="col-md-4">
                    <label for="daily_cap" class="form-label">Daily cap (৳)</label>
                    <input id="daily_cap" name="daily_cap" type="number" step="0.01" min="0" class="form-control" value="{{ old('daily_cap', $taka($rules[R::DAILY_CAP] ?? 0)) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="weekly_cap" class="form-label">Weekly cap (৳)</label>
                    <input id="weekly_cap" name="weekly_cap" type="number" step="0.01" min="0" class="form-control" value="{{ old('weekly_cap', $taka($rules[R::WEEKLY_CAP] ?? 0)) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="monthly_cap" class="form-label">Monthly cap (৳)</label>
                    <input id="monthly_cap" name="monthly_cap" type="number" step="0.01" min="0" class="form-control" value="{{ old('monthly_cap', $taka($rules[R::MONTHLY_CAP] ?? 0)) }}" required>
                </div>
                <div class="col-12 form-text mt-0">A cap of 0 means no cap for that window.</div>
                <div class="col-md-6">
                    <label for="cap_overflow_behavior" class="form-label">Commission above a cap</label>
                    <select id="cap_overflow_behavior" name="cap_overflow_behavior" class="form-select">
                        <option value="void" @selected(($rules[R::CAP_OVERFLOW_BEHAVIOR] ?? '') === 'void')>Void it</option>
                        <option value="carry_forward" @selected(($rules[R::CAP_OVERFLOW_BEHAVIOR] ?? '') === 'carry_forward')>Carry it forward to later cycles</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="carry_forward_enabled" class="form-label">Unmatched volume</label>
                    <select id="carry_forward_enabled" name="carry_forward_enabled" class="form-select">
                        <option value="1" @selected(($rules[R::CARRY_FORWARD_ENABLED] ?? '1') === '1')>Carry forward to the next cycle</option>
                        <option value="0" @selected(($rules[R::CARRY_FORWARD_ENABLED] ?? '1') === '0')>Discard (flush) each cycle</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-header border-top"><h3 class="card-title">Withdrawals</h3></div>
        <div class="card-body">
            <label for="min_withdrawal" class="form-label">Minimum withdrawal (৳)</label>
            <input id="min_withdrawal" name="min_withdrawal" type="number" step="0.01" min="1" class="form-control" style="max-width: 240px" value="{{ old('min_withdrawal', $taka($minWithdrawal)) }}" required>
        </div>
        <div class="card-footer"><button class="btn btn-primary">Save settings</button></div>
    </form>
@stop
