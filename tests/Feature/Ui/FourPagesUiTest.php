<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Models\User;
use Database\Seeders\MasterAndRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * F4: UI Pages Slicing & Header Parity Tests (PRD §17 UI-01 & UI-03)
 */
class FourPagesUiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // The UI is bilingual now; these assertions target the Indonesian copy, so pin the locale.
        // (The English rendering is covered by LocaleSwitchTest.)
        app()->setLocale('id');
        $this->seed(MasterAndRoleSeeder::class);
        $this->user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_dashboard_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Monitoring Stok Paket');
        $response->assertSee('Kode Paket');
        $response->assertSee('Judul Paket');
        $response->assertSee('Status Stok');
    }

    public function test_monitoring_delivery_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get('/monitoring-delivery');
        $response->assertStatus(200);
        $response->assertSee('Monitoring Delivery');
        $response->assertSee('Total DO');
        $response->assertSee('Total Delivered');
        $response->assertSee('Total On Delivery');
        $response->assertSee('Total On Process');
        $response->assertSee('Total Retry');
        $response->assertSee('Total Return');
        $response->assertSee('Tanggal Pemesanan');
        $response->assertSee('Status Proses');
        $response->assertSee('Detail DO Mahasiswa'); // Shared Modal
    }

    public function test_do_per_prodi_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get('/do-per-prodi');
        $response->assertStatus(200);
        $response->assertSee('DO Per Prodi');
        $response->assertSee('Semua prodi');
        $response->assertSee('Status Pengiriman');
        $response->assertSee('Status SLA');
        $response->assertSee('Detail DO Mahasiswa');
    }

    public function test_do_per_ut_daerah_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get('/do-per-ut-daerah');
        $response->assertStatus(200);
        $response->assertSee('DO Per UT Daerah');
        $response->assertSee('Semua UT daerah');
        $response->assertSee('Status Pengiriman');
        $response->assertSee('Status SLA');
        $response->assertSee('Detail DO Mahasiswa');
    }
}
