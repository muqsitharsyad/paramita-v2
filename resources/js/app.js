import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { renderOrderDetail } from './order-detail.js';
if (['sla-analysis', 'retry-monitoring', 'distribution-map'].includes(document.body?.dataset.page)) {
    import('./analytics.js');
}
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    DoughnutController,
    ArcElement,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend, DoughnutController, ArcElement);

const charts = {};
const nf = new Intl.NumberFormat('id-ID');
const vendorColors = ['#1f6feb', '#2da44e', '#fb8500', '#8250df', '#d63384'];

const pageState = {
    paketItems: { limit: 5, offset: 0, search: '' },
    judulItems: { limit: 5, offset: 0, search: '' },
    paketMatrix: { limit: 5, offset: 0, search: '' },
    judulMatrix: { limit: 5, offset: 0, search: '' },
    orders: { limit: 5, offset: 0, search: '', vendor: '', period_code: '', program_codes: '', ut_code: '' },
};

// UI strings injected by the Blade layout (window.ParamitaI18n) — never hardcode copy here.
const T = (key, fallback = '') => window.ParamitaI18n?.[key] ?? fallback;

let vendorOptions = [];
let programOptions = [];
let utOptions = [];
let periodOptions = [];

function esc(value) {
    return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
}

function q(params) {
    const url = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') url.set(k, v);
    });
    return url.toString();
}

function debounce(fn, delay = 350) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

function isoBoundary(value, end = false) {
    if (!value) return '';
    if (!end) return `${value}T00:00:00+07:00`;
    const nextDay = new Date(`${value}T00:00:00+07:00`);
    nextDay.setDate(nextDay.getDate() + 1);
    return `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')}T00:00:00+07:00`;
}

function orderParams(extra = {}) {
    const state = pageState.orders;
    return { ...extra, period_code: state.period_code, program_codes: state.program_codes, ut_code: state.ut_code, process_status_bucket: state.process_status_bucket, ordered_from: state.ordered_from, ordered_to: state.ordered_to };
}

function paginationFrom(json) {
    return json.pagination || json.meta?.pagination || json.meta || { limit: 0, offset: 0, total_filtered: 0, has_more: false };
}

function renderPager(prefix, state, pagination, reload) {
    const total = Number(pagination.total_filtered ?? 0);
    const limit = Number(pagination.limit ?? state.limit);
    const offset = Number(pagination.offset ?? state.offset);
    const count = Number(pagination.count ?? Math.min(limit, Math.max(0, total - offset)));
    const from = total === 0 ? 0 : offset + 1;
    const to = total === 0 ? 0 : offset + count;

    const range = document.getElementById(`${prefix}-range`);
    const prev = document.getElementById(`${prefix}-prev`);
    const next = document.getElementById(`${prefix}-next`);
    const rows = document.getElementById(`${prefix}-rows`);

    if (range) range.textContent = `${from}–${to} of ${nf.format(total)}`;
    if (rows) rows.value = String(limit);
    if (prev) {
        prev.disabled = offset <= 0;
        prev.onclick = () => { state.offset = Math.max(0, state.offset - state.limit); reload(); };
    }
    if (next) {
        next.disabled = !pagination.has_more && (offset + limit >= total);
        next.onclick = () => { state.offset = offset + limit; reload(); };
    }
    if (rows && !rows.dataset.bound) {
        rows.dataset.bound = '1';
        rows.addEventListener('change', () => {
            state.limit = Number(rows.value || 5);
            state.offset = 0;
            reload();
        });
    }
}

function bindSearch(id, state, reload) {
    const input = document.getElementById(id);
    if (!input || input.dataset.bound) return;
    input.dataset.bound = '1';
    input.addEventListener('input', debounce(() => {
        const value = input.value.trim();
        if (value.length === 1) return;
        state.search = value;
        state.offset = 0;
        reload();
    }));
}

function createChart(id, config) {
    const el = document.getElementById(id);
    if (!el) return;
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(el, config);
}

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('menu-toggle');
    const wrapper = document.getElementById('wrapper');
    if (toggleBtn && wrapper) {
        const isDesktop = () => window.innerWidth >= 768;
        const setMenuState = (open) => {
            wrapper.classList.toggle('toggled', open);
            toggleBtn.setAttribute('aria-expanded', String(open));
        };
        const setCollapsed = (collapsed) => {
            wrapper.classList.toggle('sidebar-collapsed', collapsed);
            toggleBtn.setAttribute('aria-expanded', String(!collapsed));
        };
        // Initial state: expanded on desktop, collapsed (drawer closed) on mobile.
        toggleBtn.setAttribute('aria-expanded', isDesktop() ? 'true' : 'false');
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (isDesktop()) {
                setCollapsed(!wrapper.classList.contains('sidebar-collapsed'));
            } else {
                setMenuState(!wrapper.classList.contains('toggled'));
            }
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                if (isDesktop()) setCollapsed(false);
                else setMenuState(false);
            }
        });
        document.getElementById('page-content-wrapper')?.addEventListener('click', event => {
            if (!isDesktop() && wrapper.classList.contains('toggled') && !toggleBtn.contains(event.target)) setMenuState(false);
        });
    }

    const path = window.location.pathname;
    initJsonEditor();
    initVendorGroupToggle();
    if (path.includes('dashboard')) loadDashboard();
    else if (path.includes('monitoring-delivery')) loadMonitoringDelivery();
    else if (path.includes('do-per-prodi')) loadDoPage('program');
    else if (path.includes('do-per-ut-daerah')) loadDoPage('ut');
});

function initVendorGroupToggle() {
    document.querySelectorAll('[data-vendor-group]').forEach(group => {
        const toggle = group.querySelector('[data-vendor-group-toggle]');
        const body = group.querySelector('[data-vendor-group-body]');
        if (!toggle || !body) return;
        const setOpen = (open) => {
            group.classList.toggle('is-open', open);
            body.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        };
        toggle.addEventListener('click', () => setOpen(body.hidden));
        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                setOpen(body.hidden);
            }
        });
    });
}

function initJsonEditor() {
    const editor = document.getElementById('jsonEditor');
    if (!editor) return;
    const status = document.getElementById('json-editor-status');
    const setStatus = (message, tone = 'muted') => {
        if (!status) return;
        status.textContent = message;
        status.className = `json-editor-status text-${tone}`;
    };
    const parse = () => JSON.parse(editor.value);
    const format = () => {
        try {
            editor.value = JSON.stringify(parse(), null, 2);
            setStatus(window.ParamitaI18n?.json_formatted ?? 'JSON valid dan sudah dirapihkan', 'success');
        } catch (e) {
            setStatus(`${window.ParamitaI18n?.json_invalid ?? 'JSON tidak valid'}: ${e.message}`, 'danger');
        }
    };

    editor.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        event.preventDefault();
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        editor.value = editor.value.substring(0, start) + '  ' + editor.value.substring(end);
        editor.selectionStart = editor.selectionEnd = start + 2;
    });
    editor.addEventListener('blur', format);
    document.querySelector('[data-json-format]')?.addEventListener('click', format);
    document.querySelector('[data-json-minify]')?.addEventListener('click', () => {
        try {
            editor.value = JSON.stringify(parse());
            setStatus(window.ParamitaI18n?.json_minified ?? 'JSON valid dan sudah diminify', 'success');
        } catch (e) {
            setStatus(`${window.ParamitaI18n?.json_invalid ?? 'JSON tidak valid'}: ${e.message}`, 'danger');
        }
    });
    document.querySelector('[data-json-validate]')?.addEventListener('click', () => {
        try {
            parse();
            setStatus(window.ParamitaI18n?.json_valid ?? 'JSON valid', 'success');
        } catch (e) {
            setStatus(`${window.ParamitaI18n?.json_invalid ?? 'JSON tidak valid'}: ${e.message}`, 'danger');
        }
    });
    format();
}

/* ============ DASHBOARD: PAKET & JUDUL ============ */
const stockRequestSequence = {};

async function loadDashboard() {
    bindSearch('filter-search-paket', pageState.paketItems, () => loadStockItems('package'));
    bindSearch('filter-search-judul', pageState.judulItems, () => loadStockItems('book'));
    bindSearch('search-paket-matrix', pageState.paketMatrix, () => loadStockMatrix('package'));
    bindSearch('search-judul-matrix', pageState.judulMatrix, () => loadStockMatrix('book'));
    [['denseSwitch', 'denseTable'], ['denseSwitchJudul', 'denseTableJudul']].forEach(([switchId, tableId]) => {
        document.getElementById(switchId)?.addEventListener('change', event => {
            document.getElementById(tableId)?.classList.toggle('table-dense', event.target.checked);
        });
    });

    ['package', 'book'].forEach(type => {
        const isPackage = type === 'package';
        const state = isPackage ? pageState.paketItems : pageState.judulItems;
        const statusId = isPackage ? 'filter-stok' : 'filter-stok-judul';
        const startId = isPackage ? 'filter-start-date' : 'filter-start-date-judul';
        const endId = isPackage ? 'filter-end-date' : 'filter-end-date-judul';
        [statusId, startId, endId].forEach(id => document.getElementById(id)?.addEventListener('change', () => {
            state.updated_from = isoBoundary(document.getElementById(startId)?.value || '');
            state.updated_to = isoBoundary(document.getElementById(endId)?.value || '', true);
            state.offset = 0;
            loadStockItems(type);
        }));
        document.getElementById(isPackage ? 'clear-paket-filters' : 'clear-judul-filters')?.addEventListener('click', () => {
            [statusId, startId, endId, isPackage ? 'filter-search-paket' : 'filter-search-judul'].forEach(id => {
                const input = document.getElementById(id);
                if (input) input.value = '';
            });
            Object.assign(state, { offset: 0, search: '', updated_from: '', updated_to: '' });
            loadStockItems(type);
        });
    });

    await Promise.all(['package', 'book'].flatMap(type => [
        loadStockSummary(type),
        loadStockChart(type),
        loadStockMatrix(type),
        loadStockItems(type),
    ]));
}

function renderStockSourceState(prefix, meta, sources = []) {
    const element = document.getElementById(`${prefix}-source-state`);
    if (!element) return;
    const coverage = meta?.coverage || {};
    const expected = Number(coverage.expected_sources || sources.length || 0);
    const included = Number(coverage.included_sources || 0);
    const stale = Number(coverage.stale_sources || 0);
    const state = meta?.state || 'partial';
    element.className = `stock-source-state is-${state}`;
    element.innerHTML = state === 'fresh'
        ? `<strong>${esc(T('stock_complete', 'Data lengkap'))}</strong><span>${included}/${expected} ${esc(T('sources_active', 'sumber aktif'))}</span>`
        : state === 'stale'
            ? `<strong>${esc(T('stock_stale', 'Data perlu diperbarui'))}</strong><span>${stale} ${esc(T('sources_stale', 'sumber melewati batas freshness'))}</span>`
            : `<strong>${esc(T('stock_partial', 'Data sebagian'))}</strong><span>${included}/${expected} ${esc(T('sources_available', 'sumber tersedia'))}</span>`;
}

async function loadStockSummary(type) {
    const prefix = type === 'package' ? 'paket' : 'judul';
    try {
        const res = await fetch(`/api/v1/stock/summary?${q({ item_type: type, vendor_scope: 'all' })}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        const totals = json.data?.totals_all_vendors ?? json.data?.totals_available ?? {};
        ['record-count', 'stock-total', 'shortage-count', 'surplus-count'].forEach(key => {
            const field = key === 'stock-total' ? 'stock_quantity' : key.replace(/-/g, '_');
            const el = document.getElementById(`${prefix}-${key}`);
            if (el) el.textContent = nf.format(Number(totals[field] || 0));
        });
        renderStockSourceState(prefix, json.meta, json.meta?.sources || []);
        renderStockVendorSummary(prefix, json.data?.by_vendor || []);
        const state = document.getElementById(`${prefix}-chart-state`);
        if (state && json.meta?.coverage) state.textContent = `${json.meta.coverage.included_sources}/${json.meta.coverage.expected_sources} ${T('sources_active', 'sumber')}`;
    } catch (error) {
        renderStockSourceState(prefix, { state: 'partial', coverage: { expected_sources: 0, included_sources: 0 } });
        const state = document.getElementById(`${prefix}-chart-state`);
        if (state) state.textContent = T('load_failed', 'Gagal memuat data');
    }
}

function renderStockVendorSummary(prefix, contributions) {
    const container = document.getElementById(`${prefix}-vendor-summary`);
    if (!container) return;
    container.innerHTML = contributions.map(source => {
        const summary = source.summary || {};
        return `<article class="stock-vendor-card">
            <div><strong>${esc(source.vendor_name || '-')}</strong><span>${esc(T('stock_total', 'Total stok'))}</span></div>
            <b>${nf.format(Number(summary.stock_quantity || 0))}</b>
            <small>${nf.format(Number(summary.shortage_count || 0))} ${esc(T('shortage', 'kurang'))} · ${nf.format(Number(summary.surplus_count || 0))} ${esc(T('surplus', 'lebih'))}</small>
        </article>`;
    }).join('') || `<p class="text-muted mb-0">${esc(T('no_sources', 'Belum ada sumber aktif.'))}</p>`;
}

async function loadStockMatrix(type) {
    const prefix = type === 'package' ? 'paket' : 'judul';
    const state = type === 'package' ? pageState.paketMatrix : pageState.judulMatrix;
    const requestKey = `${prefix}-matrix`;
    const sequence = stockRequestSequence[requestKey] = (stockRequestSequence[requestKey] || 0) + 1;
    const body = document.getElementById(`${prefix}-matrix-body`);
    if (body) body.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">${esc(T('loading', 'Memuat...'))}</td></tr>`;
    try {
        const res = await fetch(`/api/v1/stock/matrix?${q({ item_type: type, limit: state.limit, offset: state.offset, search: state.search })}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        if (sequence !== stockRequestSequence[requestKey]) return;
        const vendors = (json.sources || []).map(source => ({ code: source.vendor_code, name: source.vendor_name, state: source.state }));
        const rows = json.data || [];
        renderMatrixRows(rows, vendors, `${prefix}-matrix-head`, `${prefix}-matrix-body`, type);
        renderPager(`${prefix}-matrix`, state, paginationFrom(json), () => loadStockMatrix(type));
        renderStockSourceState(prefix, json.meta, json.sources || []);
    } catch (error) {
        if (body) body.innerHTML = `<tr><td colspan="8" class="text-center py-4"><strong class="text-danger">${esc(T('load_failed', 'Gagal memuat matrix stok.'))}</strong><br><button class="btn btn-sm btn-outline-primary mt-2" type="button" data-stock-retry="${type}">${esc(T('retry_action', 'Muat ulang'))}</button></td></tr>`;
        body?.querySelector('[data-stock-retry]')?.addEventListener('click', () => loadStockMatrix(type));
    }
}

function renderMatrixRows(rows, vendors, headId, bodyId, type) {
    const head = document.getElementById(headId);
    const body = document.getElementById(bodyId);
    if (!head || !body) return;
    head.innerHTML = (type === 'book'
        ? `<th>${esc(T('book_code','Kode Buku'))}</th><th>${esc(T('edition','Edisi'))}</th><th>${esc(T('book_title','Judul'))}</th>`
        : `<th>${esc(T('package_code','Kode Paket'))}</th><th>${esc(T('package_title','Judul Paket'))}</th>`)
        + vendors.map(vendor => `<th>${esc(vendor.name || '-')}</th>`).join('');
    if (!rows.length) {
        body.innerHTML = `<tr><td colspan="${vendors.length + (type === 'book' ? 3 : 2)}" class="text-center text-muted py-4">${esc(T('no_data','Tidak ada stok yang cocok dengan filter.'))}</td></tr>`;
        return;
    }
    body.innerHTML = rows.map(row => {
        const cells = vendors.map(vendor => {
            if (vendor.state === 'unavailable') return `<td><span class="stock-cell-state is-unavailable">${esc(T('source_unavailable','Sumber tidak tersedia'))}</span></td>`;
            const stock = row.stocks?.[vendor.code];
            if (!stock || stock.availability === 'not_supplied' || stock.stock_quantity === null) {
                return `<td><span class="stock-cell-state">${esc(T('not_supplied','Tidak disuplai'))}</span></td>`;
            }
            const quantity = Number(stock.stock_quantity);
            return `<td><span class="stock-cell-value${quantity === 0 ? ' is-zero' : ''}">${nf.format(quantity)}</span></td>`;
        }).join('');
        const identity = type === 'book'
            ? `<td><code>${esc(row.item_code || '-')}</code></td><td>${esc(row.edition || '-')}</td><td>${esc(row.title || '-')}</td>`
            : `<td><code>${esc(row.item_code || '-')}</code></td><td>${esc(row.title || '-')}</td>`;
        return `<tr>${identity}${cells}</tr>`;
    }).join('');
}

async function loadStockChart(type) {
    const prefix = type === 'package' ? 'paket' : 'judul';
    const state = document.getElementById(`${prefix}-chart-state`);
    try {
        const res = await fetch(`/api/v1/stock/matrix?${q({ item_type: type, limit: 25, offset: 0 })}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        const vendors = (json.sources || []).map(source => ({ code: source.vendor_code, name: source.vendor_name, state: source.state }));
        renderStockItemVendorChart(json.data || [], vendors, type === 'package' ? 'paketVendorChart' : 'judulVendorChart');
    } catch (error) {
        if (state) state.textContent = T('load_failed', 'Gagal memuat data');
    }
}

function renderStockItemVendorChart(rows, vendors, canvasId) {
    const labels = rows.map(row => `${String(row.title || '-').slice(0, 34)} (${row.item_code || '-'})`);
    const datasets = vendors.map((vendor, index) => ({
        label: vendor.name || '-',
        data: rows.map(row => {
            const stock = row.stocks?.[vendor.code];
            return vendor.state === 'unavailable' || !stock || stock.availability === 'not_supplied' || stock.stock_quantity === null
                ? null
                : Number(stock.stock_quantity);
        }),
        backgroundColor: vendorColors[index % vendorColors.length],
        borderRadius: 4,
    }));
    createChart(canvasId, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', axis: 'y', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: context => `${context.dataset.label}: ${context.raw === null ? T('not_available', 'tidak tersedia') : nf.format(context.raw)}` } },
            },
            scales: { x: { beginAtZero: true, ticks: { callback: value => nf.format(value) } }, y: { grid: { display: false } } },
        },
    });
}

async function loadStockItems(type) {
    const isPackage = type === 'package';
    const prefix = isPackage ? 'paket' : 'judul';
    const state = isPackage ? pageState.paketItems : pageState.judulItems;
    const requestKey = `${prefix}-items`;
    const sequence = stockRequestSequence[requestKey] = (stockRequestSequence[requestKey] || 0) + 1;
    const stockStatus = document.getElementById(isPackage ? 'filter-stok' : 'filter-stok-judul')?.value || '';
    const body = document.getElementById(isPackage ? 'paket-table-body' : 'judul-table-body');
    const colspan = isPackage ? 9 : 11;
    if (body) body.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4 text-muted">${esc(T('loading', 'Memuat...'))}</td></tr>`;
    try {
        const res = await fetch(`/api/v1/stock/items?${q({ item_type: type, limit: state.limit, offset: state.offset, search: state.search, stock_status: stockStatus, updated_from: state.updated_from, updated_to: state.updated_to })}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        if (sequence !== stockRequestSequence[requestKey]) return;
        const rows = json.data || [];
        if (body) body.innerHTML = rows.length
            ? rows.map(row => renderStockRow(row, isPackage)).join('')
            : `<tr><td colspan="${colspan}" class="text-center text-muted py-4">${esc(T('no_data','Tidak ada stok yang cocok dengan filter.'))}</td></tr>`;
        const count = document.getElementById(`${prefix}-result-count`);
        if (count) count.textContent = nf.format(Number(paginationFrom(json).total_filtered || 0));
        renderPager(`${prefix}-items`, state, paginationFrom(json), () => loadStockItems(type));
        renderStockSourceState(prefix, json.meta, json.sources || []);
    } catch (error) {
        if (body) body.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4"><strong class="text-danger">${esc(T('load_failed','Gagal memuat detail stok.'))}</strong><br><button class="btn btn-sm btn-outline-primary mt-2" type="button" data-stock-retry="${type}">${esc(T('retry_action','Muat ulang'))}</button></td></tr>`;
        body?.querySelector('[data-stock-retry]')?.addEventListener('click', () => loadStockItems(type));
    }
}

function stockMeasure(value, unit) {
    return value === null || value === undefined ? `<span class="text-muted">${esc(T('not_available', 'Belum tersedia'))}</span>` : `${nf.format(Number(value))} ${unit}`;
}

function renderStockRow(row, isPackage) {
    const status = row.stock_status || 'unknown';
    const badge = status === 'shortage' ? 'bg-danger' : (status === 'adequate' ? 'bg-warning text-dark' : (status === 'surplus' ? 'bg-success' : 'bg-secondary'));
    const label = status === 'shortage' ? T('shortage','Kurang') : (status === 'adequate' ? T('adequate','Cukup') : (status === 'surplus' ? T('surplus','Lebih') : T('unknown','Belum tersedia')));
    const quantity = row.stock_quantity === null || row.stock_quantity === undefined ? '-' : nf.format(Number(row.stock_quantity));
    const common = `<td><strong>${esc(row.vendor_name || '-')}</strong></td><td><code>${esc(row.item_code || '-')}</code></td>`;
    const measures = `<td>${stockMeasure(row.unit_weight_kg, 'Kg')}</td><td>${stockMeasure(row.total_weight_kg, 'Kg')}</td><td>${stockMeasure(row.total_height_cm, 'Cm')}</td><td>${stockMeasure(row.total_area_m2, 'm²')}</td>`;
    if (isPackage) {
        return `<tr>${common}<td>${esc(row.title || '-')}</td><td><span class="badge ${badge}">${quantity}</span></td>${measures}<td><span class="badge ${badge}">${esc(label)}</span></td></tr>`;
    }
    return `<tr>${common}<td>${esc(row.edition || '-')}</td><td>${esc(row.title || '-')}</td><td>${esc(row.size_label || '-')}</td><td><span class="badge ${badge}">${quantity}</span></td>${measures}<td><span class="badge ${badge}">${esc(label)}</span></td></tr>`;
}

/* ============ DELIVERY / DO ============ */
async function loadOptions() {
    const [vendors, programs, uts, periods] = await Promise.all([
        fetch('/api/v1/options/vendors').then(r => r.json()).catch(() => ({ data: [] })),
        fetch('/api/v1/options/programs').then(r => r.json()).catch(() => ({ data: [] })),
        fetch('/api/v1/options/ut').then(r => r.json()).catch(() => ({ data: [] })),
        fetch('/api/v1/options/period').then(r => r.json()).catch(() => ({ data: [] })),
    ]);
    vendorOptions = vendors.data || [];
    programOptions = programs.data || [];
    utOptions = uts.data || [];
    periodOptions = periods.data || [];
}

async function loadMonitoringDelivery() {
    await loadOptions();
    hydrateDeliveryFilters();
    renderVendorTabs('delivery');
    bindDeliveryFilters('delivery');
    await Promise.all([loadOrdersSummary(), loadOrdersTable()]);
}

async function loadDoPage(groupMode) {
    await loadOptions();
    hydrateDoFilters(groupMode);
    renderVendorTabs(groupMode);
    bindDeliveryFilters(groupMode);
    await Promise.all([loadOrdersSummary(), loadDoCharts(groupMode), loadOrdersTable()]);
}

function hydratePeriodFilter() {
    const period = document.getElementById('filter-period');
    if (!period || period.dataset.hydrated) return;
    period.dataset.hydrated = '1';
    period.innerHTML = '<option value="">Semua</option>' + periodOptions.map(o => `<option value="${esc(o.code)}">${esc(o.name)}</option>`).join('');
    if (periodOptions.length) period.value = periodOptions[0].code;
}

function hydrateDoFilters(groupMode) {
    hydratePeriodFilter();
    const target = document.getElementById(groupMode === 'program' ? 'filter-program' : 'filter-ut');
    if (!target || target.dataset.hydrated) return;
    target.dataset.hydrated = '1';
    const source = groupMode === 'program' ? programOptions : utOptions;
    target.innerHTML = '<option value="">Semua</option>' + source.map(o => `<option value="${esc(o.code)}">${esc(o.name)}</option>`).join('');
    pageState.orders.period_code = document.getElementById('filter-period')?.value || '';
    pageState.orders.program_codes = document.getElementById('filter-program')?.value || '';
    pageState.orders.ut_code = document.getElementById('filter-ut')?.value || '';
}

function hydrateDeliveryFilters() {
    hydratePeriodFilter();
    const program = document.getElementById('filter-program');
    const ut = document.getElementById('filter-ut');
    if (program) program.innerHTML = '<option value="">Semua prodi</option>' + programOptions.map(o => `<option value="${esc(o.code)}">${esc(o.name)}</option>`).join('');
    if (ut) ut.innerHTML = '<option value="">Semua UT daerah</option>' + utOptions.map(o => `<option value="${esc(o.code)}">${esc(o.name)}</option>`).join('');
    pageState.orders.period_code = document.getElementById('filter-period')?.value || '';
}

function bindDeliveryFilters(mode) {
    bindSearch('orders-search', pageState.orders, () => loadOrdersTable());
    ['filter-period', 'filter-program', 'filter-ut', 'filter-status', 'filter-ordered-from', 'filter-ordered-to'].forEach(id => {
        const el = document.getElementById(id);
        if (!el || el.dataset.bound) return;
        el.dataset.bound = '1';
        el.addEventListener('change', () => {
            pageState.orders.period_code = document.getElementById('filter-period')?.value || '';
            pageState.orders.program_codes = document.getElementById('filter-program')?.value || '';
            pageState.orders.ut_code = document.getElementById('filter-ut')?.value || '';
            pageState.orders.process_status_bucket = document.getElementById('filter-status')?.value || '';
            pageState.orders.ordered_from = isoBoundary(document.getElementById('filter-ordered-from')?.value || '');
            pageState.orders.ordered_to = isoBoundary(document.getElementById('filter-ordered-to')?.value || '', true);
            pageState.orders.offset = 0;
            if (mode !== 'delivery') loadDoCharts(mode);
            loadOrdersTable();
        });
    });
}

async function loadOrdersSummary() {
    const res = await fetch(`/api/v1/orders/summary?${q(orderParams({ group_by: 'none', vendor_scope: 'all' }))}`);
    if (!res.ok) return;
    const sum = await res.json();
    const data = sum.data?.totals_all_vendors || sum.data?.totals_available || sum.data || {};
    const sc = data.status_counts || {};
    const scc = data.status_code_counts || {};
    const byVendor = sum.data?.by_vendor || [];
    setText('total-do-card', data.total_orders);
    setText('total-delivered-card', scc['07'] ?? sc.delivered);
    setText('total-on-delivery-card', scc['02'] ?? sc.on_delivery);
    setText('total-on-process-card', scc['01'] ?? sc.on_process);
    setText('total-retry-card', Number(scc['03'] || 0) + Number(scc['04'] || 0) + Number(scc['05'] || 0));
    setText('total-return-card', scc['06'] ?? sc.returned);
    renderVendorBreakdown(byVendor);
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = nf.format(Number(value || 0));
}

function renderVendorBreakdown(byVendor) {
    const mapping = {
        total: s => s.total_orders ?? 0,
        delivered: s => s.status_code_counts?.['07'] ?? s.status_counts?.delivered ?? 0,
        on_delivery: s => s.status_code_counts?.['02'] ?? s.status_counts?.on_delivery ?? 0,
        on_process: s => s.status_code_counts?.['01'] ?? s.status_counts?.on_process ?? 0,
        retry: s => Number(s.status_code_counts?.['03'] || 0) + Number(s.status_code_counts?.['04'] || 0) + Number(s.status_code_counts?.['05'] || 0),
        returned: s => s.status_code_counts?.['06'] ?? s.status_counts?.returned ?? 0,
    };
    document.querySelectorAll('[data-metric]').forEach(container => {
        const metric = container.getAttribute('data-metric');
        container.innerHTML = byVendor.map(v => {
            const value = Number(mapping[metric]?.(v.summary || {}) || 0);
            return `<div class="vendor-summary-item">
                <div>
                    <p class="vendor-summary-name">${esc(v.vendor_name || T('unknown_master', 'Master tidak dikenal'))}</p>
                    <div class="vendor-summary-code">${esc(v.vendor_code || '')}</div>
                </div>
                <div class="vendor-summary-value">${nf.format(value)} DO</div>
            </div>`;
        }).join('') || '<p class="text-muted small mb-0">Belum ada vendor aktif.</p>';
    });
}

async function loadDoCharts(groupMode) {
    const groupBy = groupMode === 'ut' ? 'ut' : 'program';
    const params = orderParams({ group_by: groupBy, vendor_scope: 'all' });
    const summary = await fetch(`/api/v1/orders/summary?${q(params)}`).then(r => r.ok ? r.json() : null);
    if (!summary) return;
    const groupsByCode = new Map();
    const vendorGroups = summary.data?.by_vendor || [];
    vendorGroups.forEach(v => {
        const rows = Array.isArray(v.summary) ? v.summary : [];
        rows.forEach(g => {
            const code = g.group_code;
            const current = groupsByCode.get(code) || {
                group_code: code,
                group_name: g.group_name,
                total_orders: 0,
                status_code_counts: {'01':0,'02':0,'03':0,'04':0,'05':0,'06':0,'07':0},
                sla_counts: {on_sla:0, over_sla:0, not_applicable:0, unknown:0},
            };
            current.total_orders += Number(g.total_orders || 0);
            current.group_name ||= g.group_name;
            Object.keys(current.status_code_counts).forEach(k => current.status_code_counts[k] += Number(g.status_code_counts?.[k] || 0));
            Object.keys(current.sla_counts).forEach(k => current.sla_counts[k] += Number(g.sla_counts?.[k] || 0));
            groupsByCode.set(code, current);
        });
    });
    const groups = [...groupsByCode.values()];

    const topGroups = groups
        .sort((a, b) => Number(b.total_orders || 0) - Number(a.total_orders || 0))
        .slice(0, 10);
    const labels = topGroups.map(g => g.group_name || T('unknown_master', 'Master tidak dikenal'));
    const totals = topGroups.map(g => Number(g.total_orders || 0));
    createChart('chart-prodi-main', {
        type: 'bar',
        data: { labels, datasets: [{ label: T('total_do','Total DO'), data: totals, backgroundColor: '#1f6feb', borderRadius: 8, barThickness: 18 }] },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { mode: 'index' } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#eef2f7' } },
                y: { ticks: { autoSkip: false, font: { size: 11 } }, grid: { display: false } },
            },
        },
    });

    const aggregate = Array.isArray(groups) ? groups.reduce((acc, g) => {
        const c = g.status_code_counts || {};
        acc.delivered += Number(c['07'] || 0);
        acc.returned += Number(c['06'] || 0);
        acc.process += Number(c['01'] || 0) + Number(c['02'] || 0) + Number(c['03'] || 0) + Number(c['04'] || 0) + Number(c['05'] || 0);
        const sla = g.sla_counts || {};
        acc.onSla += Number(sla.on_sla || 0);
        acc.overSla += Number(sla.over_sla || 0);
        return acc;
    }, { delivered: 0, returned: 0, process: 0, onSla: 0, overSla: 0 }) : { delivered: 0, returned: 0, process: 0, onSla: 0, overSla: 0 };

    createChart('chart-kirim-main', {
        type: 'doughnut',
        data: { labels: [T('status_delivered','Delivered'), T('status_return','Return'), T('status_process','Proses')], datasets: [{ data: [aggregate.delivered, aggregate.returned, aggregate.process], backgroundColor: ['#12b76a', '#f04438', '#f79009'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } } } },
    });
    createChart('chart-sla-main', {
        type: 'doughnut',
        data: { labels: [T('sla_on','On SLA'), T('sla_over','Over SLA')], datasets: [{ data: [aggregate.onSla, aggregate.overSla], backgroundColor: ['#1f6feb', '#f04438'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } } } },
    });
}

function renderVendorTabs(context) {
    const nav = document.getElementById('vendor-tabs');
    const content = document.getElementById('vendor-tab-content');
    if (!nav || !content || nav.dataset.rendered) return;
    nav.dataset.rendered = '1';
    const all = [{ code: '', name: T('all_vendors','Semua Penyedia') }, ...vendorOptions];
    nav.innerHTML = all.map((v, idx) => `<li class="nav-item me-3" role="presentation"><button class="nav-link d-flex ${idx === 0 ? 'active' : ''}" type="button" data-vendor-tab="${esc(v.code)}"><div class="border border-tab rounded"><p class="fw-bold">${idx + 1}</p></div><p class="fw-semibold mb-0 ms-2">${esc(v.name)}</p></button></li>`).join('');
    content.innerHTML = `<div class="tab-pane fade show active"><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>${esc(T('col_vendor','Penyedia'))}</th><th>${esc(T('col_do','No DO'))}</th><th>${esc(T('col_ordered','Tanggal Pemesanan'))}</th><th>${esc(T('col_name','Nama'))}</th><th>${esc(T('col_province','Provinsi'))}</th><th>${esc(T('col_city','Kabupaten'))}</th><th>${esc(T('col_district','Kecamatan'))}</th><th>${esc(T('col_village','Kelurahan'))}</th><th>${esc(T('col_ut','UT Daerah'))}</th><th>${esc(T('col_status','Status Proses'))}</th><th>${esc(T('col_prodi','Nama Prodi'))}</th></tr></thead><tbody id="orders-table-body"><tr><td colspan="11" class="text-center py-4 text-muted">${esc(T('loading','Memuat...'))}</td></tr></tbody></table></div></div>`;
    nav.querySelectorAll('[data-vendor-tab]').forEach(btn => btn.addEventListener('click', () => {
        nav.querySelectorAll('.nav-link').forEach(x => x.classList.remove('active'));
        btn.classList.add('active');
        pageState.orders.vendor = btn.getAttribute('data-vendor-tab') || '';
        pageState.orders.offset = 0;
        loadOrdersTable();
    }));
}

async function loadOrdersTable() {
    const state = pageState.orders;
    const params = orderParams({ limit: state.limit, offset: state.offset, search: state.search, vendor_codes: state.vendor });
    const body = document.getElementById('orders-table-body');
    if (!body) return;
    body.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${esc(T('loading_from_vendor','Memuat data dari vendor...'))}</td></tr>`;
    try {
        const res = await fetch(`/api/v1/orders?${q(params)}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        const rows = json.data || [];
        const sources = json.meta?.sources || [];
        const selectedSource = state.vendor ? sources.find((source) => source.vendor_code === state.vendor) : null;
        const unavailable = selectedSource ? selectedSource.state === 'unavailable' : (sources.length > 0 && sources.every((source) => source.state === 'unavailable'));
        if (unavailable) {
            const vendorName = selectedSource?.vendor_name || T('selected_vendors','semua vendor yang dipilih');
            body.innerHTML = `<tr><td colspan="11" class="text-center py-4"><strong class="text-danger">${esc(vendorName)}: ${esc(T('vendor_unavailable','Data tidak dapat dimuat.'))}</strong><br><small class="text-muted">${esc(T('vendor_unavailable_hint',''))}</small></td></tr>`;
        } else if (!rows.length) {
            body.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted"><strong>${esc(T('no_do','Tidak ada DO.'))}</strong><br><small>${esc(T('no_do_hint',''))}</small></td></tr>`;
        } else {
            body.innerHTML = rows.map(renderOrderRow).join('');
        }
        renderPager('orders', state, paginationFrom(json), () => loadOrdersTable());
    } catch (error) {
        console.error('loadOrdersTable:', error);
        body.innerHTML = `<tr><td colspan="11" class="text-center py-4"><strong class="text-danger">${esc(T('load_failed','Gagal memuat data delivery.'))}</strong><br><small class="text-muted">${esc(T('load_failed_hint',''))}</small></td></tr>`;
    }
}

function renderOrderRow(r) {
    const cls = r.process_status_code === '07' ? 'bg-success' : (r.process_status_code === '06' ? 'bg-danger' : (r.process_status_code === '01' ? 'bg-info text-dark' : 'bg-warning text-dark'));
    return `<tr class="cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalDetailDO" data-vendor="${esc(r.vendor_code)}" data-source="${esc(r.id)}" onclick="loadDetailDO('${esc(r.vendor_code)}', '${esc(r.id)}')">
        <td><strong>${esc(r.vendor_name || T('unknown_master', 'Master tidak dikenal'))}</strong></td>
        <td><a href="#" class="fw-bold text-decoration-none" onclick="event.preventDefault()">${esc(r.order_number || r.id)}</a></td>
        <td>${r.ordered_at ? new Date(r.ordered_at).toLocaleString('id-ID') : '-'}</td>
        <td>${esc(r.student_name || '-')}</td>
        <td>${esc(r.province || '-')}</td>
        <td>${esc(r.city || '-')}</td>
        <td>${esc(r.district || '-')}</td>
        <td>${esc(r.village || '-')}</td>
        <td>${esc(r.ut_name || T('unknown_master', 'Master tidak dikenal'))}</td>
        <td><span class="badge ${cls}">${esc(r.process_status_name || T('unknown_master', 'Master tidak dikenal'))}</span></td>
        <td>${esc(r.program_name || T('unknown_master', 'Master tidak dikenal'))}</td>
    </tr>`;
}

window.loadDetailDO = async function(vendor, sourceId) {
    const modalBody = document.getElementById('modal-detail-content');
    if (!modalBody) return;
    await renderOrderDetail(modalBody, vendor, sourceId);
};
