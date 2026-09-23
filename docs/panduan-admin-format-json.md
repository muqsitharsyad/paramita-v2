# Panduan Admin — Menambah / Mengubah Format JSON

Dokumen ini menjawab satu pertanyaan yang paling sering muncul:

> **"Kenapa ada tombol Tambah Format JSON di halaman admin, tapi menambah format baru kok tidak cukup dari situ saja?"**

Jawaban singkatnya: ada **dua jenis pekerjaan** yang berbeda, dan keduanya sering tertukar.

| Jenis | Contoh | Cukup dari admin? |
|---|---|---|
| **A. Ubah format operasi yang SUDAH ada** | Rename field, tambah/hapus kolom, ubah tipe nilai di `inventory.list` | ✅ Ya |
| **B. Tambah operasi vendor yang BENAR-BENAR BARU** | Operasi ke-9 mis. `orders.invoice`, `inventory.price` | ❌ Perlu sentuhan kode |

---

## A. Ubah format operasi yang sudah ada → cukup admin

Format JSON yang disimpan admin di `admin/templates` adalah **satu-satunya acuan validasi** saat vendor melakukan Test. Jadi:

1. Buka **Format JSON** di Pusat Administrasi.
2. Pilih template yang ingin diubah (mis. `inventory.list`).
3. Edit `template_data` (tambah/rename/hapus field), simpan.
4. Vendor otomatis divalidasi memakai format baru.

**Aturan yang harus tetap dipenuhi** (kalau dilanggar, form menolak):

- `template_data` harus berbentuk **object** `{ "data": ..., "meta": ... }` (bukan array list).
- `data` boleh array ATAU object.
- `meta` WAJIB object (bukan list), dan wajib memuat `generated_at`, `data_as_of`, `scope`.

> Rename field = vendor juga wajib ikut update endpointnya. Selama vendor belum menyesuaikan, Test mereka akan gagal dan datanya diblokir. Ini memang by-design (enforcement kontrak).

---

## B. Tambah operasi vendor yang baru → 4 tempat yang wajib disentuh

Menambah **operasi baru** (bukan cuma edit yang lama) memerlukan perubahan di 4 file. Kalau salah satu saja terlewat, sistem akan error atau operasi baru tidak muncul.

| # | File | Apa yang ditambah |
|---|---|---|
| 1 | `config/paramita.php` → `schemas` | Mapping `operation_key` baru → file schema |
| 2 | `app/Services/Contracts/VendorContractGuide.php` | `match` di `buildGuide()` + `defaultPath()` (kalau tidak: `Unknown vendor contract`) |
| 3 | `database/seeders/DefaultContractsSeeder.php` | Satu entri operasi (key, module, label, schema_file) |
| 4 | `database/seeders/ProdevVendorSeeder.php` → `operationPaths()` | Path endpoint default agar binding vendor ter-seed |

Opsional (hanya kalau datanya mau ditampilkan):

- Schema JSON baru di `contracts/v1/` kalau mengikuti pola validasi file.
- View + route + aggregator baru kalau operasi butuh halaman sendiri.

---

## Jalan pintas terbaik: berikan prompt ini ke AI agent

Cara paling mudah dan paling aman: **jangan dikerjakan manual**. Berikan prompt berikut ke AI coding agent (Cursor, Claude Code, Hermes, dsb) lengkap dengan detail operasi baru:

```text
Di project Laravel "Paramita-Final" (path: D:\laragon\www\Paramita-Final), saya mau
menambah operasi vendor baru bernama "<OPERATION_KEY>" (contoh: orders.invoice).

Operasi ini:
- Module: <stock | delivery>
- Label: <judul operasi>
- Deskripsi: <apa yang dikembalikan>
- Kategori response: <list | summary | detail | events | analytics>

Tolong kerjakan SEMUA langkah berikut secara lengkap dan konsisten:
1. Tambah mapping schema di config/paramita.php bagian 'schemas'.
2. Tambah arm `match` di app/Services/Contracts/VendorContractGuide.php:
   - buildGuide()  → response_example + response_fields + query default
   - defaultPath() → path default v1/...
3. Tambah satu entri operasi di database/seeders/DefaultContractsSeeder.php.
4. Tambah operation_key + path default di database/seeders/ProdevVendorSeeder.php
   (method operationPaths()).
5. (Opsional) buat schema JSON di contracts/v1/ bila operasi memakai validasi file.
6. Jalankan: php artisan migrate:fresh --seed  lalu php artisan test.
Pastikan tidak ada regresi dan seluruh test tetap hijau.
```

Dengan prompt itu, AI agent akan menyentuh ke-4 file secara otomatis dan menjalankan test sampai hijau. Admin hanya perlu menyediakan **spesifikasi operasi** (nama, module, dan bentuk response-nya).

---

## Struktur minimal template_data (pola valid)

```json
{
  "data": [],
  "meta": {
    "generated_at": "2026-09-23T10:00:00+07:00",
    "data_as_of": "2026-09-23T09:59:59+07:00",
    "scope": { "ut_codes": ["UN31.UT1"], "period_code": "20252" },
    "limit": 25,
    "offset": 0,
    "total_filtered": 1,
    "has_more": false
  }
}
```

- `data` berisi list/object hasil operasi.
- `meta` WAJIB object, memuat metadata standar di atas (untuk operasi list/lookup/events, `limit/offset/total_filtered/has_more` wajib ada).
