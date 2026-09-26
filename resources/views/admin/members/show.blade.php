@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
    $node = $member->binaryNode;
@endphp

@section('title', $member->member_code ?? $member->user->name)

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="m-0">
            {{ $member->user->name }}
            <small class="text-body-secondary">{{ $member->member_code ?? 'no code yet' }}</small>
            <span class="fs-6 align-middle">@include('admin.partials.status-badge', ['status' => $member->status->value])</span>
        </h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.members.edit', $member) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @can('manage-tree')
                @if ($node)
                    <a href="{{ route('admin.tree.index', ['member' => $member->member_code]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-diagram-3"></i> Tree</a>
                @endif
            @endcan
        </div>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    @if ($openFlags > 0)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-flag-fill"></i>
            This member has {{ $openFlags }} open fraud flag(s).
            @can('manage-members')<a href="{{ route('admin.fraud.index') }}" class="alert-link">Review</a>@endcan
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Profile</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $member->user->email }}</dd>
                        <dt class="col-sm-4">Mobile</dt><dd class="col-sm-8">{{ $member->user->phone ?? '—' }}</dd>
                        <dt class="col-sm-4">NID</dt><dd class="col-sm-8">{{ $member->nid ?? '—' }}</dd>
                        <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $member->address ?? '—' }}</dd>
                        <dt class="col-sm-4">Package</dt><dd class="col-sm-8">{{ $member->package?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Rank</dt><dd class="col-sm-8">{{ $member->currentRank?->name ?? 'Member' }}</dd>
                        <dt class="col-sm-4">Registered</dt><dd class="col-sm-8">{{ $member->created_at?->format('d M Y H:i') }}</dd>
                        <dt class="col-sm-4">Activated</dt><dd class="col-sm-8">{{ $member->activated_at?->format('d M Y H:i') ?? '—' }}</dd>
                        <dt class="col-sm-4">Registration IP</dt><dd class="col-sm-8">{{ $member->user->ip_registered ?? '—' }}</dd>
                        <dt class="col-sm-4">Device</dt><dd class="col-sm-8 small text-break">{{ $member->user->device_registered ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Sponsor &amp; placement</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Sponsor</dt>
                        <dd class="col-sm-8">
                            @if ($member->sponsor)
                                <a href="{{ route('admin.members.show', $member->sponsor) }}">{{ $member->sponsor->member_code }}</a>
                                · {{ $member->sponsor->user->name }}
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4">Placed under</dt>
                        <dd class="col-sm-8">
                            @if ($member->placementParent)
                                <a href="{{ route('admin.members.show', $member->placementParent) }}">{{ $member->placementParent->member_code }}</a>
                                ({{ $member->placement_side?->value }} side)
                            @elseif ($node)
                                Top of the tree
                            @else
                                Not placed yet (preferred: {{ $member->preferred_side?->value ?? '—' }})
                            @endif
                        </dd>
                        <dt class="col-sm-4">Personally sponsored</dt><dd class="col-sm-8">{{ $directReferrals }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Team &amp; money</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Wallet balance</dt><dd class="col-sm-7 fw-semibold" data-value="balance">{{ $money($balance) }}</dd>
                        <dt class="col-sm-5">Personal sales</dt><dd class="col-sm-7">{{ $money($personalSales) }}</dd>
                        @if ($legs && $node)
                            <dt class="col-sm-5">Left team</dt>
                            <dd class="col-sm-7">{{ $legs['left']['total'] }} members ({{ $legs['left']['active'] }} active) · {{ number_format(intdiv($node->left_lifetime_volume, 100)) }} BV</dd>
                            <dt class="col-sm-5">Right team</dt>
                            <dd class="col-sm-7">{{ $legs['right']['total'] }} members ({{ $legs['right']['active'] }} active) · {{ number_format(intdiv($node->right_lifetime_volume, 100)) }} BV</dd>
                            <dt class="col-sm-5">Unmatched volume</dt>
                            <dd class="col-sm-7">L {{ number_format(intdiv($node->left_volume, 100)) }} · R {{ number_format(intdiv($node->right_volume, 100)) }} BV</dd>
                            <dt class="col-sm-5">Deferred commission</dt><dd class="col-sm-7">{{ $money($node->deferred_commission) }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Actions</h3></div>
                <div class="card-body d-grid gap-3">
                    @if ($member->status->value === 'pending')
                        <form method="POST" action="{{ route('admin.members.activate', $member) }}" onsubmit="return confirm('Activate and place this member in the tree?')">
                            @csrf
                            <button class="btn btn-primary btn-sm">Activate manually</button>
                        </form>
                    @elseif ($member->status->value === 'active')
                        <form method="POST" action="{{ route('admin.members.suspend', $member) }}" class="d-flex gap-2" onsubmit="return confirm('Suspend this member?')">
                            @csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="Reason for suspension" aria-label="Reason for suspension" required>
                            <button class="btn btn-danger btn-sm text-nowrap">Suspend</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.members.reinstate', $member) }}" class="d-flex gap-2">
                            @csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="Reason for reinstating" aria-label="Reason for reinstating" required>
                            <button class="btn btn-success btn-sm text-nowrap">Reinstate</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.members.package', $member) }}" class="d-flex gap-2">
                        @csrf
                        <select name="package_id" class="form-select form-select-sm" aria-label="New package">
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" @selected($package->id === $member->package_id)>{{ $package->name }}</option>
                            @endforeach
                        </select>
                        <input name="reason" class="form-control form-control-sm" placeholder="Reason" aria-label="Reason for package change" required>
                        <button class="btn btn-outline-primary btn-sm text-nowrap">Change package</button>
                    </form>
                    <p class="small text-body-secondary mb-0">Changing the package only relabels the member; it creates no sale, BV or commission.</p>

                    @can('manage-settings')
                        @if ($member->status->value === 'active')
                            <form method="POST" action="{{ route('admin.members.performance-bonus', $member) }}" class="d-flex gap-2"
                                  onsubmit="return confirm('Pay this performance bonus into the member’s wallet?')">
                                @csrf
                                <input name="amount" class="form-control form-control-sm" style="max-width: 8rem" placeholder="৳ amount" aria-label="Bonus amount in taka" inputmode="decimal" required>
                                <input name="reason" class="form-control form-control-sm" placeholder="Reason (e.g. top seller in March)" aria-label="Bonus reason" required minlength="5">
                                <button class="btn btn-outline-success btn-sm text-nowrap">Pay performance bonus</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Latest wallet transactions</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $t)
                                <tr>
                                    <td class="text-nowrap">{{ $t->created_at?->format('d M Y H:i') }}</td>
                                    <td>{{ str_replace('_', ' ', $t->type->value) }}</td>
                                    <td class="text-end text-nowrap {{ $t->direction->value === 'credit' ? 'text-success' : 'text-danger' }}" style="font-variant-numeric: tabular-nums">
                                        {{ $t->direction->value === 'credit' ? '+' : '−' }}{{ $money($t->amount) }}
                                    </td>
                                    <td>@include('admin.partials.status-badge', ['status' => $t->status->value])</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">No transactions.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Activity</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>When</th><th>What</th><th>By</th></tr></thead>
                        <tbody>
                            @forelse ($activity as $entry)
                                <tr>
                                    <td class="text-nowrap">{{ $entry->created_at?->format('d M Y H:i') }}</td>
                                    <td>
                                        {{ $entry->description }}
                                        @if ($entry->properties['reason'] ?? null)
                                            <div class="small text-body-secondary">Reason: {{ $entry->properties['reason'] }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $entry->causer?->name ?? 'system' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">No activity recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Sign-in history</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>When</th><th>Event</th><th>IP</th><th>Device</th><th></th></tr></thead>
                <tbody>
                    @forelse ($logins as $login)
                        <tr>
                            <td class="text-nowrap">{{ $login->created_at?->format('d M Y H:i') }}</td>
                            <td>@include('admin.partials.status-badge', ['status' => $login->event === 'failed' ? 'failed' : ($login->event === 'registered' ? 'registered' : 'success')])</td>
                            <td class="text-nowrap">{{ $login->ip ?? '—' }}</td>
                            <td class="small text-break">{{ \Illuminate\Support\Str::limit($login->user_agent ?? '—', 80) }}</td>
                            <td class="text-nowrap">
                                @if ($login->new_device)<span class="badge text-bg-warning">new device</span>@endif
                                @if ($login->new_ip)<span class="badge text-bg-warning">new IP</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">No sign-ins recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
