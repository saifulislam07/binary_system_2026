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
        <div class="card" id="tree-frame">
            <div class="card-header tree-toolbar">
                <h3 class="card-title mb-0">
                    Subtree of <a href="{{ route('admin.members.show', $root) }}">{{ $root->member_code }}</a>
                    <span class="text-body-secondary fw-normal small">· {{ $root->user?->name }}</span>
                </h3>
                <div class="btn-group btn-group-sm" role="group" aria-label="Zoom">
                    <button type="button" class="btn btn-outline-secondary" data-zoom="out" aria-label="Zoom out"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="btn btn-outline-secondary tabular-nums" data-zoom="reset" aria-label="Reset zoom" style="min-width: 4rem">100%</button>
                    <button type="button" class="btn btn-outline-secondary" data-zoom="in" aria-label="Zoom in"><i class="bi bi-plus-lg"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-zoom="fit" title="Fit to screen" aria-label="Fit to screen"><i class="bi bi-arrows-angle-contract"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-zoom="full" title="Full screen" aria-label="Full screen"><i class="bi bi-fullscreen"></i></button>
                </div>
            </div>
            <div class="tree-viewport" id="tree-viewport" tabindex="0" aria-label="Tree canvas. Drag or use the arrow keys to move, plus and minus to zoom.">
                <div class="tree-canvas" id="tree"
                     data-url-template="{{ route('admin.tree.node', ['member' => '__CODE__']) }}"
                     data-focus-url-template="{{ route('admin.tree.index', ['member' => '__CODE__']) }}"
                     data-profile-url-template="{{ route('admin.members.index', ['q' => '__CODE__']) }}"
                     data-root="{{ $root->member_code }}">
                    <span class="text-body-secondary">Loading…</span>
                </div>
            </div>
            <div class="card-footer tree-toolbar small">
                <div class="tree-legend">
                    <span><i style="background: var(--at-green)"></i> Active</span>
                    <span><i style="background: var(--at-amber)"></i> Pending</span>
                    <span><i style="background: var(--at-red)"></i> Suspended</span>
                    <span><i style="border: 2px dashed var(--at-muted)"></i> Vacant slot</span>
                </div>
                <span class="text-body-secondary">Drag to move · Ctrl + scroll to zoom · <i class="bi bi-plus-circle"></i> opens a member's team</span>
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
    const viewport = document.getElementById('tree-viewport');
    const frame = document.getElementById('tree-frame');
    if (!host || !viewport) return;

    const fill = (template, code) => template.replace('__CODE__', encodeURIComponent(code));
    const bv = new Intl.NumberFormat('en-BD');
    const palettes = [['#2a78d6', '#6ea8f0'], ['#0f9d7a', '#4fd1a5'], ['#7c4dff', '#b39bff'], ['#e0672b', '#f7a26b'], ['#c2377b', '#ef7fb4'], ['#0e7490', '#4cc3dc']];

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const icon = (name) => {
        const i = el('i', 'bi bi-' + name);
        i.setAttribute('aria-hidden', 'true');
        return i;
    };

    async function fetchNode(code) {
        const response = await fetch(fill(host.dataset.urlTemplate, code), { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    }

    // Initials and a stable avatar color per member.
    const initials = (name) => name.replace(/^(mr|mrs|ms|miss|dr|prof)\.?\s+/i, '').split(/\s+/).filter(Boolean)
        .slice(0, 2).map((part) => part[0].toUpperCase()).join('');

    const avatarColors = (code) => {
        let hash = 0;
        for (const char of code) hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
        return palettes[hash % palettes.length];
    };

    function card(data, isRoot) {
        const box = el('div', 'bt-card' + (isRoot ? ' is-root' : '') + (data.active ? '' : ' is-inactive'));
        box.dataset.status = data.status;

        const head = el('div', 'bt-head');
        const avatar = el('span', 'bt-avatar', initials(data.name));
        const [a1, a2] = avatarColors(data.code ?? data.name);
        avatar.style.setProperty('--bt-a1', a1);
        avatar.style.setProperty('--bt-a2', a2);
        avatar.setAttribute('aria-hidden', 'true');
        const who = el('div');
        who.style.minWidth = '0';
        who.append(el('div', 'bt-name', data.name), el('div', 'bt-code', data.code ?? '—'));
        head.append(avatar, who);
        box.append(head);

        const tags = el('div', 'bt-tags');
        if (data.package) tags.append(el('span', 'bt-tag is-package', data.package));
        if (data.rank && data.rank !== 'Member') tags.append(el('span', 'bt-tag is-rank', data.rank));
        if (!data.active) tags.append(el('span', 'bt-tag', data.status));
        if (data.joined) tags.append(el('span', 'bt-tag', 'Joined ' + data.joined));
        box.append(tags);

        const total = data.leftBv + data.rightBv;
        const share = total === 0 ? 50 : Math.round(data.leftBv / total * 100);
        const legs = el('div', 'bt-legs');
        const row = el('div', 'bt-legs-row');
        row.append(el('span', '', 'L ' + bv.format(data.leftBv) + ' BV'), el('span', '', 'R ' + bv.format(data.rightBv) + ' BV'));
        const bar = el('div', 'bt-bar');
        const l = el('span', 'l');
        const r = el('span', 'r');
        l.style.width = share + '%';
        r.style.width = (100 - share) + '%';
        bar.append(l, r);
        legs.append(row, bar);
        box.append(legs);

        const actions = el('div', 'bt-actions');
        const profile = el('a');
        profile.href = fill(host.dataset.profileUrlTemplate, data.code);
        profile.append(icon('person'), document.createTextNode(' Profile'));
        actions.append(profile);
        if (!isRoot) {
            const focus = el('a');
            focus.href = fill(host.dataset.focusUrlTemplate, data.code);
            focus.append(icon('bullseye'), document.createTextNode(' Focus'));
            actions.append(focus);
        }
        box.append(actions);

        return box;
    }

    function emptySlot() {
        const box = el('div', 'bt-empty');
        box.append(icon('person-plus'), document.createTextNode('Vacant slot'));
        return box;
    }

    function render(data, isRoot) {
        const wrap = el('div', 'bt-node');
        wrap.append(card(data, isRoot));

        if (!data.hasLeft && !data.hasRight) return wrap;

        const toggle = el('button', 'bt-toggle');
        toggle.type = 'button';
        const error = el('div', 'bt-error');
        error.setAttribute('role', 'alert');
        const kids = el('div', 'bt-children');
        wrap.append(toggle, error, kids);

        let loaded = data.children;

        const draw = () => {
            kids.replaceChildren();
            for (const side of ['left', 'right']) {
                const branch = el('div', 'bt-branch ' + (side === 'left' ? 'is-left' : 'is-right'));
                branch.append(el('span', 'bt-side', side === 'left' ? 'Left' : 'Right'));
                branch.append(loaded[side] ? render(loaded[side], false) : emptySlot());
                kids.append(branch);
            }
        };

        const setOpen = (open) => {
            kids.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Collapse team' : 'Expand team');
            toggle.replaceChildren(icon(open ? 'dash' : 'plus'));
        };

        toggle.addEventListener('click', async () => {
            if (!kids.hidden && loaded) return setOpen(false);
            if (!loaded) {
                toggle.disabled = true;
                toggle.replaceChildren(icon('hourglass-split'));
                try {
                    loaded = (await fetchNode(data.code)).children;
                    error.textContent = '';
                } catch {
                    error.textContent = 'Could not load this branch.';
                    toggle.disabled = false;
                    return setOpen(false);
                }
                toggle.disabled = false;
            }
            draw();
            setOpen(true);
        });

        if (loaded) { draw(); setOpen(true); } else { setOpen(false); }

        return wrap;
    }

    /* Pan & zoom */
    let scale = 1, x = 0, y = 0, drag = null;
    const resetButton = document.querySelector('[data-zoom="reset"]');
    const apply = () => {
        host.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
        if (resetButton) resetButton.textContent = Math.round(scale * 100) + '%';
    };
    const clamp = (value) => Math.min(1.6, Math.max(0.3, value));
    const zoomTo = (next, ox = viewport.clientWidth / 2, oy = viewport.clientHeight / 2) => {
        const target = clamp(next);
        x = ox - (ox - x) * (target / scale);
        y = oy - (oy - y) * (target / scale);
        scale = target;
        apply();
    };
    const center = () => {
        x = (viewport.clientWidth - host.offsetWidth * scale) / 2;
        y = 8;
        apply();
    };
    const fit = () => {
        scale = clamp(Math.min(1, (viewport.clientWidth - 32) / host.offsetWidth, (viewport.clientHeight - 32) / host.offsetHeight));
        x = (viewport.clientWidth - host.offsetWidth * scale) / 2;
        y = Math.max(8, (viewport.clientHeight - host.offsetHeight * scale) / 2);
        apply();
    };

    viewport.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button, a')) return;
        drag = { id: event.pointerId, sx: event.clientX, sy: event.clientY, x, y };
        viewport.classList.add('is-panning');
        viewport.setPointerCapture(event.pointerId);
    });
    viewport.addEventListener('pointermove', (event) => {
        if (!drag || drag.id !== event.pointerId) return;
        x = drag.x + event.clientX - drag.sx;
        y = drag.y + event.clientY - drag.sy;
        apply();
    });
    const endDrag = () => { drag = null; viewport.classList.remove('is-panning'); };
    viewport.addEventListener('pointerup', endDrag);
    viewport.addEventListener('pointercancel', endDrag);

    // Ctrl/⌘ + wheel zooms at the cursor; a plain wheel still scrolls the page.
    viewport.addEventListener('wheel', (event) => {
        if (!(event.ctrlKey || event.metaKey)) return;
        event.preventDefault();
        const box = viewport.getBoundingClientRect();
        zoomTo(scale * (event.deltaY < 0 ? 1.1 : 0.9), event.clientX - box.left, event.clientY - box.top);
    }, { passive: false });

    viewport.addEventListener('keydown', (event) => {
        if (event.target !== viewport) return;
        const moves = { ArrowLeft: [60, 0], ArrowRight: [-60, 0], ArrowUp: [0, 60], ArrowDown: [0, -60] };
        if (moves[event.key]) {
            event.preventDefault();
            x += moves[event.key][0];
            y += moves[event.key][1];
            apply();
        } else if (event.key === '+' || event.key === '=') {
            zoomTo(scale * 1.15);
        } else if (event.key === '-') {
            zoomTo(scale / 1.15);
        }
    });

    document.querySelectorAll('[data-zoom]').forEach((button) => button.addEventListener('click', () => {
        const action = button.dataset.zoom;
        if (action === 'in') zoomTo(scale * 1.2);
        if (action === 'out') zoomTo(scale / 1.2);
        if (action === 'reset') { scale = 1; center(); }
        if (action === 'fit') fit();
        if (action === 'full') {
            if (document.fullscreenElement) document.exitFullscreen();
            else frame?.requestFullscreen?.();
        }
    }));

    document.addEventListener('fullscreenchange', () => {
        viewport.style.height = document.fullscreenElement ? 'calc(100vh - 130px)' : '';
        fit();
    });

    fetchNode(host.dataset.root)
        .then((data) => { host.replaceChildren(render(data, true)); center(); })
        .catch(() => { host.textContent = 'Could not load the tree.'; });
})();
</script>
@endpush

