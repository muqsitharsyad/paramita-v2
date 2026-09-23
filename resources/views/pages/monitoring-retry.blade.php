@extends('layouts.app')

@section('title', __('ui.analytics.retry_title'))
@section('page-title', __('ui.analytics.retry_title'))
@section('page', 'retry-monitoring')

@section('content')
<div class="py-3 analytics-page monret-content">
    <div class="analytics-toolbar">
        <div class="period-switch" role="group" aria-label="{{ __('ui.analytics.period_filter') }}">
            <button type="button" class="is-active" data-analytics-period="daily">{{ __('ui.analytics.daily') }}</button>
            <button type="button" data-analytics-period="weekly">{{ __('ui.analytics.weekly') }}</button>
            <button type="button" data-analytics-period="monthly">{{ __('ui.analytics.monthly') }}</button>
        </div>
        <span id="analytics-period-label" class="analytics-period-label"></span>
    </div>

    <div id="analytics-source-state" class="analytics-source-state" role="status" aria-live="polite"></div>

    <div class="retry-layout">
        <div class="retry-charts">
            <section class="analytics-panel chart-panel">
                <div class="analytics-panel-header"><div><h2>{{ __('ui.analytics.retry_count_chart') }}</h2><p>{{ __('ui.analytics.retry_vendor_chart_note') }}</p></div></div>
                <div class="retry-chart-canvas"><canvas id="retryCountChart"></canvas></div>
            </section>
            <section class="analytics-panel chart-panel">
                <div class="analytics-panel-header"><div><h2>{{ __('ui.analytics.retry_carrier_chart_title') }}</h2><p>{{ __('ui.analytics.retry_carrier_chart_note') }}</p></div></div>
                <div class="retry-chart-canvas retry-chart-percent"><canvas id="retryPercentChart"></canvas></div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="retryDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-lg-down modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div><h2 class="modal-title fs-5 fw-bold">{{ __('ui.analytics.retry_detail_title') }}</h2><p id="retry-detail-subtitle" class="text-muted small mb-0"></p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body"><div class="table-responsive"><table class="table analytics-table align-middle mb-0">
                <thead><tr><th>DO</th><th>{{ __('ui.analytics.provider') }}</th><th>{{ __('ui.analytics.carrier') }}</th><th>{{ __('ui.analytics.retry_reason') }}</th><th>{{ __('ui.analytics.student_identifier') }}</th><th>{{ __('ui.analytics.student') }}</th><th>UT</th><th>{{ __('ui.analytics.program') }}</th><th>{{ __('ui.analytics.tracking_number') }}</th><th>{{ __('ui.analytics.retry_attempt') }}</th><th>{{ __('ui.analytics.occurred_at') }}</th><th>{{ __('ui.analytics.process_status') }}</th></tr></thead>
                <tbody id="retry-detail-body"></tbody>
            </table></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.action.close') }}</button></div>
        </div>
    </div>
</div>
@endsection
