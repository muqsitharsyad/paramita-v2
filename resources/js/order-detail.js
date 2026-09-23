const T = (key, fallback = '') => window.ParamitaI18n?.[`do_detail.${key}`] ?? fallback;
const locale = document.documentElement.lang === 'en' ? 'en-US' : 'id-ID';

function esc(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char]);
}

function text(value) {
    return value === null || value === undefined || value === '' ? '-' : esc(value);
}

function date(value) {
    return value ? new Date(value).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

function named(name, code) {
    if (name) return esc(name);
    return text(name || code);
}

function item(label, value, wide = false) {
    return `<div class="do-fact${wide ? ' do-fact-wide' : ''}"><dt>${esc(label)}</dt><dd>${value}</dd></div>`;
}

function timeline(events, emptyText) {
    if (!events.length) return `<p class="do-empty">${esc(emptyText)}</p>`;
    return `<ol class="do-timeline">${events.map((event) => `
        <li>
            <span class="do-timeline-dot" aria-hidden="true"></span>
            <div class="do-timeline-head"><strong>${text(event.process_status_name || event.title)}</strong><time>${date(event.occurred_at)}</time></div>
            ${event.description ? `<p>${esc(event.description)}</p>` : ''}
            ${event.location ? `<small>${esc(event.location)}</small>` : ''}
        </li>`).join('')}</ol>`;
}

function slaLabel(value) {
    return ({ faster: T('sla_faster', 'Lebih cepat'), on_sla: T('sla_on', 'Sesuai SLA'), over_sla: T('sla_over', 'Melewati SLA') })[value] || value || '-';
}

async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`${response.status} ${url}`);
    return response.json();
}

export async function renderOrderDetail(container, vendorCode, sourceId) {
    if (!container) return;
    container.innerHTML = `<div class="do-detail-state">${esc(T('loading', 'Memuat detail...'))}</div>`;

    const base = `/api/v1/vendors/${encodeURIComponent(vendorCode)}/orders/${encodeURIComponent(sourceId)}`;
    try {
        const [detail, historyResult, retryResult] = await Promise.all([
            getJson(base),
            getJson(`${base}/events?kind=history`).catch(() => ({ data: [] })),
            getJson(`${base}/events?kind=retry`).catch(() => ({ data: [] })),
        ]);
        const d = detail.data || {};
        const history = Array.isArray(historyResult.data) ? historyResult.data : [];
        const retries = Array.isArray(retryResult.data) ? retryResult.data : [];
        const coordinates = d.latitude !== null && d.latitude !== undefined && d.longitude !== null && d.longitude !== undefined
            ? `${esc(d.latitude)}, ${esc(d.longitude)}`
            : '-';
        const packageLabel = named(d.package_name || d.package_title, d.package_code);
        const sla = `${text(d.sla_target_days)} ${esc(T('days', 'hari'))} · ${esc(slaLabel(d.sla_status))}`;

        container.innerHTML = `
            <div class="do-detail-shell">
                <section class="do-detail-section do-detail-student">
                    <div class="do-section-heading"><span>${esc(T('student_information', 'Informasi mahasiswa'))}</span><strong>${text(d.order_number || d.id)}</strong></div>
                    <dl class="do-fact-grid">
                        ${item(T('student_identifier', 'NIM'), text(d.student_identifier))}
                        ${item(T('student_name', 'Nama mahasiswa'), text(d.student_name))}
                        ${item(T('address_line', 'Alamat lengkap'), text(d.address_line), true)}
                        ${item(T('village', 'Kelurahan'), text(d.village))}
                        ${item(T('district', 'Kecamatan'), text(d.district))}
                        ${item(T('city', 'Kabupaten/Kota'), text(d.city))}
                        ${item(T('province', 'Provinsi'), text(d.province))}
                        ${item(T('phone', 'Nomor telepon'), text(d.student_phone))}
                        ${item(T('regional_ut', 'UT Daerah'), named(d.ut_name, d.ut_code))}
                        ${item(T('study_program', 'Program studi'), named(d.program_name, d.program_code))}
                    </dl>
                </section>

                <section class="do-detail-section">
                    <div class="do-section-heading"><span>${esc(T('delivery_information', 'Informasi delivery'))}</span></div>
                    <dl class="do-fact-grid">
                        ${item(T('carrier', 'Ekspedisi'), text(d.carrier_name))}
                        ${item(T('tracking_number', 'Nomor resi'), text(d.tracking_number))}
                        ${item(T('package', 'Paket'), packageLabel, true)}
                        ${item(T('sla_target', 'Target SLA'), sla)}
                        ${item(T('latest_status', 'Status terakhir'), named(d.process_status_name || d.latest_event?.process_status_name, d.process_status_code))}
                        ${item(T('coordinate', 'Koordinat'), coordinates)}
                    </dl>
                </section>

                <div class="do-detail-columns">
                    <section class="do-detail-section">
                        <div class="do-section-heading"><span>${esc(T('delivery_history', 'Riwayat delivery'))}</span></div>
                        ${timeline(history, T('no_history', 'Belum ada riwayat delivery.'))}
                    </section>
                    <section class="do-detail-section">
                        <div class="do-section-heading"><span>${esc(T('retry_history', 'Riwayat retry'))}</span></div>
                        ${timeline(retries, T('no_retry', 'Tidak ada percobaan ulang.'))}
                    </section>
                </div>

                <div class="do-detail-columns do-detail-columns-bottom">
                    <section class="do-detail-section">
                        <div class="do-section-heading"><span>${esc(T('process_time', 'Waktu proses'))}</span></div>
                        <dl class="do-fact-grid do-time-grid">
                            ${item(T('order_time', 'Order time'), date(d.ordered_at))}
                            ${item(T('payment_time', 'Payment time'), date(d.paid_at))}
                            ${item(T('carrier_handover_time', 'Diserahkan ke ekspedisi'), date(d.handed_to_carrier_at))}
                            ${item(T('completion_time', 'Completion time'), date(d.completed_at))}
                        </dl>
                    </section>
                    <section class="do-detail-section do-proof-section">
                        <div class="do-section-heading"><span>${esc(T('proof_delivery', 'Bukti pengiriman'))}</span></div>
                        ${d.proof_of_delivery_url ? `
                            <figure class="do-proof-media">
                                <a href="${base}/proof" target="_blank" rel="noopener" aria-label="${esc(T('open_proof', 'Buka bukti pengiriman ukuran penuh'))}">
                                    <img src="${base}/proof" alt="${esc(T('proof_alt', 'Foto bukti pengiriman untuk DO ini'))}" loading="lazy">
                                </a>
                                <figcaption>${esc(T('proof_protected', 'Gambar dilayani aman melalui Paramita; URL vendor tidak diekspos.'))}</figcaption>
                            </figure>` : `
                            <div class="do-proof-status">
                                <span aria-hidden="true"></span>
                                <strong>${esc(T('no_proof', 'Belum ada bukti pengiriman'))}</strong>
                            </div>`}
                    </section>
                </div>
            </div>`;
    } catch (error) {
        console.error('renderOrderDetail:', error);
        container.innerHTML = `<div class="alert alert-danger mb-0">${esc(T('load_failed', 'Gagal memuat detail DO dari vendor API.'))}</div>`;
    }
}
