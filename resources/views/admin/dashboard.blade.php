@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            Welcome, {{ auth('admin')->user()->name }}. Business metrics arrive in Phase 9.
        </div>
    </div>
@stop
