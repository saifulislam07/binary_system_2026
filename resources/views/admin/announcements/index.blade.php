@extends('adminlte::page')

@php
    use App\Models\Announcement;
@endphp

@section('title', 'Announcements')

@section('content_header')
    <h1>Announcements</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.announcements.store') }}" class="card mb-4" style="max-width: 760px" id="compose"
          data-preview-url="{{ route('admin.announcements.preview') }}"
          onsubmit="return confirm('Send this announcement to ' + (document.getElementById('recipient-count').textContent || 'the selected') + ' member(s)? This cannot be undone.')">
        @csrf
        <div class="card-header"><h3 class="card-title">New important announcement</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="audience" class="form-label">Members</label>
                    <select id="audience" name="audience" class="form-select" data-segment>
                        @foreach (Announcement::AUDIENCES as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="min_rank_id" class="form-label">Rank</label>
                    <select id="min_rank_id" name="min_rank_id" class="form-select" data-segment>
                        <option value="">Any rank</option>
                        @foreach ($ranks as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('min_rank_id') === (string) $id)>{{ $name }} and above</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="package_id" class="form-label">Package</label>
                    <select id="package_id" name="package_id" class="form-select" data-segment>
                        <option value="">Any package</option>
                        @foreach ($packages as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('package_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 form-text mt-1" aria-live="polite">
                    Reaches <strong id="recipient-count">…</strong> member(s).
                </div>

                <div class="col-12">
                    <label for="title" class="form-label">Title</label>
                    <input id="title" name="title" maxlength="150" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="body" class="form-label">Message</label>
                    <textarea id="body" name="body" rows="5" maxlength="5000" class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Members see this in their notification bell. Write it in Bengali, English or both.</div>
                </div>

                <fieldset class="col-12">
                    <legend class="form-label fs-6">Also send by</legend>
                    @foreach (Announcement::CHANNELS as $value => $label)
                        @php $on = in_array($value, $enabledChannels, true); @endphp
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $value }}" id="channel-{{ $value }}"
                                   @checked(in_array($value, old('channels', ['mail']), true)) @disabled(! $on)>
                            <label class="form-check-label" for="channel-{{ $value }}">
                                {{ $label }}@unless ($on) <span class="text-body-secondary small">(not configured)</span>@endunless
                            </label>
                        </div>
                    @endforeach
                </fieldset>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary"><i class="bi bi-megaphone"></i> Send announcement</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Sent announcements</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Sent</th><th>Title</th><th>Segment</th><th>Channels</th><th class="text-end">Recipients</th><th>By</th></tr></thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td class="text-nowrap">
                                {{ $announcement->created_at?->format('d M Y H:i') }}
                                @if ($announcement->sent_at === null)<span class="badge text-bg-secondary">queued</span>@endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $announcement->title }}</div>
                                <div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</div>
                            </td>
                            <td class="small">
                                {{ Announcement::AUDIENCES[$announcement->audience] ?? $announcement->audience }}
                                @if ($announcement->minRank)<div>{{ $announcement->minRank->name }}+</div>@endif
                                @if ($announcement->package)<div>{{ $announcement->package->name }} package</div>@endif
                            </td>
                            <td class="small">In-app{{ collect($announcement->channels)->map(fn ($c) => ', '.(Announcement::CHANNELS[$c] ?? $c))->implode('') }}</td>
                            <td class="text-end tabular-nums">{{ number_format($announcement->recipients) }}</td>
                            <td class="small">{{ $announcement->admin->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No announcements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($announcements->hasPages())
            <div class="card-footer">{{ $announcements->links() }}</div>
        @endif
    </div>
@stop

@push('js')
    <script>
        (() => {
            const form = document.getElementById('compose');
            const out = document.getElementById('recipient-count');
            let seq = 0;

            async function refresh() {
                const mine = ++seq;
                const params = new URLSearchParams();
                form.querySelectorAll('[data-segment]').forEach((el) => {
                    if (el.value !== '') params.set(el.name, el.value);
                });
                out.textContent = '…';
                try {
                    const res = await fetch(form.dataset.previewUrl + '?' + params, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error(res.status);
                    const data = await res.json();
                    if (mine === seq) out.textContent = Number(data.recipients).toLocaleString();
                } catch (e) {
                    if (mine === seq) out.textContent = '?';
                }
            }

            form.querySelectorAll('[data-segment]').forEach((el) => el.addEventListener('change', refresh));
            refresh();
        })();
    </script>
@endpush
