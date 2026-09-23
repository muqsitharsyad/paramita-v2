@extends('layouts.app')

@section('title', __('ui.vendor.page_title'))
@section('page-title', __('ui.vendor.page_title'))

@section('content')
<div class="vendor-page vendor-workspace py-3">
    <nav class="vendor-work-nav" aria-label="{{ __('ui.nav.vendor_portal') }}">
        <a href="{{ route('vendor.dashboard') }}" class="{{ $section === 'overview' ? 'is-active' : '' }}">{{ __('ui.vendor.summary_tab') }}</a>
        <a href="{{ route('vendor.connections') }}" class="{{ $section === 'connections' ? 'is-active' : '' }}">{{ __('ui.vendor.connection_tab') }}</a>
        <a href="{{ route('vendor.endpoints') }}" class="{{ $section === 'endpoints' ? 'is-active' : '' }}">{{ __('ui.vendor.endpoint_tab') }}</a>
        <a href="{{ route('vendor.contracts') }}" class="{{ $section === 'contracts' ? 'is-active' : '' }}">{{ __('ui.vendor.contracts_tab') }}</a>
        <a href="{{ route('vendor.reference') }}" class="{{ $section === 'reference' ? 'is-active' : '' }}">{{ __('ui.vendor.reference_tab') }}</a>
    </nav>

    @if($section === 'overview')
        @php($tested = $bindings->filter(fn($binding) => $binding->latest_run?->status === 'passed')->count())
        @php($configured = $bindings->filter(fn($binding) => filled($binding->effective_path))->count())
        @php($submittedOrActive = $bindings->filter(fn($binding) => $binding->active_revision_id || $binding->draft_status === 'submitted')->count())
        @php($drafts = $bindings->filter(fn($binding) => $binding->draft_revision_id)->count())
        <section class="vendor-section vendor-onboarding" aria-labelledby="vendor-onboarding-title">
            <div class="vendor-section-heading"><div><p class="vendor-kicker">{{ __('ui.vendor.onboarding_kicker') }}</p><h3 id="vendor-onboarding-title">{{ __('ui.vendor.onboarding_title') }}</h3><p>{{ __('ui.vendor.onboarding_intro') }}</p></div></div>
            <ol class="vendor-progress-list">
                <li class="{{ $connections->isNotEmpty() ? 'is-complete' : 'is-current' }}"><span class="vendor-progress-number">1</span><div><strong>{{ __('ui.vendor.onboarding_connection') }}</strong><p>{{ __('ui.vendor.onboarding_connection_note') }}</p></div><a href="{{ route('vendor.connections') }}">{{ $connections->isNotEmpty() ? __('ui.vendor.review_action') : __('ui.vendor.start_action') }}</a></li>
                <li class="{{ $connections->isNotEmpty() ? ($configured === $bindings->count() ? 'is-complete' : 'is-current') : '' }}"><span class="vendor-progress-number">2</span><div><strong>{{ __('ui.vendor.onboarding_contract') }}</strong><p>{{ __('ui.vendor.onboarding_contract_note') }}</p></div><a href="{{ route('vendor.contracts') }}">{{ __('ui.vendor.read_contract_action') }}</a></li>
                <li class="{{ $configured === $bindings->count() && $tested === $bindings->count() ? 'is-complete' : ($connections->isNotEmpty() ? 'is-current' : '') }}"><span class="vendor-progress-number">3</span><div><strong>{{ __('ui.vendor.onboarding_test') }}</strong><p>{{ $configured }}/{{ $bindings->count() }} {{ __('ui.vendor.configured_short') }} · {{ $tested }}/{{ $bindings->count() }} {{ __('ui.vendor.passed_short') }}</p></div><a href="{{ route('vendor.endpoints') }}">{{ __('ui.vendor.configure_action') }}</a></li>
                <li class="{{ $submittedOrActive === $bindings->count() ? 'is-complete' : '' }}"><span class="vendor-progress-number">4</span><div><strong>{{ __('ui.vendor.onboarding_submit') }}</strong><p>{{ $submittedOrActive }}/{{ $bindings->count() }} {{ __('ui.vendor.submitted_or_active_short') }}</p></div><a href="{{ route('vendor.endpoints') }}">{{ __('ui.vendor.open_endpoints_action') }}</a></li>
            </ol>
            <div class="vendor-definition-done"><strong>{{ __('ui.vendor.done_title') }}</strong><span>{{ __('ui.vendor.done_note') }}</span></div>
        </section>
        @if($drafts)<div class="vendor-notice vendor-notice-warning"><strong>{{ trans_choice('ui.vendor.pending_drafts', $drafts, ['count' => $drafts]) }}</strong></div>@endif
    @elseif($section === 'connections')
        <section class="vendor-section"><div class="vendor-section-heading"><h3>{{ __('ui.vendor.connection_tab') }}</h3></div>
        @if(!$credentialStorageReady)<div class="vendor-notice vendor-notice-warning"><strong>{{ __('ui.vendor.cred_note') }}</strong> {!! __('ui.vendor.operator_note') !!} {{ __('ui.vendor.development_no_auth') }}</div>@endif
        @if($connections->isEmpty())
            @include('vendor.partials.connection-form', ['action' => route('vendor.connection.store'), 'formUid' => 'new', 'submitLabel' => __('ui.vendor.save_connection')])
        @endif

        <div class="vendor-connection-list mt-4">
            <h4 class="vendor-connection-list-title">{{ __('ui.vendor.stored_connection') }}</h4>
            @forelse($connections as $connection)
            <article class="vendor-connection-detail">
                <header class="vendor-connection-row">
                    <div class="vendor-connection-id"><strong>{{ $connection->label }}</strong><code>{{ $connection->base_url }}</code><small>{{ __('ui.common.revision_short') }} {{ $connection->revision }} · {{ $connection->auth_label }}</small></div>
                    <div class="vendor-connection-meta">
                        <span class="status-label {{ $connection->uses_auto_token ?? false ? 'status-token' : ($connection->credential_configured ? 'status-approved' : 'status-waiting') }}">{{ $connection->credential_configured ? ($connection->auth_type === 'token_login' || $connection->auth_type === 'oauth2_client_credentials' ? __('ui.vendor.automatic_token') : __('ui.vendor.ready_to_use')) : __('ui.vendor.credential_missing') }}</span>
                        <form method="POST" action="{{ route('vendor.connection.testLogin', $connection->id) }}" data-testing-form data-testing-label="{{ __('ui.vendor.testing_login') }}">@csrf<button type="submit" class="btn btn-sm btn-outline-primary">{{ __('ui.vendor.test_login') }}</button></form>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#conn-edit-{{ $connection->id }}" aria-expanded="false" aria-controls="conn-edit-{{ $connection->id }}">{{ __('ui.vendor.update_action') }}</button>
                    </div>
                </header>
                <div class="collapse" id="conn-edit-{{ $connection->id }}">
                    <div class="vendor-connection-edit">@php($editFormArgs = ['action' => route('vendor.connection.update', $connection->id), 'connection' => $connection, 'formUid' => 'edit-'.$connection->id, 'submitLabel' => trans('ui.conn.save_revision')]) @include('vendor.partials.connection-form', $editFormArgs)</div>
                </div>
            </article>
            @empty
            <div class="vendor-empty-state">{{ __('ui.vendor.empty_connection') }}</div>
            @endforelse
        </div>
        </section>
    @elseif($section === 'endpoints')
        <section class="vendor-section"><div class="vendor-section-heading"><div><h3>{{ __('ui.vendor.endpoint_vendor_label') }}</h3><p>{{ __('ui.vendor.test_hint') }}</p></div><form method="POST" action="{{ route('vendor.bindings.testAll') }}" data-testing-form data-testing-label="{{ __('ui.vendor.testing_all') }}">@csrf<button type="submit" class="btn btn-outline-primary">{{ __('ui.vendor.test_all') }}</button></form></div>
        <div class="table-responsive"><table class="table vendor-endpoint-table align-middle mb-0"><thead><tr><th>{{ __('ui.admin.kebutuhan_data') }}</th><th>{{ __('ui.vendor.endpoint_tab') }}</th><th>{{ __('ui.vendor.last_test_label') }}</th><th class="text-end">{{ __('ui.tpl.aksi') }}</th></tr></thead><tbody>
        @foreach($bindings as $binding) @php($latest = $binding->latest_run) @php($hasDraft = (bool) $binding->draft_revision_id) @php($canSubmit = $hasDraft && $latest && $latest->status === 'passed')
            @php($report = $latest ? (json_decode((string) $latest->report_json, true) ?: []) : [])
            <tr>
                <td><strong>{{ $binding->label }}</strong><code>{{ $binding->operation_key }}</code></td>
                <td>@if($binding->sample_url)<code class="vendor-url">{{ $binding->sample_url }}</code>@if($hasDraft)<span class="status-label status-waiting">{{ __('ui.vendor.draft') }}</span>@endif @else<span class="text-muted">{{ __('ui.vendor.no_path_yet') }}</span>@endif</td>
                <td>@if($latest)<span class="status-label {{ $latest->status === 'passed' ? 'status-approved' : 'status-error' }}">{{ $latest->status === 'passed' ? __('ui.vendor.passed') : __('ui.vendor.needs_fix') }}</span>@if($latest->status !== 'passed')<button type="button" class="btn btn-sm btn-link p-0 ms-2 align-baseline" data-test-report="{{ $binding->id }}" data-bs-toggle="modal" data-bs-target="#modalTestReport">{{ __('ui.vendor.view_problems') }}</button>@endif @else<span class="status-label status-waiting">{{ __('ui.vendor.untested') }}</span>@endif</td>
                <td class="text-end"><div class="vendor-actions"><a class="btn btn-sm btn-outline-secondary" href="{{ route('vendor.contracts', ['operation' => $binding->operation_key]) }}">{{ __('ui.vendor.format_action') }}</a><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $binding->id }}">{{ __('ui.vendor.configure_action') }}</button><form method="POST" action="{{ route('vendor.bindings.test', $binding->id) }}" data-testing-form data-testing-label="{{ __('ui.vendor.testing') }}">@csrf<button class="btn btn-sm btn-outline-primary" @disabled(!$binding->effective_path)>{{ __('ui.vendor.test') }}</button></form><form method="POST" action="{{ route('vendor.bindings.submit', $binding->id) }}">@csrf<button class="btn btn-sm btn-primary" @disabled(!$canSubmit)>{{ __('ui.vendor.submit_action') }}</button></form></div></td>
            </tr>
            @if($latest)<script type="application/json" id="test-report-{{ $binding->id }}">{!! json_encode(['operation' => $binding->operation_key, 'status' => $latest->status, 'finished_at' => (string) $latest->finished_at, 'error' => $report['error'] ?? null]) !!}</script>@endif
            <tr class="collapse" id="edit-{{ $binding->id }}"><td colspan="4" class="vendor-detail-cell"><form method="POST" action="{{ route('vendor.bindings.updateDraft', $binding->id) }}" class="vendor-endpoint-form">@csrf
                @if($connections->isEmpty())
                    <div class="vendor-empty-state">{{ __('ui.vendor.empty_no_connection') }} <a href="{{ route('vendor.connections') }}">{{ __('ui.vendor.connection_tab') }}</a> {{ __('ui.vendor.need_connection_first') }}</div>
                @else
                @if($connections->count() === 1 && $connections->first()->revision_id)
                    <input type="hidden" name="connection_revision_id" value="{{ $connections->first()->revision_id }}">
                    <div class="vendor-endpoint-conn-static"><label>{{ __('ui.vendor.single_connection') }}</label><code>{{ $connections->first()->label }}</code></div>
                @else
                    <div><label for="connection-{{ $binding->id }}">{{ __('ui.vendor.use_this') }}</label><select id="connection-{{ $binding->id }}" name="connection_revision_id" class="form-select" required><option value="">{{ __('ui.vendor.select_conn_note') }}</option>@foreach($connections as $connection) @if($connection->revision_id)<option value="{{ $connection->revision_id }}" @selected($connection->revision_id === ($binding->draft_connection_revision_id ?? null) || (!$binding->draft_revision_id && $connection->revision_id === ($binding->active_connection_revision_id ?? null)))>{{ $connection->label }} · {{ $connection->auth_label }} · {{ $connection->base_url }}</option>@endif @endforeach</select></div>
                @endif
                @endif
                <div><label for="path-{{ $binding->id }}">{{ __('ui.vendor.path_label') }}</label><input id="path-{{ $binding->id }}" type="text" name="path" class="form-control" value="{{ $binding->effective_path ?: '' }}" placeholder="v1/orders/list" required><small>{!! __('ui.vendor.path_hint_full') !!} <code>v1/orders/list</code>. {{ __('ui.vendor.no_query_parameter') }}</small></div><button type="submit" class="btn btn-primary">{{ __('ui.vendor.save_draft') }}</button></form></td></tr>
        @endforeach</tbody></table></div></section>
        <div class="modal fade" id="modalTestReport" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title" id="test-report-title">{{ __('ui.vendor.detail_test') }}</h5><p class="modal-sub" id="test-report-sub"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.vendor.close') }}"></button></div>
            <div class="modal-body" id="test-report-body"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.vendor.close') }}</button></div>
        </div></div></div>
    @elseif($section === 'contracts')
        @php($selected = $bindings->firstWhere('operation_key', $selectedOperation) ?: $bindings->first())
        @php($contractGroups = $bindings->groupBy(fn($binding) => $binding->module === 'stock' ? 'stock' : 'delivery'))
        <section class="vendor-section vendor-doc-shell"><div class="vendor-section-heading"><div><p class="vendor-kicker">{{ __('ui.vendor.api_reference_kicker') }}</p><h3>{{ __('ui.vendor.contracts_tab') }}</h3><p>{{ __('ui.vendor.api_reference_intro') }}</p></div></div>
        @if($selected)
        <div class="vendor-doc-layout">
            <aside class="vendor-doc-index" aria-label="{{ __('ui.vendor.endpoint_index') }}">
                @foreach(['stock' => __('ui.vendor.domain_stock'), 'delivery' => __('ui.vendor.domain_delivery')] as $groupKey => $groupLabel)
                    @if(($contractGroups[$groupKey] ?? collect())->isNotEmpty())<div class="vendor-doc-group"><strong>{{ $groupLabel }}</strong>@foreach($contractGroups[$groupKey] as $binding)<a href="{{ route('vendor.contracts', ['operation' => $binding->operation_key]) }}" class="{{ $selected->operation_key === $binding->operation_key ? 'is-active' : '' }}"><span>{{ $binding->label }}</span><code>{{ $binding->operation_key }}</code></a>@endforeach</div>@endif
                @endforeach
            </aside>
            <article class="vendor-api-doc">
                <header class="vendor-api-header"><p class="vendor-kicker">{{ $selected->module === 'stock' ? __('ui.vendor.domain_stock') : __('ui.vendor.domain_delivery') }}</p><h4>{{ $selected->label }}</h4><code>{{ $selected->operation_key }}</code><p>{{ $selected->guide['purpose'] }}</p><div class="vendor-api-badges"><span>GET</span><span>/v1</span><span>application/json</span></div></header>

                <section class="vendor-doc-section" id="request"><div class="vendor-doc-heading"><span>01</span><div><h5>{{ __('ui.vendor.request_title') }}</h5><p>{{ __('ui.vendor.request_intro') }}</p></div></div><pre class="vendor-example-code"><code>{{ $selected->guide['example_request'] }}</code></pre>
                    <div class="vendor-header-list">@foreach($transport['headers'] as $header)<div><code>{{ $header['name'] }}: {{ $header['value'] }}</code><span>{{ $header['meaning'] }}</span></div>@endforeach</div>
                    <div class="table-responsive"><table class="table vendor-param-table mb-0"><thead><tr><th>{{ __('ui.vendor.parameter_name') }}</th><th>{{ __('ui.vendor.requirement') }}</th><th>{{ __('ui.vendor.data_type') }}</th><th>{{ __('ui.vendor.example') }}</th><th>{{ __('ui.vendor.meaning') }}</th></tr></thead><tbody>@foreach(['required' => __('ui.vendor.required_parameters'), 'optional' => __('ui.vendor.optional_parameters')] as $key => $title) @foreach($selected->guide['request'][$key] ?? [] as $parameter)<tr><td><code>{{ $parameter['name'] }}</code></td><td>{{ $title }}</td><td>{{ $parameter['type'] }}</td><td><code>{{ $parameter['example'] }}</code></td><td>{{ $parameter['meaning'] }}</td></tr>@endforeach @endforeach</tbody></table></div>
                </section>

                <section class="vendor-doc-section" id="response"><div class="vendor-doc-heading"><span>02</span><div><h5>{{ __('ui.vendor.response_example_title') }}</h5><p>{{ __('ui.vendor.response_example_rule') }}</p></div></div><pre class="vendor-response-example"><code>{{ json_encode($selected->guide['response_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre></section>

                <section class="vendor-doc-section" id="validation"><div class="vendor-doc-heading"><span>03</span><div><h5>{{ __('ui.vendor.validation_rules_title') }}</h5><p>{{ __('ui.vendor.validation_rules_intro') }}</p></div></div>
                    <ul class="vendor-rule-list"><li>{{ __('ui.vendor.read_json_structure') }}</li><li>{{ __('ui.vendor.read_json_values') }}</li><li>{{ __('ui.vendor.read_json_null') }}</li><li>{{ __('ui.vendor.read_json_array') }}</li><li>{{ __('ui.vendor.timestamp_rule') }}</li><li>{{ __('ui.vendor.pagination_rule') }}</li><li>{{ __('ui.vendor.versioning_rule') }}</li></ul>
                    <dl class="vendor-meta-list"><div><dt><code>generated_at</code></dt><dd>{{ __('ui.vendor.metadata_generated_at') }}</dd></div><div><dt><code>data_as_of</code></dt><dd>{{ __('ui.vendor.metadata_data_as_of') }}</dd></div><div><dt><code>scope</code></dt><dd>{{ __('ui.vendor.metadata_scope') }}</dd></div><div><dt><code>limit / offset / total_filtered / has_more</code></dt><dd>{{ __('ui.vendor.metadata_pagination') }}</dd></div></dl>
                </section>

                <section class="vendor-doc-section" id="fields"><div class="vendor-doc-heading"><span>04</span><div><h5>{{ __('ui.vendor.field_types_title') }}</h5><p>{{ __('ui.vendor.fields_intro') }}</p></div></div><div class="table-responsive"><table class="table vendor-field-table mb-0"><thead><tr><th>JSON path</th><th>{{ __('ui.vendor.data_type') }}</th></tr></thead><tbody>@foreach($selected->guide['response_fields'] as $field)<tr><td><code>{{ $field['path'] }}</code></td><td>{{ $field['type'] }}</td></tr>@endforeach</tbody></table></div></section>

                <section class="vendor-doc-section" id="http"><div class="vendor-doc-heading"><span>05</span><div><h5>{{ __('ui.vendor.http_outcomes_title') }}</h5><p>{{ __('ui.vendor.http_outcomes_intro') }}</p></div></div><div class="vendor-outcome-list">@foreach($transport['outcomes'] as $outcome)<div><code>{{ $outcome['status'] }}</code><span>{{ $outcome['meaning'] }}</span></div>@endforeach</div></section>
            </article>
        </div>
        @endif</section>
    @elseif($section === 'reference')
        <section class="vendor-section">
            <div class="vendor-section-heading"><h3>{{ __('ui.ref.vendor_title') }}</h3></div>

            <div class="vendor-ref-grid">
                <article class="vendor-ref-card">
                    <h4>{{ __('ui.ref.ut') }} <span class="badge bg-secondary">ut_code</span></h4>
                    <div class="vendor-ref-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('ui.ref.code') }}</th><th>{{ __('ui.ref.name') }}</th><th>{{ __('ui.ref.type') }}</th></tr></thead>
                            <tbody>
                            @foreach($reference['ut'] as $row)
                                <tr><td><code>{{ $row->code }}</code></td><td>{{ $row->name }}</td><td>{{ $row->type }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="vendor-ref-card">
                    <h4>{{ __('ui.ref.program') }} <span class="badge bg-secondary">program_code</span></h4>
                    <div class="vendor-ref-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('ui.ref.code') }}</th><th>{{ __('ui.ref.name') }}</th></tr></thead>
                            <tbody>
                            @foreach($reference['program'] as $row)
                                <tr><td><code>{{ $row->code }}</code></td><td>{{ $row->name }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="vendor-ref-card">
                    <h4>{{ __('ui.ref.status') }} <span class="badge bg-secondary">process_status_code</span></h4>
                    <div class="vendor-ref-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('ui.ref.code') }}</th><th>{{ __('ui.ref.name') }}</th><th>{{ __('ui.ref.bucket') }}</th></tr></thead>
                            <tbody>
                            @foreach($reference['status'] as $row)
                                <tr><td><code>{{ $row->code }}</code></td><td>{{ $row->label }}</td><td>{{ $row->bucket }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="vendor-ref-card">
                    <h4>{{ __('ui.ref.retry_reason') }} <span class="badge bg-secondary">reason_code</span></h4>
                    <div class="vendor-ref-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('ui.ref.code') }}</th><th>{{ __('ui.ref.name') }}</th></tr></thead>
                            <tbody>
                            @foreach($reference['retry_reason'] as $row)
                                <tr><td><code>{{ $row->code }}</code></td><td>{{ $row->label }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="vendor-ref-card vendor-ref-wide">
                    <h4>{{ __('ui.ref.catalog') }} <span class="badge bg-secondary">catalog_key</span></h4>
                    <div class="vendor-ref-scroll">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>catalog_key</th><th>{{ __('ui.ref.type') }}</th><th>{{ __('ui.ref.item_code') }}</th><th>{{ __('ui.ref.edition') }}</th><th>{{ __('ui.ref.title') }}</th></tr></thead>
                            <tbody>
                            @foreach($reference['catalog'] as $row)
                                <tr>
                                    <td><code>{{ $row->catalog_key }}</code></td>
                                    <td>{{ $row->item_type }}</td>
                                    <td>{{ $row->item_code }}</td>
                                    <td>{{ $row->edition }}</td>
                                    <td>{{ $row->title }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>
    @endif
</div>
@endsection

@section('scripts')
<script>
const vendorTestText = {!! json_encode([
  'testing' => __('ui.vendor.testing'),
  'result' => __('ui.vendor.test_result'),
  'problems' => __('ui.vendor.problems_title'),
  'failed' => __('ui.vendor.test_failed_short'),
  'passed' => __('ui.vendor.passed'),
  'matches' => __('ui.vendor.response_matches'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
document.querySelectorAll('[data-auth-form]').forEach(form=>{
  const s=form.querySelector('[data-auth-type]'),fs=form.querySelector('[data-auth-fields]');
  if(!s||!fs)return;
  const show=(...names)=>fs.querySelectorAll('[data-auth-field]').forEach(e=>{
    e.hidden=!names.includes(e.dataset.authField);
    const inp=e.querySelector('input');
    if(inp&&inp.hasAttribute('data-auth-secret'))inp.required=!e.hidden;
  });
  const update=()=>{
    const t=s.value;
    fs.hidden=t==='none';
    const map={
      api_key_header:()=>show('header','secret'),
      api_key_query:()=>show('param','secret'),
      basic:()=>show('username','secret'),
      bearer:()=>show('secret'),
      oauth2_client_credentials:()=>show('oauth-token-url','oauth-client-id','oauth-scope','secret'),
      token_login:()=>show('login-url','username','secret','login-user-field','login-password-field','login-token-field','login-expiry-field'),
      none:()=>show(),
    };(map[t]||map.none)();
  };
  s.addEventListener('change',update);
  fs.addEventListener('input',update);
  update();
});
document.querySelectorAll('[data-testing-form]').forEach(form=>form.addEventListener('submit',()=>{
  const button=form.querySelector('button[type="submit"]');
  if(!button||button.disabled)return;
  button.disabled=true;
  button.setAttribute('aria-busy','true');
  button.innerHTML='<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>'+String(form.dataset.testingLabel||vendorTestText.testing)+'</span>';
}));

document.querySelectorAll('[data-test-report]').forEach(btn=>btn.addEventListener('click',()=>{
  const el=document.getElementById('test-report-'+btn.dataset.testReport);
  if(!el)return;
  const r=JSON.parse(el.textContent);
  const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  document.getElementById('test-report-title').textContent=vendorTestText.result+' · '+r.operation;
  document.getElementById('test-report-sub').textContent=r.finished_at||'';
  const problems=String(r.error||'').split('\n').map(s=>s.trim()).filter(Boolean);
  let html='';
  if(r.status!=='passed'){
    html='<div class="test-error-box"><strong>'+esc(vendorTestText.problems)+'</strong>';
    html+=problems.length?'<ul class="test-error-list">'+problems.map(problem=>'<li>'+esc(problem)+'</li>').join('')+'</ul>':'<p>'+esc(vendorTestText.failed)+'</p>';
    html+='</div>';
  }else{
    html='<div class="vendor-check is-pass"><strong>'+esc(vendorTestText.passed)+'</strong><span>'+esc(vendorTestText.matches)+'</span></div>';
  }
  document.getElementById('test-report-body').innerHTML=html;
}));
</script>
@endsection
