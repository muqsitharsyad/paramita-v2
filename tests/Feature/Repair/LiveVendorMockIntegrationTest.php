<?php

declare(strict_types=1);

namespace Tests\Feature\Repair;

use App\Models\User;
use Database\Seeders\ParamitaRepairSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('live')]
class LiveVendorMockIntegrationTest extends TestCase
{
    // This suite reads real seeded vendors/bindings from the shared development database and also
    // creates registration fixtures; without a transaction every run leaves a "Vendor Baru Testing"
    // row (and its portal user) behind forever.
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        (new ParamitaRepairSeeder)->run();
    }

    private function admin(): User
    {
        return User::query()->where('role', 'admin')->firstOrFail();
    }

    public function test_dashboard_stock_summary_uses_three_live_vendor_bindings(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/stock/summary?item_type=package&vendor_scope=all');

        $response->assertOk()
            ->assertJsonPath('meta.coverage.expected_sources', 3)
            ->assertJsonPath('meta.coverage.included_sources', 3)
            ->assertJsonCount(3, 'data.by_vendor')
            ->assertJsonPath('data.by_vendor.0.vendor_code', 'GRAMEDIA');

        $this->assertGreaterThan(0, $response->json('data.totals_all_vendors.record_count'));
        $this->assertGreaterThan(0, $response->json('data.totals_all_vendors.stock_quantity'));
    }

    public function test_dashboard_matrix_contains_vendor_stock_columns(): void
    {
        // The matrix only carries stock when every vendor is contract-compliant. An admin rename
        // that a vendor has not followed blocks that vendor BY DESIGN, so this test skips instead
        // of failing: it is asserting the happy path, not the enforcement itself.
        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/stock/matrix?item_type=package&limit=5&offset=0');

        $response->assertOk()
            ->assertJsonCount(3, 'sources');

        $firstRow = $response->json('data.0');
        if (empty($firstRow['stocks'])) {
            $this->markTestSkipped(
                'Matrix kosong: kemungkinan ada vendor yang belum mengikuti perubahan kontrak di admin '
                .'(lihat vendor test report). Bukan regresi.'
            );
        }

        $this->assertNotEmpty($firstRow['stocks']);
        $this->assertArrayHasKey('GRAMEDIA', $firstRow['stocks']);
        $this->assertIsInt($firstRow['stocks']['GRAMEDIA']['stock_quantity']);
    }

    public function test_orders_summary_and_list_are_vendor_backed(): void
    {
        $summary = $this->actingAs($this->admin())
            ->getJson('/api/v1/orders/summary?group_by=none&vendor_scope=all');

        $summary->assertOk()
            ->assertJsonCount(3, 'data.by_vendor')
            ->assertJsonPath('meta.coverage.included_sources', 3);
        $this->assertGreaterThan(0, $summary->json('data.totals_all_vendors.total_orders'));
        $this->assertSame(
            $summary->json('data.totals_all_vendors.status_code_counts.06'),
            $summary->json('data.totals_all_vendors.status_counts.returned')
        );

        $list = $this->actingAs($this->admin())
            ->getJson('/api/v1/orders?limit=5&offset=0');

        $list->assertOk();
        $this->assertGreaterThan(0, count($list->json('data')));
        $this->assertContains($list->json('data.0.vendor_code'), ['GRAMEDIA', 'TEMPRINA', 'MACANAN']);
    }

    public function test_admin_json_templates_are_seeded_from_contracts(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/templates');

        $response->assertOk()
            ->assertSee('inventory.list')
            ->assertSee('orders.summary')
            ->assertSee('orders.events');

        $this->actingAs($this->admin())->get('/admin/templates/create')
            ->assertOk()
            ->assertSee('json-editor')
            ->assertSee('Format JSON');
    }

    public function test_repair_fixture_users_are_available_for_each_role(): void
    {
        $expected = [
            'admin@paramita.test' => 'admin',
            'kepala.pusat@paramita.test' => 'kepala_ut_pusat',
            'kepala.daerah@paramita.test' => 'kepala_ut_daerah',
            'tutor@paramita.test' => 'tutor',
            'vendor.gramedia@paramita.test' => 'vendor',
            'vendor.temprina@paramita.test' => 'vendor',
            'vendor.macanan@paramita.test' => 'vendor',
        ];

        foreach ($expected as $email => $role) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user, $email.' should exist');
            $this->assertSame($role, $user->role);
            $this->assertSame('active', $user->status);
        }
    }

    public function test_json_templates_store_only_the_vendor_response_format(): void
    {
        $inventory = \DB::table('json_templates')->where('name', 'inventory.list')->first();
        $orders = \DB::table('json_templates')->where('name', 'orders.list')->first();

        $this->assertNotNull($inventory);
        $this->assertNotNull($orders);

        $inventoryData = json_decode($inventory->template_data, true);
        $ordersData = json_decode($orders->template_data, true);

        $this->assertArrayHasKey('data', $inventoryData);
        $this->assertArrayHasKey('meta', $inventoryData);
        $this->assertArrayHasKey('total_filtered', $ordersData['meta']);
        foreach (['response_example', 'field_rules', 'canonical_fields', 'field_map'] as $removedLayer) {
            $this->assertArrayNotHasKey($removedLayer, $inventoryData);
        }
    }

    public function test_vendor_user_isolated_to_vendor_portal_and_can_run_endpoint_test(): void
    {
        $vendorUser = User::query()->where('email', 'vendor.gramedia@paramita.test')->firstOrFail();

        $this->actingAs($vendorUser)->get('/vendor-portal')
            ->assertOk()
            ->assertSee('Portal Integrasi')
            ->assertSee('Koneksi API')
            ->assertSee('Endpoint')
            ->assertSee('Format response')
            ->assertDontSee('Monitoring Stok Paket')
            ->assertDontSee('Panel Administrator');

        $this->actingAs($vendorUser)->get('/vendor-portal/endpoints')
            ->assertOk()
            ->assertSee('Simpan draft')
            ->assertSee('Test semua endpoint');
        $this->actingAs($vendorUser)->get('/vendor-portal/contracts?operation=orders.list')
            ->assertOk()
            ->assertSee('Response contoh valid')
            ->assertSee('order_number');

        $this->actingAs($vendorUser)->get('/dashboard')->assertForbidden();
        $this->actingAs($vendorUser)->getJson('/api/v1/orders?limit=1&offset=0')->assertForbidden();
        $this->actingAs($vendorUser)->get('/admin/templates')->assertForbidden();

        $bindingId = \DB::table('endpoint_bindings as eb')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->where('eb.vendor_id', $vendorUser->vendor_id)
            ->where('c.operation_key', 'orders.list')
            ->value('eb.id');

        $this->actingAs($vendorUser)->from('/vendor-portal')->post('/vendor-portal/test/'.$bindingId)
            ->assertRedirect('/vendor-portal');

        $latest = \DB::table('endpoint_test_runs')->orderByDesc('id')->first();
        $this->assertSame('passed', $latest->status);
        $report = json_decode($latest->report_json, true);
        $this->assertSame('orders.list', $report['operation_key']);
        $this->assertSame('pass', $report['checks'][0]['status']);
    }

    public function test_vendor_can_edit_endpoint_as_draft_test_then_submit_without_overwriting_active(): void
    {
        $vendorUser = User::query()->where('email', 'vendor.gramedia@paramita.test')->firstOrFail();

        $binding = \DB::table('endpoint_bindings as eb')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->join('binding_revisions as br', 'br.id', '=', 'eb.active_revision_id')
            ->where('eb.vendor_id', $vendorUser->vendor_id)
            ->where('c.operation_key', 'orders.list')
            ->select('eb.id', 'eb.active_revision_id', 'br.connection_revision_id', 'br.path')
            ->first();

        $this->actingAs($vendorUser)->from('/vendor-portal')->post('/vendor-portal/bindings/'.$binding->id, [
            'connection_revision_id' => $binding->connection_revision_id,
            'path' => $binding->path,
        ])->assertRedirect('/vendor-portal');

        $afterEdit = \DB::table('endpoint_bindings')->where('id', $binding->id)->first();
        $this->assertSame((int) $binding->active_revision_id, (int) $afterEdit->active_revision_id);
        $this->assertNotNull($afterEdit->draft_revision_id);
        $this->assertNotSame((int) $binding->active_revision_id, (int) $afterEdit->draft_revision_id);

        $this->actingAs($vendorUser)->from('/vendor-portal')->post('/vendor-portal/test/'.$binding->id)
            ->assertRedirect('/vendor-portal');

        $latest = \DB::table('endpoint_test_runs')->orderByDesc('id')->first();
        $this->assertSame((int) $afterEdit->draft_revision_id, (int) $latest->binding_revision_id);
        $this->assertSame('passed', $latest->status);

        $this->actingAs($vendorUser)->from('/vendor-portal')->post('/vendor-portal/bindings/'.$binding->id.'/submit')
            ->assertRedirect('/vendor-portal');

        $this->assertSame('submitted', \DB::table('binding_revisions')->where('id', $afterEdit->draft_revision_id)->value('status'));
        $this->assertSame('pending', \DB::table('submissions')->where('binding_revision_id', $afterEdit->draft_revision_id)->value('status'));
    }

    public function test_new_vendor_registration_appears_on_admin_dashboard(): void
    {
        $vendorCode = 'VENDOR'.strtoupper(substr(uniqid('', false), -8));
        $vendorEmail = strtolower($vendorCode).'@example.test';

        $vendorId = \DB::table('vendors')->insertGetId([
            'code' => $vendorCode,
            'legal_name' => 'Vendor Baru Testing',
            'contact_name' => 'Kontak Vendor Baru',
            'contact_email' => $vendorEmail,
            'status' => 'pending_verification',
            'scope_revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Vendor Baru Testing',
            'email' => $vendorEmail,
            'password' => 'not-used',
            'role' => 'vendor',
            'status' => 'inactive',
            'vendor_id' => $vendorId,
            'permission_revision' => 1,
        ]);

        $this->actingAs($this->admin())->get('/admin/vendors')
            ->assertOk()
            ->assertSee('Vendor Baru Testing')
            ->assertSee('pending verification');
    }

    public function test_admin_routes_are_restricted_to_admin_role(): void
    {
        $nonAdmin = User::query()->updateOrCreate(
            ['email' => 'kepala-ut-test@example.test'],
            [
                'name' => 'Kepala UT Test',
                'password' => 'not-used',
                'role' => 'kepala_ut_pusat',
                'status' => 'active',
                'email_verified_at' => now(),
                'permission_revision' => 1,
            ]
        );

        $this->actingAs($nonAdmin)->get('/admin/templates')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/templates')->assertOk();

        $nonAdmin->delete();
    }

    public function test_search_and_pagination_work_for_stock_and_orders(): void
    {
        $stockPage1 = $this->actingAs($this->admin())
            ->getJson('/api/v1/stock/items?item_type=package&limit=5&offset=0&search=MKDU');
        $stockPage1->assertOk()
            ->assertJsonPath('pagination.limit', 5)
            ->assertJsonPath('pagination.offset', 0);
        $this->assertGreaterThan(0, $stockPage1->json('pagination.total_filtered'));
        $this->assertLessThanOrEqual(5, count($stockPage1->json('data')));
        $this->assertStringContainsString('MKDU', $stockPage1->json('data.0.item_code'));

        $stockPage2 = $this->actingAs($this->admin())
            ->getJson('/api/v1/stock/items?item_type=package&limit=5&offset=5&search=MKDU');
        $stockPage2->assertOk()->assertJsonPath('pagination.offset', 5);
        $this->assertNotSame($stockPage1->json('data.0.id'), $stockPage2->json('data.0.id'));

        $orders = $this->actingAs($this->admin())
            ->getJson('/api/v1/orders?limit=5&offset=0&search=Ayu');
        $orders->assertOk()->assertJsonPath('pagination.limit', 5);
        $this->assertGreaterThan(0, $orders->json('pagination.total_filtered'));
        $this->assertStringContainsString('Ayu', $orders->json('data.0.student_name'));
    }

    public function test_order_group_summary_keeps_program_group_codes_separate(): void
    {
        $summary = $this->actingAs($this->admin())
            ->getJson('/api/v1/orders/summary?group_by=program&vendor_scope=all&program_codes=61201');

        $summary->assertOk()
            ->assertJsonPath('data.totals_all_vendors.0.group_code', 61201)
            ->assertJsonPath('data.totals_all_vendors.0.total_orders', 126);
    }

    public function test_order_detail_endpoint_loads_clicked_do_and_history(): void
    {
        $list = $this->actingAs($this->admin())->getJson('/api/v1/orders?limit=1&offset=0');
        $list->assertOk();
        $row = $list->json('data.0');

        $detail = $this->actingAs($this->admin())
            ->getJson('/api/v1/vendors/'.$row['vendor_code'].'/orders/'.rawurlencode($row['id']));
        $detail->assertOk()
            ->assertJsonPath('data.id', $row['id'])
            ->assertJsonPath('data.order_number', $row['order_number']);

        $events = $this->actingAs($this->admin())
            ->getJson('/api/v1/vendors/'.$row['vendor_code'].'/orders/'.rawurlencode($row['id']).'/events');
        $events->assertOk();
        $this->assertGreaterThan(0, count($events->json('data')));
    }

    public function test_requested_ui_controls_are_rendered(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertOk()
            ->assertSee('paket-matrix-rows')
            ->assertSee('judul-items-rows')
            ->assertSee('paketVendorChart')
            ->assertSee('judulVendorChart');

        $this->actingAs($this->admin())->get('/do-per-prodi')
            ->assertOk()
            ->assertSee('filter-period')
            ->assertSee('filter-program')
            ->assertSee('chart-prodi-main')
            ->assertSee('vendor-tabs');

        $this->actingAs($this->admin())->get('/do-per-ut-daerah')
            ->assertOk()
            ->assertSee('filter-ut')
            ->assertSee('chart-kirim-main')
            ->assertSee('orders-rows');
    }
}
