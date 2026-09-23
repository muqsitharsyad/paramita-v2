@extends('layouts.app')

@section('title', __('ui.analytics.sla_title'))
@section('page-title', __('ui.analytics.sla_title'))
@section('page', 'sla-analysis')

@section('content')
<div class="py-3 analytics-page analisis-sla-content">
    <div class="analytics-toolbar">
        <div class="period-switch" role="group" aria-label="{{ __('ui.analytics.period_filter') }}">
            <button type="button" class="is-active" data-analytics-period="daily">{{ __('ui.analytics.daily') }}</button>
            <button type="button" data-analytics-period="weekly">{{ __('ui.analytics.weekly') }}</button>
            <button type="button" data-analytics-period="monthly">{{ __('ui.analytics.monthly') }}</button>
        </div>
        <span id="analytics-period-label" class="analytics-period-label"></span>
    </div>

    <div id="analytics-source-state" class="analytics-source-state" role="status" aria-live="polite"></div>

    {{-- KPI Cards --}}
    <div class="sla-kpi-row">
        <article class="sla-kpi-card">
            <span>{{ __('ui.analytics.total_do') }}</span>
            <strong id="sla-kpi-total">-</strong>
        </article>
        <article class="sla-kpi-card sla-kpi-faster">
            <span>{{ __('ui.analytics.faster_sla') }}</span>
            <strong id="sla-kpi-faster">-</strong>
        </article>
        <article class="sla-kpi-card sla-kpi-on">
            <span>{{ __('ui.analytics.meets_sla') }}</span>
            <strong id="sla-kpi-compliant">-</strong>
            <small id="sla-kpi-compliant-pct">-</small>
        </article>
        <article class="sla-kpi-card sla-kpi-over">
            <span>{{ __('ui.analytics.over_sla') }}</span>
            <strong id="sla-kpi-over">-</strong>
            <small id="sla-kpi-over-pct">-</small>
        </article>
    </div>

    {{-- Grafik bar (vendor × carrier × SLA status) --}}
    <section class="analytics-panel sla-bar-panel">
        <div class="analytics-panel-header">
            <div>
                <h2>{{ __('ui.analytics.sla_chart_title') }}</h2>
                <p>{{ __('ui.analytics.click_chart_detail') }}</p>
            </div>
        </div>
        <div class="sla-bar-wrap">
            <canvas id="slaBarChart"></canvas>
        </div>
    </section>

    <section class="analytics-panel sla-carrier-panel">
        <div class="analytics-panel-header">
            <div>
                <h2 id="sla-carrier-title">{{ __('ui.analytics.carrier_breakdown') }}</h2>
                <p>{{ __('ui.analytics.carrier_breakdown_note') }}</p>
            </div>
        </div>
        <div id="sla-carrier-empty" class="analytics-empty d-none">{{ __('ui.analytics.select_provider') }}</div>
        <div class="sla-carrier-wrap"><canvas id="slaCarrierChart"></canvas></div>
    </section>

    <div id="sla-vendor-cards"></div>

    <div class="analytics-divider"></div>

    {{-- Tabel detail --}}
    <section class="analytics-panel">
        <div class="analytics-panel-header">
            <div>
                <h2>{{ __('ui.analytics.sla_table_title') }}</h2>
                <p>{{ __('ui.analytics.click_for_detail') }}</p>
            </div>
            <label class="analytics-search">
                <span class="visually-hidden">{{ __('ui.action.search') }}</span>
                <input id="sla-search" type="search" class="form-control" placeholder="{{ __('ui.analytics.search_carrier') }}">
            </label>
        </div>
        <nav id="sla-vendor-tabs" class="analytics-tabs" aria-label="{{ __('ui.analytics.provider_filter') }}"></nav>
        <div class="table-responsive">
            <table class="table analytics-table align-middle mb-0">
                <thead><tr>
                    <th>{{ __('ui.analytics.provider') }}</th>
                    <th>{{ __('ui.analytics.carrier') }}</th>
                    <th>{{ __('ui.analytics.faster_sla') }}</th>
                    <th>{{ __('ui.analytics.on_sla') }}</th>
                    <th>{{ __('ui.analytics.over_sla') }}</th>
                    <th>{{ __('ui.analytics.total_do') }}</th>
                    <th>{{ __('ui.analytics.over_sla_rate') }}</th>
                    <th>{{ __('ui.analytics.sla_target') }}</th>
                </tr></thead>
                <tbody id="sla-table-body"><tr><td colspan="8" class="analytics-empty">{{ __('ui.analytics.loading') }}</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

{{-- Modal 1: List DO --}}
<div class="modal fade" id="slaDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5 fw-bold">{{ __('ui.analytics.sla_detail_title') }}</h2>
                    <p id="sla-detail-subtitle" class="text-muted small mb-0"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table analytics-table align-middle mb-0">
                        <thead><tr>
                            <th>No</th>
                            <th>DO</th>
                            <th>{{ __('ui.analytics.provider') }}</th>
                            <th>{{ __('ui.analytics.carrier') }}</th>
                            <th>{{ __('ui.analytics.tracking_number') }}</th>
                            <th>{{ __('ui.analytics.sla_target') }}</th>
                            <th>{{ __('ui.analytics.handed_to_carrier_at') }}</th>
                            <th>{{ __('ui.analytics.completed_at') }}</th>
                            <th>{{ __('ui.analytics.process_status') }}</th>
                            <th>{{ __('ui.analytics.status') }} SLA</th>
                        </tr></thead>
                        <tbody id="sla-detail-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.action.close') }}</button></div>
        </div>
    </div>
</div>

{{-- Modal 2: Detail satu DO --}}
<div class="modal fade" id="slaDoDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h2 class="modal-title fs-5 fw-bold">{{ __('ui.analytics.do_detail_title') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div id="sla-do-detail-content" class="modal-body"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.action.close') }}</button></div>
        </div>
    </div>
</div>
@endsection
