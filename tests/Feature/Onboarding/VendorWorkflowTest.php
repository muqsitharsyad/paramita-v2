<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\User;
use App\Services\Onboarding\ApprovalService;
use App\Services\Onboarding\TestRunner;
use Database\Seeders\DefaultContractsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F2: Vendor Configuration, Revision & Approval Tests (PRD §17 CFG-01..05, TEST-01..03)
 */
class VendorWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private TestRunner $runner;

    private ApprovalService $approval;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new TestRunner;
        $this->approval = new ApprovalService;
        $this->seed(DefaultContractsSeeder::class);
    }

    public function test_test02_empty_fixture_results_in_incomplete_status(): void
    {
        // 1. Create a dummy vendor & binding
        $vendorId = DB::table('vendors')->insertGetId([
            'code' => 'VENDOR-TEST-01',
            'legal_name' => 'PT Test One',
            'contact_name' => 'Budi',
            'contact_email' => 'budi@test.com',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connId = DB::table('connections')->insertGetId([
            'vendor_id' => $vendorId,
            'label' => 'API Utama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connRevId = DB::table('connection_revisions')->insertGetId([
            'connection_id' => $connId,
            'revision' => 1,
            'base_url' => 'https://api.testvendor.com',
            'auth_type' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contract = DB::table('contracts')->where('operation_key', 'inventory.list')->first();
        $contractVersion = DB::table('contract_versions')->where('contract_id', $contract->id)->first();

        $bindingId = DB::table('endpoint_bindings')->insertGetId([
            'vendor_id' => $vendorId,
            'contract_id' => $contract->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bindingRevId = DB::table('binding_revisions')->insertGetId([
            'binding_id' => $bindingId,
            'revision' => 1,
            'connection_revision_id' => $connRevId,
            'contract_version_id' => $contractVersion->id,
            'path' => '/stock',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // TEST-02: Empty fixture (no sample records) must return 'incomplete', not 'passed'
        $report = $this->runner->execute($bindingRevId, samplePayload: null);

        $this->assertSame('incomplete', $report['status'], 'Empty fixture must result in incomplete');
    }

    public function test_test01_and_cfg01_full_workflow_end_to_end(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // 1. Setup vendor & binding
        $vendorId = DB::table('vendors')->insertGetId([
            'code' => 'VENDOR-WORKFLOW',
            'legal_name' => 'PT Workflow Vendor',
            'contact_name' => 'Ali',
            'contact_email' => 'ali@vendor.com',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connId = DB::table('connections')->insertGetId([
            'vendor_id' => $vendorId,
            'label' => 'API Utama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $connRevId = DB::table('connection_revisions')->insertGetId([
            'connection_id' => $connId,
            'revision' => 1,
            'base_url' => 'https://api.workflow.com',
            'auth_type' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contract = DB::table('contracts')->where('operation_key', 'orders.list')->first();
        $contractVersion = DB::table('contract_versions')->where('contract_id', $contract->id)->first();

        $bindingId = DB::table('endpoint_bindings')->insertGetId([
            'vendor_id' => $vendorId,
            'contract_id' => $contract->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bindingRevId = DB::table('binding_revisions')->insertGetId([
            'binding_id' => $bindingId,
            'revision' => 1,
            'connection_revision_id' => $connRevId,
            'contract_version_id' => $contractVersion->id,
            'path' => '/orders',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Load valid order list fixture
        $validFixture = json_decode(file_get_contents(base_path('docs/fixtures/vendor/orders.list.json')));

        // 2. Run test with valid fixture -> test_passed
        $report = $this->runner->execute($bindingRevId, samplePayload: $validFixture);
        $this->assertSame('passed', $report['status'], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3. Submit for admin approval
        $submissionId = $this->approval->submit($bindingRevId, $report['test_run_id']);
        $this->assertGreaterThan(0, $submissionId);

        // 4. Admin approves -> binding active_revision_id updated atomically
        $this->approval->approve($submissionId, $admin->id);

        $binding = DB::table('endpoint_bindings')->where('id', $bindingId)->first();
        $this->assertSame($bindingRevId, (int) $binding->active_revision_id, 'Active revision must point to approved revision');

        // CFG-01: Create new draft revision — active revision remains untouched
        $draftRevId = DB::table('binding_revisions')->insertGetId([
            'binding_id' => $bindingId,
            'revision' => 2,
            'connection_revision_id' => $connRevId,
            'contract_version_id' => $contractVersion->id,
            'path' => '/orders/v2',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bindingCheck = DB::table('endpoint_bindings')->where('id', $bindingId)->first();
        $this->assertSame($bindingRevId, (int) $bindingCheck->active_revision_id, 'Creating new draft revision does not alter active revision');
    }
}
