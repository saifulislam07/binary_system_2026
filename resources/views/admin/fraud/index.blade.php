@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
    $labels = \App\Models\FraudFlag::LABELS;
@endphp

@section('title', 'Fraud flags')

@section('content_header')
    <h1>Fraud flags</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <p class="text-body-secondary small">Flags are raised automatically for review. They never block the member — decide here, and act from the member, sale or withdrawal screens.</p>

    <ul class="nav nav-pills mb-3">
        @foreach (['open', 'reviewed', 'dismissed'] as $s)
            <li class="nav-item">
                <a class="nav-link {{ $s === $status ? 'active' : '' }}" href="{{ route('admin.fraud.index', ['status' => $s]) }}" @if ($s === $status) aria-current="page" @endif>
                    {{ ucfirst($s) }} <span class="badge {{ $s === $status ? 'text-bg-light' : 'text-bg-secondary' }}">{{ $counts[$s] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Raised</th><th>Member</th><th>Why</th><th>Details</th><th style="min-width: 22rem">{{ $status === 'open' ? 'Decision' : 'Outcome' }}</th></tr></thead>
                <tbody>
                    @forelse ($flags as $flag)
                        <tr data-flag="{{ $flag->type }}">
                            <td class="text-nowrap">{{ $flag->created_at?->format('d M Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.members.show', $flag->member) }}">{{ $flag->member->member_code ?? '(pending)' }}</a>
                                <div class="small text-body-secondary">{{ $flag->member->user->name }}</div>
                            </td>
                            <td>
                                <span class="badge text-bg-warning"><i class="bi bi-flag-fill"></i> {{ $labels[$flag->type] ?? $flag->type }}</span>
                                @if ($flag->subject instanceof \App\Models\Withdrawal)
                                    <div class="small"><a href="{{ route('admin.withdrawals.index', ['status' => $flag->subject->status->value]) }}">Withdrawal #{{ $flag->subject->id }}</a> · {{ $money($flag->subject->amount) }}</div>
                                @elseif ($flag->subject instanceof \App\Models\Order)
                                    <div class="small">Order {{ $flag->subject->order_number }}</div>
                                @endif
                            </td>
                            <td class="small">
                                @foreach ($flag->details as $key => $value)
                                    <div>
                                        <span class="text-body-secondary">{{ str_replace('_', ' ', $key) }}:</span>
                                        {{ is_int($value) && in_array($key, ['amount', 'withdrawn', 'earned', 'excess'], true) ? $money($value) : (is_scalar($value) ? $value : json_encode($value)) }}
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @if ($flag->status === 'open')
                                    <form method="POST" action="{{ route('admin.fraud.review', $flag) }}" class="d-flex flex-wrap gap-1">
                                        @csrf
                                        <input name="note" class="form-control form-control-sm" placeholder="What did you check / decide?" aria-label="Review note" required>
                                        <button name="outcome" value="reviewed" class="btn btn-sm btn-primary">Reviewed</button>
                                        <button name="outcome" value="dismissed" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                    </form>
                                @else
                                    <div class="small">{{ ucfirst($flag->status) }} by {{ $flag->reviewer?->name ?? '—' }} · {{ $flag->reviewed_at?->format('d M Y') }}</div>
                                    <div class="small text-body-secondary">{{ $flag->review_note }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">No {{ $status }} flags.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($flags->hasPages())
            <div class="card-footer">{{ $flags->links() }}</div>
        @endif
    </div>
@stop
