@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
@endphp

@section('title', 'Rank report')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="m-0">Rank report</h1>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary btn-sm">Financial reports</a>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Active members by current rank ({{ number_format($activeTotal) }} active)</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle" style="font-variant-numeric: tabular-nums">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th class="text-end">Members holding it</th>
                        <th style="width: 30%">Share</th>
                        <th class="text-end">Ever achieved</th>
                        <th class="text-end">Bonus per promotion</th>
                        <th class="text-end">Rank bonus paid</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr data-rank="{{ $row['name'] }}">
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td class="text-end">{{ number_format($row['members']) }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: .5rem" role="progressbar" aria-label="{{ $row['name'] }} share" aria-valuenow="{{ $row['share'] }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: {{ $row['share'] }}%"></div>
                                    </div>
                                    <span class="small text-body-secondary" style="min-width: 3.5rem">{{ $row['share'] }}%</span>
                                </div>
                            </td>
                            <td class="text-end">{{ number_format($row['achieved']) }}</td>
                            <td class="text-end">{{ $money($row['bonus_each']) }}</td>
                            <td class="text-end">{{ $money($row['bonus_paid']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-body-secondary">
            Ranks are re-evaluated nightly after the commission cycle. Ranks are never taken away; members promoted
            past several ranks at once are credited each rank (and its bonus) once.
        </div>
    </div>
@stop
