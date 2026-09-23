<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailModalLabel">{{ __('ui.do_detail.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="orderDetailTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">{{ __('ui.do_detail.info') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#tab-delivery" type="button" role="tab">{{ __('ui.do_detail.delivery') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#tab-status" type="button" role="tab">{{ __('ui.do_detail.latest_status') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">{{ __('ui.do_detail.history') }}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="retry-tab" data-bs-toggle="tab" data-bs-target="#tab-retry" type="button" role="tab">{{ __('ui.delivery.retry') }}</button>
                    </li>
                </ul>
                <div class="tab-content" id="orderDetailTabContent">
                    <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                        <table class="table table-sm">
                            <tr><th width="30%">{{ __('ui.do_detail.number') }}</th><td id="modal-order-number">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.ordered_at') }}</th><td id="modal-ordered-at">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.student') }}</th><td id="modal-student-name">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.ut') }}</th><td id="modal-ut-name">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.program') }}</th><td id="modal-program-name">-</td></tr>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="tab-delivery" role="tabpanel">
                        <table class="table table-sm">
                            <tr><th width="30%">{{ __('ui.do_detail.carrier') }}</th><td id="modal-carrier">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.tracking') }}</th><td id="modal-tracking">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.package') }}</th><td id="modal-package">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.sent_at') }}</th><td id="modal-handed-at">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.completed_at') }}</th><td id="modal-completed-at">-</td></tr>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="tab-status" role="tabpanel">
                        <table class="table table-sm">
                            <tr><th width="30%">{{ __('ui.do_detail.status') }}</th><td><span class="badge bg-primary" id="modal-status-badge">-</span></td></tr>
                            <tr><th>{{ __('ui.do_detail.sla_status') }}</th><td id="modal-sla-status">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.sla_target') }}</th><td id="modal-sla-target">-</td></tr>
                            <tr><th>{{ __('ui.do_detail.elapsed_days') }}</th><td id="modal-sla-elapsed">-</td></tr>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="tab-history" role="tabpanel">
                        <div id="modal-history-timeline" class="py-2 text-muted small">{{ __('ui.do_detail.loading_history') }}</div>
                    </div>
                    <div class="tab-pane fade" id="tab-retry" role="tabpanel">
                        <div id="modal-retry-timeline" class="py-2 text-muted small">{{ __('ui.do_detail.no_retry') }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.action.close') }}</button>
            </div>
        </div>
    </div>
</div>
