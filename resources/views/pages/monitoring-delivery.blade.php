@extends('layouts.app')

@section('title', __('ui.delivery.title'))
@section('page-title', __('ui.delivery.title'))

@section('content')
<div class="py-3">
    <!-- Section Cards DO -->
    <section class="mondev-content rounded-4 w-100 mb-4">
        <div class="card-group-do d-flex flex-row flex-xl-fill">
            <div class="border-card d-flex flex-column flex-xl-fill">
                <div class="border-right-do d-flex justify-content-center">
                    <div class="entryarea">
                        <span class="icon-do icon-total-do"></span>
                        <div class="circle-do-blue"><div style="--i:1" class="loader-blue"></div><div style="--i:2" class="loader-blue"></div><div style="--i:3" class="loader-blue"></div></div>
                    </div>
                </div>
                <h5 class="card-title fw-semibold text-center border-right-do">{{ __('ui.delivery.total') }}</h5>
                <p class="card-text fw-bold text-center border-right-do" id="total-do-card">0</p>
            </div>
            <div class="border-card d-flex flex-column flex-xl-fill">
                <div class="border-right-do d-flex justify-content-center">
                    <div class="entryarea">
                        <span class="icon-do icon-total-delivered"></span>
                        <div class="circle-do-green"><div style="--i:1" class="loader-green"></div><div style="--i:2" class="loader-green"></div><div style="--i:3" class="loader-green"></div></div>
                    </div>
                </div>
                <h5 class="card-title fw-semibold text-center border-right-do">{{ __('ui.delivery.total_delivered') }}</h5>
                <p class="card-text fw-bold text-center border-right-do" id="total-delivered-card">0</p>
            </div>
            <div class="border-card d-flex flex-column flex-xl-fill">
                <div class="border-right-do d-flex justify-content-center">
                    <div class="entryarea">
                        <span class="icon-do icon-on-process"></span>
                        <div class="circle-do-warning"><div style="--i:1" class="loader-warning"></div><div style="--i:2" class="loader-warning"></div><div style="--i:3" class="loader-warning"></div></div>
                    </div>
                </div>
                <h5 class="card-title fw-semibold text-center border-right-do">{{ __('ui.delivery.total_on_delivery') }}</h5>
                <p class="card-text fw-bold text-center border-right-do" id="total-on-delivery-card">0</p>
            </div>
            <div class="border-card d-flex flex-column flex-xl-fill">
                <div class="border-right-do d-flex justify-content-center">
                    <div class="entryarea">
                        <span class="icon-do icon-on-process"></span>
                        <div class="circle-do-warning"><div style="--i:1" class="loader-warning"></div><div style="--i:2" class="loader-warning"></div><div style="--i:3" class="loader-warning"></div></div>
                    </div>
                </div>
                <h5 class="card-title fw-semibold text-center border-right-do">{{ __('ui.delivery.total_on_process') }}</h5>
                <p class="card-text fw-bold text-center border-right-do" id="total-on-process-card">0</p>
            </div>
            <div class="border-card d-flex flex-column flex-xl-fill">
                <div class="border-right-do d-flex justify-content-center">
                    <div class="entryarea">
                        <span class="icon-do icon-total-retry"></span>
                        <div class="circle-do-amber"><div style="--i:1" class="loader-amber"></div><div style="--i:2" class="loader-amber"></div><div style="--i:3" class="loader-amber"></div></div>
                    </div>
                </div>
                <h5 class="card-title fw-semibold text-center border-right-do">{{ __('ui.delivery.total_retry') }}</h5>
                <p class="card-text fw-bold text-center border-right-do" id="total-retry-card">0</p>
            </div>
            <div class="border-card d-flex flex-column flex-fill">
                <div class="d-flex justify-content-center">
                    <div class="entryarea">
                        <span class="icon-do icon-total-return"></span>
                        <div class="circle-do-secondary"><div style="--i:1" class="loader-secondary"></div><div style="--i:2" class="loader-secondary"></div><div style="--i:3" class="loader-secondary"></div></div>
                    </div>
                </div>
                <h5 class="card-title fw-semibold text-center">{{ __('ui.delivery.total_return') }}</h5>
                <p class="card-text fw-bold text-center" id="total-return-card">0</p>
            </div>
        </div>
    </section>

    <!-- Section Breakdown DO Per Penyedia -->
    <section class="section-card-do mb-4">
        <div class="d-flex justify-content-xxl-between justify-content-xl-center gap-xl-1 gap-lg-0 flex-wrap flex-fill" id="vendor-breakdown-cards">
            <div class="card card-paket-do col-1 mb-2 d-flex flex-fill">
                <div class="card-body">
                    <div class="border-do-penyedia"><h6 class="card-subtitle fw-semibold">{{ __('ui.delivery.per_vendor') }}</h6><br></div>
                    <div class="text-do-penyedia" data-metric="total"></div>
                </div>
            </div>
            <div class="card card-paket-do col-1 mb-2 d-flex flex-fill">
                <div class="card-body">
                    <div class="border-do-penyedia"><h6 class="card-subtitle fw-semibold">{{ __('ui.delivery.delivered_per_vendor') }}</h6><br></div>
                    <div class="text-do-penyedia" data-metric="delivered"></div>
                </div>
            </div>
            <div class="card card-paket-do col-1 mb-2 d-flex flex-fill">
                <div class="card-body">
                    <div class="border-do-penyedia"><h6 class="card-subtitle fw-semibold">{{ __('ui.delivery.on_delivery_per_vendor') }}</h6><br></div>
                    <div class="text-do-penyedia" data-metric="on_delivery"></div>
                </div>
            </div>
            <div class="card card-paket-do col-1 mb-2 d-flex flex-fill">
                <div class="card-body">
                    <div class="border-do-penyedia"><h6 class="card-subtitle fw-semibold">{{ __('ui.delivery.on_process_per_vendor') }}</h6><p class="card-subtitle">(Cetak, Picking, Packing dll)</p></div>
                    <div class="text-do-penyedia" data-metric="on_process"></div>
                </div>
            </div>
            <div class="card card-paket-do col-1 mb-2 d-flex flex-fill">
                <div class="card-body">
                    <div class="border-do-penyedia"><h6 class="card-subtitle fw-semibold">{{ __('ui.delivery.retry_per_vendor') }}</h6><br></div>
                    <div class="text-do-penyedia" data-metric="retry"></div>
                </div>
            </div>
            <div class="card card-paket-do col-1 mb-2 d-flex flex-fill">
                <div class="card-body">
                    <div class="border-do-penyedia"><h6 class="card-subtitle fw-semibold">{{ __('ui.delivery.return_per_vendor') }}</h6><br></div>
                    <div class="text-do-penyedia" data-metric="returned"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Table DO -->
    <div class="card border-0 shadow-sm p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div><h5 class="fw-bold mb-0">{{ __('ui.delivery.table') }}</h5><p class="text-muted small mb-0">{{ __('ui.delivery.table_note') }}</p></div>
        </div>
        <div class="row g-2 align-items-end mb-3" aria-label="{{ __('ui.delivery.filter') }}">
            <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="filter-period">{{ __('ui.delivery.period') }}</label><select id="filter-period" class="form-select"><option value="">{{ __('ui.delivery.all_period') }}</option></select></div>
            <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="filter-program">{{ __('ui.delivery.study_program') }}</label><select id="filter-program" class="form-select"><option value="">{{ __('ui.delivery.all_prodi') }}</option></select></div>
            <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="filter-ut">{{ __('ui.delivery.ut_region') }}</label><select id="filter-ut" class="form-select"><option value="">{{ __('ui.delivery.all_ut') }}</option></select></div>
            <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="filter-status">{{ __('ui.delivery.process_status') }}</label><select id="filter-status" class="form-select"><option value="">{{ __('ui.delivery.all_status') }}</option><option value="on_process">{{ __('ui.delivery.on_process') }}</option><option value="on_delivery">{{ __('ui.delivery.on_delivery') }}</option><option value="retry">{{ __('ui.delivery.retry') }}</option><option value="returned">{{ __('ui.delivery.return') }}</option><option value="delivered">{{ __('ui.delivery.delivered') }}</option></select></div>
            <div class="form-input-search">
                <div class="entryarea">
                    <label class="form-label form-label-cari"><img src="{{ asset('images/icon-cari.svg') }}" alt="icon-cari" /></label>
                    <input type="text" class="form-control form-control-cari" placeholder="{{ __('ui.delivery.search') }}" id="orders-search" />
                </div>
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
</div>

{{-- Modal Detail DO --}}
<div class="modal fade" id="modalDetailDO" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">{{ __('ui.do_detail.title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.action.close') }}"></button>
            </div>
            <div class="modal-body" id="modal-detail-content">
                <div class="text-center py-4 text-muted">{{ __('ui.do_detail.loading') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
