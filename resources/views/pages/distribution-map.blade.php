@extends('layouts.app')

@section('title', __('ui.analytics.map_title'))
@section('page-title', __('ui.analytics.map_title'))
@section('page', 'distribution-map')

@section('content')
<div class="py-3 analytics-page map-content">
    <div id="analytics-source-state" class="analytics-source-state" role="status" aria-live="polite"></div>
    <section id="distribution-summary" class="geo-cards" aria-label="{{ __('ui.analytics.map_summary') }}"></section>
    <p id="distribution-metadata" class="geo-metadata"></p>

    <section class="analytics-panel geo-map-panel">
        <div class="analytics-panel-header">
            <div><h2>{{ __('ui.analytics.map_title') }}</h2><p>{{ __('ui.analytics.map_visual_note') }}</p></div>
        </div>
        <div class="geo-map-key"><span>{{ __('ui.analytics.map_size_legend') }}</span><span>{{ __('ui.analytics.map_color_legend') }}</span></div>
        <div id="distributionMap" class="geo-map" role="img" aria-label="{{ __('ui.analytics.map_title') }}. {{ __('ui.analytics.map_visual_note') }}"></div>
    </section>

    <div class="geo-layout-bottom">
        <section class="analytics-panel">
            <div class="analytics-panel-header"><div><h2>{{ __('ui.analytics.city_volume_chart') }}</h2><p>{{ __('ui.analytics.city_volume_note') }}</p></div></div>
            <div class="dist-perf-chart"><canvas id="distPerfChart" aria-label="{{ __('ui.analytics.city_volume_chart') }}"></canvas></div>
        </section>
        <aside class="analytics-panel">
            <div class="analytics-panel-header"><div><h2>{{ __('ui.analytics.distribution_per_vendor') }}</h2></div></div>
            <div id="distribution-vendor-summary" class="retry-vendor-summary"></div>
        </aside>
    </div>

    <section class="analytics-panel geo-table-panel">
        <div class="analytics-panel-header"><div><h2>{{ __('ui.analytics.city_performance') }}</h2><p>{{ __('ui.analytics.click_for_detail') }}</p></div></div>
        <div class="table-responsive"><table class="table analytics-table align-middle mb-0">
            <thead><tr><th>{{ __('ui.analytics.city') }}</th><th>{{ __('ui.analytics.province') }}</th><th>{{ __('ui.analytics.total_shipments') }}</th><th>{{ __('ui.analytics.on_time_rate') }}</th><th>{{ __('ui.analytics.avg_delay_days') }}</th><th>{{ __('ui.analytics.performance_status') }}</th></tr></thead>
            <tbody id="distribution-table-body"><tr><td colspan="6" class="analytics-empty">{{ __('ui.analytics.loading') }}</td></tr></tbody>
        </table></div>
    </section>
</div>

<div class="modal fade" id="distributionDetailModal" tabindex="-1" aria-labelledby="distribution-detail-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0">
            <div class="modal-header"><h2 id="distribution-detail-title" class="modal-title fs-5 fw-bold"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button></div>
            <div id="distribution-detail-body" class="modal-body"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.action.close') }}</button></div>
        </div>
    </div>
</div>
@endsection
