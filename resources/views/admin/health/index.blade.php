@extends('adminlte::page')

@section('title', 'System health')

@section('content_header')
    <h1>System health</h1>
@stop

@section('content')
    <p class="text-body-secondary small">
        Uptime monitors should watch <code>{{ url('/up') }}</code>: it fails when a <strong>critical</strong> check fails.
        See the README's "Operations" section for what each one needs.
    </p>

    <div class="card" style="max-width: 820px">
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                <tbody>
                    @foreach ($checks as $check)
                        <tr data-check="{{ $check['check'] }}">
                            <td>
                                {{ $check['check'] }}
                                @if ($check['critical'])<span class="badge text-bg-secondary ms-1">critical</span>@endif
                            </td>
                            <td>
                                @if ($check['ok'])
                                    <span class="badge text-bg-success"><i class="bi bi-check-lg"></i> OK</span>
                                @else
                                    <span class="badge {{ $check['critical'] ? 'text-bg-danger' : 'text-bg-warning' }}"><i class="bi bi-exclamation-triangle"></i> {{ $check['critical'] ? 'Failing' : 'Attention' }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $check['detail'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop
