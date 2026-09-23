# PARAMITA FINAL — Product & Implementation Requirements

**Versi 2.2 FINAL — Acuan implementasi tunggal**  
**Status:** spesifikasi untuk pembangunan; bukan pernyataan bahwa aplikasi sudah dibangun, aman tersertifikasi, atau lulus benchmark.  
**Bahasa:** UI/dokumentasi vendor Indonesia; kode dan field API English snake_case.  
**Root proyek baru:** `D:\laragon\www\Paramita-Final`  
**Referensi legacy, read-only:** `D:\laragon\www\paramita`  
**Referensi UI:** `C:\Users\kinmin\Downloads\paramita-html-starter`

## 0. Cara menggunakan dokumen ini

Dokumen ini menggantikan seluruh keputusan PRD versi 1 di proyek lama. Jangan menggabungkan aturan versi lama dengan versi ini. Kata **WAJIB**, **DILARANG**, dan **DEFAULT** bersifat normatif. Default adalah keputusan implementasi, bukan pertanyaan yang perlu diajukan berulang.

Bangun aplikasi baru di root di atas. Jangan mengubah aplikasi/database legacy, menjalankan migrate:fresh pada database existing, menyalin credential lama, atau menghapus tabel legacy. Migrasi produksi memerlukan persetujuan dan backup tersendiri.

Implementor wajib membaca keseluruhan dokumen sebelum mulai. Ikuti fase §19; tulis tes untuk setiap acceptance criterion sebelum menyatakan fase selesai. Jika test gagal, perbaiki fase itu; jangan mengubah kontrak untuk memudahkan kode. Jika ada kebutuhan bisnis baru yang bertentangan, tulis perubahan versi PRD secara eksplisit, bukan diam-diam mengubah perilaku.

Dokumen ini mencakup keputusan produk, kontrak vendor, kontrak UI, security, state machine, database, failure handling, dan delivery gate. JSON Schema/OpenAPI/test fixtures yang dibuat saat fase pertama adalah artefak turunan dari tabel normatif di sini, bukan sumber keputusan baru. Nilai contoh adalah data sintetis, bukan data produksi.

### 0.1 Koreksi utama terhadap PRD sebelumnya

- Tutor dikembalikan: ada **4 role internal**, ditambah akun vendor terpisah secara akses; vendor bukan pengganti Tutor.
- Tidak mengklaim Filament terbukti lambat: belum ada profiling. Blade dipilih karena UI fixed dan kebutuhan custom, bukan klaim performa tanpa bukti.
- Keputusan pengguna final: **40 unit layanan = 39 daerah + 1 luar negeri**. UT Pusat adalah organisasi pengelola, bukan unit layanan ke-41 dalam agregasi. Kode/nama resmi tetap diimpor, bukan ditebak dari nomor urut.
- Kontrak memakai RFC 3339 yang konsisten, numeric/null benar, matrix ber-key jelas, serta pagination nyata.
- Jumlah vendor dinamis; tidak dikunci tiga nama di mockup.
- Lolos schema bukan bukti data benar atau bebas kebocoran. Pengujian mencakup perilaku filter, scope, pagination, auth, dan response limits.
- Tes vendor bisa diulang tanpa kuota lifetime, tetapi rate/concurrency tetap dibatasi.
- Report terikat revision konfigurasi, secret revision, schema revision, dan TTL. Pass lama tidak boleh mengesahkan konfigurasi baru.
- Cache adalah penyimpanan sementara data; tidak boleh menjanjikan data sama sekali tidak disimpan.
- Cipher default Laravel bukan otomatis AES-GCM; password default bukan otomatis Argon2id. Pilihan eksplisit ada di §14.
- HMAC tidak memiliki format universal; jangan menciptakan signature scheme generik yang diasumsikan cocok semua vendor.
- Tidak menggabungkan offset yang sama dari setiap vendor lalu menyebutnya global pagination.

## 1. Tujuan, batas produk, dan keputusan utama

Paramita adalah portal monitoring stok bahan ajar dan Delivery Order (DO) multi-vendor untuk UT. Vendor menyediakan API read-only sesuai kontrak admin; Paramita mengambil data secara live melalui backend gateway, bukan menampung jutaan baris transaksi sebagai data warehouse.

Tujuan:
1. Vendor daftar sendiri, disetujui admin, kemudian konfigurasi koneksi/API dan tes sendiri sampai memenuhi kontrak.
2. Admin menetapkan kontrak, scope vendor, dan mereview report akhir; tidak mengetes endpoint satu per satu secara manual.
3. Tujuh halaman user memakai desain HTML yang sudah tersedia, bukan page builder.
4. Scope organisasi ditegakkan di server, termasuk summary, tabel, modal, file bukti, cache, serta report.
5. Semua query dibatasi, tabel server-side, vendor outage tidak mengunci seluruh UI.
6. Vendor mendapatkan petunjuk Indonesia, contoh response, dan error yang bisa diperbaiki tanpa menebak schema.

### 1.1 Scope rilis pertama

- `/dashboard` = Monitoring Stok Paket, tab Paket dan Judul. Bukan halaman kelima.
- `/monitoring-delivery`.
- `/do-per-prodi`.
- `/do-per-ut-daerah`.
- `/analisis-sla` dengan filter hari, minggu kalender, dan bulan kalender; chart dapat membuka detail DO.
- `/monitoring-retry` dengan filter hari, minggu kalender, dan bulan kalender; chart/ringkasan dapat membuka detail retry.
- `/distribution-map` dengan filter hari, minggu kalender, dan bulan kalender; titik/baris dapat membuka detail agregat lokasi.
- Admin panel, vendor onboarding/panel, login/reset password, master data, contract test runner, report, audit, health monitoring.
- Master alasan retry dan satu kontrak agregat `orders.analytics` termasuk scope. `json_templates.template_data` tetap menjadi satu-satunya sumber struktur/tipe response runtime dan test vendor.

Perubahan scope ini disetujui pemilik produk setelah PRD 2.1. Pernyataan lama di bagian lain yang menyebut hanya empat halaman dibaca sebagai tujuh halaman di atas.

Tidak termasuk: transaksi/mutasi DO vendor, upload transaksi massal ke Paramita, page builder, arbitrary SQL/formula/script mapping, payment processing, Elasticsearch, Kafka, microservices, Kubernetes, AI runtime, mobile app, export jutaan baris. Kontrol starter yang tidak relevan harus dihapus, bukan dibiarkan tidak berfungsi.

### 1.2 Trade-off live gateway yang wajib dipahami

Jutaan record berada di database vendor. Kecepatan cold request tergantung kemampuan API vendor, index dan agregat mereka. PHP/framework tidak bisa menjamin API lambat menjadi cepat. Paramita tidak boleh mengambil semua baris lalu menghitung chart/search di browser/PHP.

Default tabel gabungan memakai **urutan vendor lalu ID sumber**, bukan urutan kronologis global. Ini dipilih untuk pagination benar dengan API limit/offset yang mudah dibuat vendor. Vendor-spesifik mendukung cursor untuk deep browsing. Jika pengguna kelak mewajibkan sort global arbitrary, random page jutaan record, join lintas vendor, historical stock, atau global dedup authoritative, buat ADR baru untuk local read model/incremental ingestion. Jangan menyelundupkan warehouse ke rilis live ini.

## 2. Tech stack yang ditetapkan

| Komponen | Keputusan | Alasan/batas |
|---|---|---|
| Backend | Laravel 12, PHP 8.3 atau 8.4 yang kompatibel | Dekat legacy; gunakan patch yang didukung dan lockfile. Verifikasi support lifecycle sebelum produksi |
| UI | Blade, Bootstrap 5.3, vanilla ES modules, Vite | Reuse layout starter tanpa SPA framework; fetch untuk tabel/filter/modal |
| Admin/vendor | Blade custom, shared form/table components | Tidak memakai Filament/Livewire/page builder di aplikasi baru |
| Chart | Chart.js 4 dan Highcharts Maps, self-hosted | Chart.js untuk SLA/retry. Highcharts Maps hanya untuk peta Indonesia dan wajib memiliki lisensi yang sesuai sebelum produksi; jangan pakai CDN produksi |
| DB | MySQL 8.4 LTS, InnoDB, utf8mb4 | Menyimpan kontrol/master/report; bukan jutaan DO. Jangan mengasumsikan MariaDB identik |
| Auth internal | Laravel session auth + Spatie Permission 6 | Form biasa, Policies/FormRequests. Tidak JWT browser/localStorage |
| JSON validator | `opis/json-schema` 2.x | Dokumentasi menyatakan dukungan draft 2020-12; uji format assertion wajib |
| Cache/lock | Redis private | Metadata/page cache terisolasi, single-flight, limiter. Payload sensitif dienkripsi sebelum masuk Redis |
| Queue | Laravel database queue, worker PHP | Job hanya membawa ID, bukan credential/response payload. Tidak perlu Horizon |
| Scheduling | Laravel scheduler | health, prune, stale report; single scheduler, distributed lock |
| Test | PHPUnit, Pint; Playwright untuk E2E | MySQL integration tests, mock vendor HTTP, bukan SQLite-only |
| Deploy | Linux Nginx + PHP-FPM/OPcache, worker, scheduler | Compose boleh; Redis/DB tanpa public ports. Node hanya build asset |

Laravel 12 dipilih untuk kompatibilitas, bukan diklaim versi terbaru. Sebelum deploy, cek tanggal security support resmi; upgrade jika sudah tidak didukung. Jangan menebak package API dari PRD: baca dokumentasi versi terpasang, pin composer.lock/package-lock.json.

Lingkungan development Laragon boleh web app HTTP localhost; koneksi vendor tetap HTTPS. Mock HTTP hanya host/port spesifik di environment `testing`, tidak ada tombol admin untuk menonaktifkan TLS/SSRF di produksi.

## 3. Pengguna, otorisasi, master data

### 3.1 Role dan hak minimum

| Akun/role | Halaman monitoring | Scope data | PII/detail | Konfigurasi |
|---|---|---|---|---|
| admin | Semua | Semua UT yang aktif | Diizinkan; akses diaudit | Semua kontrol, approval, master, users |
| kepala_ut_pusat | Tujuh halaman | Semua UT aktif | Diizinkan | Tidak |
| kepala_ut_daerah | Tujuh halaman | Tepat satu UT yang ditetapkan admin | Hanya UT sendiri | Tidak |
| tutor | Tujuh halaman | Satu UT + daftar prodi assignment eksplisit | Default masked, detail nama/alamat/POD tidak tersedia | Tidak |
| vendor | Tidak ada halaman monitoring internal | Koneksi, konfigurasi, report miliknya | Tidak mendapat data vendor lain | Mengisi slot yang ditetapkan admin |

Tutor adalah default least-privilege untuk kebutuhan yang belum diperinci pengguna: admin menetapkan UT dan prodi; jika kosong, akses data ditolak (403), bukan semua UT. Tutor hanya aggregate/stock yang scoped dan daftar DO dengan nama masked serta tanpa rincian lokasi pribadi; endpoint upstream tetap wajib mendukung filter UT/prodi. Perubahan kebijakan Tutor perlu persetujuan pemilik sebelum menambah PII. Empat role internal bersifat fixed; UI admin tidak membuat role bebas. Gunakan enum/konstanta role dan seed permissions idempotent.

Satu user memiliki satu role bisnis pada rilis ini. `vendor_id` pada users hanya untuk role vendor; akun internal tidak dapat memiliki vendor_id. Akses sumber di-check per object, bukan hanya middleware/menu.

### 3.2 Enforcement

1. Baca user/status/assignment authoritative pada setiap request; jangan mempercayai session UT yang usang.
2. Buat ScopeContext: actor, role, permission revision, allowed UT/prodi, approved vendors.
3. `ut_code`/`program_code` dari client harus subset scope. Di luar scope → 403; jangan memanggil vendor.
4. Gateway memasukkan filter scope ke SEMUA list/summary/detail/lookup/event/POD request. Untuk Tutor multi-prodi gunakan daftar `program_codes` yang dibatasi 20 assignment; selebihnya UI wajib pilih satu prodi.
5. Response row yang memiliki UT/prodi di luar scope menyebabkan **seluruh respons sumber ditolak**, bukan menyaring lalu memakai count/summary yang sudah bocor.
6. Aggregate tidak bisa diverifikasi hanya dari JSON: vendor wajib deklarasi scope echo dan lulus test fixture lintas scope; runtime/pentest tetap diperlukan.
7. Cache key selalu mengandung normalized scope dan permission revision; pencabutan hak membatalkan akses meskipun cache masih ada.
8. Vendor suspend/endpoint disabled membatalkan cache/cursor dan tidak boleh mendapat stale fallback.

### 3.3 Filter daerah wajib untuk Kepala UT Daerah

Semua data yang dapat dilihat Kepala UT Daerah WAJIB terikat ke satu `ut_code` milik user tersebut. Ini berlaku untuk:

- Tabel stok paket/judul (`inventory.list`).
- Matrix stok paket/judul (`inventory.lookup`).
- Summary stok (`inventory.summary`).
- Tabel DO di `/monitoring-delivery`, `/do-per-prodi`, `/do-per-ut-daerah` (`orders.list`).
- Summary/card/chart DO (`orders.summary` group none/program/ut).
- Modal detail DO (`orders.detail`).
- History/retry (`orders.events`).
- Analisis SLA, Monitoring Retry, dan Distribution Map (`orders.analytics`).
- Bukti pengiriman/POD (`proof`).
- Dropdown/options vendor, prodi, katalog, dan UT.

Implementasi:

1. Kepala UT Daerah tidak mengirim `ut_code` bebas; server mengambil `ut_code` dari assignment user.
2. Jika request membawa `ut_code` berbeda, response 403 dan gateway tidak boleh memanggil vendor.
3. UI filter UT untuk Kepala UT Daerah tampil disabled/readonly dengan nama daerahnya; untuk Kepala UT Pusat/Admin bisa pilih semua/daerah tertentu.
4. Query ke vendor selalu menyertakan `ut_code` authoritative dari server pada semua operasi di atas. Vendor yang tidak mendukung filter `ut_code` tidak boleh approved untuk modul terkait.
5. Response vendor wajib echo scope `meta.scope.ut_code`. Jika echo kosong/berbeda, response gagal validasi.
6. Data yang tidak punya `ut_code` valid dari master ditolak agar tidak bocor ke daerah lain.

### 3.4 Master data

- Master `ut_regions`: tepat 40 unit layanan aktif, terdiri dari 39 `daerah` dan 1 `luar_negeri`. Import CSV `code,name,type,is_active`, validasi jumlah per tipe, unique code, preview/diff dan approval admin. Tidak menyimpan UT Pusat di tabel unit layanan ini: role kepala_ut_pusat memakai scope seluruh 40 unit. Kepala luar negeri memakai role kepala_ut_daerah dengan assignment unit luar negeri; tidak menambah role baru. `code` opaque string, tidak diturunkan dari nomor urut. Tambahan/perubahan jumlah master memerlukan revisi requirement, bukan implicit seed. Fixture development boleh 39 daerah sintetis + 1 luar negeri sintetis, semuanya diberi label DEMO dan tidak dianggap master resmi.
- Prodi: `code,name,is_active`. Assignment Tutor dikelola admin.
- Katalog: `catalog_key,item_type,item_code,edition,title`. Book unik `(item_type,item_code,edition)`; package edition kosong string. Dipakai pivot matrix lintas vendor.
- Kode vendor diberikan server, immutable; nama legal bebas berubah. Tidak hardcode tiga vendor contoh.
- Kode master contoh `UT-DEMO-01`, `PRODI-DEMO-01` hanya testing; tidak boleh seed sebagai master produksi.
- Sebelum production: import/validasi daftar UT/prodi/katalog resmi. Jika belum ada, development tetap bisa dengan fixture; jangan mengarang master resmi.

## 4. UX vendor: sederhana, bukan editor integrasi rumit

### 4.1 Registrasi sampai koneksi

1. Form publik: nama perusahaan, nama kontak, email, password + konfirmasi, persetujuan penggunaan data. Telepon opsional. Tidak meminta alamat lengkap/NPWP untuk rilis ini.
2. Email verification; vendor `pending_approval`. Vendor belum boleh login ke panel sebelum disetujui. Halaman status melalui signed expiring link tanpa membocorkan daftar pendaftar. Reset password tetap tersedia.
3. Admin melihat registrasi verified, menetapkan vendor code, scope UT/prodi, kontak, approve/reject dengan catatan. Approve mengaktifkan akun vendor, bukan endpoint.
4. Vendor login; wizard 3 langkah: **Koneksi → Endpoint → Tes & Kirim**. Simpan draft otomatis/eksplisit dengan indikator terakhir tersimpan. Jangan kehilangan isian non-secret ketika tes gagal.
5. Koneksi: label (default API Utama), Base URL HTTPS, pilihan autentikasi dengan istilah mudah. Tampilkan hanya field relevan; advanced settings collapsed.
6. Pilih modul Stok, Pengiriman, atau keduanya (admin menentukan kewajiban vendor). Jangan mewajibkan vendor pengiriman menyediakan stok yang memang bukan tugasnya.
7. Endpoint: slot operasi dibuat admin/seeder dari kontrak. Vendor cukup isi path relatif atau paste full URL yang origin-nya sama; server menormalkan ke origin + path/query aman. Tidak perlu mengisi method, field mapping, schema atau pagination params satu per satu.
8. Auth diwarisi dari koneksi. Endpoint khusus boleh memilih koneksi kedua dengan auth berbeda; tidak mengetik ulang credential untuk setiap operasi.

### 4.2 Satu URL atau beberapa URL

MVP mendukung dua mode umum:
- **Path terpisah** (default): vendor isi path untuk setiap operasi, misalnya `/api/stock`, `/api/orders`, `/api/orders/detail`.
- **Satu URL dispatch**: vendor isi `/api/paramita`, gateway menambahkan `operation=inventory.list` dan seterusnya. Slot operasi tetap terpisah secara kontrak/report meskipun URL sama.

Nama query `operation` fixed. Advanced endpoint static non-secret query diperbolehkan; tidak boleh menimpa limit/offset/scope/search/sort/operation atau auth parameter. Semua operasi read memakai GET. POST hanya token endpoint atau adapter auth yang terdokumentasi, bukan bebas memilih POST/DELETE untuk baca transaksi.

Di setiap slot tampilkan: kegunaan, contoh request dengan placeholder, contoh JSON valid sintetis, field wajib/nullable + penjelasan, Download OpenAPI, tombol Salin contoh, Uji Endpoint, Lihat perbaikan. Jangan menaruh credential dalam URL contoh/cURL atau export Postman. Vendor backend memang perlu mematuhi kontrak; wizard mudah tidak berarti API arbitrer otomatis kompatibel. Tidak ada editor JSON mapping di MVP.

### 4.3 Auth yang didukung

| Mode UI | Kontrak konfigurasi | Perilaku |
|---|---|---|
| API Key (header) — rekomendasi sederhana | header_name default X-API-Key; secret value | Header server-side |
| Bearer Token | secret token | Authorization Bearer |
| Basic Auth | username + secret password | HTTPS only |
| OAuth 2 Client Credentials | HTTPS token_url, client_id, secret, optional scope/audience, client auth basic atau post | Form-urlencoded grant_type=client_credentials; cache expires_in; reacquire token, bukan mengasumsikan refresh_token |
| Header tambahan | nama + secret value, maksimal 5 | Allowlist tervalidasi, bukan header hop-by-hop |
| API Key query (legacy) | parameter name + secret | Advanced, peringatan URL/log vendor bisa bocor; disetujui admin |
| Tanpa autentikasi | tanpa secret | Hanya data non-sensitif + approval admin; dilarang modul DO ber-PII |

Mode di tabel WAJIB berfungsi dan diuji pada MVP. Tidak boleh mengklaim dukungan auth dari adanya dropdown saja.

mTLS adalah **opsi transport**, dapat digabung dengan Bearer/API key/OAuth; tahap lanjut berdasarkan vendor riil. Certificate/private key tidak disimpan di JSON biasa; gunakan encrypted private storage dan expiry monitoring. HMAC/JWT-signed/custom login-token API bukan protokol universal: adapter tambahan hanya setelah dokumentasi vendor + test vector tersedia. Jangan menampilkan opsi yang belum diimplementasikan sebagai bisa digunakan. OAuth authorization-code/password grant bukan scope MVP.

Blokir header Host, Cookie, Content-Length, Connection, Transfer-Encoding, Forwarded/X-Forwarded-*, Proxy-Authorization; Authorization hanya dimiliki strategi terpilih. CR/LF dalam nama/nilai ditolak. Token URL masuk SSRF guard yang sama. OAuth token expiry dikurangi safety margin 30s, lock token acquisition per revision; satu kali reacquire setelah 401, tidak retry terus pada 403.

## 5. State machine, revision, dan approval

### 5.1 Entitas terpisah

- Vendor status: pending_verification → pending_approval → approved; rejected/suspended terminal sampai admin mengaktifkan kembali melalui aksi eksplisit.
- Connection revision: immutable snapshot base URL/auth/secret reference. Edit membuat revision baru. Secret kosong pada edit berarti pertahankan; hapus credential lewat aksi explicit, bukan menimpa dengan kosong/masked text.
- Binding: hubungan vendor + operasi kontrak, dengan pointer `active_revision_id` dan `draft_revision_id`.
- Binding revision: draft → testing → test_failed atau test_passed → submitted → approved/rejected; approved revision immutable.
- Contract version: draft → published → deprecated → retired; published immutable.

Vendor approved tidak berarti binding aktif. Test pass tidak berarti approved. Runtime hanya menggunakan revision approved yang ditunjuk active_revision_id dan vendor approved. Perubahan draft tidak mengganggu active revision lama.

### 5.2 Gate submit dan approve

Report pass terikat `binding_revision_id`, `connection_revision_id`, `contract_version_id`, `scope_revision`, `suite_version`. Simpan hash snapshot non-secret + secret version identifier (jangan hash credential low-entropy sebagai fingerprint publik).

- Submit hanya jika report penuh pass pada snapshot yang sama dan selesai <=24 jam lalu.
- Edit URL/path/auth/secret/query/scope/contract membatalkan pass draft; vendor harus tes ulang. Jangan sekadar mengecek latest report endpoint.
- Approve dilakukan transaction + row lock, validasi semua revision dan TTL kembali. Jika report expired: sistem enqueue retest otomatis, approval pending; admin tidak perlu testing manual. Retest gagal → kirim ke vendor untuk perbaikan.
- Aktivasi atomic; invalidate cache/cursor lama, audit actor/report/revision. Rejected draft tidak mematikan active lama.
- Contract perubahan breaking tidak auto-mengubah binding active. Admin publish versi baru, vendor migrasi/test; versi lama aktif sampai deadline yang ditetapkan minimal 30 hari. Retired memblokir runtime; notifikasi sebelum deadline.
- Admin tidak boleh publish schema yang frontend fixed belum mendukung. Perubahan field inti/UI memerlukan code release + tests. Kontrak bukan page builder.

## 6. Testing mandiri dan report

Tombol Tes menghasilkan 202 + test_run_id; worker berjalan background, UI poll status tiap 2 detik sampai terminal. Jangan menjanjikan hasil seketika tanpa timeout. Tampilkan progress per check dan cara memperbaiki. Vendor boleh mengulang terus selama masa integrasi, **bukan request tak terbatas per detik**.

Default limiter: 5 test starts/menit/vendor, satu test aktif/binding, dua test aktif/vendor, global worker concurrency 4, maksimal 25 outgoing calls/run. 429 + Retry-After; tes duplikat saat running kembalikan run ID yang sama. Timeout run 120s, worker timeout di atas 120s dan queue retry_after di atas worker timeout. Job abandoned ditandai timed_out; tidak pernah menjadi pass karena worker crash.

### 6.1 Suite wajib

1. URL allowlist, DNS/IP/TLS/auth (real request ke domain vendor yang disetujui).
2. Status HTTP, application/json, payload/depth/size limits; malformed JSON gagal.
3. JSON Schema 2020-12 dengan format assertions untuk date-time/date/URI; jangan menganggap required melarang null kecuali tipe memang non-null.
4. Empty dataset valid structure; bukan bukti positive behavior. Wajib sandbox atau contoh record nyata yang boleh diuji. Jika tak ada positive fixture, status `incomplete` dan submit blocked.
5. limit=1, offset=0/1, total_filtered, stable order; next cursor bila capability cursor aktif. limit=0 untuk count-only.
6. Search known ID serta non-existing term, filter UT/prodi/status/date. Pastikan row dan count sesuai. Unknown filter tidak boleh silently ignored.
7. Cross-scope fixture dari vendor: approved UT A dan B. Scope A tidak boleh memunculkan B; detail ID B dengan scope A harus 404. Negative auth bukan kewajiban pada API tanpa auth, ditandai not_applicable dengan alasan.
8. Detail/events konsisten dengan identity, count summary konsisten pada fixture statis, SLA counters/sum diuji.
9. Timeout/429/5xx tidak ditafsir sebagai data kosong. Uji respons besar dan invalid fields via mock suite di CI; jangan menyerang produksi vendor.
10. Tidak ada PII/secret/body asli dalam report. Error memuat JSON Pointer, expected type/rule, actual type saja. Contoh: `/data/0/stock_quantity: harus integer, diterima string`.

Hasil: queued/running/passed/failed/incomplete/timed_out/cancelled. `checks` memakai pass/fail/not_applicable; overall passed hanya semua required pass. UI submit disabled belum cukup: server tetap menolak bypass.

Report final: run ID, vendor, binding/revision IDs, schema/suite version, started/finished UTC, status, response latency p50/p95 jika sampel cukup (selain itu individual), HTTP codes, check results, error code/path/message, sanitized remediation, tested scope, expires_at. Tidak simpan body DO/alamat, tidak tampil secret atau raw request URL ber-query.

Admin melihat readiness per vendor/modul dan report akhir; approve/reject dengan catatan. Report membuktikan test case yang dijalankan, bukan seluruh jutaan data vendor benar. Runtime validation dan health tetap aktif setelah approve.

## 7. Kontrak umum vendor v1

Standar: JSON RFC 8259 UTF-8, HTTPS, RFC 3339 timestamps dengan `Z` atau offset (UTC internal), OpenAPI 3.1, JSON Schema 2020-12, RFC 9457 untuk error Paramita. Nama field snake_case adalah pilihan konsistensi, bukan persyaratan ISO. Tidak memakai JSON:API penuh; envelope sederhana tetap standar JSON dan umum digunakan.

Semua angka count/quantity integer >=0; tidak ada numeric string/NaN/Infinity. Batas integer 9007199254740991 agar aman di JavaScript. Angka pengukuran decimal number >=0. Unknown value = null, bukan 0/"-"/string kosong. Tanggal tidak valid gagal. Semua contoh tanggal harus RFC 3339.

String umum max 255; code/ID max 100; title max 500; event description max 1000. Tidak menampilkan HTML vendor. Field tambahan diizinkan untuk forward compatibility tetapi di-strip lewat allowlist serializer; tidak otomatis dipersist/render. Missing required gagal. Type coercion diam-diam dilarang.

### 7.1 Envelope list, count-only, dan meta

Semua list normal:

```json
{
  "data": [],
  "meta": {
    "generated_at": "2026-09-11T03:00:00Z",
    "data_as_of": "2026-09-11T02:59:30Z",
    "scope": {"ut_code": "UT-DEMO-01", "program_codes": []},
    "limit": 25,
    "offset": 0,
    "total_filtered": 0,
    "has_more": false
  }
}
```

`total_filtered` = jumlah dalam scope setelah seluruh filter, SEBELUM pagination. Tidak mengirim count nasional ke akun daerah. Count-only memakai limit=0, offset=0, data=[], has_more=(total_filtered>0). Normal mode data.length <= limit dan has_more=(offset+data.length<total_filtered), tanpa skip/gap pada snapshot statis.

`scope.ut_code` nullable hanya untuk user nasional; `program_codes` array string, [] berarti semua prodi dalam UT yang sudah authorized, bukan bebas untuk Tutor. Echo wajib sesuai request. Pagination v1 hanya menggunakan `offset`, `limit`, `total_filtered`, dan `has_more`.

Non-list response: `{data: object, meta: {generated_at,data_as_of,scope}}`. Lookup list juga memakai meta list, tetapi pagination berdasarkan key batch (§8.1). Versi kompatibilitas berasal dari path `/v1`, bukan body response.

### 7.2 Query standar

| Parameter | Aturan |
|---|---|
| limit | Default 25; normal 1..100; 0 count-only; vendor maksimal 100 |
| offset | Default 0, integer >=0; aplikasi membatasi random offset 10000 |
| cursor | Opaque max 2048, mutual exclusive offset; optional capability vendor untuk deep navigation |
| search | Trim 2..100 karakter, empty dihilangkan; case-insensitive prefix ID/kode/nama/title; bukan regex/fuzzy |
| ut_code | Code exact dari master, satu scope; server-authoritative |
| program_codes | Comma-separated canonical sorted codes, maksimal 20; OR dalam field, AND dengan filter lain |
| item_type | package atau book |
| stock_status | shortage, adequate, surplus, unknown |
| process_status_code | One of `01`,`02`,`03`,`04`,`05`,`06`,`07`; UI may send one or many comma-separated |
| process_status_bucket | Browser-only: on_process,on_delivery,retry,returned,delivered; server expands menjadi process_status_code. Jika client memberi code dan bucket sekaligus →422; vendor hanya menerima code |
| ordered_from, ordered_to | RFC3339; inclusive start, exclusive end; default UI 30 hari terakhir; max rentang 366 hari |
| period_code | Optional academic period exact, default kosong; digabung AND dengan date |
| sort | Fixed id:asc untuk list gabungan; vendor-spesifik boleh ordered_at:desc,id:desc jika capability diuji |

Semua operasi menerima scope. Unknown recognized filter value →422, bukan diabaikan. Default tanggal ditentukan satu kali backend/UI, dikirim eksplisit ke vendor, bukan setiap vendor menghitung sekarang sendiri. Stock adalah kondisi CURRENT; tidak ada date filter stock historis. Stock dapat difilter updated_from/updated_to RFC3339 dengan arti waktu perubahan record, bukan stok pada tanggal itu; UI harus memberi label tepat jika diaktifkan. Default UI stock tanpa tanggal; tanggal palsu dari mockup dihapus.

### 7.3 Identity dan keunikan

Vendor mengirim `id` immutable opaque ASCII string; sort id:asc memakai lexicographic byte order/case-sensitive agar deterministik. Identity internal `(vendor_id, source_id)`; no_do/nomor resi bukan global unique. Gateway inject `vendor_code,vendor_name` dari registry, tidak percaya nama vendor dari payload.

Satu row DO = satu fulfillment order milik vendor (bukan mahasiswa, bukan paket). Multi-shipment untuk satu DO membutuhkan future contract, tidak diduplikasi sebagai banyak DO diam-diam. DO yang sama di dua vendor dihitung dua fulfillment. Dashboard label wajib "Total DO vendor"; jangan klaim unique DO nasional tanpa identifier/ownership authority.

## 8. Operasi vendor dan data dictionary normatif

**Operasi bukan jumlah halaman.** Satu operasi dipakai beberapa halaman. Admin seed tujuh slot dan publish kontrak default v1 saat F0; vendor langsung memilih/mengisi URL, tidak perlu menulis schema. Kontrak frontend inti tidak boleh diubah lewat admin menjadi page builder. Vendor tidak perlu 11 format berbeda.

| Operation key | Module | Data |
|---|---|---|
| inventory.list | Stok | Stok paket atau judul per scope |
| inventory.summary | Stok | Ringkasan kategori stok |
| inventory.lookup | Stok | Stok key katalog tertentu untuk matrix |
| orders.list | DO | Daftar DO, reused oleh tiga halaman |
| orders.summary | DO | Aggregate nasional/scoped/per prodi/per UT |
| orders.detail | DO | Modal detail satu ID |
| orders.events | DO | History atau retry dipaginasi |

Tujuh operasi; modul Stok memerlukan tiga, modul DO empat. Semua GET. Setiap endpoint vendor hanya mengembalikan datanya sendiri, **bukan array beberapa penyedia**. Paramita yang menambahkan penyedia dan menggabungkan.

### 8.1 InventoryRow — inventory.list dan inventory.lookup

Semua field di tabel WAJIB ada; tanda nullable berarti JSON null boleh. Satu row unik per `(vendor,ut_code,program_code,item_type,item_code,edition)`; warehouse-warehouse vendor dijumlah di upstream lebih dulu, jangan satu stok nasional diulang pada setiap UT.

| Field | Tipe | Arti |
|---|---|---|
| id | string | ID record sumber stabil |
| catalog_key | string | Key master Paramita yang disepakati |
| item_type | enum package/book | Paket atau buku |
| item_code | string | Kode paket/buku |
| edition | string | Buku wajib nonempty; paket empty string |
| title | string | Judul |
| size_label | string nullable | Ukuran buku, misalnya A4; paket boleh null |
| ut_code | string | Scope alokasi stok; wajib exact master |
| program_code | string nullable | Null = stok bersama UT; tidak masuk scope Tutor/prodi spesifik |
| stock_quantity | integer | Unit tersedia saat updated_at |
| required_quantity | integer nullable | Kebutuhan yang disetujui; bukan tebakan threshold |
| unit_weight_kg | number nullable | Berat satu unit |
| total_weight_kg | number nullable | Berat aktual total |
| total_height_cm | number nullable | Tinggi agregat aktual, bukan menghitung dari stok tanpa data |
| total_area_m2 | number nullable | Luas aktual; bukan formula asumsi |
| updated_at | date-time | Waktu stok diperbarui |

Status stok **diturunkan** untuk semua layer: required_quantity null → unknown; stock < required → shortage; stock = required → adequate; stock > required → surplus. required=0 dan stock=0 → adequate. Vendor summary/filter memakai formula sama; admin tidak mengatur threshold global tersembunyi. Kebijakan kebutuhan lain memerlukan perubahan kontrak; data kebutuhan disepakati UT/vendor sebelum go-live. Badge unknown netral, bukan hijau.

```json
{
  "id": "STOCK-0001",
  "catalog_key": "PACKAGE-DEMO-01",
  "item_type": "package",
  "item_code": "PKT-DEMO-01",
  "edition": "",
  "title": "Paket Contoh Matematika",
  "size_label": null,
  "ut_code": "UT-DEMO-01",
  "program_code": "PRODI-DEMO-01",
  "stock_quantity": 120,
  "required_quantity": 150,
  "unit_weight_kg": 0.5,
  "total_weight_kg": 60,
  "total_height_cm": null,
  "total_area_m2": null,
  "updated_at": "2026-09-11T03:00:00Z"
}
```

Matrix: admin master katalog dipaginasi di DB Paramita limit<=25; pilih vendor subset maksimal 10 kolom; minta inventory.lookup?catalog_keys=... (maks 25 key). Vendor mengembalikan satu object/key: catalog_key, stock_quantity integer nullable, availability enum available/not_supplied, updated_at nullable date-time. Nilai dijumlah vendor dalam scope yang diminta; bukan scope nasional. Untuk available quantity wajib integer dan updated_at non-null. Not_supplied → quantity null; vendor timeout ditangani gateway sebagai unavailable, bukan not_supplied atau stok 0. Meta total_filtered = jumlah requested keys; data harus mencakup tepat semua key, no duplicate. Lookup tidak memerlukan scan semua inventory. Matrix buku otomatis beda key tiap edisi. Filter matrix hanya item_type/search katalog/vendor; filter status stok digunakan pada tabel detail stok, tidak mempengaruhi daftar katalog matrix.

### 8.2 inventory.summary

Data object required: `item_type`, `stock_quantity` integer, `record_count` integer, `shortage_count`, `adequate_count`, `surplus_count`, `unknown_count` integer. Jumlah empat status = record_count. Menghitung seluruh filtered scope, bukan page. record_count = record alokasi, bukan unique judul global. Frontend boleh menampilkan jumlah katalog unik dari master terpisah dengan labelnya; dilarang sum distinct-count antar vendor sebagai unique global.

### 8.3 Status proses canonical dari mapping bisnis

Vendor WAJIB mengirim `process_status_code` sebagai string dua digit sesuai mapping berikut (leading zero dipertahankan). Keputusan final pengguna: code06 adalah RETURN saja, tidak masuk Delivered. Hanya code07 dihitung Delivered. Bucket dan retry_attempt dihitung backend dari code, bukan teks vendor.

| Code | Nama status dari dokumen bisnis | Canonical bucket | Label UI default |
|---|---|---|---|
| `01` | `ON PROSES WAREHOUSE` | `on_process` | On Proses Warehouse |
| `02` | `ON DELIVERY` | `on_delivery` | On Delivery |
| `03` | `RETRY01` | `retry` | Retry 1 |
| `04` | `RETRY02` | `retry` | Retry 2 |
| `05` | `RETRY03` | `retry` | Retry 3 |
| `06` | `RETURN` (keputusan pengguna final) | `returned` | Return |
| `07` | `DELIVERED` | `delivered` | Delivered |

Agregasi card lama tetap tersedia sebagai kelompok besar:

- `on_process` = code `01`.
- `on_delivery` = code `02`.
- `retry` = code `03` + `04` + `05`.
- `returned` = code `06`.
- `delivered` = code `07`.

Ketika tabel/detail butuh nilai detail, gunakan `process_status_code` dan label dari mapping. Ketika chart/card butuh ringkasan, gunakan bucket besar di atas. Unknown status dari vendor → validation fail, bukan ditampilkan sebagai lainnya.

### 8.4 OrderRow — orders.list

| Field | Tipe | Arti |
|---|---|---|
| id | string | Source ID immutable |
| order_number | string | Nomor DO display |
| ordered_at | date-time | Waktu pemesanan |
| student_name | string | Nama; serializer Tutor mask |
| province, city, district, village | string nullable masing-masing | Provinsi, kabupaten/kota, kecamatan, kelurahan |
| ut_code | string | Daerah authoritative |
| program_code | string | Prodi authoritative |
| process_status_code | enum `01`..`07` | Status CURRENT dari mapping bisnis §8.3 |
| process_status_bucket | field browser-only | Gateway derive dari code; tidak wajib dalam payload vendor dan tidak diteruskan sebagai filter vendor |
| retry_attempt | field browser-only integer nullable | Gateway derive 1/2/3 untuk03/04/05, null lainnya; tidak wajib dalam payload vendor |
| updated_at | date-time | Perubahan terakhir |

Label prodi/UT diambil master. Unknown master menyebabkan failed validation/quarantine respons vendor; jangan salah map berdasarkan nama mirip. Ordered_at bukan updated_at. Per-record data testing disintesis; tidak menyalin nama mockup sebagai data nyata.

### 8.5 orders.detail

Request: `id` required + scope. Response data berisi seluruh OrderRow plus:

| Field | Tipe |
|---|---|
| package_code, package_title | string nullable masing-masing |
| carrier_name, tracking_number | string nullable masing-masing |
| paid_at, handed_to_carrier_at, completed_at | date-time nullable masing-masing |
| sla_target_days | integer >=0 nullable |
| sla_elapsed_days | number >=0 nullable |
| sla_status | enum on_sla/over_sla/not_applicable/unknown |
| latitude | number -90..90 nullable |
| longitude | number -180..180 nullable |
| latest_event | EventRow nullable |
| proof_of_delivery_url | HTTPS URI nullable |

Timeline ditampilkan dengan lazy calls orders.events, bukan unlimited array dalam detail. Fields waktu yang belum terjadi null. completed_at wajib non-null jika delivered, jika data proses vendor belum punya maka test gagal sampai vendor memperbaiki; returned tidak sama delivered.

SLA default: hari kalender elapsed dari handed_to_carrier_at ke completed_at (delivered) atau generated_at (non-delivered), detik/86400 tanpa pembulatan untuk klasifikasi. Jika target/handover belum diketahui → unknown. Returned → not_applicable. Elapsed<=target → on_sla, >target → over_sla. Ringkasan memakai definisi sama. Bukti dan lat/long boleh null; tidak membuat titik peta 0,0. Labels SLA di UI menunjukkan definisi ini, tidak mengklaim hari kerja/kontrak ekspedisi bila belum ada.

POD URL hanya untuk backend; browser menerima URL route Paramita berotorisasi. Fetch image dilindungi SSRF/size/MIME, no redirects, JPEG/PNG/WebP saja; no SVG/HTML; tidak simpan permanen; streaming max 5MB dan Cache-Control private,no-store. User Tutor tidak diizinkan POD/detail. Jika vendor memerlukan auth khusus domain POD, gunakan koneksi khusus disetujui; jangan meneruskan Bearer token ke host asing.

### 8.6 EventRow — orders.events

Request id + kind=history atau retry + limit/offset + scope. Envelope list standar. Sort occurred_at:asc,id:asc fixed untuk timeline.

Required fields: `id` string, `occurred_at` date-time, `title` string max255, `description` string nullable max1000, `location` string nullable max255, `process_status_code` enum `01`..`07` nullable. Retry adalah event usaha ulang; current status retry pada cards tidak sama jumlah event retry. Detail latest_event adalah EventRow atau null. Pagination timeline max25/page, max100 per request; UI "Muat berikutnya".

### 8.7 orders.summary

Request scope dan filter sama dengan orders.list, tambahan group_by=none/program/ut. none mengembalikan data object SummaryRow; program/ut mengembalikan GroupRow sorted group_code:asc. Gateway WAJIB memberi group_codes dari master page; limit=jumlah requested codes (1..25),offset=0,total_filtered=jumlah requested codes,has_more=false,next_cursor=null. Count-only group memakai count master Paramita, bukan fanout count vendor; vendor menolak limit0 untuk operasi grouped summary. Semua filter identik termasuk search; chart/total tidak boleh mengabaikan search tabel ketika indikator "filter aktif" dipakai.

SummaryRow fields required:
- total_orders integer.
- status_counts object bucket besar: on_process,on_delivery,retry,returned,delivered integer; jumlah = total_orders.
- status_code_counts object granular: `01`,`02`,`03`,`04`,`05`,`06`,`07` integer; jumlah = total_orders. Field ini WAJIB untuk chart retry per attempt dan audit mapping.
- sla_counts object: on_sla,over_sla,not_applicable,unknown integer; jumlah = total_orders.
- completed_sla_seconds_sum integer >=0 dan completed_sla_sample_count integer >=0. Sampel hanya delivered dengan handed_to_carrier_at/completed_at valid dan nonnegative. Sample count <= delivered. Count=0 → sum=0.

GroupRow: group_code string, summary SummaryRow. Group code tipe sesuai group_by. Gateway meminta grup dari master page (lihat §10) untuk bounded aggregation, parameter group_codes comma-separated maksimal25; response group_codes wajib tepat requested authorized groups, zero group tetap object count0. Jangan request ribuan group lalu slice di PHP.

```json
{
  "data": {
    "total_orders": 10,
    "status_counts": {"on_process": 2, "on_delivery": 3, "retry": 1, "returned": 0, "delivered": 4},
    "status_code_counts": {"01": 2, "02": 3, "03": 1, "04": 0, "05": 0, "06": 0, "07": 4},
    "sla_counts": {"on_sla": 7, "over_sla": 1, "not_applicable": 0, "unknown": 2},
    "completed_sla_seconds_sum": 691200,
    "completed_sla_sample_count": 4
  },
  "meta": {"generated_at": "2026-09-11T03:00:00Z", "data_as_of": "2026-09-11T02:59:30Z", "scope": {"ut_code": "UT-DEMO-01", "program_codes": []}}
}
```

Average SLA gabungan = sum(completed_sla_seconds_sum)/sum(sample_count)/86400, display 2 desimal; count0 → null. Jangan rata-rata dari rata-rata vendor. Cards semua vendor berasal dari SummaryRow vendor, bukan dari tabel page saat ini.

### 8.8 Schema publication dan error

Fase pertama WAJIB menghasilkan schemas untuk envelope, InventoryRow, lookup row, inventory summary, OrderRow, order detail, EventRow, SummaryRow, GroupRow, Problem. Set `$schema` draft2020-12, `$id` immutable versioned, required/type/enum/range/maxLength sesuai tabel. Gunakan local registered references saja; no remote schema fetching, no PHP callbacks/filter extension vendor/admin.

Schema admin editor advanced hanya menerima standard keyword allowlist; basic editor menunjukkan tabel field read-only dan deskripsi. Admin bisa memperketat opsional melalui versi baru namun tidak menghapus field inti atau mengganti tipe tanpa frontend release. Semua response example harus validate lewat Opis dengan format assertions dan golden tests sebelum publish.

Paramita errors content-type application/problem+json; type URI stabil, title, status numeric, detail aman, instance path TANPA query personal/auth, request_id extension. 401 belum login,403 unauthorized,404 object tidak ditemukan/dilarang lintas vendor untuk mencegah enumeration,409 stale revision/cursor,422 invalid request,429 throttled,502 invalid upstream,503 no usable source,504 deadline. Vendor boleh menggunakan error format sendiri; gateway menormalkan tanpa meneruskan raw body.

## 9. Halaman dan mapping data/UI

Shared: navbar user+role+UT, logout POST, sidebar fixed empat halaman, filter pill+clear, loading skeleton, empty state, partial/stale badge dengan timestamp, error retry, responsive horizontal table, keyboard/focus modal, rows-per-page 10/25/50/100, default25. `AbortController` + sequence guard mencegah response search lama menimpa yang baru. Debounce 350ms. Tidak ada 1000 DOM rows, fetch semua data, reload halaman untuk pagination.

Chart rules:

- Semua chart memakai Chart.js 4 self-hosted via Vite bundle, bukan CDN, Highcharts, atau library berlisensi komersial.
- Responsive canvas dengan aspect ratio adaptif; wajib enak di desktop, tablet, dan mobile. Pada mobile, chart horizontal bar/list summary lebih diprioritaskan daripada chart padat yang sulit dibaca.
- Interaktif minimal: tooltip angka terformat, legend toggle, click segment/legend untuk menerapkan filter tabel bila relevan, empty/partial/stale state jelas.
- Accessibility: warna tidak menjadi satu-satunya pembeda; tooltip/label angka tersedia; kontras sesuai tema starter.
- Chart tidak boleh memuat dataset mentah jutaan baris; chart hanya memakai summary endpoint (`orders.summary`, `inventory.summary`, `inventory.lookup` bounded).

| Halaman | Sumber | Mapping wajib |
|---|---|---|
| dashboard Paket | inventory.summary/list/lookup item_type=package | Card per vendor: stok/status counts; matrix catalog_key × vendor; tabel penyedia, kode, judul, quantity, unit kg, total kg, total cm, total m2, derived status |
| dashboard Judul | operasi inventory item_type=book | Sama + edisi/ukuran; matrix key buku+edisi |
| monitoring-delivery | orders.summary group_by=none + orders.list | Total DO vendor + delivered/on_delivery/on_process/retry/returned; chart per vendor untuk tiap metric; tabel DO; modal |
| do-per-prodi | orders.summary group_by=program + orders.list | Prodi filter; DO Mahasiswa/status pengiriman/status SLA; per-vendor breakdown; tabel reused; modal |
| do-per-ut-daerah | orders.summary group_by=ut + orders.list | UT filter sesuai scope; DO Mahasiswa/status pengiriman/status SLA; per-vendor breakdown; tabel reused; modal |

Detail modal: Informasi=OrderRow/master; Delivery=carrier/tracking/package; Status Terakhir=process_status_code + label mapping §8.3/latest_event/SLA/coordinates; History=events kind history; Retry=events kind retry; Order/Payment/Handover/Completion times + POD. Timeline bukan halaman monitoring retry terpisah.

Column labels sesuai starter tetapi koreksi salah label: "Berat per unit (kg)" lebih akurat daripada Berat Buku untuk paket. Semua null tampil "Belum tersedia". Return display "Return"; enum bucket tetap returned. Tooltip menjelaskan Total DO vendor dan SLA. Tidak hardcode nama vendor/chart series/counters contoh. Total card seluruh vendor memakai aturan §10.1; chart breakdown per vendor maksimal10 series agar tetap terbaca. Jika vendor aktif >10, chart menampilkan top/selected vendors + kategori "vendor lainnya" dari aggregate cache bila tersedia, dengan label jelas.

Tabel DO columns: No DO→order_number, Tanggal Pemesanan→ordered_at localized Asia/Jakarta, Nama→student_name (masked Tutor), Provinsi→province, Kabupaten→city, Kecamatan→district, Kelurahan→village, UT Daerah→master.ut, Status Proses→label dari `process_status_code` mapping §8.3, Nama Prodi→master.program. Tambah Penyedia untuk membedakan nomor DO sama lintas vendor. Tutor lokasi detail province/city/district/village dihapus dari response, bukan CSS-hidden.

Desain: ekstrak layout/style starter menjadi Blade layout/partials; jangan copy-paste 4 versi navbar/controller. Hilangkan duplicate ID denseTableJudul, duplicate Bootstrap JS/jQuery/DataTables load, dummy cart/profile links dan animasi yang memperlambat tabel. Chart.js theme meniru visual starter; jangan menambah redesign besar. E2E screenshot comparison pada desktop/mobile saat implementasi, bukan mengklaim visual sesuai hanya dari DOM.

## 10. Algoritme gateway, multi-vendor dan pagination

### 10.1 Request execution

Browser → authorized JSON route → ScopeContext → FilterDTO validated → select approved bindings → cache → bounded HTTP pool → schema + semantic validation → normalize/allowlist → aggregate → serialize.

Tabel detail dan matrix interaktif: selection vendor maksimal10 untuk membatasi query; jika registry lebih besar UI memakai selector paginated dan label **Penyedia terpilih**, bukan “Semua” yang diam-diam dipotong. Request detail >10 vendor →422. Pilihan kosong detail otomatis memilih 10 pertama vendor_code ASC, UI dan meta mengungkap selection tersebut. Filter search/periode mempersempit daftar; tidak ada global chronological sort.

Cards/chart summary memiliki `vendor_scope=all` DEFAULT: mencakup SELURUH vendor eligible, tanpa cap10. `vendor_scope=selected` memerlukan vendor_codes eksplisit (max10); label menjadi **Total penyedia terpilih**, bukan Total semua vendor. Pemilihan kolom matrix/pagination tabel tidak mengubah vendor_scope summary otomatis. Semua filter data lainnya diwariskan identik ke summary dan tabel. Click bar vendor secara eksplisit mengubah vendor_scope menjadi selected dengan filter chip; Clear mengembalikan all.

Eligible source = vendor approved + binding enabled/approved untuk operasi diminta + scope vendor beririsan scope user + contract belum retired. Vendor registered tanpa binding/pending/suspended tidak menyumbang angka; sumber eligible yang gagal TETAP termasuk expected_sources, tidak dikeluarkan supaya angka terlihat complete. Registry tidak fixed3 dan tidak dianggap max10. Implementasikan satu AggregateCoordinator untuk summary on-demand/background sesuai §10.5, bukan cron menghitung dari tabel page.

Secara nasional (scope seluruh40 unit), setiap vendor diminta summary SELURUH scope layanannya yang disetujui; jangan menjumlah summary nasional vendor ditambah summary40 unit lagi. Untuk daerah/luar negeri/prodi, request hanya scope tersebut. Vendor scope tidak beririsan → excluded_no_scope; expected_sources bisa0 (empty result sah), bukan vendor outage.

### 10.2 List gabungan v1: vendor-segment pagination

Urutan: vendor_code ASC (master immutable), lalu source id ASC. UI label "Urut penyedia, lalu ID sumber". Tidak menyediakan sort tanggal global palsu.

1. Count-only paralel untuk setiap sumber selected, scope/filter identical. Cache count 30s; hasil count adalah best-effort live view, bukan snapshot transaksi lintas vendor.
2. Bangun rentang kumulatif count. Global offset O/limit L dipetakan ke vendor segment yang overlap [O,O+L).
3. Fetch hanya vendor yang overlap dengan local offset dan local limit. Jangan meneruskan O ke semua vendor. Jangan fetch O+L rows per vendor.
4. Contoh fixture statis counts A=3, B=4; O=2,L=3 → A local offset2 limit1 + B offset0 limit2.
5. Gabungkan sesuai urutan; meta.total_filtered=sum counts bila semua count valid. Row count maksimal L. Jika vendor berubah count sementara, invalidate counts dan retry seluruh page satu kali dalam deadline. Tetap tidak cocok →409 DATA_CHANGED, UI minta refresh; jangan fill dengan duplicate row.
6. Max global offset 10000. Melampaui →422 DEEP_PAGINATION_REQUIRES_FILTER: pilih vendor/periode/search. Tidak menawarkan jump to last page jika total melampaui batas.
7. Single-vendor cursor capability memungkinkan deep sequential browsing; link/cursor ditandatangani server dan terikat actor scope+filter+revision. Jika vendor belum mendukung cursor, UI memakai filter lebih sempit, bukan offset jutaan.
8. Cursor dan offset tidak dicampur. Global cursor merge arbitrary tidak dibangun pada MVP.

Pagination context `snapshot_id` adalah opaque signed ID (bukan snapshot database vendor). Request pertama tanpa ID membuat context Redis TTL maksimal30s berisi selection vendor, counts, filter digest, scope/permission/source revisions dan sort; tidak berisi rows/PII. Page berikutnya mengirim ID; server memverifikasi actor/effective scope, filter dan revisions sebelum menggunakan counts. Context hilang/expired/berbeda →409 PAGINATION_CONTEXT_EXPIRED, UI reset offset0 dan refresh. Jangan memperpanjang TTL saat dibaca. Jika count/data berubah saat fetch, retry sekali dengan context baru dalam deadline; response membawa ID pengganti. Definisikan `meta.count_accuracy=best_effort_live` dan `meta.snapshot_id` pada list; matrix/options/events tidak memerlukan snapshot_id karena bukan paginator gabungan. Cursor vendor-spesifik tetap terpisah dan mengikuti §7.1.

Konsistensi live: tidak menjanjikan no-skip antar halaman jika data berubah. Sediakan refreshed_at; reset pagination saat filter berubah. Snapshot kuat membutuhkan vendor snapshot API atau read model, bukan sekadar timestamp dalam meta.

### 10.3 Matrix dan grouped summaries

Matrix paginate master catalog di DB, kirim tepat page keys ke inventory.lookup tiap vendor. Merge per catalog_key dan vendor_code, bukan array index. Semantik null vs0 vsunavailable wajib dijaga. Master belum lengkap → tampil peringatan coverage katalog, tidak mengeklaim semua stok tercakup; detail inventory.list tetap menolak unknown code sampai master disetujui.

Grouped summary: browser `limit` default25,max100, `offset` mempaginate master programs/UT SETELAH scope. Untuk tab UT default browser limit40 mengambil seluruh40 unit (39 daerah+luar negeri), sedangkan kepala unit hanya1. Upstream `group_codes` maksimal25/call: gateway membagi40 menjadi batch25 dan15 PER VENDOR, memakai scope/filter/date boundary sama, lalu merge keyed group_code. Meta browser total_filtered adalah jumlah master groups dalam scope, bukan penjumlahan jumlah grup tiap vendor. Unit tanpa order tetap tampil zero group jika sumber-sumbernya merespons sukses.

Untuk group di luar vendor approved scope, gateway mengisi zero (excluded_no_scope), tidak meminta vendor mengakses unit di luar izin. Expected calls dihitung dari batch yang beririsan; bila satu batch vendor gagal, contribution vendor untuk response grouped seluruh page dianggap gagal (atomic per-vendor response), jangan publish half-page sebagai lengkap. Grouped request memakai AggregateCoordinator yang sama (§10.5), termasuk queue ketika jumlah calls>10. Total card tidak dijumlah dari kelompok satu page; fetch ungrouped summary terpisah dengan filter sama.

`group_by=vendor` DILARANG ke upstream. Breakdown vendor dibentuk Paramita dari `orders.summary?group_by=none` per vendor; UI mengakses ungrouped summary yang sudah berisi by_vendor. Grouping upstream yang valid hanya none/program/ut. Times antar vendor dapat berbeda; meta menyatakan interval fetched time, bukan atomic snapshot transaksi lintas vendor.

### 10.4 Partial outage, cache, timeout

- Connect timeout 2s, per-request timeout5s, gateway end-to-end deadline8s, pool concurrency4. Count+data waves berbagi deadline. Melewati deadline hentikan; tidak menunggu semua worker tak terbatas.
- GET retry maksimal sekali hanya jika budget tersisa dan transient failure; 429 hormati Retry-After, 401 auth handling terbatas,403 tidak retry. Tidak ada retry storm dalam request user.
- Circuit opens setelah5 transient failures/60s per active binding; cooldown60s, half-open satu probe. Failure auth/schema dipisah dari transport dan dilaporkan.
- Metadata cache30s. Summary freshness30s, stale fallback maksimal5menit dari waktu fetch sukses (hard TTL300s). Cache duplicate refresh single-flight.
- DO list/detail/events/POD default **tidak dicache persisten** (termasuk Redis) untuk minimisasi PII. Detail lazy-load. Inventory/summary boleh cache encrypted; cursor cache metadata counts/filter digest saja, tidak PII, TTL5menit.
- Valid stale summary boleh HTTP200, meta state=stale/partial dan sumber unavailable. Stale tidak boleh dipakai untuk suspended/revoked/schema-retired/source scope mismatch.
- List global: jika count atau row sumber yang diperlukan tidak tersedia, gunakan response503 dan pertahankan tabel lama di UI dengan overlay error; jangan geser offset dengan menghapus vendor gagal. Summary lain boleh tetap tampil partial. Vendor-spesifik yang sehat tetap dapat diakses lewat filter.
- All sources unavailable →503 Problem. Invalid JSON/schema →502 untuk sumber terkait; tidak dianggap empty array. Jangan gabung success envelope dan Problem dalam satu body.
- Sources tanpa binding approved ditandai not_configured, tidak termasuk total; UI menampilkan coverage dan excluded vendors. Registered saja tidak berarti menyumbang data.

### 10.5 AggregateCoordinator — algoritme wajib seluruh vendor

**Satu jalur data.** Berlaku inventory.summary, orders.summary none/program/ut; endpoint summary browser tidak menghitung raw records. Ini cache ringkasan ephemeral, bukan warehouse/ingestion transaksi. Tidak ada job untuk setiap kombinasi filter nasional tanpa demand.

**A. Filter canonical dan cache isolation**
1. Validasi/authorize dahulu. Normalize item_type, ut_code, sorted unique program_codes, process_status_code, stock_status, period_code, search, ordered_from/to, updated_from/to, group_by dan exact group_codes page. Omit empty optional values; status bucket browser diexpand ke code. Tanggal selalu explicit UTC start inclusive/end exclusive. Default DO “30 hari” = mulai00:00 WIB hari ini minus29hari hingga00:00 WIB besok; boundary dikirim sekali sehingga shared filters stabil per hari.
2. Seluruh filter operasi yang valid diteruskan identik ke vendor, termasuk search: tidak mengembalikan total cached tanpa search untuk query dengan search. inventory.summary menerima filter inventory.list yang relevan kecuali limit/offset/cursor/sort; orders.summary menerima semua filter orders.list kecuali pagination/sort, plus group. Lookup matrix tetap berdasarkan master keys, bukan search transaksi.
3. Build query_signature dengan HMAC-SHA256 canonical JSON memakai dedicated cache digest key. Include role/PII permission class, effective UT/prodi scope, permission/scope revisions, master revision, operation/schema version, vendor_scope dan selected codes. Include `source_set_revision` (snapshot eligible vendor+binding+connection+scope revisions). Jangan memasukkan password/token mentah. Actor berbeda boleh share cache HANYA effective permissions+scope identik; run access tetap dicek.
4. Query descriptor dengan search (bisa nama) disimpan ENCRYPTED Redis max300s; job payload DB hanya run_id. Dilarang mencatat search mentah atau menyimpan descriptor/kontribusi ke failed_jobs. Run metadata SQL hanya IDs/hash/status/counter/deadline, tanpa filter/body.

**B. Read, schedule dan resource limits**
5. Cache-hit selesai: return200 jika contributions masih sah (§C). Fresh bila SEMUA included sources fetched<=30s; stale bila lengkap tetapi ada fetched>30s dan semua<=300s. Return old usable result lalu enqueue single refresh jika age>30s. Tidak mereset fetched_at saat baca cache.
6. Cache-miss dan expected upstream calls<=10: boleh attempt paralel bounded concurrency4 dalam8s budget. Yang gagal/belum selesai tidak di-loop terus; masuk mekanisme run yang sama. Lebih10 calls: langsung202, tidak fanout ratusan vendor dari PHP-FPM.
7. 202 body: data=null; meta.request_id, state=processing, run_id, poll_url, retry_after_seconds=2. Browser poll GET /api/v1/aggregate-runs/{run_id} tiap2s, max60s di UI lalu tampil “Masih diproses”/retry, bukan data0. GET polling authorize scope+source_set_revision kembali. Backend run hard deadline120s termasuk antrean; terminal ready/partial/failed/superseded. Terminal sukses/partial returns same success serializer, no second incompatible payload.
8. Lock per query_signature/source_set_revision: hanya satu active run (Redis lock lease30s + owner heartbeat10s, fencing token run ID). HTTP refresh duplicate returns active run ID. Queue database dedicated `aggregates`: max4 concurrent outgoing across worker pool, max2/vendor lintas run, reserve separate workers untuk onboarding supaya tidak starvation. Per vendor rate budget default30calls/min (operator dapat MENURUNKAN sesuai kontrak). Jika budget habis, defer sebelum deadline; jangan overwrite 429 dengan success.
9. Source job memproses satu batch call; connect2s/request5s, maksimum1 transient retry dalam deadline, hormati Retry-After. Chunk group>25 membuat beberapa jobs; finalize per-vendor setelah seluruh batches terminal. Every job idempotent key(run_id,vendor_id,batch_index); duplicate delivery replace result, bukan menambah counter/total dua kali. Late jobs setelah deadline/superseded dilarang publish.
10. Refresh scheduler setiap1menit, hanya signatures yang diakses dalam5menit terakhir, skip query dengan active run. Priority latest user request, fair FIFO, tidak Cartesian product40UT×semua prodi×semua filter. Batasi20 active runs global dan2 active signatures/user; excess429 Retry-After. Active query descriptors <=1000 global; LRU expire idle. Pub/sub tidak perlu; polling cukup. Bila backlog membuat sebagian vendor tidak selesai120s, hasil partial jujur; sizing worker/kontrak vendor harus di-benchmark, jangan menjanjikan SLA untuk jumlah vendor tak terbatas.

**C. Contribution, merge dan publication**
11. Simpan hasil scalar/group summary per source/batch encrypted Redis dengan fetched_at, vendor_generated_at, version tuple, signature, run_id. Validate schema, count invariants, scope echo dan time bounds sebelum menerima. contributed result tidak boleh menggunakan versi/filters/scope berbeda.
12. Setelah semua jobs terminal atau deadline, pilih satu contribution/vendor: hasil valid run ini; jika transient timeout/5xx/429, boleh fallback LAST VALID same signature+version umur<=300s. 401/403, scope mismatch, schema failure, revoked/suspended/retired TIDAK boleh fallback. Batch grouped harus fallback satu vendor-page lengkap, jangan mencampur sebagian batch lama dan baru untuk vendor sama.
13. complete bila semua expected sources punya valid contribution (fresh atau stale). `totals_all_vendors` object hanya diisi bila vendor_scope=all DAN complete. `totals_selected_vendors` hanya diisi bila vendor_scope=selected DAN complete. `totals_available` = sum included valid contributions; jika belum lengkap, total utama null dan UI menampilkan subtotal available dengan “Data sebagian (x/y sumber)”. Jangan anggap missing=0. Eligible0 → complete zero SummaryRow, label “Belum ada sumber aktif”; eligible>0 tetapi included0 →503 Problem.
14. Sum integer metric additive dan status_code_counts. Verify bucket01=on_process,02=on_delivery,03+04+05=retry,06=returned,07=delivered; tiap count total cocok. Average SLA pakai sum seconds / sum samples /86400, count0=null. No distinct DO/student nasional, no average-of-averages. Complete totals = seluruh kontribusi vendor, bukan top9 chart atau tabel page25.
15. Publish aggregate immutable generation dengan pointer atomic SET/CAS setelah recheck source_set_revision+run fencing token. Pointer hanya menunjuk run yang berlaku. Publish jika descriptor masih ada; missing Redis descriptor → failed, bukan query tanpa filter. Commit cached aggregate hard expiration berdasarkan oldest included fetched_at+300s, bukan publication_time+300s. Tiap read revalidate ages; bila expired contribution menyebabkan partial, rebuild/refresh atau return202/503, jangan biarkan complete lama tampil.
16. by_vendor memuat ringkasan chart terurut metric DESC, tie vendor_code ASC: maksimal9 vendor + satu synthetic group `__others__` jika lebih9. Others dijumlah dari SEMUA eligible included vendors sisanya; group ini bukan vendor endpoint dan tidak dapat dipakai sebagai vendor_code detail. Click Others membuka selector vendor, bukan request upstream. Source metadata paginated terpisah via /api/v1/aggregate-runs/{run_id}/sources, limit<=100; total full registry tidak perlu dimasukkan dalam setiap payload.
17. Activation/suspend/scope/schema/master change menaikkan source_set_revision dan menandai run affected superseded; invalidate cache key generation. Worker lama tidak boleh publish. User permission change →403 pada poll; source config change dengan hak user tetap sama →409 AGGREGATE_SUPERSEDED, UI refetch route summary. Tidak boleh meneruskan nationwide snapshot ke user daerah setelah assignment berubah.

**D. Coverage dan waktu**
18. Summary meta wajib: request_id, state fresh/stale/partial, run_id, filter_signature, generated_at=publication UTC, data_as_of_oldest/newest=fetched_at bounds, scope, vendor_scope, coverage(expected_sources,included_sources,fresh_sources,stale_sources,missing_sources,excluded_sources). Invariants included=fresh+stale; expected=included+missing; complete jika missing0. excluded_sources adalah vendor relevan yang tidak eligible pada snapshot, bukan failures. Registered/approved/suspended registry counts hanya panel admin (jangan bocorkan profil vendor global ke Tutor).
19. Setiap sumber di paginated sources route: vendor_code,name,state fresh/stale/unavailable/excluded, fetched_at nullable,error_code nullable. `sources.pagination.total_filtered` jumlah source metadata snapshot, bukan jumlah order. Partial memiliki daftar missing source code lewat route ini. Browser summary body tidak menyatakan sources=[] sambil mengklaim banyak included.
20. UI menampilkan “Seluruh vendor aktif · Unit X · filter aktif · diperbarui...” atau “Penyedia terpilih”; changing filter clears old numbers to loading until matching signature available. Boleh mempertahankan previous visual dengan overlay jelas tetapi tidak melabelnya hasil filter baru. Semua charts/card satu summary response memakai generation sama; grouped/list response terpisah boleh beda waktu yang ditampilkan.

**E. Storage, recovery dan acceptance**
21. Tables aggregate_runs(id,query_hash,source_set_revision,status,expected_jobs,terminal_jobs,started_at,deadline_at,finished_at) dan aggregate_run_jobs(run_id,vendor_id,batch_index,status unique tuple) menyimpan metadata saja, prune24h. SQL fencing transactional counter saat Redis lock outage; jika Redis unavailable fail503 aggregate (tidak bypass ke unlimited live calls). Payload contribution encrypted Redis; no backup/persistent transaction store. Abandoned jobs deadline → failed/partial; scheduler cleanup idempotent.
22. Wajib integration scenarios:12vendor semuanya sukses menghasilkan total12vendor; 1missing dengan cache lama sah→complete stale; 1missing tanpa cache→partial total utama null; semuanya gagal→503; duplicate/retry job tidak double count; cache key filter/scope berbeda tidak share; suspend saat running→superseded; batch40unit25+15 tepat; number top9+others sama total; imported luar negeri tidak hilang. Simulasikan clock untuk30s/300s/120s; jangan hanya test waktu nyata yang flaky.

## 11. JSON routes browser dan admin/vendor API

Browser same-origin, session authenticated; definisikan JSON routes di middleware web/session atau konfigurasi stateful setara, bukan api.php stateless lalu berharap session otomatis terbaca. Semua mutations CSRF. JSON GET response private,no-store untuk PII. Browser tidak mengetahui URL vendor/credential.

| Route | Kegunaan |
|---|---|
| GET /api/v1/stock/summary | inventory.summary aggregate |
| GET /api/v1/stock/items | inventory.list global/single |
| GET /api/v1/stock/matrix | master catalog + lookup |
| GET /api/v1/orders | orders.list |
| GET /api/v1/orders/summary | orders.summary none/program/ut |
| GET /api/v1/vendors/{vendor}/orders/{source_id} | scoped detail |
| GET /api/v1/vendors/{vendor}/orders/{source_id}/events | scoped timeline |
| GET /api/v1/vendors/{vendor}/orders/{source_id}/proof | scoped POD proxy |
| GET /api/v1/options/{ut,programs,vendors,catalog} | Empat route terpisah, scoped searchable master options, limit default25 max100, offset>=0; options ut default40 |
| GET /api/v1/aggregate-runs/{run_id} | Poll summary job; authorize role/effective scope/current revisions tiap read |
| GET /api/v1/aggregate-runs/{run_id}/sources | Source metadata paginated limit25 max100, offset>=0; same authorization |

**Tidak ada namespace /pages di API.** Empat route HTML memanggil route reusable di tabel ini. Parameter `{vendor}` adalah vendor_code dari registry, bukan nama display. Browser-only parameters: vendor_scope, vendor_codes, process_status_bucket, snapshot_id (pagination context); tidak diteruskan upstream. Controller menerjemahkan ke tujuh operation keys §8, injecting scope dan exact approved binding. Dilarang mengirim group_by=vendor ke vendor.

**Serializer browser canonical:**
- Tidak expose schema_version vendor, raw upstream URL/auth/POD. Base meta: request_id,state,generated_at,scope(role,ut_code,ut_name,is_all_regions,program_codes), filter_signature. Label UT/prodi/vendor dari master; row menambah vendor_code/vendor_name dan derived status, retry_attempt.
- List/matrix/events: data array; meta.pagination(limit,offset,total_filtered,has_more,next_offset), selection_vendor_codes, count_accuracy, sources array(vendor_code,vendor_name,state,fetched_at,error_code). source list lengkap untuk selection<=10; events/detail hanya1. List gagal count/data tidak memakai summary partial fallback: §10.1 tetap fail-closed. Matrix boleh partial cells unavailable. Source revision terikat snapshot_id pada meta sesuai paginator §10.1; lanjut page wajib kirim snapshot_id agar selection/count stabil. Complete empty page has_more=false,next_offset=null. Jika offset>=total, return[] dengan total asli.
- Ungrouped summary: data object berisi totals_all_vendors nullable, totals_selected_vendors nullable, totals_available (InventorySummary atau SummaryRow), by_vendor array. Entry by_vendor={vendor_code,vendor_name,summary}, synthetic __others__ memakai bentuk sama. All totals hanya jika complete+all; selected total hanya jika complete+selected. Summary meta sesuai §10.5 plus sources_url; tidak menambah sources=[] palsu. No pagination pada ungrouped summary.
- Grouped summary: data array berisi group_code,group_name dan empat field aggregate SAMA seperti ungrouped summary. meta.pagination berdasarkan master groups, meta coverage/source metadata aggregated per-vendor page sesuai §10.3. Partial subtotal group tidak boleh dilabel complete summary. Grouped summary hanya untuk breakdown; cards global tetap ungrouped.
- Source status endpoint: data array of sources, meta.request_id,run_id,pagination; required total_filtered sesuai seluruh metadata source run (eligible+excluded). Data bukan transaksi.
- Options: data array `code,name` (ut juga `type`), meta.pagination dan scope; program/vendor/catalog fields tambahan didokumentasi OpenAPI. Unknown/inactive requested UT ditolak. Header fallback display Nama belum terdaftar dilarang untuk foreign keys invalid.
- Detail: data OrderRow+detail fields+derived display; replace proof_of_delivery_url dengan proof_url browser private proxy (null bila absent); updated_at wajib ikut. Response detail meta base+single-source status. Events use list format; proof returns binary MIME, bukan JSON.
- Browser HTTP failure memakai Problem §8.8. Pending aggregate202 adalah state processing,data=null, bukan Problem. Partial200 memiliki total utama null dan subtotal tersedia berlabel jelas. Semua serializers ditest terhadap fixtures lampiran; tidak menyalin field admin-only registry counts ke user response.

Vendor mutations: POST registration, POST connections, POST connection revisions, PUT draft binding, POST binding tests, GET test-run status/report, POST binding submissions. Ownership check for every object. Server generates IDs/revision/status/approved_by; reject mass-assignment attempts. Admin: POST approve/reject vendor, POST approve/reject binding, POST publish contract, PUT user assignments, POST master imports, POST suspend. Routes explicit named controllers, no generic execute endpoint.

## 12. Data model aplikasi baru

All tables timestamps UTC(6) where useful; bigint internal IDs; unique constraints/FKs explicit. Secrets TEXT ciphertext, **bukan tipe JSON** karena ciphertext bukan JSON queryable. Public IDs ULID where enumeration undesirable; still policy checks. No transactions/stock table storing all vendor records.

| Table | Required columns/constraints |
|---|---|
| users | id,name,email unique,password,email_verified_at,status,role,ut_id nullable,vendor_id nullable,permission_revision; cross-field role validation |
| vendors | id,code unique,legal_name,contact_name,contact_email,status,approved_by/at,rejection_note,scope_revision |
| ut_regions | id,code unique,name,type daerah/luar_negeri,is_active; production gate 39 daerah + 1 luar_negeri aktif, tanpa pusat |
| programs | id,code unique,name,is_active |
| tutor_program | user_id,program_id unique pair |
| vendor_ut_scope | vendor_id,ut_id unique pair; explicit approved scope |
| catalog_items | id,catalog_key unique,item_type,item_code,edition,title,is_active; unique type/code/edition |
| contracts | id,operation_key unique,module,label,description |
| contract_versions | id,contract_id,version,schema_json,request_schema_json,status,suite_version,published_at,retire_at; unique contract/version |
| connections | id,vendor_id,label |
| connection_revisions | id,connection_id,revision,base_url,auth_type,auth_config_ciphertext,secret_version,created_by; immutable |
| endpoint_bindings | id,vendor_id,contract_id,active_revision_id nullable,draft_revision_id nullable,is_enabled; unique vendor/contract |
| binding_revisions | id,binding_id,revision,connection_revision_id,contract_version_id,path,static_query_json,dispatch_mode,scope_revision,status; immutable after test start; edit clones |
| endpoint_test_runs | id,binding_revision_id,connection_revision_id,contract_version_id,suite_version,status,report_json,started_at,finished_at,expires_at,created_by |
| submissions | id,binding_revision_id,test_run_id,status,reviewed_by,reviewed_at,note; immutable report link |
| api_request_metrics | id,vendor_id,binding_revision_id,operation_key,request_id,http_status,duration_ms,bytes,error_code,created_at; NO body/secret/search value |
| audit_logs | id,actor_id,action,subject_type/id,changed_field_names_json,old/new revision IDs,request_id,created_at; NO raw credential diff |
| jobs/failed_jobs | Laravel DB queue; only references in payload, sanitized exceptions |

Do not create users.vendor_id AND vendors.user_id circular ownership. A vendor may have multiple contact users later; one initial owner now. Endpoint bindings and revisions FKs need two-step migrations for circular pointer references; verify active pointer belongs to same binding/vendor in transaction. Contract/scope changes must not leave references pointing to foreign tenant revisions.

Indexes: test_runs(binding_revision_id,finished_at), metrics(vendor_id,created_at), metrics(error_code,created_at), audits(subject_type,subject_id,created_at), bindings(vendor_id,is_enabled), submissions(status,created_at), catalog(item_type,item_code,edition), user(email). Add only measured query indexes; report bodies not fully indexed. Pagination admin tables server-side and select columns explicitly.

Deletion: deactivate/soft-delete vendor; preserve audit; purge secrets via explicit revoke workflow with retention rules. No cascaded erasure of audit on user/vendor delete. Endpoint report max64KB sanitized; raw error snippets prohibited.

## 13. Component boundaries dan struktur kode

Minimal modular monolith, bukan repository/interface untuk setiap model:

- Controllers: Auth, VendorRegistration, VendorConnection, VendorBinding, TestRun, AdminVendorReview, AdminBindingReview, Contract, MasterData, Monitoring pages, MonitoringData.
- FormRequests: registration, connection config per auth mode, filters, binding draft, approval, master import.
- Policies: Vendor, Connection, Binding, TestRun, MonitoringAccess, ProofAccess.
- Services/Access/ScopeResolver: only source of allowed scope.
- Services/Gateway/SafeHttpClient: URL/DNS/TLS/size/time budgets; dipakai ALL outgoing calls termasuk auth/health/test/POD.
- Services/Gateway/AuthResolver + small strategy classes untuk supported modes.
- Services/Contracts/ResponseValidator: Opis + semantic invariants, standardized errors, no schema fetching.
- Services/Gateway/VendorGateway: execute one approved binding.
- Services/Monitoring/ListPaginator, SummaryAggregator, MatrixService: algorithms §10, no auth logic duplicate.
- Services/Onboarding/TestRunner + ApprovalService: snapshot/report/activation transaction.
- Jobs/RunEndpointTest, Jobs/CheckEndpointHealth; commands PruneOperationalData, RotateIntegrationKey (documented/tested).
- DTOs scoped validated request/response; resources serializers mask PII and strip extra keys.
- resources/views/layouts, pages/monitoring, admin, vendor, components; resources/js/modules split tables/charts/modal/forms.
- tests/Unit/Contracts, Pagination, Aggregates, Security; tests/Feature/Access, VendorOnboarding; tests/Integration/Gateway; tests/e2e.

Do not reuse DynamicPageController as-is: it includes dynamic template runtime and weak aggregation assumptions. Legacy auth URL builder/test service are reference only; independently test security before porting any logic. No credentials or production payloads copied into fixtures.

## 14. Security dan kepercayaan vendor

Target engineering: OWASP ASVS level2 controls relevant to scope, OWASP API security threat model, privacy/data minimization; ini **bukan sertifikasi**. Vendor diberi data-flow/privacy statement, retention, contact incident, dan daftar processor. UU PDP/kontrak UT memerlukan legal review pemilik data; jangan mengklaim compliance penuh hanya dari encryption.

### 14.1 Threats dan controls

- Vendor tries SSRF/internal metadata → public HTTPS allowlist, resolve all IPv4/IPv6, block private/loopback/linklocal/reserved/mapped IPv4, DNS pin to validated address preserving SNI/Host, redirects disabled, network egress restriction. Registration approval memverifikasi kepemilikan domain lewat challenge atau kontak resmi. Token/POD URL juga tunduk.
- Vendor A opens vendor B report/secret → object policies on every route, tests guessed IDs, no trust client vendor_id.
- Kepala daerah requests national summary or cross-scope cache → ScopeResolver + scope echo + output check + scoped cache namespace.
- Malformed vendor payload → strict size/depth/types, plain text output, no HTML injection, schema only standard keywords.
- Test spam/retry storm → limiter/concurrency/deadline/circuit.
- Stale pass approval/race → immutable revisions, row locks, TTL at submit+approve, auto retest.
- Credential leak → write-only UI, dedicated encryption key, redaction by default deny logs, no plaintext export/cURL.

### 14.2 Encryption and authentication configuration

Use dedicated Laravel Encrypter instance for integration secrets with AES-256-GCM **explicitly configured and tested**, random32-byte key from secret manager/environment `PARAMITA_INTEGRATION_KEY`; ciphertext TEXT + key version. Do not assume standard encrypted cast switches to this key. APP_KEY remains framework key for cookies/etc; its default Laravel12 cipher AES-256-CBC is authenticated via framework MAC, not inherently insecure. Do not implement custom crypto primitives.

Decrypt only in backend memory during request; user/admin UI never reveals value. IMPORTANT: a compromised server/operator with key access can decrypt; no promise "tidak pernah bisa dibaca manusia". Least privilege, OS/DB/secret separation, audits, backups protect this residual risk.

Rotation: create new key version, encrypt new writes with it, batch re-encrypt old ciphertext in transaction, verify decrypt sample/count, preserve old key for rollback/backups retention, revoke obsolete key after retention. Never blindly run key:generate on production.

Use HASH_DRIVER=argon2id explicitly after PHP support check; tune cost to hardware. Laravel default is bcrypt, not Argon2id. Strong passwords >=12 chars, allow spaces/password manager, generic login/reset errors, login throttle5/min per account+IP; registration3/hour/IP with abuse review. MFA mandatory admin before production using maintained TOTP implementation with recovery codes hashed; optional vendor MFA. Never roll own TOTP crypto.

Cookie Secure,HttpOnly,SameSite=Lax, CSRF all mutations, session regenerate on login/privilege change, session idle30min, absolute12h, revoke sessions on suspend/password reset. Same-origin CORS only. CSP self scripts/styles via assets/nonces, frame-ancestors none, nosniff, Referrer-Policy no-referrer, HSTS only after HTTPS validated; APP_DEBUG=false production.

### 14.3 Data inventory/retention (defaults)

| Data | Storage | Retention/access |
|---|---|---|
| Vendor contacts, user assignments | MySQL | While active; deactivate and privacy-reviewed purge; internal admin only |
| Integration credential | Encrypted MySQL TEXT | Until revoked/replaced; key separated; never UI readback |
| OAuth access token | Encrypted Redis | expires_in minus margin; no persistence/backups of token cache |
| DO rows/details/events/POD | In-memory request/stream | Not stored in DB/log/Redis by default; browser private no-store |
| Summary/stock response | Encrypted Redis | Fresh30s; hard TTL300s; contains scoped business data, disclosed as cache |
| Pagination/count context | Redis | <=5min; no names/addresses, HMAC filter digest |
| Aggregate query descriptor/contribution | Encrypted Redis | <=300s; descriptor boleh mengandung search personal, tidak di-log/back-up; dedicated application encryption key |
| Aggregate run/job metadata | MySQL |24h lalu prune; hanya IDs/hash/counters/state, no body/query/secret |
| Test report/HTTP metrics | MySQL sanitized |90days, scheduled prune; submitted report retained1year as evidence |
| Audit | MySQL |1year default, controlled legal retention; no raw values |
| DB backup | Encrypted private backup |30days default; separate key storage; restore drill required |

Disable Redis RDB/AOF for ephemeral cache/token instance; keyspace private/nonpublic with auth/TLS where transport crosses trust boundary. DB queue durable separately. No debug raw response capture in MVP, including Telescope/APM access logging. Web/proxy logs must omit query strings containing search/token and strip authorization; production exceptions sanitized. Backup deletion lags active deletion up to retention and must be disclosed.

### 14.4 Boundary limits

- JSON response max2MB decoded/decompressed, max depth32, timeout budget; gzip bomb protections check bytes while streaming.
- JSON duplicate keys: reject if parser/preparser supports; otherwise document parser behavior and add duplicate-key rejection before schema as release requirement. Never allow conflicting duplicate keys to cross validation/render interpretations.
- URL max2048, allowed ports443 only by default; additional public port requires operator-reviewed allowlist, not vendor free input.
- No private-network vendor connector in MVP production. Existing intranet vendor needs explicit network architecture/security exception, not disabled SSRF.
- No remote `$ref` resolution. Schema size max128KB, regex patterns reviewed/limited, format validators bounded. No nonstandard Opis function execution.

## 15. Operasional dan performance targets

Targets adalah acceptance benchmark, bukan hasil pengujian PRD:
- Warm summary API p95<=500ms, page shell p95<=300ms di staging baseline.
- Cold list/summary p95<=3s dengan5 mock vendors latency<=300ms,errors0; request deadline8s. Untuk async multi-vendor,ukur waktu acknowledgment202<=500ms dan separately time-to-complete/deadline120s, bukan mengklaim aggregate selesai500ms. Jalankan workload12+vendor dan error/stale tests §10.5 juga.
- Search update normal<=1s warm/healthy; debounce350ms termasuk UX.
- 50 concurrent authenticated viewers, selected vendors5, list size25, test duration10min; report latency/error/memory/outgoing QPS. Baseline minimum4vCPU/8GB untuk full stack staging (bukan jaminan production sizing).
- Dataset benchmark satu juta synthetic DO per mock vendor untuk membuktikan indexed pagination/aggregate; jangan generate jutaan JSON di browser. Vendor DB index examples (ut_code,program_code,id), (ut_code,ordered_at,id), (ut_code,process_status_code,id); EXPLAIN di vendor fixture, bukan index rekomendasi buta untuk seluruh kombinasi.
- Enforce count-only vendor response SLA; jika count expensive, vendor harus maintain aggregate/index atau ubah architecture sebelum go-live.

Health check setiap5min, stagger per vendor, no full-data query, use limit0 atau minimal non-PII summary; max one inflight per binding. Health state healthy/degraded/unavailable/auth_failed/schema_failed, last_success/error_code/latency. Notify admin+vendor perubahan status saja, cooldown, recovery notice; jangan spam setiap cron.

Monitoring: request IDs, per-vendor timeout/error rate, worker backlog, failed jobs, stale sources, cache hit, circuit state, CPU/memory; no data content. Readiness checks app/DB/queue prerequisites; vendor outage jangan menjadikan seluruh app unhealthy restart-loop.

Backup/rollback: control DB daily encrypted, test restore to isolated DB; RPO24h/RTO4h initial targets subject measured drill. Release versioned app directory/image, migrations additive/backward compatible, worker graceful restart. Rollback code+active binding pointer separately; tidak rollback DB destruktif otomatis. Production databases/Redis ports private. Composer/npm audit and dependency updates monthly/security advisories urgent.

## 16. API/documentation deliverables saat implementasi

`PRD.md` versi2.1 adalah satu-satunya sumber keputusan normatif. `docs/default-json-and-slicing.md` versi2.1 adalah lampiran contoh lengkap dan mapping visual, bukan kontrak saingan; wajib dibaca untuk fixtures/slicing. Jika ditemukan konflik, hentikan bagian terkait dan perbaiki lampiran terhadap PRD sebelum coding; jangan memilih aturan yang terasa lebih spesifik. Route hanya dari tabel §11.

Fase kontrak WAJIB menghasilkan:
1. `docs/openapi-vendor-v1.yaml`: operasi tujuh di atas (separate reference paths), query/response/error/auth schemes, descriptive Indonesia; dispatch mode dijelaskan alternatif URL bukan schema kedua.
2. `contracts/v1/*.schema.json`: seluruh models/envelopes sesuai §7–8 dan `docs/default-json-and-slicing.md`, meta and semantic test rules.
3. `docs/openapi-browser-v1.yaml`: hanya route canonical §11; tidak membuat alias route per halaman dari versi lampiran lama.
4. `tests/Fixtures/contracts/`: valid, invalid, empty, nullable, unicode, cross-scope, deep-pagination, duplicate ID, malformed date, oversize, partial, serta default examples dari `docs/default-json-and-slicing.md`.
5. `docs/vendor-quickstart.md`: panduan wizard + contoh REST PHP sederhana, pagination SQL parameterized di mock vendor, never real credentials.
6. `docs/security-and-data-handling.md`: isi §14 dalam bahasa vendor tanpa klaim absolut.
7. Postman collection optional generated dari OpenAPI; semua auth placeholder/environment variables; tidak export credential.

Contoh JSON harus berisi page lengkap, source coverage sesuai daftar sumber, timestamp kronologis, serta arithmetic summary benar. Tidak ada contoh terpotong yang dijadikan golden fixture. Jalankan `python tools/verify_prd.py` untuk pemeriksaan dokumen yang tersedia; kemudian tambah schema validation Opis dan integration tests pada F0. Schema/generated docs dan UI field dictionary must not drift: CI validate examples, resolve local refs, assert operations list and required model fields. OpenAPI spec file harus parse/validate, bukan sekadar diberi judul OpenAPI. PRD ini tetap sumber perilaku; fixture bukan pengganti requirement.

## 17. Test matrix wajib (release gates)

| ID | Skenario | Expected |
|---|---|---|
| AUTH-01 | vendor pending login | tidak masuk panel |
| AUTH-02 | vendor approved belum endpoint | wizard, tidak ada data internal |
| ACL-01 | Tutor tanpa assignment |403/no vendor call |
| ACL-02 | kepala daerah A request B via query/detail/POD |403/404 sesuai endpoint, no leak |
| ACL-03 | vendor A inspect revision/report B |404/no data |
| ACL-04 | warm cache nasional lalu daerah/Tutor | tidak ada national/PII leak |
| ACL-05 | assignment/vendor dicabut saat session aktif | request berikutnya denied/cache invalid |
| ACL-06 | import 39 daerah + 1 luar_negeri, assign user tiap unit | total unit40, pusat tidak dihitung; kepala unit luar negeri juga terisolasi |
| API-03 | status code01..07,06 RETURN,07 DELIVERED |06 hanya returned;03/04/05 bucket retry; unknown code fail |
| CFG-01 | update draft credential aktif | active lama tetap dipakai |
| CFG-02 | pass lalu edit URL/secret/schema | submit blocked |
| CFG-03 | concurrent approve/edit | hanya exact approved snapshot aktif |
| CFG-04 | report expired saat approve | auto-retest; admin tidak manual test |
| CFG-05 | schema publish incompatible UI |blocked |
| TEST-01 | vendor fail→edit→retest pass→submit→approve | end-to-end tanpa admin testing manual |
| TEST-02 | empty-only fixture |incomplete bukan passed |
| TEST-03 | spam test/duplicate job/worker crash |429/idempotent run/timed_out |
| API-01 | semua valid+invalid schema per operasi | valid pass, invalid fail by path |
| API-02 | missing/null/date-time/string quantity/extra keys | sesuai dictionary, extra stripped |
| PAGE-01 | counts A3 B4 offset2 limit3 | A terakhir+B pertama kedua |
| PAGE-02 | vendor count berubah/failed | bounded retry→409 atau503, no silent shift |
| PAGE-03 | offset>10000/cursor filter mismatch |422/409; no massive fetch |
| MAT-01 | buku beda edisi/null stock/vendor outage | key benar, null!=0!=unavailable |
| SUM-01 | unequal SLA sample counts | weighted average benar |
| SUM-02 | same DO number dua vendor |2 fulfillment dengan label jelas |
| SUM-03 |12+vendors all; top9+others | semua eligible tercakup; sum chart=total, bukan subset10 |
| SUM-04 |timeout satu sumber, fresh/stale/expired cache | §10.5 complete-stale vs partial-null vs503 benar |
| SUM-05 | duplicate job, deadline, suspend/revision race | idempotent/no double-count/no late publish |
| SUM-06 | search/date/program/scope/query changes | signature isolated; UI tidak melabel angka lama filter baru |
| SUM-07 |40unit group upstream cap25 | batch25+15,40group pusat,1group daerah/luar negeri |
| DOC-01 |route: namespace tunggal, seven slots, fixtures | no group_by vendor upstream; JSON semantics+schema pass |
| UI-03 |HTML source table/header manifest vs Blade render | setiap kolom/modal/tab ada; visual desktop/mobile comparison recorded |
| SEC-01 | loopback/IPv6/mapped/private/metadata/DNS rebind/redirect | blocked, termasuk auth/POD/test |
| SEC-02 | injection HTML/header CRLF/SQL search | escaped/rejected/parameterized |
| SEC-03 | simulated secret/PII in upstream error | tidak ada di log/report/browser |
| SEC-04 | response gzip bomb/depth/huge/error body | bounded fail |
| SEC-05 | every supported auth success/failure/expiry | tested; no default pass |
| UI-01 | empat halaman desktop/mobile keyboard | tabel, chart, filter, detail, empty/stale/error berfungsi |
| UI-02 | slow search response arrives last | tidak menimpa query baru |
| OPS-01 | cache down/vendor down/queue down | honest state, no fabricated data/restart storm |
| OPS-02 | DB restore/key rotation/rollback drill | evidence documented |
| PERF-01 | workload §15 | measurements + no full scans/materialization outside budgets |

Approval security: tests tidak hanya menggunakan Http::fake yang melewati SafeHttpClient. Integration gunakan real local mock process isolated testing network untuk URL/DNS/TLS/timeout behavior; production allowlist tetap tidak dimodifikasi demi tes.

## 18. Keputusan bisnis default dan prerequisite go-live

**Default final untuk implementasi (tidak perlu ditanya ulang):**
- Blade custom, Laravel/MySQL/Redis; fixed pages.
- 4 role internal + vendor; Tutor least privilege scoped seperti §3.
- Vendor unrestricted registry; selected fan-out bounded10.
- Standard plain JSON envelope, bukan JSON:API resource wrapping.
- Stock status berdasarkan required_quantity, unknown bila belum ada.
- Live gateway; bounded vendor-first list, bukan global chronological search engine.
- Admin approval endpoint berdasarkan automated report; optional spot-check bukan kewajiban.
- Tidak migrasi/drop legacy saat membangun proyek baru.

**Prerequisite produksi yang tidak bisa diisi model dengan tebakan:**
1. Master resmi 39 daerah + 1 luar negeri (40 unit layanan), prodi, katalog dan scope vendor; buat import UI dan fixture sintetis, jangan mengarang kode resmi. Jumlah/komposisi final sudah diputuskan; yang menunggu hanya daftar resminya.
2. Domain vendor/auth dokumentasi + sandbox fixtures + token read-only dari vendor melalui secure form.
3. Persetujuan pemilik atas default hak Tutor sebelum pemberian data mahasiswa; default tetap masked sampai disetujui.
4. SLA target/required stock quantities otoritatif; null/unknown dibolehkan, tidak mengarang angka.
5. Domain/app hosting/TLS/email delivery/secret management serta security support framework saat deploy.
6. Kontrak privacy/retention/legal PDP review, operasional incident contact.

Semua poin ini adalah production gates, bukan alasan menunda implementasi fondasi dengan fixtures sintetis.

## 19. Urutan implementasi tanpa bolak-balik

### F0 — Bootstrap, kontrak dan proof arsitektur
- Buat Laravel baru di root ini, DB khusus `paramita_final`, .env.example placeholder, lockfiles, CI skeleton. Jangan copy vendor/node_modules/env legacy.
- Generate schemas/OpenAPI/fixtures dari §7–8; Opis positive/negative tests, format assertion tests, no remote refs.
- Implement pure tests paginator/aggregate/matrix dengan fixture A/B dan mock vendors; buktikan algorithm sebelum UI. Implement AggregateCoordinator metadata/jobs/locks/clock-controlled publication tests sesuai §10.5 sebelum wiring cards. Seven published default templates seeded idempotently, bukan form kosong yang meminta admin merancang ulang.
- Exit: contract fixtures validate, PAGE/MAT/SUM tests pass, dependency compatibility recorded. Jika live performance invalid pada mock baseline, laporkan, jangan beralih stack diam-diam.

### F1 — Auth, master, scope dan SafeHttpClient
- Role/permissions, session, admin MFA, registration/email approval, master import preview, Tutor/vendor scope.
- Dedicated crypto + auth strategies + safe URL/DNS boundary + redaction; tests security/ACL before live calls.
- Exit: AUTH/ACL/SEC core passed; no vendor secrets in view/log; production data master may still pending.

### F2 — Vendor wizard, versioned configuration, test reports
- Connection/binding revisions, all supported auth modes, asynchronous test loop, report, submit, admin approval/auto-retest.
- Exit: CFG/TEST and supported-auth matrix passed using local mock; vendor flow usable without admin manual testing.

### F3 — Gateway and monitoring endpoints
- Scope-safe per-operation calls, count/data pagination, summary/matrix, circuit/cache/deadline/partial semantics, scoped detail/events/POD.
- Exit: §17 API/PAGE/MAT/SUM/SEC behavior tests pass and request budgets measured.

### F4 — Four UI pages
- Port design into shared Blade components; Bootstrap and Chart.js single bundles; charts/table/error/empty states bound real gateway endpoints.
- Exit: all four pages UI/E2E desktop/mobile, three roles data scoping + admin + vendor verified; no dummy links/data.

### F5 — Release hardening and acceptance
- Full tests/lint/build/audit, performance staging, ops runbooks, backup restore and rotation, vendor quickstart reviewed by someone not building it.
- Exit: evidence for all gates, production prerequisites checklist explicit. If live vendor inaccessible, mark live contract acceptance blocked; mock success cannot replace it.

At end of EACH phase update `IMPLEMENTATION_STATUS.md`: version PRD, phase, completed acceptance IDs, commands+actual results, files touched, remaining blockers, next phase. Do not restart completed phase in next session unless tests/spec changed. No feature beyond scope before F5.

Developer final deliverable: working app, tests, docs, reproducible run/build commands, sanitized verification report. "Done" means all named requirements verified or explicitly blocked; code scaffold/screenshots alone do not qualify.

## 20. Sumber referensi dan status review dokumen

Dibaca: composer.json legacy (Laravel12/Filament3.3/Spatie6), PRD lama, AGENTS/README legacy dari sesi, field headings/tables empat HTML starter. UI ekstraksi memastikan do-per-prodi juga memiliki status pengiriman/SLA; stock matrix Judul juga diperlukan. Tidak ada benchmark legacy sehingga tidak menyimpulkan framework penyebab lambat.

Rujukan implementasi resmi:
- https://opis.io/json-schema/2.x/ — dokumentasi dukungan draft2020-12.
- https://laravel.com/docs/12.x/encryption dan https://github.com/laravel/laravel/blob/12.x/config/app.php — cipher/key configuration.
- https://laravel.com/docs/12.x/hashing dan https://github.com/laravel/framework/blob/12.x/config/hashing.php — bcrypt default, Argon2id pilihan eksplisit.
- https://spec.openapis.org/oas/v3.1.1.html — OpenAPI 3.1.
- https://www.rfc-editor.org/rfc/rfc8259 — JSON.
- https://www.rfc-editor.org/rfc/rfc3339 — timestamps.
- https://www.rfc-editor.org/rfc/rfc9457 — Problem Details.
- https://owasp.org/www-project-application-security-verification-standard/ — security verification framework.
- https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html — SSRF threat controls.

Verifikasi dokumen dilakukan terpisah dari aplikasi. PRD ini belum merupakan OpenAPI file, belum menjalankan vendor API, dan tidak membuktikan latency/security produksi. Implementor wajib membuat serta menguji seluruh artefak turunannya sebelum mengklaim compliance/siap deploy.
