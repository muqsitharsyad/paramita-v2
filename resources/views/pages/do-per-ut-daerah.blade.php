@extends('layouts.app')

@section('title', __('ui.delivery.do_ut_title'))
@section('page-title', __('ui.delivery.do_ut_title'))

@section('content')
<div class="py-3 modern-do-page">
    <div class="do-filter-bar mb-4">
        <div>
            <label class="form-label small fw-semibold mb-1">{{ __('ui.delivery.period_short') }}</label>
            <select id="filter-period" class="form-select" style="width: 180px">
                <option value="">{{ __('ui.delivery.all_period') }}</option>
            </select>
        </div>
        <div class="flex-grow-1" style="max-width: 420px">
            <label class="form-label small fw-semibold mb-1">{{ __('ui.delivery.ut_region') }}</label>
            <select id="filter-ut" class="form-select">
                <option value="">{{ __('ui.delivery.all_ut') }}</option>
            </select>
        </div>
    </div>

    <section class="do-chart-grid mb-4">
        <div class="do-chart-card">
            <h5 class="do-chart-title">{{ __('ui.delivery.do_ut_heading') }}</h5>
            <p class="do-chart-subtitle">{{ __('ui.delivery.do_ut_note') }}</p>
            <div class="do-chart-canvas"><canvas id="chart-prodi-main"></canvas></div>
        </div>
        <div class="do-chart-card">
            <h5 class="do-chart-title">{{ __('ui.delivery.shipment_status') }}</h5>
            <p class="do-chart-subtitle">{{ __('ui.delivery.status_flow') }}</p>
            <div class="do-chart-canvas"><canvas id="chart-kirim-main"></canvas></div>
        </div>
        <div class="do-chart-card">
            <h5 class="do-chart-title">{{ __('ui.delivery.sla_status') }}</h5>
            <p class="do-chart-subtitle">{{ __('ui.delivery.sla_compare') }}</p>
            <div class="do-chart-canvas"><canvas id="chart-sla-main"></canvas></div>
        </div>
        <div class="vendor-summary-panel">
            <div class="vendor-summary-title">
                <div>
                    <h5 class="do-chart-title">{{ __('ui.delivery.all_vendors') }}</h5>
                    <p class="do-chart-subtitle mb-0">{{ __('ui.delivery.active_per_vendor') }}</p>
                </div>
                <span class="badge bg-primary-subtle text-primary">{{ __('ui.common.vendor') }}</span>
            </div>
            <div class="vendor-summary-list" data-metric="total"></div>
        </div>
    </section>

    <section class="section-do-ut-daerah-tabs">
        <div class="admin-panel p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold card-title-do-ut-daerah mb-0">{{ __('ui.delivery.do_ut_title') }}</h5>
                    <p class="text-muted small mb-0">{{ __('ui.delivery.vendor_tabs') }}</p>
                </div>
                <div class="entryarea">
                    <label class="form-label form-label-cari"><img src="{{ asset('images/icon-cari.svg') }}" alt="icon-cari" /></label>
                    <input type="text" class="form-control form-control-cari form-cari-do" placeholder="{{ __('ui.delivery.search_ut') }}" id="orders-search" />
                </div>
            </div>
            <ul class="nav nav-underline mb-3" id="vendor-tabs" role="tablist"></ul>
            <div class="tab-content tab-ut-daerah" id="vendor-tab-content"></div>
            <div class="dt-toolbar-mondev d-flex align-items-center justify-content-end gap-2 mt-2">
                <label for="orders-rows" class="mb-0">Rows per page:</label>
                <select id="orders-rows" class="form-select form-select-sm" style="width: auto"><option>5</option><option>10</option><option>25</option><option>50</option></select>
                <span id="orders-range" class="text-muted">0–0 of 0</span>
                <button id="orders-prev" class="btn btn-sm btn-outline-secondary" type="button">&lsaquo;</button>
                <button id="orders-next" class="btn btn-sm btn-outline-secondary" type="button">&rsaquo;</button>
            </div>
        </div>
    </section>
</div>
@include('pages.partials.modal-detail-do')
@endsection
