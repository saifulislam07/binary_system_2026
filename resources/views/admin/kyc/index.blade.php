@extends('adminlte::page')

@section('title', 'KYC review')

@section('content_header')
    <h1>KYC review</h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <ul class="nav nav-pills mb-3">
        @foreach ($statuses as $s)
            <li class="nav-item">
                <a class="nav-link {{ $s === $status ? 'active' : '' }}" href="{{ route('admin.kyc.index', ['status' => $s->value]) }}" @if ($s === $status) aria-current="page" @endif>
                    {{ ucfirst($s->value) }}
                    <span class="badge {{ $s === $status ? 'text-bg-light' : 'text-bg-secondary' }}">{{ $counts[$s->value] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr><th>Submitted</th><th>Member</th><th>Document</th><th>NID on profile</th><th>Reviewed</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($documents as $d)
                        <tr>
                            <td class="text-nowrap">{{ $d->created_at?->format('d M Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.members.show', $d->member) }}">{{ $d->member->member_code ?? '(pending)' }}</a>
                                <div class="small text-body-secondary">{{ $d->member->user->name }}</div>
                            </td>
                            <td>{{ strtoupper($d->type->value) }} {{ $d->document_number }}</td>
                            <td>
                                {{ $d->member->nid ?? '—' }}
                                @if ($d->type->value === 'nid' && $d->member->nid && $d->member->nid !== $d->document_number)
                                    <span class="badge text-bg-warning">differs</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($d->reviewer)
                                    {{ $d->reviewer->name }} · {{ $d->reviewed_at?->format('d M Y') }}
                                @else — @endif
                            </td>
                            <td class="text-end"><a href="{{ route('admin.kyc.show', $d) }}" class="btn btn-sm btn-outline-primary">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No {{ $status->value }} submissions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())
            <div class="card-footer">{{ $documents->links() }}</div>
        @endif
    </div>
@stop
