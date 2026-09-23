<?php

declare(strict_types=1);

namespace Tests\Feature\Repair;

use App\Models\JsonTemplate;
use App\Models\User;
use App\Services\Contracts\TemplateContractGuard;
use App\Services\Contracts\VendorPayloadContract;
use Database\Seeders\ParamitaRepairSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** One JSON in json_templates is the only admin and vendor-test contract. */
class JsonTemplateContractGateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        (new ParamitaRepairSeeder)->run();
    }

    private function admin(): User
    {
        $admin = User::where('email', 'admin@paramita.test')->first();
        if (! $admin) {
            $this->markTestSkipped('Data admin demo belum tersedia; jalankan ParamitaRepairSeeder.');
        }

        return $admin;
    }

    private function template(): JsonTemplate
    {
        $template = JsonTemplate::where('name', 'inventory.lookup')->first();
        if (! $template) {
            $this->markTestSkipped('Template inventory.lookup belum tersedia.');
        }

        return $template;
    }

    /** @return array<string, mixed> */
    private function format(): array
    {
        return json_decode((string) $this->template()->template_data, true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_admin_form_shows_one_json_editor_without_rule_columns(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.templates.edit', $this->template()->id));

        $response->assertOk()
            ->assertSee('id="jsonEditor"', false)
            ->assertSee('&quot;data&quot;', false)
            ->assertDontSee('name="field_rules[', false)
            ->assertDontSee('Aturan per Field')
            ->assertDontSee('Checklist sebelum simpan')
            ->assertDontSee('Contoh envelope');
    }

    public function test_vendor_test_buttons_have_a_visible_loading_state(): void
    {
        $vendor = User::where('email', 'vendor.gramedia@paramita.test')->first();
        if (! $vendor) {
            $this->markTestSkipped('Data vendor demo belum tersedia; jalankan ParamitaRepairSeeder.');
        }

        $this->actingAs($vendor)->get(route('vendor.endpoints'))
            ->assertOk()
            ->assertSee('data-testing-form', false)
            ->assertSee('spinner-border spinner-border-sm', false)
            ->assertSee('Menguji...', false);
    }

    public function test_vendor_contract_page_explains_all_active_formats_from_the_template(): void
    {
        $vendor = User::where('email', 'vendor.gramedia@paramita.test')->first();
        if (! $vendor) {
            $this->markTestSkipped('Data vendor demo belum tersedia; jalankan ParamitaRepairSeeder.');
        }

        foreach (['inventory.list', 'inventory.lookup', 'inventory.summary', 'orders.list', 'orders.summary', 'orders.detail', 'orders.events', 'orders.analytics'] as $operation) {
            $response = $this->actingAs($vendor)->get(route('vendor.contracts', ['operation' => $operation]));
            $response->assertOk()
                ->assertSee($operation)
                ->assertSee('Aturan yang diuji')
                ->assertSee('generated_at')
                ->assertSee('data_as_of')
                ->assertSee('JSON path');
        }

        $this->actingAs($vendor)
            ->get(route('vendor.contracts', ['operation' => 'inventory.lookup']))
            ->assertSee('catalog_keys')
            ->assertSee('Daftar catalog_key dari Paramita');
    }

    public function test_multiple_alert_messages_are_rendered_as_a_list(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['error' => "Masalah pertama\nMasalah kedua"])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="toast-list"', false)
            ->assertSee('<li>Masalah pertama</li>', false)
            ->assertSee('<li>Masalah kedua</li>', false);
    }

    public function test_saved_template_is_direct_response_json(): void
    {
        $format = $this->format();

        $this->assertArrayHasKey('data', $format);
        $this->assertArrayHasKey('meta', $format);
        $this->assertArrayNotHasKey('response_example', $format);
        $this->assertArrayNotHasKey('field_rules', $format);
        $this->assertArrayNotHasKey('canonical_fields', $format);
        $this->assertArrayNotHasKey('field_map', $format);
    }

    public function test_guard_checks_only_required_envelope(): void
    {
        $this->assertSame([], app(TemplateContractGuard::class)->inspect('inventory.lookup', $this->format())['problems']);
        $this->assertNotSame([], app(TemplateContractGuard::class)->inspect('inventory.lookup', ['data' => []])['problems']);
    }

    public function test_admin_rename_is_saved_directly_and_vendor_old_name_fails(): void
    {
        $template = $this->template();
        $format = $this->format();
        $row = $format['data'][0];
        $source = 'stock_quantity';
        $renamed = 'available_quantity';
        $format['data'][0][$renamed] = $row[$source];
        unset($format['data'][0][$source]);

        $this->actingAs($this->admin())->put(route('admin.templates.update', $template->id), [
            'name' => $template->name,
            'category' => $template->category,
            'description' => $template->description,
            'version' => $template->version,
            'template_data' => json_encode($format, JSON_THROW_ON_ERROR),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $saved = json_decode((string) $template->fresh()->template_data, true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey($renamed, $saved['data'][0]);
        $this->assertArrayNotHasKey('field_map', $saved);

        $vendorPayload = $format;
        $vendorPayload['data'][0][$source] = $vendorPayload['data'][0][$renamed];
        unset($vendorPayload['data'][0][$renamed]);
        $problems = app(VendorPayloadContract::class)->validate('inventory.lookup', $vendorPayload);
        $this->assertStringContainsString("Column {$renamed} is required", implode(' ', $problems));
    }

    public function test_admin_type_change_becomes_the_new_vendor_test_type(): void
    {
        $template = $this->template();
        $format = $this->format();
        $format['data'][0]['stock_quantity'] = '120';

        $this->actingAs($this->admin())->put(route('admin.templates.update', $template->id), [
            'name' => $template->name,
            'category' => $template->category,
            'description' => $template->description,
            'version' => $template->version,
            'template_data' => json_encode($format, JSON_THROW_ON_ERROR),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $vendorPayload = $format;
        $vendorPayload['data'][0]['stock_quantity'] = 120;
        $problems = app(VendorPayloadContract::class)->validate('inventory.lookup', $vendorPayload);
        $this->assertStringContainsString('Column stock_quantity must be STRING, got INTEGER', implode(' ', $problems));
    }
}
