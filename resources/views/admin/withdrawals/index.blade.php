@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
@endphp

@section('title', 'Withdrawals')

@section('content_header')
    <h1>Withdrawals</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <ul class="nav nav-pills mb-3" role="tablist">
        @foreach ($statuses as $s)
            <li class="nav-item">
                <a class="nav-link {{ $s === $status ? 'active' : '' }}" href="{{ route('admin.withdrawals.index', ['status' => $s->value]) }}" @if ($s === $status) aria-current="page" @endif>
                    {{ ucfirst($s->value) }}
                    <span class="badge {{ $s === $status ? 'text-bg-light' : 'text-bg-secondary' }}">{{ $counts[$s->value]['n'] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <p class="text-body-secondary small">
        {{ ucfirst($status->value) }}: {{ $money($counts[$status->value]['total'] ?? 0) }} across {{ $counts[$status->value]['n'] ?? 0 }} requests, oldest first.
    </p>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Requested</th>
                        <th>Member</th>
                        <th class="text-end">Amount</th>
                        <th>Pay to</th>
                        <th>Last action</th>
                        <th style="min-width: 22rem">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawals as $w)
                        <tr>
                            <td>{{ $w->id }}</td>
                            <td class="text-nowrap">{{ $w->created_at?->format('d M Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.members.show', $w->member) }}">{{ $w->member->member_code }}</a>
                                <div class="small text-body-secondary">{{ $w->member->user->name }} · {{ $w->member->user->phone }}</div>
                            </td>
                            <td class="text-end fw-semibold" style="font-variant-numeric: tabular-nums">{{ $money($w->amount) }}</td>
                            <td>
                                {{ $describe($w) }}
                                {{-- Admins need the full details to actually pay. --}}
                                <details class="small">
                                    <summary>Full details</summary>
                                    @foreach ($w->account_details as $key => $value)
                                        <div><span class="text-body-secondary">{{ str_replace('_', ' ', $key) }}:</span> {{ $value }}</div>
                                    @endforeach
                                </details>
                            </td>
                            <td class="small">
                                @if ($w->admin)
                                    {{ $w->admin->name }}<br>{{ $w->updated_at?->format('d M H:i') }}
                                @endif
                                @if ($w->payout_reference)<div>Ref: {{ $w->payout_reference }}</div>@endif
                                @if ($w->rejection_reason)<div class="text-danger">{{ $w->rejection_reason }}</div>@endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    @if ($w->status->canTransitionTo(\App\Enums\WithdrawalStatus::Approved))
                                        <form method="POST" action="{{ route('admin.withdrawals.approve', $w) }}">
                                            @csrf <button class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                    @endif
                                    @if ($w->status->canTransitionTo(\App\Enums\WithdrawalStatus::Processing))
                                        <form method="POST" action="{{ route('admin.withdrawals.processing', $w) }}">
                                            @csrf <button class="btn btn-sm btn-info">Start processing</button>
                                        </form>
                                    @endif
                                    @if ($w->status->canTransitionTo(\App\Enums\WithdrawalStatus::Paid))
                                        <form method="POST" action="{{ route('admin.withdrawals.paid', $w) }}" class="d-flex gap-1">
                                            @csrf
                                            <input name="payout_reference" class="form-control form-control-sm" placeholder="Payout transaction ID" aria-label="Payout transaction ID" required>
                                            <button class="btn btn-sm btn-primary text-nowrap">Mark paid</button>
                                        </form>
                                    @endif
                                    @if ($w->status->canTransitionTo(\App\Enums\WithdrawalStatus::Rejected))
                                        <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" class="d-flex gap-1" onsubmit="return confirm('Reject and return the money to the member\'s wallet?')">
                                            @csrf
                                            <input name="reason" class="form-control form-control-sm" placeholder="Rejection reason" aria-label="Rejection reason" required>
                                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">No {{ $status->value }} withdrawals.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($withdrawals->hasPages())
            <div class="card-footer">{{ $withdrawals->links() }}</div>
        @endif
    </div>
@stop
