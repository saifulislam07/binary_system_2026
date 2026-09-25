@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
@endphp

@section('title', 'Binary Tree')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="m-0">Binary Tree</h1>
        <form method="GET" class="d-flex gap-2" role="search">
            <input name="member" value="{{ $searched }}" class="form-control form-control-sm" placeholder="Member code, e.g. MBR-100004" aria-label="Member code">
            <button class="btn btn-primary btn-sm text-nowrap">Show subtree</button>
            @if ($searched !== '')
                <a href="{{ route('admin.tree.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">Top</a>
            @endif
        </form>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    @if ($root === null)
        <div class="callout callout-warning">
            @if ($searched !== '')
                No placed member with code <strong>{{ $searched }}</strong>.
            @else
                The tree is empty.
            @endif
        </div>
    @else
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Subtree of <a href="{{ route('admin.members.show', $root) }}">{{ $root->member_code }}</a>
                </h3>
            </div>
            <div class="card-body">
                <p class="small text-body-secondary">Click a member to expand or collapse; deeper levels load as you go.</p>
                <div class="overflow-auto pb-2">
                    <div id="tree" class="d-flex justify-content-center" style="min-width: max-content"
                         data-url-template="{{ route('admin.tree.node', ['member' => '__CODE__']) }}"
                         data-member-url-template="{{ route('admin.members.show', ['member' => '__ID__']) }}"
                         data-root="{{ $root->member_code }}">
                        <span class="text-body-secondary">Loading…</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Matching history — {{ $root->member_code }}</h3></div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm mb-0" style="font-variant-numeric: tabular-nums">
                            <thead>
                                <tr>
                                    <th>Cycle</th>
                                    <th class="text-end">Left BV</th>
                                    <th class="text-end">Right BV</th>
                                    <th class="text-end">Matched</th>
                                    <th class="text-end">Carried L / R</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Over cap</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($history as $tv)
                                    <tr>
                                        <td>{{ $tv->cycle?->cycle_date->toDateString() }}</td>
                                        <td class="text-end">{{ number_format(intdiv($tv->left_volume, 100)) }}</td>
                                        <td class="text-end">{{ number_format(intdiv($tv->right_volume, 100)) }}</td>
                                        <td class="text-end">{{ number_format(intdiv($tv->matched_volume, 100)) }}</td>
                                        <td class="text-end">{{ number_format(intdiv($tv->carried_left, 100)) }} / {{ number_format(intdiv($tv->carried_right, 100)) }}</td>
                                        <td class="text-end">{{ $money($tv->paid_commission + $tv->deferred_released) }}</td>
                                        <td class="text-end">
                                            @if ($tv->overflow_commission > 0)
                                                {{ $money($tv->overflow_commission) }} <span class="small text-body-secondary">({{ $tv->overflow_action }})</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-body-secondary py-3">No commission cycles have matched this member yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-outline card-danger">
                    <div class="card-header"><h3 class="card-title">Manual placement adjustment</h3></div>
                    <form method="POST" action="{{ route('admin.tree.adjust', ['member' => old('member', $root->member_code)]) }}"
                          id="adjust-form" onsubmit="return confirm('Move this member and their whole team? This is logged.')">
                        @csrf
                        <div class="card-body">
                            <p class="small">
                                Moves a member <strong>with their entire downline</strong> into a vacant slot. Their team's
                                volume moves to the new upline. Refused once any of that volume has been matched in a
                                commission cycle. Sponsor does not change. Every move is logged with before/after positions.
                            </p>
                            <div class="mb-2">
                                <label for="move-member" class="form-label">Member to move</label>
                                <input id="move-member" class="form-control form-control-sm" value="{{ old('member', $root->member_code) }}"
                                       oninput="document.getElementById('adjust-form').action = '{{ route('admin.tree.adjust', ['member' => '__CODE__']) }}'.replace('__CODE__', encodeURIComponent(this.value.trim().toUpperCase()))">
                            </div>
                            <div class="mb-2">
                                <label for="new_parent" class="form-label">New parent (member code)</label>
                                <input id="new_parent" name="new_parent" class="form-control form-control-sm" value="{{ old('new_parent') }}" required>
                            </div>
                            <div class="mb-2">
                                <label for="side" class="form-label">Slot</label>
                                <select id="side" name="side" class="form-select form-select-sm">
                                    <option value="left" @selected(old('side') === 'left')>Left</option>
                                    <option value="right" @selected(old('side') === 'right')>Right</option>
                                </select>
                            </div>
                            <div>
                                <label for="reason" class="form-label">Reason (required, logged)</label>
                                <textarea id="reason" name="reason" rows="2" class="form-control form-control-sm" required minlength="10">{{ old('reason') }}</textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-danger btn-sm">Move member</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@stop

@push('js')
<script>
(() => {
    const host = document.getElementById('tree');
    if (!host) return;

    const urlFor = (code) => host.dataset.urlTemplate.replace('__CODE__', encodeURIComponent(code));
    const bv = new Intl.NumberFormat('en-BD');

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    async function fetchNode(code) {
        const response = await fetch(urlFor(code), { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    }

    function emptySlot() {
        return el('div', 'border border-dashed rounded p-2 text-center small text-body-secondary', 'Empty slot');
    }

    function render(data) {
        const wrap = el('div', 'd-flex flex-column align-items-center');
        const card = el('button', 'btn btn-light border text-start p-2 small');
        card.type = 'button';
        card.style.width = '12rem';
        if (!data.active) card.style.opacity = '0.65';

        const head = el('div', 'd-flex justify-content-between align-items-center gap-2');
        head.append(el('strong', '', data.code ?? '—'));
        head.append(el('span', 'badge ' + (data.active ? 'text-bg-success' : 'text-bg-secondary'), data.status));
        card.append(head);
        card.append(el('div', 'text-truncate', data.name));
        card.append(el('div', 'text-body-secondary', (data.package ?? '—') + ' · team ' + bv.format(data.teamBv) + ' BV'));
        card.append(el('div', 'text-body-secondary', 'L ' + bv.format(data.leftBv) + ' · R ' + bv.format(data.rightBv)));

        const expandable = data.hasLeft || data.hasRight;
        const hint = el('div', 'text-center text-body-secondary', '');
        if (expandable) card.append(hint);
        wrap.append(card);

        const kids = el('div', 'd-flex gap-3 mt-2 pt-2 border-top');
        let open = false;
        let loaded = data.children;

        const draw = () => {
            kids.replaceChildren();
            for (const side of ['left', 'right']) {
                const column = el('div', 'd-flex flex-column align-items-center');
                column.append(el('div', 'small text-body-secondary mb-1', side === 'left' ? 'Left' : 'Right'));
                column.append(loaded[side] ? render(loaded[side]) : emptySlot());
                kids.append(column);
            }
        };

        const setOpen = (value) => {
            open = value;
            kids.hidden = !open;
            card.setAttribute('aria-expanded', String(open));
            hint.textContent = open ? '▲ collapse' : '▼ expand';
        };

        if (expandable) {
            card.addEventListener('click', async () => {
                if (open) return setOpen(false);
                if (!loaded) {
                    hint.textContent = 'Loading…';
                    try {
                        loaded = (await fetchNode(data.code)).children;
                    } catch {
                        hint.textContent = 'Could not load';
                        return;
                    }
                }
                draw();
                setOpen(true);
            });
            wrap.append(kids);
            if (loaded) { draw(); setOpen(true); } else { setOpen(false); }
        } else {
            card.disabled = true;
        }

        return wrap;
    }

    fetchNode(host.dataset.root)
        .then((data) => host.replaceChildren(render(data)))
        .catch(() => { host.textContent = 'Could not load the tree.'; });
})();
</script>
@endpush
