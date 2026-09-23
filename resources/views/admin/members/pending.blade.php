@extends('adminlte::page')

@section('title', 'Pending Members')

@section('content_header')
    <h1>Pending Members</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="alert alert-warning">
        Temporary testing tool: activation normally happens automatically when payment succeeds (Phase 4).
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Sponsor</th>
                        <th>Side</th>
                        <th>Package</th>
                        <th>Registered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>{{ $member->user->name }}<br><small class="text-muted">{{ $member->user->email }}</small></td>
                            <td>{{ $member->user->phone }}</td>
                            <td>{{ $member->sponsor?->member_code ?? '—' }}</td>
                            <td>{{ ucfirst($member->preferred_side?->value ?? 'left') }}</td>
                            <td>{{ $member->package?->name }}</td>
                            <td>{{ $member->created_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.members.activate', $member) }}"
                                      onsubmit="return confirm('Activate and place this member in the tree?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">Activate</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No pending members.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="card-footer">{{ $members->links() }}</div>
        @endif
    </div>
@stop
