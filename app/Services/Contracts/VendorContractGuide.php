<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Exception;
use Illuminate\Support\Facades\DB;

final class VendorContractGuide
{
    /**
     * HTTP transport contract shared by all eight read operations. Paramita selalu
     * memakai GET + query string; hasil operasi dinyatakan dengan status HTTP,
     * bukan field code/message di body (itu pilihan desain PRD §8, lihat transportNotes).
     */
    public static function transport(): array
    {
        return [
            'method' => 'GET',
            'query_in' => 'URL query string (bukan body)',
            'headers' => [
                ['name' => 'Accept', 'value' => 'application/json', 'meaning' => 'Selalu dikirim Paramita.'],
                ['name' => 'Authorization', 'value' => 'Bearer <token>', 'meaning' => 'Dikirim otomatis bila koneksi memakai Login Endpoint atau OAuth. Nilai token tidak pernah ditampilkan di portal.'],
            ],
            'outcomes' => [
                ['status' => '200', 'meaning' => 'Berhasil. Content-Type application/json; body WAJIB envelope {data, meta} dan lolos validasi kontrak.'],
                ['status' => '401', 'meaning' => 'Token salah/kedaluwarsa. Untuk login/OAuth, Paramita otomatis login ulang lalu mencoba sekali lagi; jika tetap 401, sumber ditandai tidak tersedia.'],
                ['status' => '4xx/5xx lain', 'meaning' => 'Permintaan gagal. Gunakan Content-Type application/problem+json dan struktur Problem Details RFC 9457 agar error dapat ditelusuri tanpa format khusus.'],
            ],
            'notes' => [
                'Envelope memakai data + meta. Status hasil sudah dibawa HTTP code, jadi field code/message di body TIDAK dipakai dan tidak divalidasi.',
                'Semua response wajib memakai envelope {data, meta}. meta selalu memuat generated_at, data_as_of, dan scope; endpoint list/lookup/events juga memuat limit, offset, total_filtered, dan has_more.',
                'Format tanggal/waktu wajib RFC 3339 dengan zona waktu, mis. 2026-09-12T10:00:00+07:00 atau 2026-09-12T03:00:00Z.',
                'Vendor tidak perlu mengirim schema_version. Versi kompatibilitas ditentukan oleh path /v1; perubahan yang memutus kompatibilitas akan memakai path mayor baru.',
            ],
        ];
    }

    /** Default path per operation (vendor boleh memakai path berbeda saat mengisi endpoint). */
    public static function defaultPath(string $operationKey): string
    {
        return match ($operationKey) {
            'inventory.list' => 'v1/inventory/list',
            'inventory.summary' => 'v1/inventory/summary',
            'inventory.lookup' => 'v1/inventory/lookup',
            'orders.list' => 'v1/orders/list',
            'orders.summary' => 'v1/orders/summary',
            'orders.detail' => 'v1/orders/{source_id}',
            'orders.events' => 'v1/orders/{source_id}/events',
            'orders.analytics' => 'v1/orders/analytics',
            default => 'v1/'.$operationKey,
        };
    }

    /**
     * Authentication guidance for the "Autentikasi" tab. Explains, per auth method,
     * what credentials the vendor's API must accept, the exact JSON Paramita sends,
     * and the response shape Paramita parses to obtain a token.
     *
     * @return array<int, array<string, mixed>>
     */
    public function authenticationFormats(): array
    {
        return [
            [
                'type' => 'none',
                'label' => 'Tanpa autentikasi',
                'when' => 'Endpoint publik tanpa token. Jarang dipakai produksi.',
                'needs' => [],
            ],
            [
                'type' => 'api_key_header',
                'label' => 'API key di header',
                'when' => 'Vendor memberi satu kunci yang dikirim pada setiap request.',
                'needs' => [
                    ['name' => 'credential_header_name', 'label' => 'Nama header', 'example' => 'X-API-Key'],
                    ['name' => 'credential_secret', 'label' => 'Nilai API key', 'example' => 'rahasia-dari-vendor'],
                ],
            ],
            [
                'type' => 'bearer',
                'label' => 'Bearer token statis',
                'when' => 'Token tetap yang tidak berubah. Tidak cocok bila token sering kedaluwarsa.',
                'needs' => [
                    ['name' => 'credential_secret', 'label' => 'Token', 'example' => 'eyJhbGci...'],
                ],
            ],
            [
                'type' => 'basic',
                'label' => 'Basic Auth',
                'when' => 'Vendor memberi username + password yang dikirim sebagai Basic header.',
                'needs' => [
                    ['name' => 'credential_username', 'label' => 'Username', 'example' => 'vendor-service'],
                    ['name' => 'credential_secret', 'label' => 'Password', 'example' => '[rahasia]'],
                ],
            ],
            [
                'type' => 'oauth2_client_credentials',
                'label' => 'OAuth 2 Client Credentials',
                'when' => 'Vendor memakai token endpoint standar OAuth2 (form-urlencoded).',
                'needs' => [
                    ['name' => 'oauth_token_url', 'label' => 'Token endpoint URL', 'example' => 'https://api.vendor.co.id/oauth/token'],
                    ['name' => 'oauth_client_id', 'label' => 'Client ID', 'example' => 'paramita'],
                    ['name' => 'oauth_scope', 'label' => 'Scope (opsional)', 'example' => 'inventory orders'],
                    ['name' => 'credential_secret', 'label' => 'Client Secret', 'example' => '[rahasia]'],
                ],
                'request_example' => ['method' => 'POST', 'content_type' => 'application/x-www-form-urlencoded', 'body' => 'grant_type=client_credentials&client_id=paramita&client_secret=***&scope=inventory+orders'],
                'response_example' => $this->tokenResponse('access_token', 3600),
            ],
            [
                'type' => 'token_login',
                'label' => 'Login Endpoint (token otomatis)',
                'when' => 'Vendor punya endpoint login JSON. Ini yang dipakai API Prodev Paramita.',
                'needs' => [
                    ['name' => 'login_url', 'label' => 'Login endpoint URL', 'example' => 'https://prodev.ut.ac.id/paramita-vendor-api/gramedia/v1/auth/login'],
                    ['name' => 'credential_username', 'label' => 'Email / username akun service', 'example' => 'akun service dari vendor, BUKAN email portal'],
                    ['name' => 'credential_secret', 'label' => 'Password akun service', 'example' => '[rahasia]'],
                    ['name' => 'login_username_field', 'label' => 'Nama field username', 'example' => 'username'],
                    ['name' => 'login_password_field', 'label' => 'Nama field password', 'example' => 'password'],
                    ['name' => 'login_token_field', 'label' => 'Path token pada response', 'example' => 'data.access_token'],
                    ['name' => 'login_expires_in_field', 'label' => 'Path masa berlaku (opsional)', 'example' => 'data.expires_in'],
                ],
                'request_example' => ['method' => 'POST', 'content_type' => 'application/json', 'body' => ['username' => 'akun-service', 'password' => '[rahasia]']],
                'response_example' => $this->tokenResponse('data.access_token', 300),
            ],
        ];
    }

    private function tokenResponse(string $tokenPath, int $expires): array
    {
        $token = 'eyJ2ZW5kb3IiOiJHUkFNRURJQSIsImV4cCI6MTc1NzAwMDAwMH0.signature';

        return str_starts_with($tokenPath, 'data.')
            ? ['data' => ['access_token' => $token, 'expires_in' => $expires, 'token_type' => 'Bearer']]
            : ['access_token' => $token, 'expires_in' => $expires, 'token_type' => 'Bearer'];
    }

    /** @return array<string, mixed> */
    public function canonicalExample(string $operationKey): array
    {
        return $this->buildGuide($operationKey)['response_example'];
    }

    /**
     * The vendor-facing guide intentionally exposes only what is needed to implement
     * an HTTP response. JSON Schema remains an internal validation asset.
     */
    public function forOperation(string $operationKey): array
    {
        $guide = $this->buildGuide($operationKey);

        // The admin template is the contract, so its example WINS over the fallback hardcoded here.
        // Without this, a rename in the admin screen left the vendor page advertising the old field
        // name — the vendor copies it and the gateway rejects it.
        $fromTemplate = $this->templateExample($operationKey);
        if ($fromTemplate !== null) {
            $guide['response_example'] = $fromTemplate;
        }

        $guide['response_fields'] = $this->describeExample($guide['response_example']);

        return $guide;
    }

    /**
     * The response example stored in the active json_template, or null when there is none.
     *
     * @return array<string, mixed>|null
     */
    private function templateExample(string $operationKey): ?array
    {
        $stored = DB::table('json_templates')
            ->where('name', $operationKey)
            ->where('is_active', true)
            ->value('template_data');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        $example = json_decode($stored, true);

        return is_array($example) && ! array_is_list($example) && $example !== [] ? $example : null;
    }

    /** @return array<string, mixed> */
    private function buildGuide(string $operationKey): array
    {
        $guide = match ($operationKey) {
            'inventory.list', 'inventory.lookup' => [
                'operation' => $operationKey,
                'purpose' => 'Menampilkan stok paket atau judul pada halaman Monitoring Stok.',
                'request' => [
                    'required' => [
                        ['name' => 'limit', 'type' => 'integer', 'example' => 25, 'meaning' => 'Jumlah maksimum data pada satu respons.'],
                        ['name' => 'offset', 'type' => 'integer', 'example' => 0, 'meaning' => 'Posisi awal data untuk pagination.'],
                    ],
                    'optional' => [
                        ['name' => 'search', 'type' => 'string', 'example' => 'MKDU', 'meaning' => 'Pencarian judul atau kode item.'],
                        ['name' => 'item_type', 'type' => 'package|book', 'example' => 'package', 'meaning' => 'Jenis stok yang diminta.'],
                        ['name' => 'ut_code', 'type' => 'string', 'example' => 'UN31.UT15', 'meaning' => 'Filter UT daerah (kode master data, contoh UN31.UT15 = Bandung).'],
                        ['name' => 'program_codes', 'type' => 'string', 'example' => '61201', 'meaning' => 'Filter kode prodi, boleh CSV jika vendor mendukung banyak nilai.'],
                        ['name' => 'stock_status', 'type' => 'string', 'example' => 'adequate', 'meaning' => 'Filter status stok jika tersedia.'],
                        ['name' => 'updated_from', 'type' => 'date-time ISO 8601', 'example' => '2026-08-15T00:00:00+07:00', 'meaning' => 'Range tanggal pembaruan stok (filter rentang tanggal di halaman user).'],
                        ['name' => 'updated_to', 'type' => 'date-time ISO 8601', 'example' => '2026-09-15T00:00:00+07:00', 'meaning' => 'Batas akhir eksklusif; data tepat pada waktu ini tidak ikut. Untuk 14 September, kirim tengah malam 15 September.'],
                    ],
                ],
                'response_example' => $this->inventoryList(),
                'view_fields' => ['item_code', 'title', 'stock_quantity', 'required_quantity', 'ut_code', 'program_code', 'updated_at'],
            ],
            'inventory.summary' => [
                'operation' => $operationKey,
                'purpose' => 'Menampilkan ringkasan stok pada dashboard.',
                'request' => [
                    'required' => [],
                    'optional' => [
                        ['name' => 'item_type', 'type' => 'package|book', 'example' => 'package', 'meaning' => 'Jenis stok yang diringkas.'],
                        ['name' => 'ut_code', 'type' => 'string', 'example' => 'UN31.UT15', 'meaning' => 'Filter UT daerah (kode master data, contoh UN31.UT15 = Bandung).'],
                        ['name' => 'program_codes', 'type' => 'string', 'example' => '61201', 'meaning' => 'Filter prodi.'],
                    ],
                ],
                'response_example' => $this->inventorySummary(),
                'view_fields' => ['item_type', 'stock_quantity', 'record_count', 'shortage_count', 'adequate_count', 'surplus_count'],
            ],
            'orders.list' => [
                'operation' => $operationKey,
                'purpose' => 'Menampilkan tabel Monitoring Delivery dan sumber data halaman DO per Prodi/UT Daerah.',
                'request' => [
                    'required' => [
                        ['name' => 'limit', 'type' => 'integer', 'example' => 25, 'meaning' => 'Jumlah maksimum DO pada satu respons.'],
                        ['name' => 'offset', 'type' => 'integer', 'example' => 0, 'meaning' => 'Posisi awal DO untuk pagination.'],
                    ],
                    'optional' => [
                        ['name' => 'search', 'type' => 'string', 'example' => 'DO-20252-0001', 'meaning' => 'Pencarian nomor DO atau nama mahasiswa di payload vendor.'],
                        ['name' => 'period_code', 'type' => 'string', 'example' => '20252', 'meaning' => 'Masa pemesanan. WAJIB didukung: halaman user selalu mengirim filter ini, dan baris tanpa period_code tidak akan muncul di tabel.'],
                        ['name' => 'ut_code', 'type' => 'string', 'example' => 'UN31.UT15', 'meaning' => 'Filter UT daerah (kode master data, contoh UN31.UT15 = Bandung).'],
                        ['name' => 'program_codes', 'type' => 'string', 'example' => '61201', 'meaning' => 'Filter kode prodi.'],
                        ['name' => 'process_status_code', 'type' => 'string', 'example' => '03', 'meaning' => 'Filter status proses 01 sampai 07.'],
                        ['name' => 'ordered_from', 'type' => 'date-time ISO 8601', 'example' => '2026-08-15T00:00:00+07:00', 'meaning' => 'Range tanggal pemesanan (filter di halaman user).'],
                        ['name' => 'ordered_to', 'type' => 'date-time ISO 8601', 'example' => '2026-09-15T00:00:00+07:00', 'meaning' => 'Batas akhir eksklusif; data tepat pada waktu ini tidak ikut.'],
                    ],
                ],
                'response_example' => $this->ordersList(),
                'view_fields' => ['order_number', 'period_code', 'ordered_at', 'student_name', 'province', 'city', 'district', 'village', 'ut_code', 'program_code', 'process_status_code'],
            ],
            'orders.summary' => [
                'operation' => $operationKey,
                'purpose' => 'Menyediakan total dan grup DO untuk chart per prodi atau UT daerah.',
                'request' => [
                    'required' => [
                        ['name' => 'group_by', 'type' => 'none|program|ut', 'example' => 'program', 'meaning' => 'Cara pengelompokan data chart.'],
                    ],
                    'optional' => [
                        ['name' => 'period_code', 'type' => 'string', 'example' => '20252', 'meaning' => 'Masa pemesanan yang sama dengan orders.list.'],
                        ['name' => 'program_codes', 'type' => 'string', 'example' => '61201', 'meaning' => 'Filter kode prodi.'],
                        ['name' => 'ut_code', 'type' => 'string', 'example' => 'UN31.UT15', 'meaning' => 'Filter UT daerah (kode master data, contoh UN31.UT15 = Bandung).'],
                    ],
                ],
                'response_example' => $this->ordersSummary(),
                'view_fields' => ['total_orders', 'status_counts', 'status_code_counts', 'sla_counts'],
            ],
            'orders.detail' => [
                'operation' => $operationKey,
                'purpose' => 'Menampilkan detail DO ketika baris Monitoring Delivery diklik.',
                'request' => [
                    'required' => [
                        ['name' => 'source_id', 'type' => 'path parameter', 'example' => 'ord-001', 'meaning' => 'Nilai id dari orders.list.data[].id.'],
                    ],
                    'optional' => [],
                ],
                'response_example' => $this->orderDetail(),
                'view_fields' => ['tracking_number', 'carrier_name', 'sla_status', 'latest_event', 'proof_of_delivery_url'],
            ],
            'orders.events' => [
                'operation' => $operationKey,
                'purpose' => 'Menampilkan riwayat proses pengiriman pada detail DO.',
                'request' => [
                    'required' => [
                        ['name' => 'source_id', 'type' => 'path parameter', 'example' => 'ord-001', 'meaning' => 'Nilai id dari orders.list.data[].id.'],
                    ],
                    'optional' => [],
                ],
                'response_example' => $this->orderEvents(),
                'view_fields' => ['occurred_at', 'title', 'description', 'location', 'process_status_code'],
            ],
            'orders.analytics' => [
                'operation' => $operationKey,
                'purpose' => 'Menyediakan agregat tervalidasi untuk Analisis SLA, Monitoring Retry, dan Distribution Map.',
                'request' => [
                    'required' => [
                        ['name' => 'ordered_from', 'type' => 'date-time ISO 8601', 'example' => '2026-09-16T00:00:00+07:00', 'meaning' => 'Awal periode analisis.'],
                        ['name' => 'ordered_to', 'type' => 'date-time ISO 8601', 'example' => '2026-09-17T00:00:00+07:00', 'meaning' => 'Batas akhir periode analisis.'],
                        ['name' => 'occurred_from', 'type' => 'date-time ISO 8601', 'example' => '2026-09-16T00:00:00+07:00', 'meaning' => 'Awal periode kejadian retry.'],
                        ['name' => 'occurred_to', 'type' => 'date-time ISO 8601', 'example' => '2026-09-17T00:00:00+07:00', 'meaning' => 'Batas akhir periode kejadian retry.'],
                    ],
                    'optional' => [
                        ['name' => 'ut_code', 'type' => 'string', 'example' => 'UN31.UT15', 'meaning' => 'Filter UT daerah (kode master data, contoh UN31.UT15 = Bandung).'],
                        ['name' => 'program_codes', 'type' => 'string', 'example' => '61201', 'meaning' => 'Filter kode program studi.'],
                    ],
                ],
                'response_example' => $this->ordersAnalytics(),
                'view_fields' => ['sla', 'sla_details', 'retries', 'retry_details', 'distribution'],
            ],
            default => throw new Exception("Unknown vendor contract: {$operationKey}"),
        };

        $query = match ($operationKey) {
            'inventory.list', 'inventory.lookup' => 'limit=25&offset=0&item_type=package',
            'inventory.summary' => 'item_type=package',
            'orders.list' => 'limit=25&offset=0&period_code=20252',
            'orders.summary' => 'group_by=program&period_code=20252',
            'orders.analytics' => 'ordered_from=2026-09-16T00%3A00%3A00%2B07%3A00&ordered_to=2026-09-17T00%3A00%3A00%2B07%3A00&occurred_from=2026-09-16T00%3A00%3A00%2B07%3A00&occurred_to=2026-09-17T00%3A00%3A00%2B07%3A00',
            default => '',
        };
        $guide['method'] = 'GET';
        $guide['default_path'] = self::defaultPath($operationKey);
        $guide['example_request'] = 'GET {BASE_URL}/'.$guide['default_path'].($query !== '' ? '?'.$query : '');

        if ($operationKey === 'inventory.lookup') {
            $guide['response_example'] = $this->inventoryLookup();
            $guide['view_fields'] = ['catalog_key', 'stock_quantity', 'availability', 'updated_at'];
            $guide['request']['required'][] = [
                'name' => 'catalog_keys',
                'type' => 'string CSV',
                'example' => 'PKT-MKDU4109,BUKU-EKMA4115-2025.1',
                'meaning' => 'Daftar catalog_key dari Paramita yang stoknya harus dikembalikan. Key yang tidak disuplai tetap dikembalikan dengan availability not_supplied.',
            ];
            $guide['request']['optional'] = array_values(array_filter(
                $guide['request']['optional'],
                fn (array $parameter): bool => ! in_array($parameter['name'], ['stock_status', 'updated_from', 'updated_to'], true)
            ));
            $guide['example_request'] = 'GET {BASE_URL}/'.$guide['default_path'].'?limit=25&offset=0&item_type=package&catalog_keys=PKT-MKDU4109%2CBUKU-EKMA4115-2025.1';
        }

        return $guide;
    }

    /**
     * Build a readable field/type list from the exact active example.
     * This is derived documentation, not a second contract declaration.
     *
     * @return list<array{path: string, type: string}>
     */
    private function describeExample(mixed $value, string $path = '$'): array
    {
        $rows = [['path' => $path, 'type' => $this->exampleType($value)]];
        if (! is_array($value)) {
            return $rows;
        }

        if (array_is_list($value)) {
            return isset($value[0]) ? array_merge($rows, $this->describeExample($value[0], $path.'[]')) : $rows;
        }

        foreach ($value as $key => $child) {
            $rows = array_merge($rows, $this->describeExample($child, $path.'.'.$key));
        }

        return $rows;
    }

    private function exampleType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null / nullable',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            default => get_debug_type($value),
        };
    }

    private function listMeta(int $total = 1): array
    {
        return array_merge($this->objectMeta(), [
            'limit' => 25,
            'offset' => 0,
            'total_filtered' => $total,
            'has_more' => false,
        ]);
    }

    private function objectMeta(): array
    {
        return [
            'generated_at' => '2026-09-12T10:00:00+00:00',
            'data_as_of' => '2026-09-12T09:59:30+00:00',
            'scope' => ['ut_code' => null, 'program_codes' => []],
        ];
    }

    private function inventoryRow(): array
    {
        return [
            'id' => 'inv-001', 'catalog_key' => 'PKT-MKDU4109', 'item_type' => 'package',
            'item_code' => 'MKDU4109', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Pendidikan Kewarganegaraan',
            'size_label' => 'Paket', 'ut_code' => 'UN31.UT15', 'program_code' => '61201',
            'stock_quantity' => 120, 'required_quantity' => 100,
            'unit_weight_kg' => 0.8, 'total_weight_kg' => 96.0, 'total_height_cm' => 20.0,
            'total_area_m2' => 1.2, 'updated_at' => '2026-09-12T10:00:00+00:00',
        ];
    }

    private function orderRow(): array
    {
        return [
            'id' => 'ord-001', 'order_number' => 'DO-20252-0001', 'period_code' => '20252', 'ordered_at' => '2026-09-01T08:00:00+00:00',
            'student_name' => 'Nama Mahasiswa Contoh', 'province' => 'Jawa Barat', 'city' => 'Bandung',
            'district' => 'Coblong', 'village' => 'Dago', 'ut_code' => 'UN31.UT15', 'program_code' => '61201',
            'process_status_code' => '03', 'updated_at' => '2026-09-12T10:00:00+00:00',
        ];
    }

    private function inventoryList(): array
    {
        return ['data' => [$this->inventoryRow()], 'meta' => $this->listMeta()];
    }

    private function inventoryLookup(): array
    {
        return ['data' => [[
            'catalog_key' => 'PKT-MKDU4109',
            'stock_quantity' => 120,
            'availability' => 'available',
            'updated_at' => '2026-09-12T10:00:00+00:00',
        ]], 'meta' => $this->listMeta()];
    }

    private function ordersList(): array
    {
        return ['data' => [$this->orderRow()], 'meta' => $this->listMeta()];
    }

    private function inventorySummary(): array
    {
        return ['data' => [
            'item_type' => 'package', 'stock_quantity' => 120, 'record_count' => 1,
            'shortage_count' => 0, 'adequate_count' => 1, 'surplus_count' => 0, 'unknown_count' => 0,
        ], 'meta' => [
            'generated_at' => '2026-09-12T10:00:00+00:00',
            'data_as_of' => '2026-09-12T09:59:30+00:00',
            'scope' => ['ut_code' => null, 'program_codes' => []],
        ]];
    }

    private function ordersSummary(): array
    {
        return ['data' => [
            'total_orders' => 1,
            'status_counts' => ['on_process' => 0, 'on_delivery' => 1, 'retry' => 0, 'returned' => 0, 'delivered' => 0],
            'status_code_counts' => ['01' => 0, '02' => 0, '03' => 1, '04' => 0, '05' => 0, '06' => 0, '07' => 0],
            'sla_counts' => ['on_sla' => 1, 'over_sla' => 0, 'not_applicable' => 0, 'unknown' => 0],
            'completed_sla_seconds_sum' => 0, 'completed_sla_sample_count' => 0,
        ], 'meta' => [
            'generated_at' => '2026-09-12T10:00:00+00:00',
            'data_as_of' => '2026-09-12T09:59:30+00:00',
            'scope' => ['ut_code' => null, 'program_codes' => []],
        ]];
    }

    private function orderDetail(): array
    {
        return ['data' => array_merge($this->orderRow(), [
            'student_identifier' => '012345678', 'student_phone' => '081234567890',
            'address_line' => 'Jl. Pendidikan No. 1',
            'package_code' => 'PKT-MKDU4109', 'package_title' => 'Paket Bahan Ajar Pendidikan Kewarganegaraan',
            'carrier_name' => 'Contoh Kurir', 'tracking_number' => 'TRK-001',
            'paid_at' => '2026-09-01T08:05:00+00:00', 'handed_to_carrier_at' => null,
            'completed_at' => null, 'sla_target_days' => 7, 'sla_elapsed_days' => 2.0, 'sla_status' => 'on_sla',
            'latitude' => null, 'longitude' => null,
            'latest_event' => ['id' => 'evt-001', 'occurred_at' => '2026-09-02T08:00:00+00:00', 'title' => 'Diserahkan ke kurir', 'description' => null, 'location' => 'Bandung', 'process_status_code' => '03'],
            'proof_of_delivery_url' => null,
        ]), 'meta' => $this->objectMeta()];
    }

    private function orderEvents(): array
    {
        return ['data' => [[
            'id' => 'evt-001', 'occurred_at' => '2026-09-02T08:00:00+00:00', 'title' => 'Diserahkan ke kurir',
            'description' => null, 'location' => 'Bandung', 'process_status_code' => '03',
        ]], 'meta' => $this->listMeta()];
    }

    private function ordersAnalytics(): array
    {
        return [
            'data' => [
                'sla' => [[
                    'carrier_name' => 'JNE', 'faster_count' => 12, 'on_sla_count' => 31,
                    'over_sla_count' => 4, 'total_orders' => 47, 'sla_target_days' => 3,
                ]],
                'sla_details' => [[
                    'id' => 'ord-001', 'order_number' => 'DO-20252-0001', 'ut_code' => 'UN31.UT15',
                    'carrier_name' => 'JNE',
                    'tracking_number' => 'JNE123456789',
                    'sla_status' => 'on_sla', 'ordered_at' => '2026-09-16T08:00:00+07:00',
                    'handed_to_carrier_at' => '2026-09-16T15:00:00+07:00',
                    'completed_at' => '2026-09-18T14:00:00+07:00', 'process_status_code' => '07', 'sla_target_days' => 3,
                    'sla_elapsed_days' => 2.25,
                ]],
                'retries' => [[
                    'reason_code' => 'ADDRESS_NOT_FOUND', 'carrier_name' => 'JNE', 'retry_count' => 8,
                ]],
                'retry_details' => [[
                    'id' => 'ord-001', 'order_number' => 'DO-20252-0001', 'student_identifier' => null, 'student_name' => 'Nama Mahasiswa',
                    'ut_code' => 'UN31.UT15', 'program_code' => '61201', 'carrier_name' => 'JNE',
                    'tracking_number' => 'JNE123456789',
                    'reason_code' => 'ADDRESS_NOT_FOUND', 'retry_attempt' => 1,
                    'occurred_at' => '2026-09-16T12:00:00+07:00', 'process_status_code' => '03',
                    'proof_of_delivery_url' => null,
                ]],
                'distribution' => [[
                    'province_code' => '32', 'province_name' => 'Jawa Barat', 'city_code' => '3273',
                    'city_name' => 'Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191,
                    'total_shipments' => 120, 'delivered_on_time' => 96,
                    'delivered_late' => 16, 'failed_or_returned' => 8, 'avg_delay_days' => 1.5,
                ]],
            ],
            'meta' => [
                'generated_at' => '2026-09-16T12:00:00+07:00',
                'data_as_of' => '2026-09-16T11:59:30+07:00',
                'scope' => ['ut_code' => null, 'program_codes' => []],
                'ordered_from' => '2026-09-16T00:00:00+07:00',
                'ordered_to' => '2026-09-17T00:00:00+07:00',
                'occurred_from' => '2026-09-16T00:00:00+07:00',
                'occurred_to' => '2026-09-17T00:00:00+07:00',
            ],
        ];
    }
}
