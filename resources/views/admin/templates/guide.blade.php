@extends('layouts.app')

@section('title', 'Panduan Menambah / Mengubah Format JSON')
@section('page-title', 'Panduan Format JSON')

@section('content')
<div class="py-3 admin-page">
    @include('admin.partials.work-nav')

    <div class="admin-action-row mb-3">
        <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary">Kembali ke Format JSON</a>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-header"><h5 class="admin-panel-title">Panduan Admin — Format JSON</h5></div>
        <div class="card-body">
            <p class="mb-4">Dua jenis pekerjaan yang sering tertukar:</p>

            <div class="table-responsive mb-4">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>Jenis</th><th>Contoh</th><th>Cukup dari admin?</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><strong>A. Ubah format operasi yang SUDAH ada</strong></td>
                            <td>Rename field, tambah/hapus kolom, ubah tipe nilai di <code>inventory.list</code></td>
                            <td><span class="status-label status-approved">Ya</span></td>
                        </tr>
                        <tr>
                            <td><strong>B. Tambah operasi vendor yang BARU</strong></td>
                            <td>Operasi ke-9 mis. <code>orders.invoice</code>, <code>inventory.price</code></td>
                            <td><span class="status-label status-error">Perlu kode</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold">A. Ubah format yang sudah ada — cukup admin</h6>
            <p>Format JSON di halaman ini adalah satu-satunya acuan validasi saat vendor Test. Edit <code>template_data</code> lalu simpan, vendor otomatis divalidasi memakai format baru.</p>
            <ul class="mb-4">
                <li><code>template_data</code> wajib object <code>{ "data": ..., "meta": ... }</code> (bukan array list).</li>
                <li><code>data</code> boleh array atau object.</li>
                <li><code>meta</code> wajib object, memuat <code>generated_at</code>, <code>data_as_of</code>, <code>scope</code>.</li>
            </ul>

            <h6 class="fw-bold">B. Tambah operasi baru — 4 file wajib disentuh</h6>
            <div class="table-responsive mb-4">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>#</th><th>File</th><th>Yang ditambah</th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td><code>config/paramita.php</code> → <code>schemas</code></td><td>Mapping <code>operation_key</code> baru → file schema</td></tr>
                        <tr><td>2</td><td><code>app/Services/Contracts/VendorContractGuide.php</code></td><td><code>match</code> di <code>buildGuide()</code> + <code>defaultPath()</code></td></tr>
                        <tr><td>3</td><td><code>database/seeders/DefaultContractsSeeder.php</code></td><td>Satu entri operasi (key, module, label, schema_file)</td></tr>
                        <tr><td>4</td><td><code>database/seeders/ProdevVendorSeeder.php</code> → <code>operationPaths()</code></td><td>Path endpoint default agar binding vendor ter-seed</td></tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold">Jalan pintas terbaik: prompt AI agent</h6>
            <p>Jangan kerjakan manual. Berikan prompt berikut ke AI coding agent (Cursor, Claude Code, Hermes) lengkap dengan spesifikasi operasi:</p>
            <pre class="json-help-card"><code>Di project Laravel "Paramita-Final", saya mau menambah operasi vendor baru
bernama "&lt;OPERATION_KEY&gt;" (contoh: orders.invoice).

Operasi ini:
- Module: &lt;stock | delivery&gt;
- Label: &lt;judul operasi&gt;
- Deskripsi: &lt;apa yang dikembalikan&gt;
- Kategori response: &lt;list | summary | detail | events | analytics&gt;

Kerjakan SEMUA langkah ini lengkap dan konsisten:
1. Tambah mapping schema di config/paramita.php bagian 'schemas'.
2. Tambah arm match di app/Services/Contracts/VendorContractGuide.php:
   buildGuide() dan defaultPath().
3. Tambah satu entri operasi di database/seeders/DefaultContractsSeeder.php.
4. Tambah operation_key + path default di ProdevVendorSeeder.php (operationPaths()).
5. (Opsional) buat schema JSON di contracts/v1/.
6. Jalankan: php artisan migrate:fresh --seed lalu php artisan test.
Pastikan tidak ada regresi dan seluruh test tetap hijau.</code></pre>
        </div>
    </div>
</div>
@endsection
