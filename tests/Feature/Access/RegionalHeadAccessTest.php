<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\User;
use App\Services\Access\ScopeResolver;
use App\Services\Monitoring\MonitoringException;
use App\Services\Monitoring\MonitoringFilters;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegionalHeadAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $regionalHead;

    private object $assignedRegion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignedRegion = DB::table('ut_regions')
            ->where('code', 'UN31.UT15')
            ->where('is_active', true)
            ->firstOrFail();
        $this->regionalHead = User::factory()->create([
            'role' => 'kepala_ut_daerah',
            'status' => 'active',
            'ut_id' => $this->assignedRegion->id,
        ]);
    }

    public function test_regional_head_only_opens_three_monitoring_pages(): void
    {
        foreach (['/do-per-ut-daerah', '/analisis-sla', '/monitoring-retry'] as $path) {
            $this->actingAs($this->regionalHead)->get($path)->assertOk();
        }

        foreach (['/dashboard', '/monitoring-delivery', '/do-per-prodi', '/distribution-map'] as $path) {
            $this->actingAs($this->regionalHead)->get($path)->assertForbidden();
        }
    }

    public function test_regional_head_sidebar_only_contains_allowed_pages(): void
    {
        $response = $this->actingAs($this->regionalHead)->get('/do-per-ut-daerah');

        $response->assertOk()
            ->assertSee('/do-per-ut-daerah', false)
            ->assertSee('/analisis-sla', false)
            ->assertSee('/monitoring-retry', false)
            ->assertDontSee('/dashboard', false)
            ->assertDontSee('/monitoring-delivery', false)
            ->assertDontSee('/do-per-prodi', false)
            ->assertDontSee('/distribution-map', false);
    }

    public function test_regional_head_home_and_login_redirect_to_regional_orders(): void
    {
        $this->actingAs($this->regionalHead)
            ->get('/')
            ->assertRedirect('/do-per-ut-daerah');

        auth()->logout();
        $plainPassword = Str::random(24);
        $this->regionalHead->update(['password' => Hash::make($plainPassword)]);

        $this->post('/login', [
            'email' => $this->regionalHead->email,
            'password' => $plainPassword,
        ])->assertRedirect('/do-per-ut-daerah');
    }

    public function test_regional_head_cannot_call_stock_api(): void
    {
        $this->actingAs($this->regionalHead)
            ->getJson('/api/v1/stock/summary?item_type=package')
            ->assertForbidden();
    }

    public function test_regional_head_ut_options_only_return_assigned_region(): void
    {
        $response = $this->actingAs($this->regionalHead)->getJson('/api/v1/options/ut');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $this->assignedRegion->code)
            ->assertJsonPath('meta.scope.ut_code', $this->assignedRegion->code);
    }

    public function test_regional_head_filters_are_forced_to_assigned_ut(): void
    {
        $scope = app(ScopeResolver::class)->resolve($this->regionalHead);
        $filters = (new MonitoringFilters)->normalize([], $scope, 'orders.analytics');

        $this->assertSame($this->assignedRegion->code, $filters['ut_code']);

        $otherRegion = DB::table('ut_regions')
            ->where('is_active', true)
            ->where('id', '!=', $this->assignedRegion->id)
            ->orderBy('id')
            ->firstOrFail();

        try {
            (new MonitoringFilters)->normalize(['ut_code' => $otherRegion->code], $scope, 'orders.analytics');
            $this->fail('A regional head must not override the assigned UT.');
        } catch (MonitoringException $exception) {
            $this->assertSame(403, $exception->status);
            $this->assertSame('SCOPE_FORBIDDEN', $exception->errorCode);
        }
    }

    public function test_regional_head_cross_region_api_request_returns_safe_problem_response(): void
    {
        $otherRegion = DB::table('ut_regions')
            ->where('is_active', true)
            ->where('id', '!=', $this->assignedRegion->id)
            ->orderBy('id')
            ->firstOrFail();

        $this->actingAs($this->regionalHead)
            ->getJson('/api/v1/orders/analytics?ut_code='.$otherRegion->code)
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 403)
            ->assertJsonPath('detail', 'SCOPE_FORBIDDEN')
            ->assertJsonMissing(['exception' => MonitoringException::class])
            ->assertJsonMissingPath('trace');
    }

    public function test_regional_head_sla_chart_and_details_only_contain_assigned_ut(): void
    {
        $response = $this->actingAs($this->regionalHead)
            ->getJson('/api/v1/orders/analytics')
            ->assertOk();

        $vendors = $response->json('data.by_vendor');
        $this->assertNotEmpty($vendors);

        foreach ($vendors as $vendor) {
            $details = $vendor['summary']['sla_details'] ?? [];
            $this->assertNotEmpty($details, 'Scoped SLA details must be available for '.$vendor['vendor_code']);
            foreach ($details as $row) {
                $this->assertSame($this->assignedRegion->code, $row['ut_code'] ?? null);
            }

            $chartTotal = array_sum(array_map(
                static fn (array $row): int => (int) ($row['total_orders'] ?? 0),
                $vendor['summary']['sla'] ?? []
            ));
            $this->assertSame(count($details), $chartTotal, 'SLA chart total must match the scoped DO detail list.');
        }

        $firstVendor = $vendors[0];
        $firstRow = $firstVendor['summary']['sla_details'][0];
        $this->actingAs($this->regionalHead)
            ->getJson('/api/v1/vendors/'.$firstVendor['vendor_code'].'/orders/'.rawurlencode($firstRow['id']))
            ->assertOk()
            ->assertJsonPath('data.ut_code', $this->assignedRegion->code);
    }
}
