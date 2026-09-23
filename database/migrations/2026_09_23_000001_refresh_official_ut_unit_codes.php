<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{old_code: string, new_code: string, old_name: string, new_name: string, type: string}> */
    private array $units = [
        ['old_code' => '90', 'new_code' => 'UN31.UT40', 'old_name' => 'Layanan Luar Negeri', 'new_name' => 'Layanan Luar Negeri', 'type' => 'luar_negeri'],
        ['old_code' => '10', 'new_code' => 'UN31.UT1', 'old_name' => 'Sorong', 'new_name' => 'Sorong', 'type' => 'daerah'],
        ['old_code' => '11', 'new_code' => 'UN31.UT2', 'old_name' => 'Banda Aceh', 'new_name' => 'Banda Aceh', 'type' => 'daerah'],
        ['old_code' => '12', 'new_code' => 'UN31.UT3', 'old_name' => 'Medan', 'new_name' => 'Medan', 'type' => 'daerah'],
        ['old_code' => '13', 'new_code' => 'UN31.UT4', 'old_name' => 'Batam', 'new_name' => 'Batam', 'type' => 'daerah'],
        ['old_code' => '14', 'new_code' => 'UN31.UT5', 'old_name' => 'Padang', 'new_name' => 'Padang', 'type' => 'daerah'],
        ['old_code' => '15', 'new_code' => 'UN31.UT6', 'old_name' => 'Pangkalpinang', 'new_name' => 'Pangkalpinang', 'type' => 'daerah'],
        ['old_code' => '16', 'new_code' => 'UN31.UT7', 'old_name' => 'Pekanbaru', 'new_name' => 'Pekanbaru', 'type' => 'daerah'],
        ['old_code' => '17', 'new_code' => 'UN31.UT8', 'old_name' => 'Jambi', 'new_name' => 'Jambi', 'type' => 'daerah'],
        ['old_code' => '18', 'new_code' => 'UN31.UT9', 'old_name' => 'Palembang', 'new_name' => 'Palembang', 'type' => 'daerah'],
        ['old_code' => '19', 'new_code' => 'UN31.UT10', 'old_name' => 'Bengkulu', 'new_name' => 'Bengkulu', 'type' => 'daerah'],
        ['old_code' => '20', 'new_code' => 'UN31.UT11', 'old_name' => 'Bandar Lampung', 'new_name' => 'Bandar Lampung', 'type' => 'daerah'],
        ['old_code' => '21', 'new_code' => 'UN31.UT12', 'old_name' => 'Jakarta', 'new_name' => 'Jakarta', 'type' => 'daerah'],
        ['old_code' => '22', 'new_code' => 'UN31.UT13', 'old_name' => 'Serang', 'new_name' => 'Serang', 'type' => 'daerah'],
        ['old_code' => '23', 'new_code' => 'UN31.UT14', 'old_name' => 'Bogor', 'new_name' => 'Bogor', 'type' => 'daerah'],
        ['old_code' => '24', 'new_code' => 'UN31.UT15', 'old_name' => 'Bandung', 'new_name' => 'Bandung', 'type' => 'daerah'],
        ['old_code' => '41', 'new_code' => 'UN31.UT16', 'old_name' => 'Purwokerto', 'new_name' => 'Purwokerto', 'type' => 'daerah'],
        ['old_code' => '42', 'new_code' => 'UN31.UT17', 'old_name' => 'Semarang', 'new_name' => 'Semarang', 'type' => 'daerah'],
        ['old_code' => '44', 'new_code' => 'UN31.UT18', 'old_name' => 'Surakarta', 'new_name' => 'Surakarta', 'type' => 'daerah'],
        ['old_code' => '45', 'new_code' => 'UN31.UT19', 'old_name' => 'Yogyakarta', 'new_name' => 'Yogyakarta', 'type' => 'daerah'],
        ['old_code' => '47', 'new_code' => 'UN31.UT20', 'old_name' => 'Pontianak', 'new_name' => 'Pontianak', 'type' => 'daerah'],
        ['old_code' => '48', 'new_code' => 'UN31.UT21', 'old_name' => 'Palangka Raya', 'new_name' => 'Palangkaraya', 'type' => 'daerah'],
        ['old_code' => '49', 'new_code' => 'UN31.UT22', 'old_name' => 'Banjarmasin', 'new_name' => 'Banjarmasin', 'type' => 'daerah'],
        ['old_code' => '50', 'new_code' => 'UN31.UT23', 'old_name' => 'Samarinda', 'new_name' => 'Samarinda', 'type' => 'daerah'],
        ['old_code' => '51', 'new_code' => 'UN31.UT24', 'old_name' => 'Tarakan', 'new_name' => 'Tarakan', 'type' => 'daerah'],
        ['old_code' => '71', 'new_code' => 'UN31.UT25', 'old_name' => 'Surabaya', 'new_name' => 'Surabaya', 'type' => 'daerah'],
        ['old_code' => '74', 'new_code' => 'UN31.UT26', 'old_name' => 'Malang', 'new_name' => 'Malang', 'type' => 'daerah'],
        ['old_code' => '76', 'new_code' => 'UN31.UT27', 'old_name' => 'Jember', 'new_name' => 'Jember', 'type' => 'daerah'],
        ['old_code' => '77', 'new_code' => 'UN31.UT28', 'old_name' => 'Denpasar', 'new_name' => 'Denpasar', 'type' => 'daerah'],
        ['old_code' => '78', 'new_code' => 'UN31.UT29', 'old_name' => 'Mataram', 'new_name' => 'Mataram', 'type' => 'daerah'],
        ['old_code' => '79', 'new_code' => 'UN31.UT30', 'old_name' => 'Kupang', 'new_name' => 'Kupang', 'type' => 'daerah'],
        ['old_code' => '80', 'new_code' => 'UN31.UT31', 'old_name' => 'Makassar', 'new_name' => 'Makassar', 'type' => 'daerah'],
        ['old_code' => '81', 'new_code' => 'UN31.UT32', 'old_name' => 'Majene', 'new_name' => 'Majene', 'type' => 'daerah'],
        ['old_code' => '82', 'new_code' => 'UN31.UT33', 'old_name' => 'Palu', 'new_name' => 'Palu', 'type' => 'daerah'],
        ['old_code' => '83', 'new_code' => 'UN31.UT34', 'old_name' => 'Kendari', 'new_name' => 'Kendari', 'type' => 'daerah'],
        ['old_code' => '84', 'new_code' => 'UN31.UT35', 'old_name' => 'Manado', 'new_name' => 'Manado', 'type' => 'daerah'],
        ['old_code' => '85', 'new_code' => 'UN31.UT36', 'old_name' => 'Gorontalo', 'new_name' => 'Gorontalo', 'type' => 'daerah'],
        ['old_code' => '86', 'new_code' => 'UN31.UT37', 'old_name' => 'Ambon', 'new_name' => 'Ambon', 'type' => 'daerah'],
        ['old_code' => '87', 'new_code' => 'UN31.UT38', 'old_name' => 'Jayapura', 'new_name' => 'Jayapura', 'type' => 'daerah'],
        ['old_code' => '89', 'new_code' => 'UN31.UT39', 'old_name' => 'Ternate', 'new_name' => 'Ternate', 'type' => 'daerah'],
    ];

    public function up(): void
    {
        foreach ($this->units as $unit) {
            DB::table('ut_regions')
                ->where('code', $unit['old_code'])
                ->update([
                    'code' => $unit['new_code'],
                    'name' => $unit['new_name'],
                    'type' => $unit['type'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->units) as $unit) {
            DB::table('ut_regions')
                ->where('code', $unit['new_code'])
                ->update([
                    'code' => $unit['old_code'],
                    'name' => $unit['old_name'],
                    'type' => $unit['type'],
                    'updated_at' => now(),
                ]);
        }
    }
};
