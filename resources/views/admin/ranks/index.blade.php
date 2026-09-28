@extends('adminlte::page')

@php
    use App\Models\BonusRule;
    $plain = fn (int $scaled) => \App\Support\Money::toInputString($scaled);
@endphp

@section('title', 'Ranks & bonuses')

@section('content_header')
    <h1>Ranks &amp; bonuses</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.ranks.update') }}" class="card mb-4"
          onsubmit="return confirm('Save the rank thresholds? Members are re-evaluated at the next nightly run; nobody loses a rank. The change is logged.')">
        @csrf
        @method('PUT')
        <div class="card-header"><h3 class="card-title">Rank requirements</h3></div>
        <div class="card-body">
            <p class="text-body-secondary small mb-3">
                A member reaches a rank when <strong>all three</strong> requirements are met; its bonus is paid once.
                Each rank must ask at least as much as the one below it. Ranks already reached are never taken away,
                and changes apply from the next nightly evaluation (00:45).
            </p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Rank</th><th>Personal sales (৳)</th><th>Team sales (৳ of BV, both legs)</th>
                            <th>Active team</th><th>Rank bonus (৳)</th><th class="text-end">Reached by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ranks as $rank)
                            @php $key = "ranks.{$rank->id}"; @endphp
                            <tr data-rank="{{ $rank->slug }}">
                                <th scope="row">{{ $rank->name }}</th>
                                <td><input name="ranks[{{ $rank->id }}][min_personal_sales]" type="number" step="0.01" min="0" class="form-control form-control-sm @error($key.'.min_personal_sales') is-invalid @enderror" value="{{ old($key.'.min_personal_sales', $plain($rank->min_personal_sales)) }}" aria-label="{{ $rank->name }} personal sales" required></td>
                                <td><input name="ranks[{{ $rank->id }}][min_team_sales]" type="number" step="0.01" min="0" class="form-control form-control-sm @error($key.'.min_team_sales') is-invalid @enderror" value="{{ old($key.'.min_team_sales', $plain($rank->min_team_sales)) }}" aria-label="{{ $rank->name }} team sales" required></td>
                                <td><input name="ranks[{{ $rank->id }}][min_active_team]" type="number" step="1" min="0" class="form-control form-control-sm @error($key.'.min_active_team') is-invalid @enderror" value="{{ old($key.'.min_active_team', $rank->min_active_team) }}" aria-label="{{ $rank->name }} active team" required></td>
                                <td><input name="ranks[{{ $rank->id }}][bonus_amount]" type="number" step="0.01" min="0" class="form-control form-control-sm @error($key.'.bonus_amount') is-invalid @enderror" value="{{ old($key.'.bonus_amount', $plain($rank->bonus_amount)) }}" aria-label="{{ $rank->name }} bonus" required></td>
                                <td class="text-end tabular-nums">{{ number_format($rank->achievements_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary">Save rank requirements</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Leadership &amp; sales bonus rules</h3></div>
        <div class="card-body">
            <p class="text-body-secondary small mb-3">
                Each rule pays its amount once per member, the first night they reach the threshold.
                <strong>Leadership</strong> thresholds count active members in the team; <strong>sales</strong> thresholds are personal sales in taka.
                Inactive rules stop paying; payments already made stay.
            </p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Type</th><th style="min-width: 18rem">Name</th><th style="min-width: 11rem">Threshold</th><th>Bonus (৳)</th><th>Status</th><th class="text-end">Paid</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            @php $form = 'rule-'.$rule->id; $leadership = $rule->type === BonusRule::LEADERSHIP; @endphp
                            <tr data-rule="{{ $rule->id }}">
                                <td><span class="badge {{ $leadership ? 'text-bg-primary' : 'text-bg-info' }}">{{ $leadership ? 'Leadership' : 'Sales' }}</span></td>
                                <td><input form="{{ $form }}" name="name" maxlength="100" class="form-control form-control-sm" value="{{ $rule->name }}" aria-label="Rule name" required></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input form="{{ $form }}" name="threshold" type="number" step="{{ $leadership ? '1' : '0.01' }}" min="{{ $leadership ? '1' : '0.01' }}" class="form-control" value="{{ $leadership ? $rule->threshold : $plain($rule->threshold) }}" aria-label="Threshold" required>
                                        <span class="input-group-text">{{ $leadership ? 'members' : '৳' }}</span>
                                    </div>
                                </td>
                                <td><input form="{{ $form }}" name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" value="{{ $plain($rule->amount) }}" aria-label="Bonus amount" required></td>
                                <td>
                                    <select form="{{ $form }}" name="is_active" class="form-select form-select-sm" aria-label="Status">
                                        <option value="1" @selected($rule->is_active)>Active</option>
                                        <option value="0" @selected(! $rule->is_active)>Inactive</option>
                                    </select>
                                </td>
                                <td class="text-end tabular-nums">{{ number_format($rule->bonuses_count) }}</td>
                                <td class="text-end">
                                    <form id="{{ $form }}" method="POST" action="{{ route('admin.bonus-rules.update', $rule) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.bonus-rules.store') }}" class="card-footer">
            @csrf
            <h4 class="h6">Add a rule</h4>
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="new-type" class="form-label small">Type</label>
                    <select id="new-type" name="type" class="form-select form-select-sm">
                        <option value="{{ BonusRule::LEADERSHIP }}" @selected(old('type') === BonusRule::LEADERSHIP)>Leadership</option>
                        <option value="{{ BonusRule::SALES }}" @selected(old('type') === BonusRule::SALES)>Sales</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="new-name" class="form-label small">Name</label>
                    <input id="new-name" name="name" maxlength="100" class="form-control form-control-sm" value="{{ old('name') }}" placeholder="Leadership: 100 active in team" required>
                </div>
                <div class="col-md-2">
                    <label for="new-threshold" class="form-label small">Threshold</label>
                    <input id="new-threshold" name="threshold" type="number" step="0.01" min="0.01" class="form-control form-control-sm" value="{{ old('threshold') }}" required>
                </div>
                <div class="col-md-2">
                    <label for="new-amount" class="form-label small">Bonus (৳)</label>
                    <input id="new-amount" name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" value="{{ old('amount') }}" required>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Add rule</button></div>
            </div>
            <div class="form-text">Leadership: number of active team members. Sales: personal sales in taka.</div>
        </form>
    </div>
@stop
