# contracts/v1 — arsip, bukan acuan runtime

File `*.schema.json` di folder ini **tidak dibaca** saat vendor menekan Test.

Acuan runtime tunggal adalah `json_templates.template_data`, berupa response JSON langsung dengan envelope `{data, meta}`. Nama field dan tipe diambil dari nilai contoh pada JSON tersebut.

## Mengubah format vendor

Buka `/admin/templates/{id}/edit` dan ubah JSON langsung. Setelah disimpan:

- portal vendor menampilkan JSON yang sama;
- Test vendor memakai JSON yang sama;
- payload yang tidak sesuai diblokir dari halaman user.

Tidak ada import schema, editor aturan per field, field map, atau normalisasi kontrak kedua.

## Fungsi folder ini

- referensi historis struktur PRD;
- bahan unit test schema lama;
- dokumentasi migrasi sebelum model satu-JSON diterapkan.

Jangan menggunakan file di folder ini sebagai sumber runtime baru.
