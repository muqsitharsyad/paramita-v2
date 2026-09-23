@extends('layouts.app')

@section('title', __('ui.tpl.index_title'))
@section('page-title', __('ui.tpl.index_title'))

@section('content')
<div class="py-3 admin-page">
    @include('admin.partials.work-nav')

    <div class="admin-action-row justify-content-end mb-3">
        <a href="{{ route('admin.templates.guide') }}" class="btn btn-outline-secondary fw-semibold">{{ __('ui.tpl.panduan') }}</a>
        <a href="{{ route('admin.templates.create') }}" class="btn btn-primary fw-semibold">{{ __('ui.tpl.new') }}</a>
    </div>

    <form method="GET" action="{{ route('admin.templates.index') }}" class="admin-action-row mb-3">
        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('ui.tpl.search_placeholder') }}">
        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('ui.tpl.cari') }}</button>
        @if($search)<a href="{{ route('admin.templates.index') }}" class="btn btn-sm btn-link">{{ __('ui.tpl.reset') }}</a>@endif
    </form>

    <div class="admin-panel">
        <div class="admin-panel-header">
            <h5 class="admin-panel-title">{{ __('ui.tpl.daftar') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table">
                <thead>
                    <tr>
                        <th class="ps-4">{{ __('ui.tpl.nama') }}</th>
                        <th>{{ __('ui.tpl.kategori') }}</th>
                        <th>{{ __('ui.tpl.status') }}</th>
                        <th class="text-end pe-4">{{ __('ui.tpl.aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="ps-4"><div class="fw-bold">{{ $tpl->name }}</div></td>
                            <td>{{ $tpl->category }}</td>
                            <td>
                                <span class="status-label {{ $tpl->is_active ? 'status-approved' : 'status-waiting' }}">
                                    {{ $tpl->is_active ? __('ui.ref.active') : __('ui.ref.inactive') }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="{{ route('admin.templates.edit', $tpl->id) }}" class="btn btn-sm btn-outline-primary">{{ __('ui.tpl.edit') }}</a>
                                <form action="{{ route('admin.templates.destroy', $tpl->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('ui.tpl.confirm_delete') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('ui.tpl.hapus') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-muted">{{ __('ui.tpl.belum_ada') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
            <div class="card-footer bg-white py-3 border-0">{{ $templates->appends(request()->query())->links() }}</div>
        @endif
    </div>
</div>
@endsection
