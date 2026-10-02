@extends('adminlte::page')

@section('title', 'Membership page')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1>Membership page</h1>
            <p class="page-lead">The text sections of the public “Membership &amp; earnings” page, in English and Bangla.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('membership') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> View page</a>
            <a href="{{ route('admin.membership.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New section</a>
        </div>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="callout callout-info small">
        <p class="mb-1"><strong>Filled in automatically:</strong> packages (price, BV, referral bonus), commission rates and caps,
            the rank ladder with its requirements and bonuses, active bonus rules and the minimum withdrawal. They follow
            <a href="{{ route('admin.settings.index') }}">Business rules</a>, <a href="{{ route('admin.packages.index') }}">Packages</a> and
            <a href="{{ route('admin.ranks.index') }}">Ranks &amp; bonuses</a>.</p>
        <p class="mb-0"><strong>Always shown, not editable:</strong> the earnings disclaimer (membership is sponsor-based; income is not guaranteed; nothing is paid for recruiting alone).</p>
    </div>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>Section</th><th>Bangla</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sections as $section)
                        <tr data-section="{{ $section->id }}">
                            <td class="tabular-nums">{{ $section->sort_order }}</td>
                            <td>
                                <div class="fw-semibold">{{ $section->title_en }}</div>
                                <div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit(\App\Support\RichText::toPlain($section->body_en), 110) }}</div>
                            </td>
                            <td>
                                @if ($section->title_bn && $section->body_bn)
                                    <span class="badge text-bg-success">Translated</span>
                                    <div class="small text-body-secondary mt-1" lang="bn">{{ $section->title_bn }}</div>
                                @else
                                    <span class="badge text-bg-warning">Missing — shows English</span>
                                @endif
                            </td>
                            <td>@if ($section->is_active)<span class="badge text-bg-success">Shown</span>@else<span class="badge text-bg-secondary">Hidden</span>@endif</td>
                            <td class="text-end"><a href="{{ route('admin.membership.edit', $section) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-file-earmark-richtext"></i>No sections yet. The page still shows the disclaimer and the live rates.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
