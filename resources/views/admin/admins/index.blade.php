@extends('adminlte::page')

@php
    use App\Services\AdminAccountService;
    $me = auth('admin')->user();
@endphp

@section('title', 'Admins & roles')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h1 class="mb-0">Admins &amp; roles</h1>
        <a href="{{ route('admin.admins.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> New admin</a>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Admin accounts</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
                <tbody>
                    @foreach ($admins as $admin)
                        <tr data-admin="{{ $admin->email }}">
                            <td>{{ $admin->name }} @if ($me && $admin->is($me))<span class="badge text-bg-light">you</span>@endif</td>
                            <td>{{ $admin->email }}</td>
                            <td>{{ $admin->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td>
                                @if ($admin->is_active)<span class="badge text-bg-success">Active</span>@else<span class="badge text-bg-secondary">Deactivated</span>@endif
                            </td>
                            <td class="small text-nowrap">
                                {{ $admin->last_login_at?->format('d M Y H:i') ?? 'never' }}
                                @if ($admin->last_login_ip)<div class="text-body-secondary">{{ $admin->last_login_ip }}</div>@endif
                            </td>
                            <td class="text-end"><a href="{{ route('admin.admins.edit', $admin) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Roles and what they can do</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table mb-0 align-middle text-center">
                <thead>
                    <tr>
                        <th class="text-start">Role</th>
                        @foreach ($permissions as $permission)
                            <th class="small fw-normal">{{ str_replace('-', ' ', $permission->value) }}</th>
                        @endforeach
                        <th class="text-end">Admins</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        @php
                            $super = $role->name === AdminAccountService::SUPER_ROLE;
                            $has = $role->permissions->pluck('name')->all();
                            $form = 'role-'.$role->id;
                        @endphp
                        <tr data-role="{{ $role->name }}">
                            <th scope="row" class="text-start">{{ $role->name }}</th>
                            @foreach ($permissions as $permission)
                                <td>
                                    <input type="checkbox" class="form-check-input" form="{{ $form }}" name="permissions[]" value="{{ $permission->value }}"
                                           aria-label="{{ $role->name }}: {{ $permission->value }}"
                                           @checked($super || in_array($permission->value, $has, true)) @disabled($super)>
                                </td>
                            @endforeach
                            <td class="text-end tabular-nums">{{ $role->users_count }}</td>
                            <td class="text-end">
                                @if ($super)
                                    <span class="small text-body-secondary">always everything</span>
                                @else
                                    <form id="{{ $form }}" method="POST" action="{{ route('admin.roles.update', $role) }}"
                                          onsubmit="return confirm('Change what the {{ $role->name }} role can do? It applies to its admins immediately.')">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.roles.store') }}" class="card-footer">
            @csrf
            <h4 class="h6">New role</h4>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="role-name" class="form-label small">Name</label>
                    <input id="role-name" name="name" maxlength="50" pattern="[a-z][a-z0-9-]*" class="form-control form-control-sm" value="{{ old('name') }}" placeholder="accounts" required>
                </div>
                <div class="col-md-7">
                    <span class="form-label small d-block">Permissions</span>
                    @foreach ($permissions as $permission)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->value }}" id="new-{{ $permission->value }}"
                                   @checked(in_array($permission->value, old('permissions', []), true))>
                            <label class="form-check-label small" for="new-{{ $permission->value }}">{{ str_replace('-', ' ', $permission->value) }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Create role</button></div>
            </div>
        </form>
    </div>
@stop
