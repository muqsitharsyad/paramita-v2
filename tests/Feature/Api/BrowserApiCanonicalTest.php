<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\MasterAndRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F3: Canonical Browser API Tests (PRD §11 & §17)
 */
class BrowserApiCanonicalTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $tutor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterAndRoleSeeder::class);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $tutorUt = DB::table('ut_regions')->first();
        $tutorProgram = DB::table('programs')->first();

        $this->tutor = User::factory()->create([
            'role' => 'tutor',
            'status' => 'active',
            'ut_id' => $tutorUt->id,
        ]);

        DB::table('tutor_program')->insert([
            'user_id' => $this->tutor->id,
            'program_id' => $tutorProgram->id,
        ]);
    }

    public function test_unauthenticated_access_returns302_or401(): void
    {
        $response = $this->getJson('/api/v1/stock/summary?item_type=package');
        $response->assertStatus(401);
    }

    public function test_stock_summary_canonical_route_works(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/stock/summary?item_type=package');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'totals_all_vendors',
                'totals_available',
                'by_vendor',
            ],
            'meta' => [
                'request_id',
                'state',
                'scope',
            ],
        ]);
    }

    public function test_orders_list_canonical_route_works(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/orders?limit=25&offset=0');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['pagination', 'scope'],
        ]);
    }

    public function test_mutually_exclusive_status_bucket_and_code_returns422(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            '/api/v1/orders?process_status_bucket=delivered&process_status_code=07'
        );
        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_tutor_pii_is_masked_in_orders_list(): void
    {
        $response = $this->actingAs($this->tutor)->getJson('/api/v1/orders?limit=25&offset=0');
        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $row) {
            $this->assertSame('Mahasiswa (Disamarkan)', $row['student_name']);
            $this->assertNull($row['province']);
            $this->assertNull($row['city']);
        }
    }

    public function test_tutor_order_detail_blocked_with403_problem(): void
    {
        $response = $this->actingAs($this->tutor)->getJson(
            '/api/v1/vendors/VENDOR-DEMO-A/orders/ORDER-DEMO-01'
        );
        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_options_ut_route_works(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/options/ut');
        $response->assertStatus(200);
        $this->assertCount(40, $response->json('data'), 'Admin sees all 40 active UT regions');
    }
}
