<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.brand.name') }} | @yield('title', __('ui.nav.monitoring'))</title>
    <link rel="stylesheet" href="{{ asset('style/main.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body" data-page="@yield('page', '')">
<a class="visually-hidden-focusable skip-link" href="#main-content">{{ __('ui.action.skip_to_content') }}</a>
<div class="page-dashboard">
    <div class="d-flex" id="wrapper">
        @include('partials.sidebar')
        <div id="page-content-wrapper">
            @include('partials.topbar')
            <section class="section-content section-breadcrumbs">
                <h1 class="breadcrumbs-title fw-bold">@yield('page-title', __('ui.brand.name'))</h1>
            </section>
            <main id="main-content" class="content" tabindex="-1">@yield('content')</main>
        </div>
    </div>
</div>
@yield('scripts')
@php
    // UI strings for client-side rendering (charts, tables, modals).
    // Built as a PHP array so both languages stay in sync with lang/*/ui.php.
    $jsStrings = [
        'loading' => __('ui.action.loading'),
        'empty' => __('ui.stock.no_data'),
        'all' => __('ui.stock.all'),
        'all_vendors' => __('ui.delivery.all_vendors'),
        'all_prodi' => __('ui.delivery.all_prodi'),
        'all_ut' => __('ui.delivery.all_ut'),
        'selected_vendors' => __('ui.delivery.selected_vendors'),
        'shortage' => __('ui.stock.shortage'),
        'adequate' => __('ui.stock.adequate'),
        'surplus' => __('ui.stock.surplus'),
        'unknown' => __('ui.stock.unknown'),
        'not_supplied' => __('ui.stock.not_supplied'),
        'package_code' => __('ui.stock.package_code'),
        'package_title' => __('ui.stock.package_title'),
        'book_code' => __('ui.stock.book_code'),
        'edition' => __('ui.stock.edition'),
        'book_title' => __('ui.stock.book_title'),
        'orders' => __('ui.delivery.orders'),
        'status_delivered' => __('ui.delivery.delivered'),
        'status_on_delivery' => __('ui.delivery.on_delivery'),
        'status_on_process' => __('ui.delivery.on_process'),
        'status_retry' => __('ui.delivery.retry'),
        'status_return' => __('ui.delivery.return'),
        'status_process' => __('ui.delivery.process'),
        'sla_on' => __('ui.delivery.sla_on'),
        'sla_over' => __('ui.delivery.sla_over'),
        'vendor_unavailable' => __('ui.delivery.vendor_unavailable'),
        'vendor_unavailable_hint' => __('ui.delivery.vendor_unavailable_hint'),
        'no_do' => __('ui.delivery.no_do'),
        'no_do_hint' => __('ui.delivery.no_do_hint'),
        'load_failed' => __('ui.delivery.load_failed'),
        'load_failed_hint' => __('ui.delivery.load_failed_hint'),
        'loading_from_vendor' => __('ui.delivery.loading_from_vendor'),
        'history_empty' => __('ui.do_detail.no_history'),
        'retry_label' => __('ui.delivery.retry'),
        'total_do' => __('ui.total_do'),
        'json_valid' => __('ui.json_valid'),
        'json_formatted' => __('ui.json_formatted'),
        'json_minified' => __('ui.json_minified'),
        'json_invalid' => __('ui.json_invalid'),
        'col_vendor' => __('ui.stock.provider'),
        'col_do' => __('ui.do_detail.number'),
        'col_ordered' => __('ui.do_detail.ordered_at'),
        'col_name' => __('ui.do_detail.student'),
        'col_province' => __('ui.address.province'),
        'col_city' => __('ui.address.city'),
        'col_district' => __('ui.address.district'),
        'col_village' => __('ui.address.village'),
        'col_ut' => __('ui.delivery.ut_region'),
        'col_status' => __('ui.delivery.process_status'),
        'col_prodi' => __('ui.delivery.study_program'),
        'no_data' => __('ui.stock.no_data'),
    ];
    foreach ([
        'loading', 'no_active_source', 'configure_source', 'invalid_format', 'source_unavailable',
        'data_blocked', 'partial_data', 'no_valid_data', 'load_failed', 'no_detail', 'days',
        'faster_sla', 'on_sla', 'over_sla', 'sla_faster', 'sla_on_sla', 'sla_over_sla',
        'all_providers', 'no_retry', 'total_shipments', 'on_time', 'late', 'failed_returned',
        'on_time_rate', 'avg_delay_days', 'performance_good', 'performance_medium',
        'performance_attention', 'no_data', 'best_city', 'worst_city', 'total_cities',
        'vendors_active', 'snapshot_30_days',
        'provider', 'reload',
    ] as $analyticsKey) {
        $jsStrings['analytics.'.$analyticsKey] = __('ui.analytics.'.$analyticsKey);
    }
    foreach ([
        'loading', 'load_failed', 'no_history', 'no_retry', 'student_information',
        'student_identifier', 'student_name', 'address_line', 'village', 'district',
        'city', 'province', 'phone', 'regional_ut', 'study_program', 'delivery_information',
        'carrier', 'tracking_number', 'package', 'sla_target', 'latest_status', 'coordinate',
        'delivery_history', 'retry_history', 'process_time', 'order_time', 'payment_time',
        'carrier_handover_time', 'completion_time', 'proof_delivery', 'proof_available',
        'no_proof', 'proof_protected', 'open_proof', 'proof_alt', 'days', 'sla_faster', 'sla_on', 'sla_over',
    ] as $detailKey) {
        $jsStrings['do_detail.'.$detailKey] = __('ui.do_detail.'.$detailKey);
    }
@endphp
<script>window.ParamitaI18n = @json($jsStrings);</script>
@php($flashSuccess = session('success'))
@php($flashError = session('error'))
@php($flashErrorItems = $flashError ? array_values(array_filter(array_map(static fn ($line) => trim((string) preg_replace('/^[•\-*]\s*/u', '', $line)), preg_split('/\R/u', (string) $flashError) ?: []))) : [])
@php($validationItems = array_values(array_filter(array_map(static fn ($line) => trim((string) preg_replace('/^[•\-*]\s*/u', '', $line)), $errors->all()))))
@if($flashSuccess || $flashErrorItems || $validationItems)
<div class="toast-stack" aria-live="polite">
    @if($flashSuccess)
    <div class="toast-note toast-ok" role="status"><div class="toast-body">{{ $flashSuccess }}</div><button type="button" class="toast-x" aria-label="Tutup">&times;</button></div>
    @endif
    @if($flashErrorItems)
    <div class="toast-note toast-err" role="alert"><div class="toast-body">@if(count($flashErrorItems) === 1){{ $flashErrorItems[0] }}@else<strong>{{ __('ui.action.review_again') }}</strong><ul class="toast-list">@foreach($flashErrorItems as $item)<li>{{ $item }}</li>@endforeach</ul>@endif</div><button type="button" class="toast-x" aria-label="Tutup">&times;</button></div>
    @endif
    @if($validationItems)
    <div class="toast-note toast-err" role="alert"><div class="toast-body">@if(count($validationItems) === 1){{ $validationItems[0] }}@else<strong>{{ __('ui.action.review_again') }}</strong><ul class="toast-list">@foreach($validationItems as $item)<li>{{ $item }}</li>@endforeach</ul>@endif</div><button type="button" class="toast-x" aria-label="Tutup">&times;</button></div>
    @endif
</div>
<script>
(function(){
  document.querySelectorAll('.toast-note').forEach(function(toast){
    toast.querySelector('.toast-x').addEventListener('click', function(){ toast.remove(); });
    setTimeout(function(){ toast.classList.add('is-leaving'); setTimeout(function(){ toast.remove(); }, 300); }, toast.classList.contains('toast-err') ? 14000 : 7000);
  });
})();
</script>
@endif
</body>
</html>
