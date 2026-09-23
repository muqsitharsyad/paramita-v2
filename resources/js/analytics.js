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
    LineController,
    LineElement,
    PointElement,
} from 'chart.js';
import Modal from 'bootstrap/js/dist/modal';
import { renderOrderDetail } from './order-detail.js';
import Highcharts from 'highcharts/highmaps';
import indonesiaMap from '@highcharts/map-collection/countries/id/id-all.topo.json';

Chart.register(
    BarController, BarElement, CategoryScale, LinearScale,
    Tooltip, Legend, DoughnutController, ArcElement,
    LineController, LineElement, PointElement,
);

const T = (key, fallback = '') => window.ParamitaI18n?.[key] ?? fallback;
const number = new Intl.NumberFormat(document.documentElement.lang || 'id-ID');
const vendorColors = ['#005b96', '#52a86a', '#eb8b36', '#7759a8', '#b94d69', '#2c8fa3', '#a05c2a'];
const slaColors = { faster: '#58b96b', on_sla: '#005b96', over_sla: '#d65454' };
const charts = [];

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);
}

function fmtDt(value) {
    if (!value) return '-';
    const d = new Date(value);
    return isNaN(d) ? String(value) : d.toLocaleString(document.documentElement.lang || 'id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function fmtDate(value) {
    if (!value) return '-';
    const d = new Date(value);
    return isNaN(d) ? String(value) : d.toLocaleDateString(document.documentElement.lang || 'id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

/* ── Period range ── */
function periodRange(mode) {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    if (mode === 'weekly') start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
    if (mode === 'monthly') start.setDate(1);

    const end = new Date(start);
    if (mode === 'daily') end.setDate(end.getDate() + 1);
    if (mode === 'weekly') end.setDate(end.getDate() + 7);
    if (mode === 'monthly') end.setMonth(end.getMonth() + 1);

    const day = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const label = mode === 'daily'
        ? start.toLocaleDateString(document.documentElement.lang || 'id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
        : `${start.toLocaleDateString(document.documentElement.lang || 'id-ID', { day: 'numeric', month: 'short' })} – ${new Date(end - 86400000).toLocaleDateString(document.documentElement.lang || 'id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}`;

    return {
        ordered_from: `${day(start)}T00:00:00+07:00`,
        ordered_to: `${day(end)}T00:00:00+07:00`,
        occurred_from: `${day(start)}T00:00:00+07:00`,
        occurred_to: `${day(end)}T00:00:00+07:00`,
        label,
        mode,
    };
}

function contributions(payload) {
    return (payload?.data?.by_vendor || []).filter(v => v && v.summary && typeof v.summary === 'object');
}

function renderSourceState(payload) {
    const box = document.getElementById('analytics-source-state');
    if (!box) return;
    const sources = payload?.meta?.sources || [];
    const valid = Number(payload?.meta?.coverage?.included_sources || 0);
    const invalid = sources.filter(s => s.state === 'invalid_format');
    const unavailable = sources.filter(s => s.state === 'unavailable');

    if (!sources.length && valid === 0) {
        box.className = 'analytics-source-state is-error';
        box.innerHTML = `<strong>${esc(T('analytics.no_active_source', 'Belum ada endpoint analytics aktif.'))}</strong><span>${esc(T('analytics.configure_source', 'Vendor perlu mengatur dan mengajukan endpoint orders.analytics.'))}</span>`;
        return;
    }
    if (!invalid.length && !unavailable.length) { box.className = 'analytics-source-state'; box.innerHTML = ''; return; }

    const items = [
        ...invalid.map(s => `<li><strong>${esc(s.vendor_name)}</strong>: ${esc(T('analytics.invalid_format', 'format JSON tidak sesuai'))}${s.problems?.length ? `<ul>${s.problems.map(p => `<li>${esc(p)}</li>`).join('')}</ul>` : ''}</li>`),
        ...unavailable.map(s => `<li><strong>${esc(s.vendor_name)}</strong>: ${esc(T('analytics.source_unavailable', 'sumber data tidak tersedia'))}</li>`),
    ];
    box.className = `analytics-source-state${valid === 0 ? ' is-error' : ''}`;
    box.innerHTML = `<strong>${esc(valid === 0 ? T('analytics.data_blocked', 'Data tidak ditampilkan.') : T('analytics.partial_data', 'Data tampil sebagian.'))}</strong><ul>${items.join('')}</ul>`;
}

async function fetchAnalytics(mode) {
    const range = periodRange(mode);
    const params = new URLSearchParams({
        ordered_from: range.ordered_from,
        ordered_to: range.ordered_to,
        occurred_from: range.occurred_from,
        occurred_to: range.occurred_to,
    });
    const res = await fetch(`/api/v1/orders/analytics?${params}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`analytics ${res.status}`);
    return { payload: await res.json(), range };
}

async function fetchAnalyticsSnapshot() {
    const res = await fetch('/api/v1/orders/analytics', { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`analytics ${res.status}`);
    return res.json();
}

function bindPeriodButtons(load) {
    document.querySelectorAll('[data-analytics-period]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-analytics-period]').forEach(b => b.classList.toggle('is-active', b === btn));
            load(btn.dataset.analyticsPeriod || 'daily');
        });
    });
}

function destroyCharts() {
    while (charts.length) charts.pop()?.destroy();
}

/* ══════════════════════════════════════════════
   ANALISIS SLA
   - Grouped horizontal bar (vendor × carrier × status) — scales well for N vendors
   - Table: click row → list modal
   - List modal: click a DO row → detail modal (order_number, carrier, SLA status, timestamps)
══════════════════════════════════════════════ */
function initSlaPage() {
    let allRows = [];   // aggregated sla[]
    let detailRows = []; // sla_details[]
    let selectedVendor = 'all';
    let currentVendorData = [];
    let slaBarChart = null;
    let slaCarrierChart = null;
    let seq = 0;

    /* detail modal (second popup) */
    const openSlaDoDetail = async row => {
        const modal = document.getElementById('slaDoDetailModal');
        if (!modal) return;
        Modal.getOrCreateInstance(modal).show();
        await renderOrderDetail(modal.querySelector('#sla-do-detail-content'), row.vendor_code, row.id);
    };

    /* list modal */
    const showSlaList = (rows, title) => {
        const modal = document.getElementById('slaDetailModal');
        if (!modal) return;
        modal.querySelector('#sla-detail-subtitle').textContent = title;
        const body = modal.querySelector('#sla-detail-body');
        body.innerHTML = rows.length
            ? rows.map((row, i) => {
                const badge = row.sla_status === 'over_sla' ? 'badge-danger' : row.sla_status === 'faster' ? 'badge-success' : 'badge-info';
                return `<tr class="analytics-clickable" tabindex="0" data-sla-do="${i}">
                    <td>${i + 1}</td>
                    <td><strong>${esc(row.order_number || row.id)}</strong></td>
                    <td>${esc(row.vendor_name)}</td>
                    <td>${esc(row.carrier_name)}</td>
                    <td><small>${esc(row.tracking_number || '-')}</small></td>
                    <td>${row.sla_target_days != null ? `${row.sla_target_days} ${esc(T('analytics.days','hari'))}` : '-'}</td>
                    <td>${fmtDate(row.handed_to_carrier_at)}</td>
                    <td>${fmtDate(row.completed_at)}</td>
                    <td>${esc(row.process_status_name || T('analytics.unknown_master', 'Master tidak dikenal'))}</td>
                    <td><span class="sla-badge ${badge}">${esc(T(`analytics.sla_${row.sla_status}`, row.sla_status))}</span></td>
                </tr>`;
            }).join('')
            : `<tr><td colspan="10" class="analytics-empty">${esc(T('analytics.no_detail', 'Tidak ada detail pada periode ini.'))}</td></tr>`;
        body.querySelectorAll('[data-sla-do]').forEach((el, i) => {
            const open = () => {
                modal.addEventListener('hidden.bs.modal', () => {
                    window.setTimeout(() => openSlaDoDetail(rows[i]), 100);
                }, { once: true });
                Modal.getOrCreateInstance(modal).hide();
            };
            el.addEventListener('click', open);
            el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        });
        Modal.getOrCreateInstance(modal).show();
    };

    const renderTable = () => {
        const q = (document.getElementById('sla-search')?.value || '').trim().toLowerCase();
        const rows = allRows.filter(r =>
            (selectedVendor === 'all' || r.vendor_code === selectedVendor) &&
            (!q || `${r.carrier_name} ${r.vendor_name}`.toLowerCase().includes(q))
        );
        const body = document.getElementById('sla-table-body');
        body.innerHTML = rows.length
            ? rows.map((row, i) => {
                const total = Number(row.faster_count || 0) + Number(row.on_sla_count || 0) + Number(row.over_sla_count || 0);
                const onPct = total > 0 ? ((Number(row.on_sla_count) / total) * 100).toFixed(0) : '-';
                const overPct = total > 0 ? ((Number(row.over_sla_count) / total) * 100).toFixed(0) : '-';
                return `<tr class="analytics-clickable" tabindex="0" data-sla-row="${i}">
                    <td>${esc(row.vendor_name)}</td>
                    <td><strong>${esc(row.carrier_name)}</strong></td>
                    <td><span class="sla-badge badge-success">${number.format(row.faster_count)}</span></td>
                    <td><span class="sla-badge badge-info">${number.format(row.on_sla_count)}</span></td>
                    <td><span class="sla-badge badge-danger">${number.format(row.over_sla_count)}</span></td>
                    <td>${number.format(total)}</td>
                    <td>${overPct !== '-' ? `<span class="sla-pct-over">${overPct}%</span>` : '-'}</td>
                    <td>${esc(row.sla_target_days)} ${esc(T('analytics.days', 'hari'))}</td>
                </tr>`;
            }).join('')
            : `<tr><td colspan="8" class="analytics-empty">${esc(T('analytics.no_valid_data', 'Belum ada data valid untuk periode ini.'))}</td></tr>`;
        body.querySelectorAll('[data-sla-row]').forEach((el, i) => {
            const open = () => {
                const row = rows[i];
                const listRows = detailRows.filter(d => d.vendor_code === row.vendor_code && d.carrier_name === row.carrier_name);
                showSlaList(listRows, `${row.vendor_name} · ${row.carrier_name}`);
            };
            el.addEventListener('click', open);
            el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        });
    };

    const vendorRollups = vendorData => vendorData.map(vendor => {
        const totals = (vendor.summary.sla || []).reduce((acc, row) => {
            acc.faster += Number(row.faster_count || 0);
            acc.on += Number(row.on_sla_count || 0);
            acc.over += Number(row.over_sla_count || 0);
            return acc;
        }, { faster: 0, on: 0, over: 0 });
        totals.total = totals.faster + totals.on + totals.over;
        return { ...vendor, ...totals, overRate: totals.total ? totals.over / totals.total : 0 };
    }).sort((a, b) => b.overRate - a.overRate || b.total - a.total);

    const buildSlaChart = vendorData => {
        slaBarChart?.destroy();
        const canvas = document.getElementById('slaBarChart');
        const rows = vendorRollups(vendorData);
        if (!canvas || !rows.length) return;
        const percentage = (value, total) => total ? Number((value / total * 100).toFixed(2)) : 0;
        const wrap = canvas.closest('.sla-bar-wrap');
        if (wrap) wrap.style.height = `${Math.min(520, Math.max(320, 112 + rows.length * 52))}px`;
        const counts = {
            faster: rows.map(row => row.faster),
            on_sla: rows.map(row => row.on),
            over_sla: rows.map(row => row.over),
        };
        slaBarChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(row => row.vendor_name),
                datasets: [
                    { key: 'faster', label: T('analytics.faster_sla', 'Lebih cepat'), data: rows.map(row => percentage(row.faster, row.total)), backgroundColor: slaColors.faster, borderRadius: 2 },
                    { key: 'on_sla', label: T('analytics.on_sla', 'Sesuai SLA'), data: rows.map(row => percentage(row.on, row.total)), backgroundColor: slaColors.on_sla, borderRadius: 2 },
                    { key: 'over_sla', label: T('analytics.over_sla', 'Melebihi SLA'), data: rows.map(row => percentage(row.over, row.total)), backgroundColor: slaColors.over_sla, borderRadius: 2 },
                ],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', axis: 'y', intersect: false },
                scales: {
                    x: { stacked: true, min: 0, max: 100, ticks: { callback: value => `${value}%` }, grid: { color: '#eef2f7' } },
                    y: { stacked: true, grid: { display: false }, ticks: { font: { size: 11, weight: '600' } } },
                },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: { callbacks: {
                        title: items => rows[items[0].dataIndex].vendor_name,
                        label: context => `${context.dataset.label}: ${number.format(counts[context.dataset.key][context.dataIndex])} DO (${context.parsed.x.toFixed(1)}%)`,
                        footer: items => `${T('analytics.total_do', 'Total DO')}: ${number.format(rows[items[0].dataIndex].total)}`,
                    } },
                },
                onClick: (_event, points) => { if (points.length) selectVendor(rows[points[0].index].vendor_code); },
            },
        });
        charts.push(slaBarChart);
    };

    const buildCarrierChart = vendorData => {
        slaCarrierChart?.destroy();
        const canvas = document.getElementById('slaCarrierChart');
        const empty = document.getElementById('sla-carrier-empty');
        const vendor = vendorData.find(item => item.vendor_code === selectedVendor);
        const rows = [...(vendor?.summary.sla || [])].sort((a, b) => {
            const aTotal = Number(a.total_orders || 0);
            const bTotal = Number(b.total_orders || 0);
            return (Number(b.over_sla_count || 0) / Math.max(1, bTotal)) - (Number(a.over_sla_count || 0) / Math.max(1, aTotal)) || bTotal - aTotal;
        });
        if (!canvas || !vendor || !rows.length) {
            canvas?.closest('.sla-carrier-wrap')?.classList.add('d-none');
            empty?.classList.remove('d-none');
            return;
        }
        canvas.closest('.sla-carrier-wrap')?.classList.remove('d-none');
        empty?.classList.add('d-none');
        document.getElementById('sla-carrier-title').textContent = `${T('analytics.carrier_breakdown', 'Rincian ekspedisi')}: ${vendor.vendor_name}`;
        const wrap = canvas.closest('.sla-carrier-wrap');
        if (wrap) wrap.style.height = `${Math.min(500, Math.max(300, 100 + rows.length * 48))}px`;
        slaCarrierChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(row => row.carrier_name),
                datasets: [
                    { key: 'faster', label: T('analytics.faster_sla', 'Lebih cepat'), data: rows.map(row => Number(row.faster_count || 0)), backgroundColor: slaColors.faster, borderRadius: 2 },
                    { key: 'on_sla', label: T('analytics.on_sla', 'Sesuai SLA'), data: rows.map(row => Number(row.on_sla_count || 0)), backgroundColor: slaColors.on_sla, borderRadius: 2 },
                    { key: 'over_sla', label: T('analytics.over_sla', 'Melebihi SLA'), data: rows.map(row => Number(row.over_sla_count || 0)), backgroundColor: slaColors.over_sla, borderRadius: 2 },
                ],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'nearest', axis: 'y', intersect: true },
                scales: { x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef2f7' } }, y: { stacked: true, grid: { display: false } } },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: { callbacks: { footer: items => `${T('analytics.sla_target', 'Target SLA')}: ${rows[items[0].dataIndex].sla_target_days} ${T('analytics.days', 'hari')}` } },
                },
                onClick: (_event, points) => {
                    if (!points.length) return;
                    const point = points[0];
                    const row = rows[point.index];
                    const status = slaCarrierChart.data.datasets[point.datasetIndex].key;
                    const listRows = detailRows.filter(detail => detail.vendor_code === vendor.vendor_code && detail.carrier_name === row.carrier_name && detail.sla_status === status);
                    showSlaList(listRows, `${vendor.vendor_name} · ${row.carrier_name} · ${T(`analytics.sla_${status}`, status)}`);
                },
            },
        });
        charts.push(slaCarrierChart);
    };

    const buildKpiCards = vendorData => {
        let tFaster = 0, tOn = 0, tOver = 0;
        vendorData.forEach(v => (v.summary.sla || []).forEach(r => {
            tFaster += Number(r.faster_count || 0);
            tOn += Number(r.on_sla_count || 0);
            tOver += Number(r.over_sla_count || 0);
        }));
        const total = tFaster + tOn + tOver;
        const pct = n => total > 0 ? `${((n / total) * 100).toFixed(1)}%` : '-';
        document.getElementById('sla-kpi-faster').textContent = number.format(tFaster);
        document.getElementById('sla-kpi-compliant').textContent = number.format(tFaster + tOn);
        document.getElementById('sla-kpi-over').textContent = number.format(tOver);
        document.getElementById('sla-kpi-total').textContent = number.format(total);
        document.getElementById('sla-kpi-compliant-pct').textContent = pct(tFaster + tOn);
        document.getElementById('sla-kpi-over-pct').textContent = pct(tOver);
    };

    const selectVendor = vendorCode => {
        selectedVendor = vendorCode;
        const tabs = document.getElementById('sla-vendor-tabs');
        tabs?.querySelectorAll('[data-vendor-tab]').forEach(button => {
            button.classList.toggle('is-active', button.dataset.vendorTab === vendorCode);
        });
        const scoped = vendorCode === 'all'
            ? currentVendorData
            : currentVendorData.filter(vendor => vendor.vendor_code === vendorCode);
        buildKpiCards(scoped);
        buildCarrierChart(currentVendorData);
        renderTable();
    };

    const load = async mode => {
        const sequence = ++seq;
        const cards = document.getElementById('sla-vendor-cards');
        cards.innerHTML = `<div class="analytics-loading"><span class="spinner-border spinner-border-sm"></span>${esc(T('analytics.loading', 'Memuat data...'))}</div>`;
        try {
            const { payload, range } = await fetchAnalytics(mode);
            if (sequence !== seq) return;
            document.getElementById('analytics-period-label').textContent = range.label;
            renderSourceState(payload);
            destroyCharts();

            const vendorData = contributions(payload);
            currentVendorData = vendorData;
            allRows = vendorData.flatMap(v => (v.summary.sla || []).map(r => ({ ...r, vendor_code: v.vendor_code, vendor_name: v.vendor_name })));
            detailRows = vendorData.flatMap(v => (v.summary.sla_details || []).map(r => ({ ...r, vendor_code: v.vendor_code, vendor_name: v.vendor_name })));

            if (!vendorData.length) {
                cards.innerHTML = `<div class="analytics-empty">${esc(T('analytics.no_valid_data', 'Belum ada data valid untuk periode ini.'))}</div>`;
            } else {
                cards.innerHTML = '';
                buildSlaChart(vendorData);

                const tabs = document.getElementById('sla-vendor-tabs');
                tabs.innerHTML = `<button type="button" class="is-active" data-vendor-tab="all">${esc(T('analytics.all_providers', 'Semua penyedia'))}</button>${vendorData.map(v => `<button type="button" data-vendor-tab="${esc(v.vendor_code)}">${esc(v.vendor_name)}</button>`).join('')}`;
                tabs.querySelectorAll('[data-vendor-tab]').forEach(btn => btn.addEventListener('click', () => {
                    selectVendor(btn.dataset.vendorTab);
                }));
                const worstVendor = vendorRollups(vendorData)[0]?.vendor_code || 'all';
                selectVendor(worstVendor);
            }
        } catch (err) {
            console.error(err);
            const s = document.getElementById('analytics-source-state');
            s.className = 'analytics-source-state is-error';
            s.textContent = T('analytics.load_failed', 'Data analytics gagal dimuat. Coba lagi.');
            cards.innerHTML = '';
        }
    };

    document.getElementById('sla-search')?.addEventListener('input', renderTable);
    bindPeriodButtons(load);
    load('daily');
}

/* ══════════════════════════════════════════════
   MONITORING RETRY
   - Chart 1: grouped bar by reason × vendor (jumlah absolut)
   - Chart 2: stacked bar by reason × carrier (ekspedisi)
   - Click chart → detail modal (DO list)
══════════════════════════════════════════════ */
async function retryReasonMap() {
    try {
        const res = await fetch('/api/v1/options/retry-reasons', { headers: { Accept: 'application/json' } });
        if (!res.ok) return new Map();
        const payload = await res.json();
        return new Map((payload.data || []).map(item => [item.code, item.name]));
    } catch { return new Map(); }
}

function initRetryPage() {
    let chartVendor = null;
    let chartCarrier = null;
    let details = [];
    let reasons = new Map();
    let seq = 0;

    const showDetail = (filterFn, title) => {
        const rows = details.filter(filterFn);
        document.getElementById('retry-detail-subtitle').textContent = title;
        document.getElementById('retry-detail-body').innerHTML = rows.length
            ? rows.map(row => `<tr>
                <td><strong>${esc(row.order_number || row.id)}</strong></td>
                <td>${esc(row.vendor_name)}</td>
                <td>${esc(row.carrier_name)}</td>
                <td>${esc(row.reason_name || reasons.get(row.reason_code) || T('analytics.unknown_master', 'Master tidak dikenal'))}</td>
                <td>${esc(row.student_identifier || '-')}</td>
                <td>${esc(row.student_name)}</td>
                <td>${esc(row.ut_name || T('analytics.unknown_master', 'Master tidak dikenal'))}</td>
                <td>${esc(row.program_name || T('analytics.unknown_master', 'Master tidak dikenal'))}</td>
                <td>${esc(row.tracking_number || '-')}</td>
                <td>${number.format(row.retry_attempt)}</td>
                <td>${esc(fmtDt(row.occurred_at))}</td>
                <td>${esc(row.process_status_name || T('analytics.unknown_master', 'Master tidak dikenal'))}</td>
            </tr>`).join('')
            : `<tr><td colspan="12" class="analytics-empty">${esc(T('analytics.no_detail', 'Tidak ada detail.'))}</td></tr>`;
        Modal.getOrCreateInstance(document.getElementById('retryDetailModal')).show();
    };

    const load = async mode => {
        const sequence = ++seq;
        try {
            const [{ payload, range }, reasonLabels] = await Promise.all([fetchAnalytics(mode), retryReasonMap()]);
            if (sequence !== seq) return;
            reasons = reasonLabels;
            document.getElementById('analytics-period-label').textContent = range.label;
            renderSourceState(payload);

            const vendors = contributions(payload);
            details = vendors.flatMap(v => (v.summary.retry_details || []).map(r => ({ ...r, vendor_code: v.vendor_code, vendor_name: v.vendor_name })));

            /* collect all used reason codes */
            const retrySummaries = vendors.flatMap(v => v.summary.retries || []);
            retrySummaries.forEach(row => {
                if (row.reason_name) reasons.set(row.reason_code, row.reason_name);
            });
            const reasonCodes = [...new Set(retrySummaries.map(row => row.reason_code))];
            const reasonLabelsArr = reasonCodes.map(code => reasons.get(code) || T('analytics.unknown_master', 'Master tidak dikenal'));

            /* Chart 1: vendor dimension — grouped bar (reason on Y axis, vendor as dataset) */
            chartVendor?.destroy(); chartCarrier?.destroy();

            const vendorDatasets = vendors.map((v, i) => ({
                label: v.vendor_name,
                data: reasonCodes.map(c => (v.summary.retries || []).filter(r => r.reason_code === c).reduce((s, r) => s + Number(r.retry_count || 0), 0)),
                backgroundColor: vendorColors[i % vendorColors.length],
                borderRadius: 3,
                vendorCode: v.vendor_code,
            }));

            const cvCanvas = document.getElementById('retryCountChart');
            if (cvCanvas) {
                chartVendor = new Chart(cvCanvas, {
                    type: 'bar',
                    data: { labels: reasonLabelsArr, datasets: vendorDatasets },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { stacked: false, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef2f7' } },
                            y: { stacked: false, ticks: { font: { size: 11 } } },
                        },
                        plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index' } },
                        onClick: (_e, points) => {
                            if (!points.length) return;
                            const pt = points[0];
                            const ds = vendorDatasets[pt.datasetIndex];
                            const code = reasonCodes[pt.index];
                            showDetail(r => r.vendor_code === ds.vendorCode && r.reason_code === code, `${ds.label} · ${reasons.get(code) || code}`);
                        },
                    },
                });
                charts.push(chartVendor);
            }

            /* Chart 2: carrier dimension — collect all carriers, stacked bar per reason × carrier */
            const allCarriers = [...new Set(vendors.flatMap(v => (v.summary.retries || []).map(r => r.carrier_name || '-')))].sort();
            const carrierPalette = ['#2c8fa3', '#eb8b36', '#7759a8', '#b94d69', '#52a86a', '#005b96', '#a05c2a'];
            const carrierDatasets = allCarriers.map((carrier, i) => ({
                label: carrier,
                data: reasonCodes.map(code =>
                    vendors.reduce((total, v) =>
                        total + (v.summary.retries || []).filter(r => r.reason_code === code && (r.carrier_name || '-') === carrier).reduce((s, r) => s + Number(r.retry_count || 0), 0),
                        0)
                ),
                backgroundColor: carrierPalette[i % carrierPalette.length],
                borderRadius: 3,
                carrier,
            }));

            const cpCanvas = document.getElementById('retryPercentChart');
            if (cpCanvas) {
                chartCarrier = new Chart(cpCanvas, {
                    type: 'bar',
                    data: { labels: reasonLabelsArr, datasets: carrierDatasets },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef2f7' } },
                            y: { stacked: true, ticks: { font: { size: 11 } } },
                        },
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                mode: 'index',
                                callbacks: {
                                    footer: items => {
                                        const total = items.reduce((s, i) => s + i.raw, 0);
                                        return `Total: ${total}`;
                                    },
                                },
                            },
                        },
                        onClick: (_e, points) => {
                            if (!points.length) return;
                            const pt = points[0];
                            const ds = carrierDatasets[pt.datasetIndex];
                            const code = reasonCodes[pt.index];
                            showDetail(r => r.carrier_name === ds.carrier && r.reason_code === code, `${ds.label} · ${reasons.get(code) || code}`);
                        },
                    },
                });
                charts.push(chartCarrier);
            }

        } catch (err) {
            console.error(err);
            const s = document.getElementById('analytics-source-state');
            s.className = 'analytics-source-state is-error';
            s.textContent = T('analytics.load_failed', 'Data analytics gagal dimuat. Coba lagi.');
        }
    };

    bindPeriodButtons(load);
    load('daily');
}

/* ══════════════════════════════════════════════
   DISTRIBUTION MAP
   - Snapshot 30 hari otomatis, tanpa filter periode
   - Peta: choropleth warna provinsi (% on-time) + bubble city
   - Bar chart top-10 kota bervolume terbesar (tepat waktu vs terlambat vs gagal)
   - KPI cards yang richer
   - Popup detail: per kota dengan breakdown vendor
══════════════════════════════════════════════ */
function initDistributionPage() {
    let mapChart;
    let perfBarChart;
    let groupedRows = [];
    let vendorBreakdownMap = new Map(); /* key: "provinceCode:cityCode" → rows per vendor */
    const normalizeProvince = value => String(value || '').toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
    const provinceAliases = {
        'dki jakarta': 'jakarta raya',
        'daerah khusus ibukota jakarta': 'jakarta raya',
        'di yogyakarta': 'yogyakarta',
        'daerah istimewa yogyakarta': 'yogyakarta',
        'papua barat': 'irian jaya barat',
        'kepulauan bangka belitung': 'bangka belitung',
    };
    const provinceGeometryKeys = new Map(
        indonesiaMap.objects.default.geometries
            .filter(geometry => geometry.properties?.name)
            .map(geometry => [normalizeProvince(geometry.properties.name), geometry.properties['hc-key']])
    );
    const provinceKey = name => provinceGeometryKeys.get(provinceAliases[normalizeProvince(name)] || normalizeProvince(name));

    const performance = rate => rate === null
        ? ['is-neutral', T('analytics.no_data', 'Tidak ada data')]
        : rate >= .95 ? ['is-good', T('analytics.performance_good', 'Baik (≥95%)')]
        : rate >= .80 ? ['is-warning', T('analytics.performance_medium', 'Sedang (80–94%)')]
        : ['is-danger', T('analytics.performance_attention', 'Perlu perhatian (<80%)')];

    const openDetail = row => {
        const modal = document.getElementById('distributionDetailModal');
        if (!modal) return;
        modal.querySelector('#distribution-detail-title').textContent = `${row.city_name}, ${row.province_name}`;

        const key = `${row.province_code}:${row.city_code}`;
        const byVendor = vendorBreakdownMap.get(key) || [];
        const rate = row.total_shipments > 0 ? row.delivered_on_time / row.total_shipments : null;
        const [pClass, pLabel] = performance(rate);

        modal.querySelector('#distribution-detail-body').innerHTML = `
            <div class="dist-detail-primary">
                <div><span>${esc(T('analytics.on_time_rate', '% tepat waktu'))}</span><strong>${rate !== null ? `${(rate * 100).toFixed(1)}%` : '-'}</strong><small class="performance-badge ${pClass}">${esc(pLabel)}</small></div>
                <div><span>${esc(T('analytics.total_shipments', 'Total kiriman'))}</span><strong>${number.format(row.total_shipments)}</strong><small>${esc(T('analytics.snapshot_30_days', '30 hari terakhir'))}</small></div>
            </div>
            <div class="dist-outcome-row">
                <div><span>${esc(T('analytics.on_time', 'Tepat waktu'))}</span><strong>${number.format(row.delivered_on_time)}</strong></div>
                <div><span>${esc(T('analytics.late', 'Terlambat'))}</span><strong>${number.format(row.delivered_late)}</strong></div>
                <div><span>${esc(T('analytics.failed_returned', 'Gagal/retur'))}</span><strong>${number.format(row.failed_or_returned)}</strong></div>
                <div><span>${esc(T('analytics.avg_delay_days', 'Rata-rata terlambat'))}</span><strong>${row.avg_delay_days !== null ? `${Number(row.avg_delay_days).toFixed(1)} ${esc(T('analytics.days', 'hari'))}` : '-'}</strong></div>
            </div>
            <h3 class="dist-vendor-heading">${esc(T('analytics.provider', 'Penyedia'))}</h3>
            <div class="dist-provider-list">${byVendor.map(v => {
                const vRate = v.total_shipments > 0 ? (v.delivered_on_time / v.total_shipments * 100).toFixed(1) : '-';
                return `<div class="dist-provider-row"><strong>${esc(v.vendor_name)}</strong><span>${number.format(v.total_shipments)} ${esc(T('analytics.total_shipments', 'kiriman'))}</span><b>${vRate !== '-' ? `${vRate}%` : '-'}</b></div>`;
            }).join('') || `<p class="analytics-empty">${esc(T('analytics.no_data', 'Tidak ada data'))}</p>`}</div>`;
        Modal.getOrCreateInstance(modal).show();
    };

    const buildPerfChart = rows => {
        perfBarChart?.destroy();
        const canvas = document.getElementById('distPerfChart');
        if (!canvas) return;
        const top10 = [...rows].sort((a, b) => b.total_shipments - a.total_shipments).slice(0, 10);
        const labels = top10.map(r => r.city_name);
        perfBarChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: T('analytics.on_time', 'Tepat waktu'), data: top10.map(r => r.delivered_on_time), backgroundColor: '#58b96b', borderRadius: 3 },
                    { label: T('analytics.late', 'Terlambat'), data: top10.map(r => r.delivered_late), backgroundColor: '#e3a435', borderRadius: 3 },
                    { label: T('analytics.failed_returned', 'Gagal/retur'), data: top10.map(r => r.failed_or_returned), backgroundColor: '#d65454', borderRadius: 3 },
                ],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, beginAtZero: true, grid: { color: '#eef2f7' } },
                    y: { stacked: true, ticks: { font: { size: 11 } } },
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        mode: 'index',
                        callbacks: {
                            label(context) {
                                const total = Number(top10[context.dataIndex]?.total_shipments || 0);
                                const raw = Number(context.raw || 0);
                                const percent = total > 0 ? ` (${(raw / total * 100).toFixed(1)}%)` : '';
                                return `${context.dataset.label}: ${number.format(raw)}${percent}`;
                            },
                            footer(items) {
                                const total = Number(top10[items[0]?.dataIndex]?.total_shipments || 0);
                                return `${T('analytics.total_shipments', 'Total kiriman')}: ${number.format(total)}`;
                            },
                        },
                    },
                },
                onClick: (_e, points) => {
                    if (!points.length) return;
                    openDetail(top10[points[0].index]);
                },
            },
        });
        charts.push(perfBarChart);
    };

    const load = async () => {
        const loadingText = esc(T('analytics.loading', 'Memuat distribusi 30 hari terakhir...'));
        document.getElementById('distribution-summary').innerHTML = `<div class="geo-region-state">${loadingText}</div>`;
        document.getElementById('distributionMap').innerHTML = `<div class="geo-region-state">${loadingText}</div>`;
        document.getElementById('distribution-vendor-summary').innerHTML = `<div class="analytics-empty">${loadingText}</div>`;
        try {
            const payload = await fetchAnalyticsSnapshot();
            renderSourceState(payload);
            const vendors = contributions(payload);

            /* merge locations, keep per-vendor breakdown */
            const grouped = new Map();
            vendorBreakdownMap = new Map();

            vendors.forEach(v => {
                (v.summary.distribution || []).forEach(row => {
                    const key = `${row.province_code}:${row.city_code}`;
                    const cur = grouped.get(key) || { ...row, vendor_name: T('analytics.all_providers', 'Semua penyedia'), total_shipments: 0, delivered_on_time: 0, delivered_late: 0, failed_or_returned: 0, delay_sum: 0, delay_w: 0 };
                    ['total_shipments', 'delivered_on_time', 'delivered_late', 'failed_or_returned'].forEach(f => { cur[f] += Number(row[f] || 0); });
                    if (row.avg_delay_days != null) { const w = Math.max(1, Number(row.delivered_late || 0)); cur.delay_sum += Number(row.avg_delay_days) * w; cur.delay_w += w; }
                    grouped.set(key, cur);

                    /* vendor breakdown */
                    const arr = vendorBreakdownMap.get(key) || [];
                    arr.push({ ...row, vendor_name: v.vendor_name, vendor_code: v.vendor_code });
                    vendorBreakdownMap.set(key, arr);
                });
            });

            groupedRows = [...grouped.values()].map(r => ({
                ...r,
                avg_delay_days: r.delay_w ? r.delay_sum / r.delay_w : null,
                on_time_rate: r.total_shipments ? r.delivered_on_time / r.total_shipments : null,
            })).sort((a, b) => (a.on_time_rate ?? -1) - (b.on_time_rate ?? -1));

            if (!groupedRows.length) {
                const emptyText = esc(T('analytics.no_valid_data', 'Belum ada pengiriman dalam 30 hari terakhir.'));
                document.getElementById('distribution-summary').innerHTML = `<div class="geo-region-state">${emptyText}</div>`;
                document.getElementById('distribution-metadata').textContent = '';
                document.getElementById('distributionMap').innerHTML = `<div class="geo-region-state">${emptyText}</div>`;
                document.getElementById('distribution-table-body').innerHTML = `<tr><td colspan="6" class="analytics-empty">${emptyText}</td></tr>`;
                document.getElementById('distribution-vendor-summary').innerHTML = `<div class="analytics-empty">${emptyText}</div>`;
                return;
            }

            /* KPI */
            const totals = groupedRows.reduce((acc, r) => {
                acc.total += r.total_shipments; acc.on += r.delivered_on_time;
                acc.late += r.delivered_late; acc.failed += r.failed_or_returned; return acc;
            }, { total: 0, on: 0, late: 0, failed: 0 });
            const natRate = totals.total > 0 ? totals.on / totals.total : null;
            const [natClass, natLabel] = performance(natRate);
            document.getElementById('distribution-summary').innerHTML = `
                <article class="geo-card">
                    <span>${esc(T('analytics.total_shipments', 'Total kiriman'))}</span>
                    <strong>${number.format(totals.total)}</strong>
                    <small>${esc(T('analytics.snapshot_30_days', '30 hari terakhir'))}</small>
                </article>
                <article class="geo-card">
                    <span>${esc(T('analytics.on_time_rate', '% tepat waktu nasional'))}</span>
                    <strong>${natRate !== null ? `${(natRate * 100).toFixed(1)}%` : '-'}</strong>
                    <small class="performance-badge ${natClass}">${esc(natLabel)}</small>
                </article>
                <article class="geo-card">
                    <span>${esc(T('analytics.late', 'Terlambat'))}</span>
                    <strong>${number.format(totals.late)}</strong>
                    <small>${esc(T('analytics.failed_returned', 'Gagal/retur'))}: ${number.format(totals.failed)}</small>
                </article>`;
            document.getElementById('distribution-metadata').textContent = `${number.format(groupedRows.length)} ${T('analytics.total_cities', 'kota/kabupaten')} · ${vendors.length} ${T('analytics.vendors_active', 'vendor aktif')} · ${T('analytics.snapshot_30_days', '30 hari terakhir')}`;

            /* Table */
            const body = document.getElementById('distribution-table-body');
            body.innerHTML = groupedRows.length
                ? groupedRows.map((row, i) => {
                    const [cls, lbl] = performance(row.on_time_rate);
                    return `<tr class="analytics-clickable" tabindex="0" data-location="${i}">
                        <td><strong>${esc(row.city_name)}</strong></td>
                        <td>${esc(row.province_name)}</td>
                        <td>${number.format(row.total_shipments)}</td>
                        <td>${row.on_time_rate !== null ? `${(row.on_time_rate * 100).toFixed(1)}%` : '-'}</td>
                        <td>${row.avg_delay_days !== null ? `${row.avg_delay_days.toFixed(1)} ${esc(T('analytics.days', 'hari'))}` : '-'}</td>
                        <td><span class="performance-badge ${cls}">${esc(lbl)}</span></td>
                    </tr>`;
                }).join('')
                : `<tr><td colspan="6" class="analytics-empty">${esc(T('analytics.no_valid_data', 'Belum ada data valid untuk periode ini.'))}</td></tr>`;
            body.querySelectorAll('[data-location]').forEach((el, i) => {
                const open = () => openDetail(groupedRows[i]);
                el.addEventListener('click', open);
                el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
            });

            /* build perf chart */
            buildPerfChart(groupedRows);

            /* choropleth + bubble map */
            mapChart?.destroy();

            /* build province on-time aggregation for choropleth */
            const provMap = new Map();
            groupedRows.forEach(r => {
                const cur = provMap.get(r.province_code) || { code: r.province_code, name: r.province_name, on: 0, total: 0 };
                cur.on += r.delivered_on_time; cur.total += r.total_shipments;
                provMap.set(r.province_code, cur);
            });
            const unmatchedProvinces = [];
            const provinceData = [...provMap.values()].map(p => {
                const key = provinceKey(p.name);
                if (!key) unmatchedProvinces.push(`${p.code}:${p.name}`);
                return {
                    'hc-key': key,
                    value: p.total > 0 ? parseFloat(((p.on / p.total) * 100).toFixed(1)) : null,
                    custom: { total_shipments: p.total, delivered_on_time: p.on },
                };
            }).filter(p => p['hc-key']);
            if (unmatchedProvinces.length) console.warn('Unmatched Highcharts provinces:', unmatchedProvinces);
            const topCityLabels = new Set([...groupedRows].sort((a, b) => b.total_shipments - a.total_shipments).slice(0, 5).map(row => row.city_code));

            mapChart = Highcharts.mapChart('distributionMap', {
                chart: { backgroundColor: '#f8fafc', spacing: [16, 16, 8, 16] },
                title: { text: null }, credits: { enabled: false },
                mapView: { padding: [20, 20, 12, 20] },
                mapNavigation: {
                    enabled: true,
                    enableMouseWheelZoom: true,
                    enableDoubleClickZoomTo: true,
                    buttonOptions: {
                        align: 'left', verticalAlign: 'bottom',
                        theme: { width: 30, height: 30, r: 4, fill: '#fff', stroke: '#b8c4ce' },
                    },
                },
                colorAxis: {
                    dataClasses: [
                        { to: 79.99, color: '#d65454', name: '<80%' },
                        { from: 80, to: 94.99, color: '#e3a435', name: '80–94%' },
                        { from: 95, color: '#2f8f5b', name: '≥95%' },
                    ],
                },
                legend: {
                    title: { text: '% Tepat Waktu', style: { fontSize: '11px', color: '#52616f' } },
                    layout: 'horizontal', align: 'center', verticalAlign: 'bottom', symbolWidth: 28, itemDistance: 18,
                },
                tooltip: {
                    useHTML: true, outside: true, shared: false, split: false, stickOnContact: true,
                    followPointer: false, borderWidth: 0, backgroundColor: 'transparent', padding: 0, shadow: false,
                    className: 'geo-tooltip-host',
                    formatter() {
                        const p = this.point;
                        if (p.custom) {
                            if (p.custom.province_name) {
                                const rate = p.custom.on_time_rate;
                                const [cls, label] = performance(rate);
                                return `<div class="geo-tooltip">
                                    <div class="geo-tooltip__header"><div><strong>${esc(p.name)}</strong><span>${esc(p.custom.province_name)}</span></div><span class="performance-badge ${cls}">${esc(label)}</span></div>
                                    <dl class="geo-tooltip__metrics">
                                        <div class="is-primary"><dt>${esc(T('analytics.on_time_rate', 'Tepat waktu'))}</dt><dd>${rate !== null ? `${(rate * 100).toFixed(1)}%` : '-'}</dd></div>
                                        <div><dt>${esc(T('analytics.total_shipments', 'Total kiriman'))}</dt><dd>${number.format(p.custom.total_shipments)}</dd></div>
                                        <div><dt>${esc(T('analytics.late', 'Terlambat'))}</dt><dd>${number.format(p.custom.delivered_late)}</dd></div>
                                        <div><dt>${esc(T('analytics.failed_returned', 'Gagal/retur'))}</dt><dd>${number.format(p.custom.failed_or_returned)}</dd></div>
                                        <div><dt>${esc(T('analytics.avg_delay_days', 'Rata-rata terlambat'))}</dt><dd>${p.custom.avg_delay_days !== null ? `${Number(p.custom.avg_delay_days).toFixed(1)} ${esc(T('analytics.days', 'hari'))}` : '-'}</dd></div>
                                    </dl>
                                    <div class="geo-tooltip__hint">${esc(T('analytics.click_region_detail', 'Klik untuk rincian per vendor'))}</div>
                                </div>`;
                            }
                            return `<div class="geo-tooltip geo-tooltip--province"><div class="geo-tooltip__header"><div><strong>${esc(p.name)}</strong><span>${esc(T('analytics.province_summary', 'Ringkasan provinsi'))}</span></div></div><dl class="geo-tooltip__metrics"><div class="is-primary"><dt>${esc(T('analytics.on_time_rate', 'Tepat waktu'))}</dt><dd>${p.value != null ? `${p.value}%` : '-'}</dd></div><div><dt>${esc(T('analytics.total_shipments', 'Total kiriman'))}</dt><dd>${number.format(p.custom.total_shipments)}</dd></div></dl></div>`;
                        }
                        return esc(p.name || '');
                    },
                },
                plotOptions: {
                    map: { borderColor: '#a8b7c2', borderWidth: .75, states: { hover: { borderColor: '#173c5a', borderWidth: 1.5, brightness: .04 } } },
                    mapbubble: {
                        minSize: 8, maxSize: '8%', sizeBy: 'area', cursor: 'pointer',
                        marker: { lineWidth: 2, lineColor: '#fff', fillOpacity: .88 },
                        states: { hover: { lineWidthPlus: 1, brightness: .08 } },
                        point: { events: { click() { openDetail(this.custom); } } },
                    },
                },
                series: [
                    {
                        id: 'provinces',
                        mapData: indonesiaMap, data: provinceData,
                        allAreas: true, nullColor: '#e8edf2',
                        borderColor: '#b8c4ce', borderWidth: .5,
                        name: '% Tepat Waktu (Provinsi)',
                        enableMouseTracking: true,
                    },
                    {
                        id: 'cities', type: 'mapbubble', name: T('analytics.city_performance', 'Kota'),
                        data: groupedRows.filter(r => r.latitude !== null && r.latitude !== undefined && r.longitude !== null && r.longitude !== undefined).map(row => ({
                            name: row.city_name,
                            lat: Number(row.latitude),
                            lon: Number(row.longitude),
                            z: Number(row.total_shipments || 0),
                            color: row.on_time_rate === null ? '#8b98a5'
                                : row.on_time_rate >= .95 ? '#1a6f35'
                                : row.on_time_rate >= .80 ? '#c77d00'
                                : '#a83434',
                            custom: row,
                        })),
                        dataLabels: {
                            enabled: window.innerWidth >= 768,
                            formatter() { return topCityLabels.has(this.point.custom.city_code) ? this.point.name : null; },
                            style: { fontSize: '10px', fontWeight: '600', textOutline: '2px #fff', color: '#1e3343' },
                        },
                        enableMouseTracking: true,
                    },
                ],
                responsive: { rules: [{
                    condition: { maxWidth: 680 },
                    chartOptions: {
                        chart: { height: 380, spacing: [8, 4, 8, 4] },
                        legend: { layout: 'horizontal', align: 'center', verticalAlign: 'bottom', itemDistance: 8, itemStyle: { fontSize: '11px' } },
                        mapNavigation: { enabled: false },
                        series: [
                            { id: 'provinces', dataLabels: { enabled: false } },
                            { id: 'cities', dataLabels: { enabled: false }, maxSize: '11%' },
                        ],
                    },
                }] },
            });

            /* Vendor summary sidebar */
            document.getElementById('distribution-vendor-summary').innerHTML = vendors.length
                ? vendors.map(v => {
                    const vTotal = (v.summary.distribution || []).reduce((s, r) => s + Number(r.total_shipments || 0), 0);
                    const vOn = (v.summary.distribution || []).reduce((s, r) => s + Number(r.delivered_on_time || 0), 0);
                    const vRate = vTotal > 0 ? (vOn / vTotal * 100).toFixed(1) : null;
                    const [cls] = performance(vTotal > 0 ? vOn / vTotal : null);
                    return `<div class="retry-vendor-row">
                        <div>
                            <strong>${esc(v.vendor_name)}</strong>
                            <span>${vRate !== null ? `<span class="performance-badge ${cls}">${vRate}% tepat waktu</span>` : '-'}</span>
                        </div>
                        <b>${number.format(vTotal)}</b>
                    </div>`;
                }).join('')
                : `<div class="analytics-empty">${esc(T('analytics.no_valid_data', 'Belum ada data valid.'))}</div>`;

        } catch (err) {
            console.error(err);
            const s = document.getElementById('analytics-source-state');
            s.className = 'analytics-source-state is-error';
            s.innerHTML = `${esc(T('analytics.load_failed', 'Data analytics gagal dimuat. Coba lagi.'))} <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="distribution-reload">${esc(T('analytics.reload', 'Muat ulang'))}</button>`;
            document.getElementById('distribution-reload')?.addEventListener('click', load, { once: true });
            const failed = esc(T('analytics.load_failed', 'Data gagal dimuat.'));
            document.getElementById('distribution-summary').innerHTML = `<div class="geo-region-state">${failed}</div>`;
            document.getElementById('distributionMap').innerHTML = `<div class="geo-region-state">${failed}</div>`;
            document.getElementById('distribution-table-body').innerHTML = `<tr><td colspan="6" class="analytics-empty">${failed}</td></tr>`;
            document.getElementById('distribution-vendor-summary').innerHTML = `<div class="analytics-empty">${failed}</div>`;
        }
    };

    load();
}

const page = document.body?.dataset.page;
if (page === 'sla-analysis') initSlaPage();
if (page === 'retry-monitoring') initRetryPage();
if (page === 'distribution-map') initDistributionPage();
