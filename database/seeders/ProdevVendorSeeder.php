<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Security\IntegrationKeyEncrypter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProdevVendorSeeder extends Seeder
{
    public function run(): void
    {
        $username = (string) getenv('PARAMITA_VENDOR_API_USERNAME');
        $password = (string) getenv('PARAMITA_VENDOR_API_PASSWORD');
        $hasCreds = $username !== '' && $password !== '';

        $now = now();
        $adminId = DB::table('users')->where('role', 'admin')->value('id');
        $ciphertext = $hasCreds
            ? app(IntegrationKeyEncrypter::class)->encrypt(json_encode([
                'login_url' => null,
                'username' => $username,
                'password' => $password,
                'username_field' => 'username',
                'password_field' => 'password',
                'token_field' => 'data.access_token',
                'expires_in_field' => 'data.expires_in',
            ], JSON_THROW_ON_ERROR))
            : null;

        foreach ($this->vendors() as $vendor) {
            DB::table('vendors')->updateOrInsert(
                ['code' => $vendor['code']],
                [
                    'legal_name' => $vendor['legal_name'],
                    'contact_name' => $vendor['contact_name'],
                    'contact_email' => $vendor['contact_email'],
                    'status' => 'approved',
                    'approved_by' => $adminId,
                    'approved_at' => $now,
                    'rejection_note' => null,
                    'scope_revision' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $vendorId = (int) DB::table('vendors')->where('code', $vendor['code'])->value('id');
            foreach (DB::table('ut_regions')->where('is_active', true)->pluck('id') as $utId) {
                DB::table('vendor_ut_scope')->updateOrInsert(
                    ['vendor_id' => $vendorId, 'ut_id' => $utId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }

            DB::table('connections')->updateOrInsert(
                ['vendor_id' => $vendorId, 'label' => 'Prodev API '.$vendor['legal_name']],
                ['created_at' => $now, 'updated_at' => $now]
            );
            $connectionId = (int) DB::table('connections')
                ->where('vendor_id', $vendorId)
                ->where('label', 'Prodev API '.$vendor['legal_name'])
                ->value('id');

            $vendorCiphertext = null;
            if ($ciphertext !== null) {
                $authConfig = json_decode(app(IntegrationKeyEncrypter::class)->decrypt($ciphertext), true, flags: JSON_THROW_ON_ERROR);
                $authConfig['login_url'] = 'https://prodev.ut.ac.id/paramita-vendor-api/'.$vendor['slug'].'/v1/auth/login';
                $vendorCiphertext = app(IntegrationKeyEncrypter::class)->encrypt(json_encode($authConfig, JSON_THROW_ON_ERROR));
            }

            DB::table('connection_revisions')->updateOrInsert(
                ['connection_id' => $connectionId, 'revision' => 1],
                [
                    'base_url' => 'https://prodev.ut.ac.id/paramita-vendor-api/'.$vendor['slug'],
                    'auth_type' => $vendorCiphertext === null ? 'none' : 'token_login',
                    'auth_config_ciphertext' => $vendorCiphertext,
                    'secret_version' => 1,
                    'created_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $connectionRevisionId = (int) DB::table('connection_revisions')
                ->where('connection_id', $connectionId)
                ->where('revision', 1)
                ->value('id');

            foreach ($this->operationPaths() as $operationKey => $path) {
                $contract = DB::table('contracts')->where('operation_key', $operationKey)->first();
                if (! $contract) {
                    throw new RuntimeException("Missing contract {$operationKey}; seed contracts before Prodev vendors.");
                }
                $contractVersionId = DB::table('contract_versions')
                    ->where('contract_id', $contract->id)
                    ->where('status', 'published')
                    ->orderByDesc('id')
                    ->value('id');
                if (! $contractVersionId) {
                    throw new RuntimeException("Missing published contract version for {$operationKey}.");
                }

                DB::table('endpoint_bindings')->updateOrInsert(
                    ['vendor_id' => $vendorId, 'contract_id' => $contract->id],
                    ['is_enabled' => true, 'created_at' => $now, 'updated_at' => $now]
                );
                $bindingId = (int) DB::table('endpoint_bindings')
                    ->where('vendor_id', $vendorId)
                    ->where('contract_id', $contract->id)
                    ->value('id');

                DB::table('binding_revisions')->updateOrInsert(
                    ['binding_id' => $bindingId, 'revision' => 1],
                    [
                        'connection_revision_id' => $connectionRevisionId,
                        'contract_version_id' => $contractVersionId,
                        'path' => $path,
                        'static_query_json' => null,
                        'dispatch_mode' => 'separate_path',
                        'scope_revision' => 1,
                        'status' => 'approved',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $bindingRevisionId = (int) DB::table('binding_revisions')
                    ->where('binding_id', $bindingId)
                    ->where('revision', 1)
                    ->value('id');

                DB::table('endpoint_bindings')->where('id', $bindingId)->update([
                    'active_revision_id' => $bindingRevisionId,
                    'draft_revision_id' => null,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** @return list<array{code: string, legal_name: string, slug: string, contact_name: string, contact_email: string}> */
    private function vendors(): array
    {
        return [
            ['code' => 'GRAMEDIA', 'legal_name' => 'Gramedia Printing Group', 'slug' => 'gramedia', 'contact_name' => 'Tim Integrasi Gramedia', 'contact_email' => 'integration.gramedia@example.test'],
            ['code' => 'TEMPRINA', 'legal_name' => 'Temprina Media Grafika', 'slug' => 'temprina', 'contact_name' => 'Tim Integrasi Temprina', 'contact_email' => 'integration.temprina@example.test'],
            ['code' => 'MACANAN', 'legal_name' => 'Macanan Jaya Cemerlang', 'slug' => 'macanan', 'contact_name' => 'Tim Integrasi Macanan', 'contact_email' => 'integration.macanan@example.test'],
        ];
    }

    /** @return array<string, string> */
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
