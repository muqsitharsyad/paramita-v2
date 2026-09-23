<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The seven process status codes from the PRD. Admin can edit/extend these afterwards; the vendor
 * reference page and the monitoring UI both read this table.
 */
class ProcessStatusSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => '01', 'label' => 'Sedang Diproses', 'bucket' => 'process', 'sort_order' => 1],
            ['code' => '02', 'label' => 'Dalam Pengiriman', 'bucket' => 'process', 'sort_order' => 2],
            ['code' => '03', 'label' => 'Retry 1', 'bucket' => 'retry', 'sort_order' => 3],
            ['code' => '04', 'label' => 'Retry 2', 'bucket' => 'retry', 'sort_order' => 4],
            ['code' => '05', 'label' => 'Retry 3', 'bucket' => 'retry', 'sort_order' => 5],
            ['code' => '06', 'label' => 'Dikembalikan', 'bucket' => 'return', 'sort_order' => 6],
            ['code' => '07', 'label' => 'Terkirim', 'bucket' => 'delivered', 'sort_order' => 7],
        ];

        foreach ($rows as $row) {
            DB::table('process_statuses')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
