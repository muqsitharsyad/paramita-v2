@extends('layouts.app')

@section('title', __('ui.stock.title_package'))
@section('page-title', __('ui.stock.title_package'))

@section('content')
<div class="py-3">
    <div class="dashboard-content">
        <ul class="nav nav-underline mb-3" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-paket-tab" data-bs-toggle="pill" data-bs-target="#pills-paket" type="button" role="tab" aria-controls="pills-paket" aria-selected="true">
                    <img src="{{ asset('images/icon-paket.svg') }}" alt="icon-paket" /> Paket
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-judul-tab" data-bs-toggle="pill" data-bs-target="#pills-judul" type="button" role="tab" aria-controls="pills-judul" aria-selected="false">
                    <img src="{{ asset('images/icon-judul.svg') }}" alt="icon-judul" /> Judul
                </button>
            </li>
        </ul>

        <div class="tab-content mt-3" id="pills-tabContent">
            {{-- TAB PAKET --}}
            <div class="tab-pane fade show active" id="pills-paket" role="tabpanel" tabindex="0">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.package_records') }}</div><h3 id="paket-record-count" class="fw-bold mb-0">0</h3></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.package_unit') }}</div><h3 id="paket-stock-total" class="fw-bold mb-0">0</h3></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.shortage') }}</div><h3 id="paket-shortage-count" class="fw-bold mb-0 text-danger">0</h3></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.surplus') }}</div><h3 id="paket-surplus-count" class="fw-bold mb-0 text-success">0</h3></div></div>
                </div>
                <div id="paket-source-state" class="stock-source-state" role="status" aria-live="polite"></div>
                <div id="paket-vendor-summary" class="stock-vendor-summary mb-3"></div>
                <div class="card border-0 shadow-sm p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fw-bold mb-0">{{ __('ui.stock.chart_package_title') }}</h5>
                        <span id="paket-chart-state" class="small text-muted">Memuat...</span>
                    </div>
                    <div style="height: 260px"><canvas id="paketVendorChart" aria-label="Chart stok paket per penyedia"></canvas></div>
                </div>
                {{-- Matrix Tabel Stok Paket --}}
                <div class="section-all-paket" data-aos="fade-up">
                    <div class="card mb-3 shadow-1 border-0">
                        <div class="card-body card-body-input">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="form-input-search">
                                    <div class="entryarea">
                                        <label class="form-label form-label-cari"><img src="{{ asset('images/icon-cari.svg') }}" alt="icon-cari" /></label>
                                        <input type="text" class="form-control form-control-cari" placeholder="cari kode/judul paket" id="search-paket-matrix" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-dense-wrap">
                            <table id="denseTableStok" class="table table-hover align-middle" style="width: 100%">
                                <thead>
                                    <tr id="paket-matrix-head">
                                        <th>{{ __('ui.stock.package_code') }}</th>
                                        {{-- Kolom vendor dinamis --}}
                                    </tr>
                                </thead>
                                <tbody id="paket-matrix-body">
                                    <tr><td colspan="2" class="text-center py-4 text-muted">{{ __('ui.pg.loading_matrix_package') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="dt-toolbar">
                            <div class="text-muted small">{{ __('ui.stock.matrix_package') }}</div>
                            <div class="right">
                                <label for="paket-matrix-rows" class="mb-0">Rows per page:</label>
                                <select id="paket-matrix-rows" class="form-select form-select-sm" style="width: auto"><option>5</option><option>10</option><option>25</option></select>
                                <span id="paket-matrix-range" class="text-muted">0–0 of 0</span>
                                <button id="paket-matrix-prev" class="btn btn-sm btn-outline-secondary" type="button">&lsaquo;</button>
                                <button id="paket-matrix-next" class="btn btn-sm btn-outline-secondary" type="button">&rsaquo;</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Detail Stok Paket --}}
                <div class="section-all-paket" data-aos="fade-up">
                    <div class="card mb-3 shadow-1 border-0">
                        <div class="card-body card-body-input">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">

                                <div class="entryarea mb-2">
                                    <label class="label-float fw-semibold">{{ __('ui.stock.stock') }}</label>
                                    <select class="form-control form-select" id="filter-stok" style="width: 483px !important; height: 55px !important">
                                        <option value="" selected>{{ __('ui.stock.all') }}</option>
                                        <option value="shortage">{{ __('ui.stock.shortage') }}</option>
                                        <option value="adequate">{{ __('ui.stock.adequate') }}</option>
                                        <option value="surplus">{{ __('ui.stock.surplus') }}</option>
                                        <option value="unknown">{{ __('ui.stock.unknown') }}</option>
                                    </select>
                                </div>
                                <div class="entryarea mb-2">
                                    <label class="label-float fw-semibold">{{ __('ui.stock.start_date') }}</label>
                                    <input type="date" class="form-control" id="filter-start-date" style="width: 180px !important; height: 55px !important" />
                                </div>
                                <div class="entryarea mb-2">
                                    <label class="label-float fw-semibold">{{ __('ui.stock.end_date') }}</label>
                                    <input type="date" class="form-control" id="filter-end-date" style="width: 180px !important; height: 55px !important" />
                                </div>
                                <div class="form-input-search">
                                    <div class="entryarea">
                                        <label class="form-label form-label-cari"><img src="{{ asset('images/icon-cari.svg') }}" alt="icon-cari" /></label>
                                        <input type="search" class="form-control form-control-cari" placeholder="cari" id="filter-search-paket" aria-label="Cari detail stok paket" />
                                    </div>
                                </div>
                                <button id="clear-paket-filters" class="btn btn-outline-secondary mb-2" type="button">{{ __('ui.action.clear_filters') }}</button>
                            </div>

                            <div class="d-flex align-items-center justify-content-start gap-1 card-valket">
                                <h5 class="fw-semibold card-value-paket" id="paket-result-count">0</h5>
                                <p class="card-keterangan">{{ __('ui.stock.result_found') }}</p>
                            </div>
                        </div>

                        <div class="table-dense-wrap">
                            <table id="denseTable" class="table table-hover align-middle" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('ui.stock.provider') }}</th>
                                        <th>{{ __('ui.stock.package_code') }}</th>
                                        <th>{{ __('ui.stock.package_title') }}</th>
                                        <th>{{ __('ui.stock.stock') }}</th>
                                        <th>{{ __('ui.stock.unit_weight') }}</th>
                                        <th>{{ __('ui.stock.total_weight') }}</th>
                                        <th>{{ __('ui.stock.total_height') }}</th>
                                        <th>{{ __('ui.stock.total_area') }}</th>
                                        <th>{{ __('ui.stock.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="paket-table-body">
                                    <tr><td colspan="9" class="text-center py-4 text-muted">{{ __('ui.pg.loading_stock_package') }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="dt-toolbar">
                            <div class="d-flex align-items-center form-check form-switch gap-1">
                                <input class="form-check-input" type="checkbox" id="denseSwitch" />
                                <label class="form-check-label mb-0" for="denseSwitch">{{ __('ui.stock.dense') }}</label>
                            </div>
                            <div class="right">
                                <label for="paket-items-rows" class="mb-0">Rows per page:</label>
                                <select id="paket-items-rows" class="form-select form-select-sm" style="width: auto">
                                    <option>5</option>
                                    <option>10</option>
                                    <option>25</option>
                                    <option>50</option>
                                </select>
                                <span id="paket-items-range" class="text-muted">0–0 of 0</span>
                                <button id="paket-items-prev" class="btn btn-sm btn-outline-secondary" type="button">&lsaquo;</button>
                                <button id="paket-items-next" class="btn btn-sm btn-outline-secondary" type="button">&rsaquo;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB JUDUL --}}
            <div class="tab-pane fade" id="pills-judul" role="tabpanel" aria-labelledby="pills-judul-tab" tabindex="0">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.book_records') }}</div><h3 id="judul-record-count" class="fw-bold mb-0">0</h3></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.book_unit') }}</div><h3 id="judul-stock-total" class="fw-bold mb-0">0</h3></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.shortage') }}</div><h3 id="judul-shortage-count" class="fw-bold mb-0 text-danger">0</h3></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">{{ __('ui.stock.surplus') }}</div><h3 id="judul-surplus-count" class="fw-bold mb-0 text-success">0</h3></div></div>
                </div>
                <div id="judul-source-state" class="stock-source-state" role="status" aria-live="polite"></div>
                <div id="judul-vendor-summary" class="stock-vendor-summary mb-3"></div>
                <div class="card border-0 shadow-sm p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fw-bold mb-0">{{ __('ui.stock.chart_book_title') }}</h5>
                        <span id="judul-chart-state" class="small text-muted">Memuat...</span>
                    </div>
                    <div style="height: 260px"><canvas id="judulVendorChart" aria-label="Chart stok judul per penyedia"></canvas></div>
                </div>
                {{-- Matrix Tabel Stok Judul --}}
                <div class="section-all-paket" data-aos="fade-up">
                    <div class="card mb-3 shadow-1 border-0">
                        <div class="card-body card-body-input">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="form-input-search">
                                    <div class="entryarea">
                                        <label class="form-label form-label-cari"><img src="{{ asset('images/icon-cari.svg') }}" alt="icon-cari" /></label>
                                        <input type="text" class="form-control form-control-cari" placeholder="cari kode/judul buku" id="search-judul-matrix" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-dense-wrap">
                            <table id="denseTableJudulMatrix" class="table table-hover align-middle" style="width: 100%">
                                <thead>
                                    <tr id="judul-matrix-head">
                                        <th>{{ __('ui.stock.book_code') }}</th>
                                        <th>{{ __('ui.stock.edition') }}</th>
                                        {{-- Kolom vendor dinamis --}}
                                    </tr>
                                </thead>
                                <tbody id="judul-matrix-body">
                                    <tr><td colspan="3" class="text-center py-4 text-muted">{{ __('ui.pg.loading_matrix_book') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="dt-toolbar">
                            <div class="text-muted small">{{ __('ui.stock.matrix_book') }}</div>
                            <div class="right">
                                <label for="judul-matrix-rows" class="mb-0">Rows per page:</label>
                                <select id="judul-matrix-rows" class="form-select form-select-sm" style="width: auto"><option>5</option><option>10</option><option>25</option></select>
                                <span id="judul-matrix-range" class="text-muted">0–0 of 0</span>
                                <button id="judul-matrix-prev" class="btn btn-sm btn-outline-secondary" type="button">&lsaquo;</button>
                                <button id="judul-matrix-next" class="btn btn-sm btn-outline-secondary" type="button">&rsaquo;</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Detail Stok Judul --}}
                <div class="section-all-paket" data-aos="fade-up">
                    <div class="card mb-3 shadow-1 border-0">
                        <div class="card-body card-body-input">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="entryarea mb-2">
                                    <label class="label-float fw-semibold">{{ __('ui.stock.stock') }}</label>
                                    <select class="form-control form-select" id="filter-stok-judul" style="width: 180px !important; height: 55px !important">
                                        <option value="" selected>{{ __('ui.stock.all') }}</option>
                                        <option value="shortage">{{ __('ui.stock.shortage') }}</option>
                                        <option value="adequate">{{ __('ui.stock.adequate') }}</option>
                                        <option value="surplus">{{ __('ui.stock.surplus') }}</option>
                                        <option value="unknown">{{ __('ui.stock.unknown') }}</option>
                                    </select>
                                </div>
                                <div class="entryarea mb-2">
                                    <label class="label-float fw-semibold" for="filter-start-date-judul">{{ __('ui.stock.start_date') }}</label>
                                    <input type="date" class="form-control" id="filter-start-date-judul" />
                                </div>
                                <div class="entryarea mb-2">
                                    <label class="label-float fw-semibold" for="filter-end-date-judul">{{ __('ui.stock.end_date') }}</label>
                                    <input type="date" class="form-control" id="filter-end-date-judul" />
                                </div>
                                <div class="form-input-search">
                                    <div class="entryarea">
                                        <label class="form-label form-label-cari"><img src="{{ asset('images/icon-cari.svg') }}" alt="icon-cari" /></label>
                                        <input type="search" class="form-control form-control-cari" placeholder="cari judul buku" id="filter-search-judul" aria-label="Cari detail stok judul" />
                                    </div>
                                </div>
                                <button id="clear-judul-filters" class="btn btn-outline-secondary mb-2" type="button">{{ __('ui.action.clear_filters') }}</button>
                            </div>
                            <div class="d-flex align-items-center justify-content-start gap-1 card-valket">
                                <h5 class="fw-semibold card-value-paket" id="judul-result-count">0</h5>
                                <p class="card-keterangan">{{ __('ui.stock.result_found') }}</p>
                            </div>
                        </div>
                        <div class="table-dense-wrap">
                            <table id="denseTableJudul" class="table table-hover align-middle" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('ui.stock.provider') }}</th>
                                        <th>{{ __('ui.stock.book_code') }}</th>
                                        <th>{{ __('ui.stock.edition') }}</th>
                                        <th>{{ __('ui.stock.book_title') }}</th>
                                        <th>{{ __('ui.stock.book_size') }}</th>
                                        <th>{{ __('ui.stock.stock') }}</th>
                                        <th>{{ __('ui.stock.unit_weight') }}</th>
                                        <th>{{ __('ui.stock.total_weight') }}</th>
                                        <th>{{ __('ui.stock.total_height') }}</th>
                                        <th>{{ __('ui.stock.total_area') }}</th>
                                        <th>{{ __('ui.stock.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="judul-table-body">
                                    <tr><td colspan="11" class="text-center py-4 text-muted">{{ __('ui.pg.loading_stock_book') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="dt-toolbar">
                            <div class="d-flex align-items-center form-check form-switch gap-1">
                                <input class="form-check-input" type="checkbox" id="denseSwitchJudul" />
                                <label class="form-check-label mb-0" for="denseSwitchJudul">{{ __('ui.stock.dense') }}</label>
                            </div>
                            <div class="right">
                                <label for="judul-items-rows" class="mb-0">Rows per page:</label>
                                <select id="judul-items-rows" class="form-select form-select-sm" style="width: auto"><option>5</option><option>10</option><option>25</option><option>50</option></select>
                                <span id="judul-items-range" class="text-muted">0–0 of 0</span>
                                <button id="judul-items-prev" class="btn btn-sm btn-outline-secondary" type="button">&lsaquo;</button>
                                <button id="judul-items-next" class="btn btn-sm btn-outline-secondary" type="button">&rsaquo;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
