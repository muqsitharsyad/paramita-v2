<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retry_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        foreach ($this->retryReasons() as $index => $reason) {
            DB::table('retry_reasons')->insert([
                'code' => $reason[0],
                'label' => $reason[1],
                'description' => null,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $contractId = DB::table('contracts')->insertGetId([
            'operation_key' => 'orders.analytics',
            'module' => 'delivery',
            'label' => 'Analitik SLA, Retry, dan Distribusi',
            'description' => 'Agregat tervalidasi untuk Analisis SLA, Monitoring Retry, dan Distribution Map.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('contract_versions')->insert([
            'contract_id' => $contractId,
            'version' => '1.0.0',
            'schema_json' => json_encode(['archived' => true, 'source' => 'json_templates.template_data']),
            'request_schema_json' => null,
            'status' => 'published',
            'suite_version' => '1.0.0',
            'published_at' => $now,
            'retire_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('json_templates')->insert([
            'name' => 'orders.analytics',
            'category' => 'delivery',
            'description' => 'Format JSON Analisis SLA, Monitoring Retry, dan Distribution Map.',
            'template_data' => json_encode($this->analyticsTemplate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'version' => '1.0.0',
            'is_active' => true,
            'created_by' => DB::table('users')->where('role', 'admin')->value('id'),
            'updated_by' => DB::table('users')->where('role', 'admin')->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('vendors')->where('status', 'approved')->pluck('id') as $vendorId) {
            DB::table('endpoint_bindings')->insert([
                'vendor_id' => $vendorId,
                'contract_id' => $contractId,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $contractId = DB::table('contracts')->where('operation_key', 'orders.analytics')->value('id');
        DB::table('json_templates')->where('name', 'orders.analytics')->delete();
        if ($contractId !== null) {
            DB::table('contracts')->where('id', $contractId)->delete();
        }
        Schema::dropIfExists('retry_reasons');
    }

    /** @return list<array{string,string}> */
    private function retryReasons(): array
    {
        return [
            ['ADDRESS_NOT_FOUND', 'Alamat Tidak Ditemukan'],
            ['UNKNOWN_RECIPIENT', 'Penerima Tidak Dikenal'],
            ['DELIVERY_FAILED', 'Paket Gagal Dikirim'],
            ['EMPTY_HOUSE', 'Rumah Kosong'],
            ['INCOMPLETE_ADDRESS', 'Alamat Penerima Tidak Lengkap'],
            ['WEATHER_OR_ENVIRONMENT', 'Kendala Cuaca/Lingkungan'],
            ['RECIPIENT_ABSENT', 'Penerima Tidak di Tempat'],
            ['RECIPIENT_CONFIRMED', 'Penerima Telah Dikonfirmasi'],
            ['RESCHEDULE_REQUESTED', 'Penerima Ingin Ubah Jadwal Kirim'],
            ['LOCATION_CLOSED', 'Kantor/Lokasi Tutup'],
            ['SYSTEM_FAILURE', 'Gagal oleh Sistem'],
            ['OUT_OF_DELIVERY_AREA', 'Luar Batas Antar'],
            ['OPERATIONAL_ISSUE', 'Kendala Operasional'],
            ['RECIPIENT_UNREACHABLE', 'Penerima Tidak Dapat Dihubungi'],
            ['RECIPIENT_MOVED', 'Alamat Cocok, Tapi Penerima Pindah'],
        ];
    }

    /** @return array<string,mixed> */
    private function analyticsTemplate(): array
    {
        return [
            'data' => [
                'sla' => [[
                    'carrier_name' => 'JNE',
                    'faster_count' => 12,
                    'on_sla_count' => 31,
                    'over_sla_count' => 4,
                    'total_orders' => 47,
                    'sla_target_days' => 3,
                ]],
                'sla_details' => [[
                    'id' => 'ord-001',
                    'order_number' => 'DO-20252-0001',
                    'carrier_name' => 'JNE',
                    'tracking_number' => 'JNE123456789',
                    'sla_status' => 'on_sla',
                    'ordered_at' => '2026-09-16T08:00:00+07:00',
                    'handed_to_carrier_at' => '2026-09-16T15:00:00+07:00',
                    'completed_at' => '2026-09-18T14:00:00+07:00',
                    'process_status_code' => '07',
                    'sla_target_days' => 3,
                    'sla_elapsed_days' => 2.25,
                ]],
                'retries' => [[
                    'reason_code' => 'ADDRESS_NOT_FOUND',
                    'carrier_name' => 'JNE',
                    'retry_count' => 8,
                ]],
                'retry_details' => [[
                    'id' => 'ord-001',
                    'order_number' => 'DO-20252-0001',
                    'student_identifier' => null,
                    'student_name' => 'Nama Mahasiswa',
                    'ut_code' => 'UN31.UT15',
                    'program_code' => '61201',
                    'carrier_name' => 'JNE',
                    'tracking_number' => 'JNE123456789',
                    'reason_code' => 'ADDRESS_NOT_FOUND',
                    'retry_attempt' => 1,
                    'occurred_at' => '2026-09-16T12:00:00+07:00',
                    'process_status_code' => '03',
                    'proof_of_delivery_url' => null,
                ]],
                'distribution' => [[
                    'province_code' => '32',
                    'province_name' => 'Jawa Barat',
                    'city_code' => '3273',
                    'city_name' => 'Bandung',
                    'latitude' => -6.9175,
                    'longitude' => 107.6191,
                    'total_shipments' => 120,
                    'delivered_on_time' => 96,
                    'delivered_late' => 16,
                    'failed_or_returned' => 8,
                    'avg_delay_days' => 1.5,
                ]],
            ],
            'meta' => [
                'generated_at' => '2026-09-16T12:00:00+07:00',
                'scope' => ['ut_code' => null, 'program_codes' => []],
                'ordered_from' => '2026-09-16T00:00:00+07:00',
                'ordered_to' => '2026-09-17T00:00:00+07:00',
                'occurred_from' => '2026-09-16T00:00:00+07:00',
                'occurred_to' => '2026-09-17T00:00:00+07:00',
            ],
        ];
    }
};
