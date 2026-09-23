<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * LOCAL DEVELOPMENT ONLY — never run in production.
 *
 * Creates demo login users for every internal role plus the three Prodev vendors,
 * matching the demo rows in ParamitaRepairSeeder::seedDemoUsers(). All users share
 * one random password that is written to storage/app/local-demo-users.txt (gitignored)
 * and NEVER echoed to console output or stored in source.
 *
 * Safe to re-run: uses updateOrCreate per email, so the local-demo-users.txt password
 * stays authoritative across resets.
 */
class LocalDemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->guardLocalOnly()) {
            return;
        }

        $now = now();
        $password = bin2hex(random_bytes(32));

        foreach ($this->demoUsers() as $demo) {
            $role = $demo['role'];
            Role::findOrCreate($role, 'web');

            $utId = $demo['ut_code'] !== null
                ? DB::table('ut_regions')->where('code', $demo['ut_code'])->value('id')
                : null;
            $vendorId = $demo['vendor_code'] !== null
                ? DB::table('vendors')->where('code', $demo['vendor_code'])->value('id')
                : null;

            $user = User::query()->updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make($password),
                    'role' => $role,
                    'status' => 'active',
                    'email_verified_at' => $now,
                    'ut_id' => $utId,
                    'vendor_id' => $vendorId,
                    'permission_revision' => 1,
                ]
            );

            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$role]);
            }
        }

        $this->attachTutorPrograms($now);
        $this->writeCredentialFile($password);
    }

    /** @return list<array{email: string, name: string, role: string, ut_code: ?string, vendor_code: ?string}> */
    private function demoUsers(): array
    {
        return [
            ['email' => 'admin@paramita.test',        'name' => 'Admin UT Pusat',       'role' => 'admin',            'ut_code' => null,         'vendor_code' => null],
            ['email' => 'kepala.pusat@paramita.test', 'name' => 'Kepala UT Pusat',      'role' => 'kepala_ut_pusat',  'ut_code' => null,         'vendor_code' => null],
            ['email' => 'kepala.daerah@paramita.test', 'name' => 'Kepala UT Bandung',    'role' => 'kepala_ut_daerah', 'ut_code' => 'UN31.UT15',  'vendor_code' => null],
            ['email' => 'tutor@paramita.test',        'name' => 'Tutor Manajemen',      'role' => 'tutor',            'ut_code' => 'UN31.UT15',  'vendor_code' => null],
            ['email' => 'vendor.gramedia@paramita.test', 'name' => 'Vendor Gramedia',   'role' => 'vendor',           'ut_code' => null,         'vendor_code' => 'GRAMEDIA'],
            ['email' => 'vendor.temprina@paramita.test', 'name' => 'Vendor Temprina',   'role' => 'vendor',           'ut_code' => null,         'vendor_code' => 'TEMPRINA'],
            ['email' => 'vendor.macanan@paramita.test',  'name' => 'Vendor Macanan',    'role' => 'vendor',           'ut_code' => null,         'vendor_code' => 'MACANAN'],
        ];
    }

    private function attachTutorPrograms(Carbon $now): void
    {
        $tutor = User::query()->where('email', 'tutor@paramita.test')->first();
        if (! $tutor) {
            return;
        }

        foreach (['61201', '70201'] as $programCode) {
            $programId = DB::table('programs')->where('code', $programCode)->value('id');
            if ($programId) {
                DB::table('tutor_program')->updateOrInsert(
                    ['user_id' => $tutor->id, 'program_id' => $programId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    private function writeCredentialFile(string $password): void
    {
        $lines = [
            'Paramita Final local demo users',
            'DO NOT COMMIT. Local development only.',
            'Password for all demo users: '.$password,
            '',
        ];
        foreach ($this->demoUsers() as $demo) {
            $lines[] = $demo['role'].' | '.$demo['email'].' | '.$demo['name'];
        }

        file_put_contents(
            storage_path('app/local-demo-users.txt'),
            implode("\r\n", $lines)."\r\n"
        );
    }

    private function guardLocalOnly(): bool
    {
        // Skip silently in production or testing: demo users are only meaningful
        // in a local development database. CI runs in 'testing' environment and
        // does not have PARAMITA_INTEGRATION_KEY set.
        if (app()->environment('production', 'testing')) {
            return false;
        }

        // Require the integration key so the Prodev connection seed can encrypt its
        // vendor credentials. Without it the encrypter would throw on boot anyway.
        $rawKey = getenv('PARAMITA_INTEGRATION_KEY') ?: config('app.paramita_integration_key');
        if (empty($rawKey)) {
            return false;
        }

        return true;
    }
}
