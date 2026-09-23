@extends('layouts.app')

@php($adminPageTitle = match($section) {
    'vendors' => __('ui.admin.vendors_and_accounts'),
    'reviews' => __('ui.admin.review_endpoint'),
    'active-endpoints' => __('ui.admin.active_endpoint'),
    default => __('ui.admin.summary'),
})
@section('title', $adminPageTitle)
@section('page-title', $adminPageTitle)

@section('content')
<div class="admin-page admin-review-page py-3">
    @include('admin.partials.work-nav')

    @if($section === 'overview')
        <section class="admin-work-list" aria-label="{{ __('ui.admin.pekerjaan') }}">
            <a class="admin-work-item" href="{{ route('admin.vendors.index') }}">
                <strong>{{ __('ui.admin.vendors_and_accounts') }}</strong>
                <b>{{ __('ui.admin.waiting_count', ['count' => $stats['pending_vendors']]) }}</b>
            </a>
            <a class="admin-work-item" href="{{ route('admin.endpointReviews.index') }}">
                <strong>{{ __('ui.admin.review_endpoint') }}</strong>
                <b>{{ __('ui.admin.waiting_count', ['count' => $stats['pending_endpoints']]) }}</b>
            </a>
            <a class="admin-work-item" href="{{ route('admin.activeEndpoints.index') }}">
                <strong>{{ __('ui.admin.active_endpoint') }}</strong>
                <b>{{ __('ui.admin.active_count', ['count' => $stats['active_bindings']]) }}</b>
            </a>
            <a class="admin-work-item" href="{{ route('admin.templates.index') }}"><strong>{{ __('ui.nav.json_templates') }}</strong></a>
            <a class="admin-work-item" href="{{ route('admin.reference.index', 'ut') }}"><strong>{{ __('ui.nav.master_data') }}</strong></a>
            <a class="admin-work-item" href="{{ route('admin.users.index') }}"><strong>{{ __('ui.users.title') }}</strong><small>{{ __('ui.users.internal_note') }}</small></a>
        </section>
    @elseif($section === 'vendors')
        <section class="admin-review-section mb-4">
            <div class="admin-review-section-heading admin-review-search-heading"><h3>{{ __('ui.admin.pending_vendors') }}</h3><form method="GET" class="admin-review-search"><input name="pending_q" type="search" class="form-control form-control-sm" value="{{ $pendingQuery }}" placeholder="{{ __('ui.admin.search_vendor') }}"><button class="btn btn-sm btn-outline-primary">{{ __('ui.tpl.cari') }}</button></form></div>
            <div class="table-responsive"><table class="table admin-review-table mb-0"><thead><tr><th>{{ __('ui.common.vendor') }}</th><th>{{ __('ui.admin.akun_login') }}</th><th>{{ __('ui.tpl.status') }}</th><th class="text-end">{{ __('ui.tpl.aksi') }}</th></tr></thead><tbody>
            @forelse($pendingVendors as $vendor)<tr><td><strong>{{ $vendor->legal_name }}</strong><code>{{ $vendor->code }}</code><small>{{ $vendor->contact_name }} · {{ $vendor->contact_email }}</small></td><td><strong>{{ $vendor->user_name ?: __('ui.admin.not_connected') }}</strong><small>{{ $vendor->user_email ?: '-' }}</small></td><td><span class="status-label status-waiting">{{ str_replace('_', ' ', $vendor->status) }}</span></td><td class="text-end"><form method="POST" action="{{ route('admin.vendors.approve', $vendor->id) }}">@csrf<button class="btn btn-sm btn-primary">{{ __('ui.admin.setujui') }}</button></form></td></tr>@empty<tr><td colspan="4"><div class="admin-empty-state">{{ __('ui.admin.tidak_ada_pending_vendor') }}</div></td></tr>@endforelse
            </tbody></table></div>
            @include('admin.partials.pager', ['items' => $pendingVendors])
        </section>
        <section class="admin-review-section"><div class="admin-review-section-heading admin-review-search-heading"><h3>{{ __('ui.admin.active_vendors') }}</h3><form method="GET" class="admin-review-search"><input name="vendor_q" type="search" class="form-control form-control-sm" value="{{ $vendorQuery }}" placeholder="{{ __('ui.admin.search_vendor') }}"><button class="btn btn-sm btn-outline-primary">{{ __('ui.tpl.cari') }}</button></form></div><div class="table-responsive"><table class="table admin-review-table mb-0"><thead><tr><th>{{ __('ui.common.vendor') }}</th><th>{{ __('ui.admin.kontak') }}</th><th>{{ __('ui.admin.akun_portal') }}</th><th>{{ __('ui.admin.disetujui') }}</th></tr></thead><tbody>
        @forelse($activeVendors as $vendor)<tr><td><strong>{{ $vendor->legal_name }}</strong><code>{{ $vendor->code }}</code></td><td><strong>{{ $vendor->contact_name }}</strong><small>{{ $vendor->contact_email }}</small></td><td><strong>{{ $vendor->user_name ?: __('ui.admin.no_account') }}</strong><small>{{ $vendor->user_email ?: '-' }} · {{ $vendor->user_status ?: __('ui.ref.inactive') }}</small></td><td>{{ $vendor->approved_at ?: '-' }}</td></tr>@empty<tr><td colspan="4"><div class="admin-empty-state">{{ __('ui.admin.tidak_ada_vendor_aktif') }}</div></td></tr>@endforelse
        </tbody></table></div>
        @include('admin.partials.pager', ['items' => $activeVendors])</section>
    @elseif($section === 'reviews')
        <section class="admin-review-section"><div class="admin-review-section-heading admin-review-search-heading"><h3>{{ __('ui.admin.review_endpoint') }}</h3><form method="GET" class="admin-review-search"><input name="review_q" type="search" class="form-control form-control-sm" value="{{ $reviewQuery }}" placeholder="{{ __('ui.admin.search_endpoint') }}"><button class="btn btn-sm btn-outline-primary">{{ __('ui.tpl.cari') }}</button></form></div>
        <div class="admin-vendor-groups">
        @forelse($submissionsByVendor as $vendorCode => $vendorSubmissions)
            <section class="admin-vendor-group is-collapsible" data-vendor-group>
                <header tabindex="0" role="button" aria-expanded="false" data-vendor-group-toggle>
                    <div class="admin-vendor-group-head">
                        <span class="admin-vendor-group-toggle" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
                        <div><strong>{{ $vendorSubmissions->first()->vendor_name }}</strong><code>{{ $vendorCode }}</code></div>
                    </div>
                    <span class="admin-vendor-group-meta"><span>{{ __('ui.admin.endpoint_count', ['count' => $vendorSubmissions->count()]) }}</span></span>
                </header>
                <div class="admin-vendor-group-body" data-vendor-group-body hidden>
                <div class="table-responsive"><table class="table admin-review-table mb-0"><thead><tr><th>{{ __('ui.admin.kebutuhan_data') }}</th><th>{{ __('ui.admin.draft_url') }}</th><th>{{ __('ui.admin.test_result') }}</th><th class="text-end">{{ __('ui.tpl.aksi') }}</th></tr></thead><tbody>
                @foreach($vendorSubmissions as $submission) @php($report = json_decode($submission->report_json, true)) <tr><td><strong>{{ $submission->contract_label }}</strong><code>{{ $submission->operation_key }} · rev {{ $submission->revision }}</code></td><td><code class="admin-url">{{ rtrim($submission->base_url, '/') }}/{{ ltrim($submission->path, '/') }}</code><small>{{ str_replace('_', ' ', $submission->auth_type) }}</small></td><td><span class="status-label {{ $submission->test_status === 'passed' ? 'status-approved' : 'status-error' }}">{{ $submission->test_status }}</span><small>{{ $submission->test_finished_at }}</small></td><td class="text-end"><div class="admin-review-actions"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#report-{{ $submission->submission_id }}">{{ __('ui.vendor.detail_test') }}</button><form method="POST" action="{{ route('admin.submissions.approve', $submission->submission_id) }}">@csrf<button class="btn btn-sm btn-primary" @disabled($submission->test_status !== 'passed')>{{ __('ui.admin.setujui') }}</button></form></div></td></tr><tr class="collapse" id="report-{{ $submission->submission_id }}"><td colspan="4" class="admin-test-detail"><h4>{{ __('ui.admin.test_evidence') }}</h4>@forelse($report['checks'] ?? [] as $check)<div class="admin-test-check {{ $check['status'] === 'pass' ? 'is-pass' : 'is-fail' }}"><strong>{{ $check['name'] }}</strong><span>{{ $check['detail'] }}</span></div>@empty<p>{{ __('ui.admin.test_report_missing') }}</p>@endforelse</td></tr>@endforeach
                </tbody></table></div>
                </div>
            </section>
        @empty<div class="admin-empty-state">{{ __('ui.admin.no_pending_review') }}</div>@endforelse
        </div>
        @include('admin.partials.pager', ['items' => $submissions])</section>
    @elseif($section === 'active-endpoints')
        <section class="admin-review-section"><div class="admin-review-section-heading admin-review-search-heading"><h3>{{ __('ui.admin.active_endpoint') }}</h3><form method="GET" class="admin-review-search"><input name="endpoint_q" type="search" class="form-control form-control-sm" value="{{ $endpointQuery }}" placeholder="{{ __('ui.admin.search_endpoint_url') }}"><button class="btn btn-sm btn-outline-primary">{{ __('ui.tpl.cari') }}</button></form></div>
        <div class="admin-vendor-groups">
        @forelse($activeBindingsByVendor as $vendorCode => $vendorBindings)
            <section class="admin-vendor-group is-collapsible" data-vendor-group>
                <header tabindex="0" role="button" aria-expanded="false" data-vendor-group-toggle>
                    <div class="admin-vendor-group-head">
                        <span class="admin-vendor-group-toggle" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
                        <div><strong>{{ $vendorBindings->first()->vendor_name }}</strong><code>{{ $vendorCode }}</code></div>
                    </div>
                    <span class="admin-vendor-group-meta"><span>{{ __('ui.admin.endpoint_count', ['count' => $vendorBindings->count()]) }}</span></span>
                </header>
                <div class="admin-vendor-group-body" data-vendor-group-body hidden>
                <div class="table-responsive"><table class="table admin-review-table mb-0"><thead><tr><th>{{ __('ui.admin.kebutuhan_data') }}</th><th>{{ __('ui.common.method') }}</th><th>{{ __('ui.admin.active_url') }}</th><th>{{ __('ui.common.auth') }}</th><th>{{ __('ui.vendor.last_test_label') }}</th></tr></thead><tbody>
                @foreach($vendorBindings as $binding)<tr><td><strong>{{ $binding->contract_label }}</strong><code>{{ $binding->operation_key }} · {{ __('ui.common.revision_short') }} {{ $binding->revision }}</code></td><td><span class="badge bg-primary">GET</span></td><td><code class="admin-url">{{ rtrim($binding->base_url, '/') }}/{{ ltrim($binding->path, '/') }}</code></td><td>{{ str_replace('_', ' ', $binding->auth_type) }}</td><td><span class="status-label {{ $binding->test_status === 'passed' ? 'status-approved' : 'status-waiting' }}">{{ $binding->test_status ?: __('ui.admin.no_test_yet') }}</span><small>{{ $binding->test_finished_at ?: '-' }}</small></td></tr>@endforeach
                </tbody></table></div>
                </div>
            </section>
        @empty<div class="admin-empty-state">{{ __('ui.admin.no_active_endpoint') }}</div>@endforelse
        </div>
        @include('admin.partials.pager', ['items' => $activeBindings])</section>
    @endif
</div>
@endsection
