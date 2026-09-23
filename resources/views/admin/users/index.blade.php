@extends('layouts.app')

@section('title', __('ui.users.title'))
@section('page-title', __('ui.users.title'))

@section('content')
<div class="admin-page py-3">
    @include('admin.partials.work-nav')

    <section class="admin-review-section">
        <header class="admin-review-section-heading admin-user-heading">
            <div>
                <h3>{{ __('ui.users.internal_users') }}</h3>
                <p>{{ __('ui.users.internal_note') }}</p>
            </div>
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">{{ __('ui.users.add') }}</a>
        </header>

        <form method="GET" class="admin-user-filters">
            <label>
                <span>{{ __('ui.users.search') }}</span>
                <input class="form-control" type="search" name="q" value="{{ $query }}" placeholder="{{ __('ui.users.search_placeholder') }}">
            </label>
            <label>
                <span>{{ __('ui.users.role') }}</span>
                <select class="form-select" name="role">
                    <option value="">{{ __('ui.users.all_roles') }}</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected($selectedRole === $role)>{{ __('ui.users.roles.'.$role) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ __('ui.common.status') }}</span>
                <select class="form-select" name="status">
                    <option value="">{{ __('ui.users.all_statuses') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ __('ui.users.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-outline-primary" type="submit">{{ __('ui.tpl.cari') }}</button>
            @if($query !== '' || $selectedRole !== '' || $selectedStatus !== '')
                <a class="btn btn-link" href="{{ route('admin.users.index') }}">{{ __('ui.users.clear') }}</a>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table admin-review-table admin-user-table mb-0">
                <thead><tr><th>{{ __('ui.users.account') }}</th><th>{{ __('ui.users.role') }}</th><th>{{ __('ui.users.scope') }}</th><th>{{ __('ui.common.status') }}</th><th class="text-end">{{ __('ui.common.action') }}</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td>
                        <td>{{ __('ui.users.roles.'.$user->role) }}</td>
                        <td>{{ $user->ut_name ?: __('ui.users.all_regions') }}</td>
                        <td><span class="status-label {{ $user->status === 'active' ? 'status-approved' : 'status-waiting' }}">{{ __('ui.users.statuses.'.$user->status) }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $user->id) }}">{{ __('ui.users.edit') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="admin-empty-state">{{ __('ui.users.empty') }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['items' => $users])
    </section>
</div>
@endsection
