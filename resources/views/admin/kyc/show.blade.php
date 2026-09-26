@extends('adminlte::page')

@section('title', 'KYC #'.$document->id)

@section('content_header')
    <h1>
        KYC #{{ $document->id }}
        <span class="fs-6 align-middle">@include('admin.partials.status-badge', ['status' => $document->status->value])</span>
    </h1>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Submission</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Member</dt>
                        <dd class="col-7"><a href="{{ route('admin.members.show', $document->member) }}">{{ $document->member->member_code ?? '(pending)' }}</a></dd>
                        <dt class="col-5">Name</dt><dd class="col-7">{{ $document->member->user->name }}</dd>
                        <dt class="col-5">Type</dt><dd class="col-7">{{ strtoupper($document->type->value) }}</dd>
                        <dt class="col-5">Number</dt><dd class="col-7">{{ $document->document_number }}</dd>
                        <dt class="col-5">NID on profile</dt><dd class="col-7">{{ $document->member->nid ?? '—' }}</dd>
                        <dt class="col-5">Submitted</dt><dd class="col-7">{{ $document->created_at?->format('d M Y H:i') }}</dd>
                        @if ($document->reviewer)
                            <dt class="col-5">Reviewed</dt><dd class="col-7">{{ $document->reviewer->name }}, {{ $document->reviewed_at?->format('d M Y H:i') }}</dd>
                        @endif
                        @if ($document->rejection_reason)
                            <dt class="col-5">Reason</dt><dd class="col-7">{{ $document->rejection_reason }}</dd>
                        @endif
                    </dl>
                </div>
                @if ($document->status->value === 'pending')
                    <div class="card-footer d-grid gap-2">
                        <form method="POST" action="{{ route('admin.kyc.approve', $document) }}" onsubmit="return confirm('Approve this KYC?')">
                            @csrf
                            <button class="btn btn-success w-100">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.kyc.reject', $document) }}" class="d-flex gap-2">
                            @csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="Reason (shown to the member)" aria-label="Rejection reason" required>
                            <button class="btn btn-outline-danger btn-sm">Reject</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Files</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        @forelse ($files as $file)
                            <div class="col-md-6">
                                <p class="small text-body-secondary mb-1">
                                    {{ $file->collection_name === 'photo' ? 'Member photo' : 'Document' }} · {{ $file->file_name }}
                                </p>
                                @if (str_starts_with((string) $file->mime_type, 'image/'))
                                    <a href="{{ route('admin.kyc.media', [$document, $file]) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('admin.kyc.media', [$document, $file]) }}" alt="{{ $file->collection_name }}" class="img-fluid rounded border" style="max-height: 360px">
                                    </a>
                                @else
                                    <a href="{{ route('admin.kyc.media', [$document, $file]) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-file-earmark-pdf"></i> Open {{ $file->file_name }}
                                    </a>
                                @endif
                            </div>
                        @empty
                            <p class="text-body-secondary">No files attached.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
