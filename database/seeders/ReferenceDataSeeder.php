<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Support\OfficialUtUnits;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * REFERENCE DATA ONLY — safe to re-run.
 *
 * Seeds the master/reference tables Paramita and its vendors share: UT regions (the source of
 * every `ut_code` a vendor may send), study programmes (`program_code`), catalogue items
 * (`catalog_key`/`item_code`) and process statuses (`process_status_code`).
 *
 * It deliberately does NOT touch vendors, connections, bindings or json_templates — re-running the
 * demo seeder resets connection base URLs to a local mock and stacks submitted revisions, which
 * silently breaks a working integration. Use ParamitaDemoSeeder when you actually want demo rows.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $officialUtUnits = OfficialUtUnits::all();

        DB::table('ut_regions')
            ->whereNotIn('code', array_column($officialUtUnits, 'code'))
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        foreach ($officialUtUnits as $unit) {
            DB::table('ut_regions')->updateOrInsert(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'type' => $unit['type'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        foreach ($this->studyPrograms() as $program) {
            DB::table('programs')->updateOrInsert(
                ['code' => $program['code']],
                [
                    'name' => $program['name'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        foreach ($this->catalogItems() as $item) {
            DB::table('catalog_items')->updateOrInsert(
                ['catalog_key' => $item['catalog_key']],
                [
                    'item_type' => $item['item_type'],
                    'item_code' => $item['item_code'],
                    'edition' => $item['edition'],
                    'title' => $item['title'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        foreach ($this->periods() as $index => $period) {
            DB::table('periods')->updateOrInsert(
                ['code' => $period['code']],
                [
                    'name' => $period['name'],
                    'sort_order' => $index,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $this->call(ProcessStatusSeeder::class);
    }

    /** @return list<array{code: string, name: string}> */
    private function studyPrograms(): array
    {
        return [
            ['code' => '31101', 'name' => 'Pendidikan Pancasila dan Kewarganegaraan'],
            ['code' => '61201', 'name' => 'Manajemen'],
            ['code' => '86207', 'name' => 'Pendidikan Guru PAUD'],
            ['code' => '70201', 'name' => 'Ilmu Administrasi Negara'],
            ['code' => '84205', 'name' => 'Biologi'],
            ['code' => '55201', 'name' => 'Matematika'],
            ['code' => '57201', 'name' => 'Sistem Informasi'],
            ['code' => '88203', 'name' => 'Pendidikan Bahasa Inggris'],
        ];
    }

    /** @return list<array{catalog_key: string, item_type: string, item_code: string, edition: string, title: string}> */
    private function catalogItems(): array
    {
        return [
            ['catalog_key' => 'PKT-MKDU4109', 'item_type' => 'package', 'item_code' => 'MKDU4109', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Pendidikan Kewarganegaraan'],
            ['catalog_key' => 'PKT-EKMA4111', 'item_type' => 'package', 'item_code' => 'EKMA4111', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Pengantar Bisnis'],
            ['catalog_key' => 'PKT-PAUD4201', 'item_type' => 'package', 'item_code' => 'PAUD4201', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Perkembangan Anak'],
            ['catalog_key' => 'PKT-ISIP4216', 'item_type' => 'package', 'item_code' => 'ISIP4216', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Metode Penelitian Sosial'],
            ['catalog_key' => 'PKT-BIOL4210', 'item_type' => 'package', 'item_code' => 'BIOL4210', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Biologi Umum'],
            ['catalog_key' => 'BKU-MKDU4109-1', 'item_type' => 'book', 'item_code' => 'MKDU4109', 'edition' => '1', 'title' => 'Pendidikan Kewarganegaraan'],
            ['catalog_key' => 'BKU-EKMA4111-1', 'item_type' => 'book', 'item_code' => 'EKMA4111', 'edition' => '2', 'title' => 'Pengantar Bisnis'],
            ['catalog_key' => 'BKU-PAUD4201-1', 'item_type' => 'book', 'item_code' => 'PAUD4201', 'edition' => '1', 'title' => 'Perkembangan Anak Usia Dini'],
            ['catalog_key' => 'BKU-ISIP4216-1', 'item_type' => 'book', 'item_code' => 'ISIP4216', 'edition' => '1', 'title' => 'Metode Penelitian Sosial'],
            ['catalog_key' => 'BKU-BIOL4210-1', 'item_type' => 'book', 'item_code' => 'BIOL4210', 'edition' => '1', 'title' => 'Biologi Umum'],
        ];
    }

    /** @return list<array{code: string, name: string}> */
    private function periods(): array
    {
        return [
            ['code' => '20252', 'name' => '2025/2026 Genap'],
            ['code' => '20261', 'name' => '2026/2027 Ganjil'],
        ];
    }
}
