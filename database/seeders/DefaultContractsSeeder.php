<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DefaultContractsSeeder extends Seeder
{
    public function run(): void
    {
        $contractsDir = base_path('contracts/v1');

        $operations = [
            [
                'key' => 'inventory.list',
                'module' => 'stock',
                'label' => 'Daftar Stok Paket/Judul',
                'description' => 'Mengembalikan daftar alokasi stok per scope (PRD §8.1)',
                'schema_file' => 'envelope.list.schema.json',
            ],
            [
                'key' => 'inventory.summary',
                'module' => 'stock',
                'label' => 'Ringkasan Stok',
                'description' => 'Mengembalikan agregat jumlah stok per status per scope (PRD §8.2)',
                'schema_file' => 'inventory.summary.schema.json',
            ],
            [
                'key' => 'inventory.lookup',
                'module' => 'stock',
                'label' => 'Lookup Stok Matrix',
                'description' => 'Mengembalikan stok per catalog_key untuk matrix (PRD §8.1)',
                'schema_file' => 'envelope.list.schema.json',
            ],
            [
                'key' => 'orders.list',
                'module' => 'delivery',
                'label' => 'Daftar Delivery Order',
                'description' => 'Mengembalikan daftar DO per scope (PRD §8.4)',
                'schema_file' => 'envelope.list.schema.json',
            ],
            [
                'key' => 'orders.summary',
                'module' => 'delivery',
                'label' => 'Ringkasan Delivery Order',
                'description' => 'Mengembalikan agregat status dan SLA DO (PRD §8.7)',
                'schema_file' => 'summary.row.schema.json',
            ],
            [
                'key' => 'orders.detail',
                'module' => 'delivery',
                'label' => 'Detail Delivery Order',
                'description' => 'Mengembalikan detail satu DO beserta informasi kurir dan SLA (PRD §8.5)',
                'schema_file' => 'order.detail.schema.json',
            ],
            [
                'key' => 'orders.events',
                'module' => 'delivery',
                'label' => 'History / Retry Events',
                'description' => 'Mengembalikan timeline event status/retry satu DO (PRD §8.6)',
                'schema_file' => 'envelope.list.schema.json',
            ],
            [
                'key' => 'orders.analytics',
                'module' => 'delivery',
                'label' => 'Analitik SLA, Retry, dan Distribusi',
                'description' => 'Agregat untuk tiga halaman analitik delivery.',
                'schema_file' => 'envelope.list.schema.json',
            ],
        ];

        $now = now();

        foreach ($operations as $op) {
            $schemaPath = $contractsDir.'/'.$op['schema_file'];
            $schemaContent = file_exists($schemaPath) ? file_get_contents($schemaPath) : json_encode(['$schema' => 'draft-2020-12']);

            $contractId = DB::table('contracts')->updateOrInsert(
                ['operation_key' => $op['key']],
                [
                    'module' => $op['module'],
                    'label' => $op['label'],
                    'description' => $op['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            // Re-fetch contract id if inserted or updated
            $contract = DB::table('contracts')->where('operation_key', $op['key'])->first();

            DB::table('contract_versions')->updateOrInsert(
                [
                    'contract_id' => $contract->id,
                    'version' => '1.0.0',
                ],
                [
                    'schema_json' => $schemaContent,
                    'request_schema_json' => null,
                    'status' => 'published',
                    'suite_version' => '1.0.0',
                    'published_at' => $now,
                    'retire_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
