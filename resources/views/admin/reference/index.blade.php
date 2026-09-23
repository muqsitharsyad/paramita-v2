@extends('layouts.app')

@section('title', $config['title'])
@section('page-title', __('ui.ref.page_title', ['title' => $config['title']]))

@section('content')
<div class="py-3 admin-page">
    @include('admin.partials.work-nav')

    <nav class="admin-action-row mb-3 flex-wrap" aria-label="{{ __('ui.ref.type_navigation') }}">
        @foreach($types as $key => $label)
            <a href="{{ route('admin.reference.index', $key) }}"
               class="btn btn-sm {{ $key === $type ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="json-panel mb-4">
        <div class="json-panel-header"><h5 class="json-panel-title">{{ __('ui.ref.add_title') }}</h5></div>
        <div class="json-panel-body">
            <form method="POST" action="{{ route('admin.reference.store', $type) }}">
                @csrf
                <div class="ref-form-grid">
                    @foreach($config['columns'] as $column)
                        @php($required = str_starts_with($config['rules'][$column] ?? '', 'required'))
                        <div>
                            <label class="form-label small fw-semibold" for="new-{{ $column }}">
                                {{ $config['labels'][$column] ?? $column }}
                                @if($required)<span class="text-danger">*</span>@endif
                            </label>
                            @if($column === 'item_type')
                                <select id="new-{{ $column }}" name="{{ $column }}" class="form-select" required>
                                    <option value="package">package</option>
                                    <option value="book">book</option>
                                </select>
                            @elseif($column === 'type')
                                <select id="new-{{ $column }}" name="{{ $column }}" class="form-select" required>
                                    <option value="daerah">daerah</option>
                                    <option value="luar_negeri">luar_negeri</option>
                                </select>
                            @else
                                <input id="new-{{ $column }}" type="text" name="{{ $column }}" class="form-control"
                                       value="{{ old($column) }}" @required($required)>
                            @endif
                            @error($column)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    <div class="ref-form-action">
                        <button type="submit" class="btn btn-primary fw-semibold">{{ __('ui.action.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="json-panel">
        <div class="json-panel-header">
            <h5 class="json-panel-title">{{ __('ui.ref.list_title') }} <span class="text-muted">({{ $rows->total() }})</span></h5>
            <form method="GET" action="{{ route('admin.reference.index', $type) }}" class="admin-action-row">
                <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm"
                       placeholder="{{ __('ui.action.search') }}" style="min-width: 200px">
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('ui.action.search') }}</button>
                @if($search !== '')
                    <a href="{{ route('admin.reference.index', $type) }}" class="btn btn-sm btn-link">{{ __('ui.action.reset') }}</a>
                @endif
            </form>
        </div>
        <div class="json-panel-body">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach($config['columns'] as $column)
                                <th>{{ $config['labels'][$column] ?? $column }}</th>
                            @endforeach
                            <th>{{ __('ui.ref.active') }}</th>
                            <th class="text-end">{{ __('ui.tpl.aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php($rowId = $row->id)
                        <tr>
                            @foreach($config['columns'] as $column)
                                <td>
                                    @if($loop->first)
                                        <code>{{ $row->{$column} }}</code>
                                    @else
                                        {{ $row->{$column} ?: '-' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <form method="POST" action="{{ route('admin.reference.toggle', [$type, $rowId]) }}">
                                    @csrf
                                    <button type="submit" class="status-label {{ ($row->is_active ?? false) ? 'status-approved' : 'status-waiting' }} border-0">
                                        {{ ($row->is_active ?? false) ? __('ui.ref.active') : __('ui.ref.inactive') }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="collapse" data-bs-target="#ref-edit-{{ $rowId }}">
                                    {{ __('ui.action.edit') }}
                                </button>
                                <form method="POST" action="{{ route('admin.reference.destroy', [$type, $rowId]) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('ui.ref.confirm_delete') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('ui.action.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="ref-edit-{{ $rowId }}">
                            <td colspan="{{ count($config['columns']) + 2 }}">
                                <form method="POST" action="{{ route('admin.reference.update', [$type, $rowId]) }}" class="ref-form-grid">
                                    @csrf
                                    @method('PUT')
                                    @foreach($config['columns'] as $column)
                                        @php($required = str_starts_with($config['rules'][$column] ?? '', 'required'))
                                        <div>
                                            <label class="form-label small fw-semibold">{{ $config['labels'][$column] ?? $column }}</label>
                                            @if($column === 'item_type')
                                                <select name="{{ $column }}" class="form-select">
                                                    <option value="package" @selected($row->{$column} === 'package')>package</option>
                                                    <option value="book" @selected($row->{$column} === 'book')>book</option>
                                                </select>
                                            @elseif($column === 'type')
                                                <select name="{{ $column }}" class="form-select">
                                                    <option value="daerah" @selected($row->{$column} === 'daerah')>daerah</option>
                                                    <option value="luar_negeri" @selected($row->{$column} === 'luar_negeri')>luar_negeri</option>
                                                </select>
                                            @else
                                                <input type="text" name="{{ $column }}" class="form-control"
                                                       value="{{ $row->{$column} }}" @required($required)>
                                            @endif
                                        </div>
                                    @endforeach
                                    <div class="ref-form-action">
                                        <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.action.save') }}</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($config['columns']) + 2 }}" class="text-center text-muted py-4">{{ __('ui.stock.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pager', ['items' => $rows])
        </div>
    </div>
</div>
@endsection
