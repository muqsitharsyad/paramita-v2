<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor', 'vendor'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
