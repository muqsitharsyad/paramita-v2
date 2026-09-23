# Paramita Final

Portal Laravel untuk onboarding API vendor, validasi kontrak, monitoring stok, dan monitoring Delivery Order Universitas Terbuka.

---

## Daftar Isi

1. [Gambaran](#gambaran)
2. [Persyaratan](#persyaratan)
3. [Instalasi manual](#instalasi-manual)
4. [Akun login](#akun-login)
5. [Microsoft SSO](#microsoft-sso)
6. [Kredensial vendor API](#kredensial-vendor-api)
7. [Menjalankan aplikasi](#menjalankan-aplikasi)
8. [Alur penggunaan](#alur-penggunaan)
9. [Deployment production](#deployment-production)
10. [Verifikasi](#verifikasi)
11. [Troubleshooting](#troubleshooting)
12. [Catatan penting](#catatan-penting)

---

## Gambaran

Paramita menghubungkan vendor logistik (percetakan/pengiriman) dengan Universitas Terbuka. Admin mengelola kontrak JSON yang wajib diikuti vendor; vendor mengisi koneksi API, menguji response-nya, lalu mengajukan approval. Hanya payload yang lolos validasi yang masuk ke dashboard, tabel, peta, KPI, dan agregasi monitoring.

Delapan operasi vendor aktif:

```text
inventory.list       inventory.lookup      inventory.summary
orders.list          orders.detail         orders.events
orders.summary       orders.analytics
```

## Persyaratan

- PHP 8.3+ dengan ekstensi `pdo_mysql`, OpenSSL, cURL, Mbstring, dan fileinfo
- Composer 2
- Node.js 22+ dan npm
- MySQL 8

## Instalasi manual

### 1. Clone dan dependency

```bash
git clone <repository-url> paramita-final
cd paramita-final
composer install
npm ci
cp .env.example .env
php artisan key:generate
npm run build
```

Pada Windows Command Prompt gunakan `copy .env.example .env`; pada PowerShell gunakan `Copy-Item .env.example .env`.

### 2. Database

Buat database dan user MySQL khusus Paramita, lalu isi konfigurasi `DB_*` di `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paramita_final
DB_USERNAME=paramita_app
DB_PASSWORD=
```

Jangan memakai akun root untuk runtime production.

### 3. Kunci enkripsi integrasi

Buat kunci 32-byte dan simpan sebagai `PARAMITA_INTEGRATION_KEY` di `.env`:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

> **Penting:** kunci ini mengenkripsi credential vendor. Jangan mengubahnya setelah credential tersimpan — rotasi kunci memerlukan re-enkripsi terencana.

Jika PHP/cURL memakai CA bundle nonstandar, isi `PARAMITA_CA_BUNDLE` dengan path file CA yang valid. Jangan menonaktifkan verifikasi TLS.

### 4. Migration dan seed

```bash
php artisan migrate --seed
php artisan storage:link
```

Seeder membuat:

| Data | Isi |
|---|---|
| Role | `admin`, `kepala_ut_pusat`, `kepala_ut_daerah`, `tutor`, `vendor` |
| Master UT | 40 unit resmi (`UN31.UT1`–`UN31.UT40`) |
| Program studi & katalog | 8 program, 10 katalog |
| Kontrak & template | 8 kontrak + 8 template JSON aktif |
| Vendor | 3 vendor Prodev (`GRAMEDIA`, `TEMPRINA`, `MACANAN`) |
| Endpoint binding | 24 (3 vendor × 8 operasi) |
| Akun demo | 7 akun lokal (lihat [Akun login](#akun-login)) |

## Akun login

### Akun demo (development lokal)

`migrate --seed` otomatis membuat 7 akun demo — satu untuk tiap role internal plus satu per vendor. Semua memakai **satu password acak** yang ditulis ke `storage/app/local-demo-users.txt`:

```text
admin@paramita.test              → Administrator
kepala.pusat@paramita.test       → Kepala UT Pusat
kepala.daerah@paramita.test      → Kepala UT Daerah (UT Bandung)
tutor@paramita.test              → Tutor (Manajemen + Ilmu Administrasi Negara)
vendor.gramedia@paramita.test    → Vendor GRAMEDIA
vendor.temprina@paramita.test    → Vendor TEMPRINA
vendor.macanan@paramita.test     → Vendor MACANAN
```

Baca password dari `storage/app/local-demo-users.txt` (file ini di-`.gitignore` dan tidak dikomit). Password berubah setiap kali seeder dijalankan ulang.

> **Catatan:** akun demo ini **hanya dibuat di environment non-production**. Seeder `LocalDemoUserSeeder` skip diam-diam saat `APP_ENV=production`.

### Admin pertama (production)

Tidak ada akun bawaan di production. Buat admin pertama secara interaktif:

```bash
php artisan paramita:create-admin
```

Command meminta nama, email, password (minimal 12 karakter), dan konfirmasi. Password tidak pernah ditulis ke source atau output.

Login hanya berhasil bila akun: berstatus `active`, email terverifikasi, memiliki role internal valid (atau vendor yang `approved`).

### Manajemen user

Setelah login sebagai admin, buka **Administrasi → Manajemen User** (`/admin/users`) untuk membuat/mengubah user internal, role, scope UT/program tutor, dan password. Akun vendor dikelola lewat onboarding (`/register`), bukan dari menu ini.

## Microsoft SSO

Microsoft SSO bersifat opsional dan hanya berlaku untuk akun internal yang sudah ada. Sistem tidak membuat user baru dari Microsoft dan tidak mengizinkan akun vendor masuk melalui SSO.

### 1. Daftarkan aplikasi di Microsoft Entra ID

Buat App Registration untuk tenant Universitas Terbuka dan tambahkan Web Redirect URI berikut:

```text
https://domain-paramita.example/auth/microsoft/callback
```

Untuk instalasi di subpath, masukkan seluruh prefix aplikasi, misalnya:

```text
https://domain.example/paramita-final/auth/microsoft/callback
```

Gunakan tenant ID spesifik organisasi, bukan `common`, agar login dibatasi ke tenant yang benar. Permission minimum yang digunakan adalah OpenID Connect (`openid`, `profile`) dan Microsoft Graph `User.Read`.

### 2. Isi environment

```dotenv
MICROSOFT_SSO_ENABLED=true
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT_ID=
MICROSOFT_REDIRECT_URI="https://domain-paramita.example/auth/microsoft/callback"
```

Setelah mengubah `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
```

Akun internal dapat login bila email Microsoft sama dengan email user Paramita, user berstatus `active`, email sudah terverifikasi, dan role termasuk `admin`, `kepala_ut_pusat`, `kepala_ut_daerah`, atau `tutor`. Login pertama mengikat akun Paramita ke ID Microsoft. ID Microsoft yang berbeda tidak dapat mengambil alih email yang sudah terikat.

## Kredensial vendor API

Agar dashboard terisi data nyata, koneksi vendor memerlukan credential service account API vendor. Isi di `.env`:

```dotenv
PARAMITA_VENDOR_API_USERNAME=
PARAMITA_VENDOR_API_PASSWORD=
```

Setelah diisi, jalankan ulang seed koneksi:

```bash
php artisan db:seed --class=ProdevVendorSeeder
```

Tanpa credential ini, vendor tetap ter-seed tapi berstatus `auth_type=none` dan data dashboard kosong.

## Menjalankan aplikasi

Tiga terminal terpisah:

```bash
# Terminal 1 — server
php artisan serve

# Terminal 2 — hot reload frontend
npm run dev

# Terminal 3 — queue worker
php artisan queue:work --tries=3 --timeout=120
```

Buka `http://127.0.0.1:8000/login`.

## Alur penggunaan

### Onboarding vendor

1. Vendor mendaftar dan memverifikasi email.
2. Admin menyetujui identitas vendor.
3. Vendor membuat satu koneksi API.
4. Vendor mengisi endpoint untuk delapan operasi.
5. Vendor menjalankan **Test** terhadap response live.
6. Vendor submit revision yang lulus.
7. Admin review dan approve.
8. Hanya payload tervalidasi yang masuk dashboard, tabel, peta, KPI, dan agregasi.

Approved revision bersifat immutable. Perubahan URL/auth/path lewat draft baru → test → submit → approve. Satu vendor hanya punya satu koneksi aktif.

### Monitoring internal

- **Dashboard** — ringkasan stok dan Delivery Order.
- **Monitoring Delivery** — daftar dan detail DO.
- **DO per Program Studi / UT Daerah** — distribusi sesuai scope user.
- **Analisis SLA** — performa SLA dan drill-down DO.
- **Monitoring Retry** — alasan retry per vendor/ekspedisi.
- **Distribution Map** — snapshot distribusi 30 hari.

Payload vendor yang invalid dikeluarkan seluruhnya dari perhitungan. Array valid kosong tetap dibedakan dari response invalid/unavailable.

## Deployment production

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Wajib: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, database backup, dan queue worker yang dikelola Supervisor atau systemd.

Document root web server harus mengarah ke `public/`. Jangan expose `.env`, database, admin database tool, atau port MySQL secara publik.

Sebelum update: backup database; backup `.env` tanpa memasukkannya ke repo; catat release sebelumnya; jalankan migration; verifikasi `/login`, asset build, queue worker, dan health aplikasi tetangga; siapkan rollback source + restore database bila migration destruktif.

## Verifikasi

```bash
php artisan test --no-coverage
npm run build
php vendor/bin/pint --test
php artisan view:cache
php artisan route:list
```

## Troubleshooting

### Semua user gagal login

```bash
php artisan migrate:status
php artisan paramita:create-admin
php artisan optimize:clear
```

Pastikan status user `active`, `email_verified_at` terisi, role valid, dan (untuk vendor) vendor berstatus `approved`. Jangan mengubah password langsung dengan plaintext SQL.

### Dashboard kosong / data vendor tidak muncul

Cek credential vendor API sudah diisi di `.env` (lihat [Kredensial vendor API](#kredensial-vendor-api)), lalu:

```bash
php artisan db:seed --class=ProdevVendorSeeder
```

### Perubahan `.env` tidak terbaca

```bash
php artisan optimize:clear
php artisan config:cache
```

Restart PHP-FPM atau web server dan queue worker setelah perubahan environment.

### Response vendor ditolak

1. Buka detail hasil Test vendor.
2. Perbaiki hanya path/type yang dilaporkan.
3. Bandingkan response dengan template aktif di portal vendor.
4. Pastikan timestamp RFC 3339 mempunyai timezone.
5. Ulangi Test dan submit revision baru.

Jangan gunakan fixture sebagai fallback production dan jangan melemahkan TLS/validator agar data terlihat masuk.

### Queue tidak memproses job

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:restart
```

## Catatan penting

- `contracts/v1/*.schema.json` adalah arsip/referensi historis dan test lama; tidak dibaca runtime.
- `docs/fixtures/vendor/` dipakai test; bukan fallback produksi.
- Production tidak memakai fixture sebagai fallback data vendor.
- Approved revision tetap immutable; perubahan endpoint memakai draft → test → submit → approval.
- Satu vendor hanya memiliki satu koneksi aktif.
- Feature test memakai `DatabaseTransactions`, bukan `RefreshDatabase`, karena database development dipakai bersama.
- OpenAPI vendor tersedia di `docs/openapi-vendor-v1.yaml`.
