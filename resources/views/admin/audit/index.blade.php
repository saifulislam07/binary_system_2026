@extends('adminlte::page')

@section('title', 'Audit log')

@section('content_header')
    <h1>Audit log</h1>
@stop

@section('content')
    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="actor" class="form-label">Admin</label>
                <select id="actor" name="actor" class="form-select">
                    <option value="">Anyone</option>
                    @foreach ($admins as $id => $name)
                        <option value="{{ $id }}" @selected((int) ($filters['actor'] ?? 0) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="type" class="form-label">Action type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Any</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="member" class="form-label">Affected member</label>
                <input id="member" name="member" value="{{ $filters['member'] ?? '' }}" class="form-control {{ $memberNotFound ? 'is-invalid' : '' }}" placeholder="MBR-100001">
                @if ($memberNotFound)<div class="invalid-feedback">No such member.</div>@endif
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="admin_only" value="1" id="admin_only" @checked(request()->boolean('admin_only'))>
                    <label class="form-check-label" for="admin_only">Admin actions only</label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead><tr><th>When</th><th>Type</th><th>What</th><th>Subject</th><th>By</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="text-nowrap small">{{ $entry->created_at?->format('d M Y H:i:s') }}</td>
                            <td><span class="badge text-bg-light border">{{ $entry->log_name }}</span></td>
                            <td>{{ $entry->description }}</td>
                            <td class="small text-nowrap">
                                @if ($entry->subject_type)
                                    {{ $entry->subject_type }} #{{ $entry->subject_id }}
                                    @if ($entry->subject instanceof \App\Models\Member && $entry->subject->member_code)
                                        · <a href="{{ route('admin.members.show', $entry->subject) }}">{{ $entry->subject->member_code }}</a>
                                    @endif
                                @else — @endif
                            </td>
                            <td class="small">
                                @if ($entry->causer instanceof \App\Models\Admin)
                                    <i class="bi bi-shield-lock"></i> {{ $entry->causer->name }}
                                @elseif ($entry->causer)
                                    {{ $entry->causer->name ?? class_basename($entry->causer) }}
                                @else
                                    <span class="text-body-secondary">system</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($entry->properties->isNotEmpty())
                                    <details>
                                        <summary>{{ $entry->properties->count() }} field(s)</summary>
                                        <pre class="mb-0 small" style="white-space: pre-wrap; max-width: 32rem">{{ json_encode($entry->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No entries match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())
            <div class="card-footer">{{ $entries->links() }}</div>
        @endif
    </div>
@stop
