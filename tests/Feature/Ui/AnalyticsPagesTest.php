<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Services\Contracts\VendorPayloadContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsPagesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        $user = User::where('role', 'admin')->where('status', 'active')->first();
        if (! $user) {
            $this->markTestSkipped('Active admin seed is required.');
        }

        return $user;
    }

    public function test_three_reference_driven_analytics_pages_render_required_structures(): void
    {
        app()->setLocale('id');
        $user = $this->user();

        $this->withSession(['locale' => 'id'])->actingAs($user)->get('/analisis-sla')
            ->assertOk()
            ->assertSee('id="sla-vendor-cards"', false)
            ->assertSee('id="slaDetailModal"', false)
            ->assertSee('id="slaDoDetailModal"', false)
            ->assertSee('Detail Delivery Order')
            ->assertSee('data-analytics-period="monthly"', false);

        $this->actingAs($user)->get('/monitoring-retry')
            ->assertOk()
            ->assertSee('id="retryCountChart"', false)
            ->assertSee('id="retryPercentChart"', false)
            ->assertSee('id="retryDetailModal"', false);

        $this->actingAs($user)->get('/distribution-map')
            ->assertOk()
            ->assertSee('id="distributionMap"', false)
            ->assertSee('id="distPerfChart"', false)
            ->assertSee('id="distribution-table-body"', false)
            ->assertSee('id="distributionDetailModal"', false)
            ->assertDontSee('data-analytics-period=', false);
    }

    public function test_orders_analytics_template_is_the_runtime_contract(): void
    {
        $stored = DB::table('json_templates')->where('name', 'orders.analytics')->value('template_data');
        $this->assertIsString($stored);
        $payload = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([], app(VendorPayloadContract::class)->validate('orders.analytics', $payload));

        unset($payload['data']['sla'][0]['carrier_name']);
        $problems = app(VendorPayloadContract::class)->validate('orders.analytics', $payload);
        $this->assertStringContainsString('Column carrier_name is required', implode(' ', $problems));
    }

    public function test_retry_reason_master_and_options_are_available(): void
    {
        $admin = $this->user();
        $this->assertGreaterThanOrEqual(15, DB::table('retry_reasons')->where('is_active', true)->count());

        $this->actingAs($admin)->get('/admin/reference/retry-reason')
            ->assertOk()
            ->assertSee('Alasan Retry')
            ->assertSee('ADDRESS_NOT_FOUND');

        $this->actingAs($admin)->getJson('/api/v1/options/retry-reasons')
            ->assertOk()
            ->assertJsonStructure(['data' => [['code', 'name']]]);
    }

    public function test_admin_can_manage_retry_reason_master_data(): void
    {
        $admin = $this->user();

        $this->actingAs($admin)->post('/admin/reference/retry-reason', [
            'code' => 'QA_RETRY_REASON',
            'label' => 'QA retry reason',
            'description' => 'Created by automated test.',
            'sort_order' => 999,
            'is_active' => '1',
        ])->assertRedirect('/admin/reference/retry-reason');

        $id = (int) DB::table('retry_reasons')->where('code', 'QA_RETRY_REASON')->value('id');
        $this->assertGreaterThan(0, $id);

        $this->actingAs($admin)->put("/admin/reference/retry-reason/{$id}", [
            'code' => 'QA_RETRY_REASON',
            'label' => 'QA retry reason updated',
            'description' => null,
            'sort_order' => 998,
            'is_active' => '1',
        ])->assertRedirect('/admin/reference/retry-reason');

        $this->assertDatabaseHas('retry_reasons', [
            'id' => $id,
            'label' => 'QA retry reason updated',
            'sort_order' => 998,
        ]);

        $this->actingAs($admin)->delete("/admin/reference/retry-reason/{$id}")
            ->assertRedirect('/admin/reference/retry-reason');
        $this->assertDatabaseMissing('retry_reasons', ['id' => $id]);
    }

    public function test_analytics_api_never_returns_invalid_payload_as_chart_data(): void
    {
        $response = $this->actingAs($this->user())->getJson('/api/v1/orders/analytics?ordered_from=2026-09-16T00:00:00%2B07:00&ordered_to=2026-09-17T00:00:00%2B07:00');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['by_vendor'],
                'meta' => ['coverage', 'sources'],
            ]);

        foreach ($response->json('data.by_vendor') as $contribution) {
            $this->assertSame([], app(VendorPayloadContract::class)->validate('orders.analytics', [
                'data' => $contribution['summary'],
                'meta' => [
                    'generated_at' => '2026-09-16T12:00:00+07:00',
                    'data_as_of' => '2026-09-16T11:59:30+07:00',
                    'scope' => ['ut_code' => null, 'program_codes' => []],
                    'ordered_from' => '2026-09-16T00:00:00+07:00',
                    'ordered_to' => '2026-09-17T00:00:00+07:00',
                    'occurred_from' => '2026-09-16T00:00:00+07:00',
                    'occurred_to' => '2026-09-17T00:00:00+07:00',
                ],
            ]));
        }
    }
}
