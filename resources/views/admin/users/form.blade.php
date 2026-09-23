@extends('layouts.app')

@php($editing = $managedUser !== null)
@section('title', $editing ? __('ui.users.edit_title') : __('ui.users.create_title'))
@section('page-title', $editing ? __('ui.users.edit_title') : __('ui.users.create_title'))

@section('content')
<div class="admin-page py-3">
    @include('admin.partials.work-nav')

    <form class="admin-user-form" method="POST" action="{{ $editing ? route('admin.users.update', $managedUser) : route('admin.users.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div><h3 class="admin-panel-title">{{ __('ui.users.account_access') }}</h3><p class="admin-panel-note">{{ __('ui.users.form_note') }}</p></div>
            </header>
            <div class="card-body admin-user-form-grid">
                <label class="admin-user-field">
                    <span>{{ __('ui.users.name') }}</span>
                    <input class="form-control" name="name" value="{{ old('name', $managedUser?->name) }}" maxlength="255" required>
                </label>
                <label class="admin-user-field">
                    <span>{{ __('ui.users.email') }}</span>
                    <input class="form-control" type="email" name="email" value="{{ old('email', $managedUser?->email) }}" maxlength="255" required>
                </label>
                <label class="admin-user-field">
                    <span>{{ __('ui.users.role') }}</span>
                    <select class="form-select" name="role" id="managed-role" required>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', $managedUser?->role ?? 'tutor') === $role)>{{ __('ui.users.roles.'.$role) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-user-field">
                    <span>{{ __('ui.common.status') }}</span>
                    <select class="form-select" name="status" required>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $managedUser?->status ?? 'active') === $status)>{{ __('ui.users.statuses.'.$status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-user-field" data-ut-scope>
                    <span>{{ __('ui.users.ut_region') }}</span>
                    <select class="form-select" name="ut_id">
                        <option value="">{{ __('ui.users.choose_region') }}</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}" @selected((string) old('ut_id', $managedUser?->ut_id) === (string) $region->id)>{{ $region->name }} ({{ $region->code }})</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="admin-user-field admin-user-programs" data-program-scope>
                    <legend>{{ __('ui.users.programs') }}</legend>
                    <p>{{ __('ui.users.programs_note') }}</p>
                    <div class="admin-user-checklist">
                        @foreach($programs as $program)
                            <label><input type="checkbox" name="program_ids[]" value="{{ $program->id }}" @checked(in_array((int) $program->id, array_map('intval', old('program_ids', $selectedPrograms)), true))> <span>{{ $program->name }} <code>{{ $program->code }}</code></span></label>
                        @endforeach
                    </div>
                </fieldset>
                <label class="admin-user-field">
                    <span>{{ $editing ? __('ui.users.new_password') : __('ui.users.password') }}</span>
                    <input class="form-control" type="password" name="password" minlength="12" maxlength="1024" autocomplete="new-password" @required(!$editing)>
                    @if($editing)<small>{{ __('ui.users.password_optional') }}</small>@endif
                </label>
                <label class="admin-user-field">
                    <span>{{ __('ui.users.password_confirmation') }}</span>
                    <input class="form-control" type="password" name="password_confirmation" minlength="12" maxlength="1024" autocomplete="new-password" @required(!$editing)>
                </label>
            </div>
        </section>

        <div class="admin-user-actions">
            <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">{{ __('ui.users.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ $editing ? __('ui.users.save') : __('ui.users.create') }}</button>
        </div>
    </form>
</div>

<script>
(() => {
    const role = document.querySelector('#managed-role');
    const utScope = document.querySelector('[data-ut-scope]');
    const programScope = document.querySelector('[data-program-scope]');
    const sync = () => {
        const scoped = role.value === 'kepala_ut_daerah' || role.value === 'tutor';
        utScope.hidden = !scoped;
        utScope.querySelector('select').required = scoped;
        programScope.hidden = role.value !== 'tutor';
        programScope.querySelectorAll('input').forEach((input) => input.disabled = role.value !== 'tutor');
    };
    role.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
