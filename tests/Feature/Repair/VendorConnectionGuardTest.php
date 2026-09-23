<?php

declare(strict_types=1);

namespace Tests\Feature\Repair;

use App\Http\Controllers\Vendor\VendorPortalController;
use App\Models\User;
use App\Services\Contracts\VendorContractGuide;
use Database\Seeders\ParamitaRepairSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class VendorConnectionGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        (new ParamitaRepairSeeder)->run();
    }

    private function vendorUser(): User
    {
        return User::query()->where('email', 'vendor.gramedia@paramita.test')->firstOrFail();
    }

    public function test_second_connection_is_rejected_and_first_stays_intact(): void
    {
        $user = $this->vendorUser();
        $before = DB::table('connections')->where('vendor_id', $user->vendor_id)->count();

        $this->actingAs($user)->post('/vendor-portal/connections', [
            'label' => 'Koneksi kedua',
            'base_url' => 'https://prodev.ut.ac.id/paramita-vendor-api/gramedia',
            'auth_type' => 'none',
        ])->assertRedirect();

        $this->assertSame($before, DB::table('connections')->where('vendor_id', $user->vendor_id)->count());
    }

    public function test_login_test_reports_success_without_exposing_token(): void
    {
        $user = $this->vendorUser();
        $connectionId = (int) DB::table('connections')->where('vendor_id', $user->vendor_id)->value('id');

        $response = $this->actingAs($user)->post("/vendor-portal/connections/{$connectionId}/test-login");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $message = (string) session('success');
        $this->assertStringNotContainsString('Bearer', $message);
        $this->assertStringNotContainsString('eyJ', $message);
    }

    public function test_transport_contract_documents_http_outcomes_and_get_method(): void
    {
        $transport = VendorContractGuide::transport();
        $this->assertSame('GET', $transport['method']);
        $this->assertCount(3, $transport['outcomes']);
        $guide = (new VendorContractGuide)->forOperation('orders.list');
        $this->assertSame('GET', $guide['method']);
        $this->assertStringContainsString('period_code=20252', $guide['example_request']);
    }

    public function test_failed_schema_validation_report_names_row_and_field(): void
    {
        $described = $this->describeError(
            'Vendor response schema validation failed for order.row.schema.json pada baris ke-3: {"\\/":["The required properties (period_code) are missing"]}'
        );
        $this->assertStringContainsString('baris ke-3', $described);
        $this->assertStringContainsString('period_code', $described);
        $this->assertStringContainsString('tidak ada di response', $described);

        $describedBody = $this->describeError(
            'Vendor response schema validation failed for envelope.list.schema.json: {"\\/meta\\/limit":["The property (\'limit\') is expected to be (\'integer\'), however it is a string"]}'
        );
        $this->assertStringContainsString('meta', $describedBody);
        $this->assertStringContainsString('integer', $describedBody);
    }

    private function describeError(string $rawMessage): string
    {
        $method = new ReflectionMethod(VendorPortalController::class, 'friendlyTestError');
        $method->setAccessible(true);

        return (string) $method->invoke(app(VendorPortalController::class), $rawMessage);
    }
}
