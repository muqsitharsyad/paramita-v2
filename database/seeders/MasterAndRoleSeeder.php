<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MasterAndRoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ParamitaRepairSeeder::class);
    }
}
