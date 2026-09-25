@extends('adminlte::page')

@section('title', $section)

@section('content_header')
    <h1>{{ $section }}</h1>
@stop

@section('content')
    <div class="callout callout-info">
        <h5>Coming in Phase {{ $phase }}</h5>
        <p class="mb-0">
            This section's screens are scheduled for Phase {{ $phase }} of the build plan.
            Access to it is already restricted to admins with the right permission.
        </p>
    </div>
@stop
