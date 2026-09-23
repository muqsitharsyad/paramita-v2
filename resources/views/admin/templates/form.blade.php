@extends('layouts.app')

@section('title', $template ? __('ui.tpl.edit_title') : __('ui.tpl.create_title'))
@section('page-title', $template ? __('ui.tpl.edit_named', ['name' => $template->name]) : __('ui.tpl.create_title'))

@php
    $rawJson = old('template_data', $template->template_data ?? "{\n  \"data\": {},\n  \"meta\": {}\n}");
    $decodedJson = json_decode((string) $rawJson, true);
    $prettyJson = json_last_error() === JSON_ERROR_NONE
        ? json_encode($decodedJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : $rawJson;
@endphp

@section('content')
<div class="py-3 admin-page">
    @include('admin.partials.work-nav')

    <div class="admin-action-row justify-content-end mb-3">
        <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary">{{ __('ui.tpl.kembali') }}</a>
        <button type="submit" form="json-template-form" class="btn btn-primary fw-semibold px-4">{{ $template ? __('ui.tpl.save_changes') : __('ui.tpl.save_template') }}</button>
    </div>

    <form id="json-template-form" method="POST" action="{{ $template ? route('admin.templates.update', $template->id) : route('admin.templates.store') }}">
        @csrf
        @if($template) @method('PUT') @endif

        <div class="json-panel mb-4">
            <div class="json-panel-header">
                <div>
                    <h5 class="json-panel-title">{{ __('ui.tpl.info_short') }}</h5>
                </div>
                <div class="form-check form-switch mt-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="tpl-active" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))>
                    <label class="form-check-label small fw-semibold" for="tpl-active">{{ __('ui.tpl.aktif_acuan') }}</label>
                </div>
            </div>
            <div class="json-panel-body">
                <div class="json-form-grid json-form-grid-compact">
                    <div>
                        <label class="form-label small fw-semibold">{{ __('ui.tpl.nama_template') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $template->name ?? '') }}" placeholder="orders.detail" required>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">{{ __('ui.tpl.kategori') }} <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" placeholder="inventory / orders" value="{{ old('category', $template->category ?? '') }}" required>
                    </div>
                </div>
                <input type="hidden" name="version" value="{{ old('version', $template->version ?? '1.0') }}">
                <input type="hidden" name="description" value="{{ old('description', $template->description ?? '') }}">
            </div>
        </div>

        <div class="json-panel">
            <div class="json-panel-header">
                <div>
                    <h5 class="json-panel-title">{{ __('ui.tpl.format_short') }} <span class="text-danger">*</span></h5>
                    <p class="json-panel-note">{{ __('ui.tpl.format_direct_note') }}</p>
                </div>
                <div class="admin-action-row">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-json-format>{{ __('ui.tpl.format_json') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-json-validate>{{ __('ui.tpl.validasi') }}</button>
                </div>
            </div>
            <div class="json-panel-body">
                <div class="json-editor-wrap">
                    <div class="json-editor-toolbar">
                        <span>template_data.json</span>
                        <span class="json-editor-status" id="json-editor-status">{{ __('ui.tpl.siap_diedit') }}</span>
                    </div>
                    <textarea name="template_data" id="jsonEditor" rows="24" class="form-control json-editor" spellcheck="false" required>{{ $prettyJson }}</textarea>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
