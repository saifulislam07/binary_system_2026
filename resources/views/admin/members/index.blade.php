@extends('adminlte::page')

@section('title', 'Members')

@section('content_header')
    <h1>Members</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Search</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Code, name, email, phone or NID">
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Any</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="package_id" class="form-label">Package</label>
                <select id="package_id" name="package_id" class="form-select">
                    <option value="">Any</option>
                    @foreach ($packages as $id => $name)
                        <option value="{{ $id }}" @selected((int) ($filters['package_id'] ?? 0) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label for="from" class="form-label">Joined from</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-1">
                <label for="to" class="form-label">to</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('admin.members.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Package</th>
                        <th>Sponsor</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td><a href="{{ route('admin.members.show', $member) }}">{{ $member->member_code ?? '—' }}</a></td>
                            <td>
                                <a href="{{ route('admin.members.show', $member) }}">{{ $member->user->name }}</a>
                                <div class="small text-body-secondary">{{ $member->user->email }}</div>
                            </td>
                            <td>{{ $member->user->phone }}</td>
                            <td>{{ $member->package?->name ?? '—' }}</td>
                            <td>{{ $member->sponsor?->member_code ?? '—' }}</td>
                            <td>@include('admin.partials.status-badge', ['status' => $member->status->value])</td>
                            <td>{{ $member->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">No members match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="card-footer">{{ $members->links() }}</div>
        @endif
    </div>
@stop
