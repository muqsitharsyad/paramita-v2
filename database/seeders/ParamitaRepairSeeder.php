<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\Contracts\VendorContractGuide;
use App\Services\Security\IntegrationKeyEncrypter;
use Database\Seeders\Support\OfficialUtUnits;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ParamitaRepairSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('vendor_ut_scope')->whereIn('ut_id', DB::table('ut_regions')->where('code', 'like', 'UT-DEMO-%')->select('id'))->delete();
        DB::table('tutor_program')->whereIn('program_id', DB::table('programs')->where('code', 'like', 'PRODI-DEMO-%')->select('id'))->delete();
        DB::table('ut_regions')->where('code', 'like', 'UT-DEMO-%')->delete();
        DB::table('programs')->where('code', 'like', 'PRODI-DEMO-%')->delete();
        DB::table('catalog_items')->where(function ($query): void {
            $query->where('catalog_key', 'like', '%DEMO%')
                ->orWhere('item_code', 'like', '%DEMO%');
        })->delete();

        foreach (['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor', 'vendor'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $units = OfficialUtUnits::all();
        foreach ($units as $unit) {
            DB::table('ut_regions')->updateOrInsert(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'type' => $unit['type'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $programs = [
            ['code' => '31101', 'name' => 'Pendidikan Pancasila dan Kewarganegaraan'],
            ['code' => '61201', 'name' => 'Manajemen'],
            ['code' => '86207', 'name' => 'Pendidikan Guru PAUD'],
            ['code' => '70201', 'name' => 'Ilmu Administrasi Negara'],
            ['code' => '84205', 'name' => 'Biologi'],
            ['code' => '55201', 'name' => 'Matematika'],
            ['code' => '57201', 'name' => 'Sistem Informasi'],
            ['code' => '88203', 'name' => 'Pendidikan Bahasa Inggris'],
        ];
        foreach ($programs as $program) {
            DB::table('programs')->updateOrInsert(
                ['code' => $program['code']],
                ['name' => $program['name'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $catalogs = [
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
        foreach ($catalogs as $catalog) {
            DB::table('catalog_items')->updateOrInsert(
                ['catalog_key' => $catalog['catalog_key']],
                array_merge($catalog, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }

        $admin = User::query()->where('role', 'admin')->first();
        if (! $admin) {
            $admin = User::query()->updateOrCreate(
                ['email' => 'admin@paramita.test'],
                [
                    'name' => 'Admin UT Pusat',
                    'password' => Hash::make(bin2hex(random_bytes(16))),
                    'role' => 'admin',
                    'status' => 'active',
                    'email_verified_at' => $now,
                    'ut_id' => null,
                    'vendor_id' => null,
                    'permission_revision' => 1,
                ]
            );
        }
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        $vendors = [
            ['code' => 'GRAMEDIA', 'legal_name' => 'Gramedia Printing Group', 'slug' => 'gramedia', 'contact_name' => 'Tim Integrasi Gramedia', 'contact_email' => 'integration.gramedia@example.test'],
            ['code' => 'TEMPRINA', 'legal_name' => 'Temprina Media Grafika', 'slug' => 'temprina', 'contact_name' => 'Tim Integrasi Temprina', 'contact_email' => 'integration.temprina@example.test'],
            ['code' => 'MACANAN', 'legal_name' => 'Macanan Jaya Cemerlang', 'slug' => 'macanan', 'contact_name' => 'Tim Integrasi Macanan', 'contact_email' => 'integration.macanan@example.test'],
        ];

        foreach ($vendors as $vendor) {
            $vendorId = DB::table('vendors')->updateOrInsert(
                ['code' => $vendor['code']],
                [
                    'legal_name' => $vendor['legal_name'],
                    'contact_name' => $vendor['contact_name'],
                    'contact_email' => $vendor['contact_email'],
                    'status' => 'approved',
                    'approved_by' => $admin?->id,
                    'approved_at' => $now,
                    'rejection_note' => null,
                    'scope_revision' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $vendorRow = DB::table('vendors')->where('code', $vendor['code'])->first();
            $vendorId = $vendorRow->id;

            foreach (DB::table('ut_regions')->pluck('id') as $utId) {
                DB::table('vendor_ut_scope')->updateOrInsert(
                    ['vendor_id' => $vendorId, 'ut_id' => $utId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }

            $authCiphertext = $this->prodevAuthCiphertext($vendor['slug']);
            $connectionLabel = 'Prodev API '.$vendor['legal_name'];
            DB::table('connections')->updateOrInsert(
                ['vendor_id' => $vendorId, 'label' => $connectionLabel],
                ['created_at' => $now, 'updated_at' => $now]
            );
            $connection = DB::table('connections')->where('vendor_id', $vendorId)->where('label', $connectionLabel)->first();

            DB::table('connection_revisions')->updateOrInsert(
                ['connection_id' => $connection->id, 'revision' => 1],
                [
                    'base_url' => 'https://prodev.ut.ac.id/paramita-vendor-api/'.$vendor['slug'],
                    'auth_type' => $authCiphertext === null ? 'none' : 'token_login',
                    'auth_config_ciphertext' => $authCiphertext,
                    'secret_version' => 1,
                    'created_by' => $admin?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $connectionRevision = DB::table('connection_revisions')->where('connection_id', $connection->id)->where('revision', 1)->first();

            foreach ($this->operationPaths() as $operationKey => $path) {
                $contract = DB::table('contracts')->where('operation_key', $operationKey)->first();
                if (! $contract) {
                    continue;
                }
                $contractVersion = DB::table('contract_versions')
                    ->where('contract_id', $contract->id)
                    ->where('status', 'published')
                    ->orderByDesc('id')
                    ->first();
                if (! $contractVersion) {
                    continue;
                }

                DB::table('endpoint_bindings')->updateOrInsert(
                    ['vendor_id' => $vendorId, 'contract_id' => $contract->id],
                    ['is_enabled' => true, 'created_at' => $now, 'updated_at' => $now]
                );
                $binding = DB::table('endpoint_bindings')->where('vendor_id', $vendorId)->where('contract_id', $contract->id)->first();

                DB::table('binding_revisions')->updateOrInsert(
                    ['binding_id' => $binding->id, 'revision' => 1],
                    [
                        'connection_revision_id' => $connectionRevision->id,
                        'contract_version_id' => $contractVersion->id,
                        'path' => $path,
                        'static_query_json' => null,
                        'dispatch_mode' => 'separate_path',
                        'scope_revision' => 1,
                        'status' => 'approved',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $bindingRevision = DB::table('binding_revisions')->where('binding_id', $binding->id)->where('revision', 1)->first();
                DB::table('endpoint_bindings')->where('id', $binding->id)->update([
                    'active_revision_id' => $bindingRevision->id,
                    'draft_revision_id' => null,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (DB::table('contract_versions as cv')->join('contracts as c', 'c.id', '=', 'cv.contract_id')->where('cv.status', 'published')->select('c.operation_key', 'c.label', 'c.module', 'cv.schema_json', 'cv.version')->get() as $contractVersion) {
            DB::table('json_templates')->updateOrInsert(
                ['name' => $contractVersion->operation_key],
                [
                    'category' => $contractVersion->module,
                    'description' => 'Production-ready vendor JSON contract for '.$contractVersion->label,
                    'template_data' => json_encode($this->productionJsonContract($contractVersion), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'version' => $contractVersion->version,
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->seedDemoUsers($now);
    }

    private function seedDemoUsers(Carbon $now): void
    {
        $users = [
            ['email' => 'admin@paramita.test', 'name' => 'Admin UT Pusat', 'role' => 'admin', 'ut_code' => null, 'vendor_code' => null],
            ['email' => 'kepala.pusat@paramita.test', 'name' => 'Kepala UT Pusat', 'role' => 'kepala_ut_pusat', 'ut_code' => null, 'vendor_code' => null],
            ['email' => 'kepala.daerah@paramita.test', 'name' => 'Kepala UT Bandung', 'role' => 'kepala_ut_daerah', 'ut_code' => 'UN31.UT15', 'vendor_code' => null],
            ['email' => 'tutor@paramita.test', 'name' => 'Tutor Manajemen', 'role' => 'tutor', 'ut_code' => 'UN31.UT15', 'vendor_code' => null],
            ['email' => 'vendor.gramedia@paramita.test', 'name' => 'Vendor Gramedia', 'role' => 'vendor', 'ut_code' => null, 'vendor_code' => 'GRAMEDIA'],
            ['email' => 'vendor.temprina@paramita.test', 'name' => 'Vendor Temprina', 'role' => 'vendor', 'ut_code' => null, 'vendor_code' => 'TEMPRINA'],
            ['email' => 'vendor.macanan@paramita.test', 'name' => 'Vendor Macanan', 'role' => 'vendor', 'ut_code' => null, 'vendor_code' => 'MACANAN'],
        ];

        foreach ($users as $demo) {
            $utId = $demo['ut_code'] ? DB::table('ut_regions')->where('code', $demo['ut_code'])->value('id') : null;
            $vendorId = $demo['vendor_code'] ? DB::table('vendors')->where('code', $demo['vendor_code'])->value('id') : null;
            $user = User::query()->updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make(bin2hex(random_bytes(32))),
                    'role' => $demo['role'],
                    'status' => 'active',
                    'email_verified_at' => $now,
                    'ut_id' => $utId,
                    'vendor_id' => $vendorId,
                    'permission_revision' => 1,
                ]
            );
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$demo['role']]);
            }
        }

        $tutor = User::query()->where('email', 'tutor@paramita.test')->first();
        if ($tutor) {
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
    }

    private function prodevAuthCiphertext(string $vendorSlug): ?string
    {
        $username = (string) getenv('PARAMITA_VENDOR_API_USERNAME');
        $password = (string) getenv('PARAMITA_VENDOR_API_PASSWORD');
        if ($username === '' || $password === '') {
            return null;
        }

        return app(IntegrationKeyEncrypter::class)->encrypt(json_encode([
            'login_url' => 'https://prodev.ut.ac.id/paramita-vendor-api/'.$vendorSlug.'/v1/auth/login',
            'username' => $username,
            'password' => $password,
            'username_field' => 'username',
            'password_field' => 'password',
            'token_field' => 'data.access_token',
            'expires_in_field' => 'data.expires_in',
        ], JSON_THROW_ON_ERROR));
    }

    private function productionJsonContract(object $contractVersion): array
    {
        $guide = (new VendorContractGuide)
            ->forOperation((string) $contractVersion->operation_key);

        return $guide['response_example'];
    }

    private function operationPaths(): array
    {
        return [
            'inventory.list' => 'v1/inventory/list',
            'inventory.summary' => 'v1/inventory/summary',
            'inventory.lookup' => 'v1/inventory/lookup',
            'orders.list' => 'v1/orders/list',
            'orders.summary' => 'v1/orders/summary',
            'orders.detail' => 'v1/orders/{source_id}',
            'orders.events' => 'v1/orders/{source_id}/events',
            'orders.analytics' => 'v1/orders/analytics',
        ];
    }
}
