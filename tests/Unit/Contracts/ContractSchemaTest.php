<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * F0: JSON Schema validation using Opis/json-schema 2.x (draft 2020-12).
 * Tests positive (valid) and negative (invalid) fixtures against schemas.
 * Format assertions (date-time, uri) are enabled.
 */
class ContractSchemaTest extends TestCase
{
    private Validator $validator;

    private string $schemasDir;

    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemasDir = dirname(__DIR__, 3).'/contracts/v1';
        $this->fixturesDir = dirname(__DIR__, 2).'/Fixtures/contracts';

        $this->validator = new Validator;
        $this->validator->setMaxErrors(10);

        // Pre-load all schemas into Opis loader so $ref resolves locally
        $loader = $this->validator->loader();
        foreach (glob($this->schemasDir.'/*.schema.json') as $schemaFile) {
            $schemaObj = json_decode(file_get_contents($schemaFile));
            // Register schema by its $id so cross-file $ref works
            $loader->resolver()->registerRaw(json_encode($schemaObj), $schemaObj->{'$id'});
        }
    }

    // ──────────────────────────────────────────────────────────
    // Positive: vendor fixtures must pass
    // ──────────────────────────────────────────────────────────

    public function test_vendor_inventory_list_valid(): void
    {
        $fixture = $this->loadFixture('vendor/inventory.list.json');
        $this->assertListEnvelope($fixture);
        foreach ($fixture->data as $row) {
            $this->assertSchemaPass($row, 'inventory.row.schema.json');
        }
    }

    public function test_vendor_inventory_book_list_valid(): void
    {
        $fixture = $this->loadFixture('vendor/inventory.book.list.json');
        $this->assertListEnvelope($fixture);
        foreach ($fixture->data as $row) {
            $this->assertSchemaPass($row, 'inventory.row.schema.json');
            $this->assertSame('book', $row->item_type);
            $this->assertNotEmpty($row->edition, 'book edition must be non-empty');
        }
    }

    public function test_vendor_inventory_lookup_valid(): void
    {
        $fixture = $this->loadFixture('vendor/inventory.lookup.json');
        $this->assertListEnvelope($fixture);
        foreach ($fixture->data as $row) {
            $this->assertSchemaPass($row, 'inventory.lookup.row.schema.json');
        }
    }

    public function test_vendor_inventory_summary_valid(): void
    {
        $fixture = $this->loadFixture('vendor/inventory.summary.json');
        $this->assertSchemaPass($fixture, 'inventory.summary.schema.json');
        // Semantic: four status counts sum to record_count
        $d = $fixture->data;
        $statusSum = $d->shortage_count + $d->adequate_count + $d->surplus_count + $d->unknown_count;
        $this->assertSame($d->record_count, $statusSum, 'status counts must sum to record_count');
    }

    public function test_vendor_orders_list_valid(): void
    {
        $fixture = $this->loadFixture('vendor/orders.list.json');
        $this->assertListEnvelope($fixture);
        foreach ($fixture->data as $row) {
            $this->assertSchemaPass($row, 'order.row.schema.json');
        }
    }

    public function test_vendor_orders_summary_valid(): void
    {
        $fixture = $this->loadFixture('vendor/orders.summary.json');
        // vendor orders.summary returns {data: SummaryRow, meta: ...} — validate data object against summary.row schema
        $this->assertSchemaPass($fixture->data, 'summary.row.schema.json');
        $this->assertSummaryInvariants($fixture->data);
    }

    public function test_vendor_orders_detail_valid(): void
    {
        $fixture = $this->loadFixture('vendor/orders.detail.json');
        $this->assertSchemaPass($fixture->data, 'order.detail.schema.json', '.data');
    }

    public function test_vendor_orders_events_valid(): void
    {
        $fixture = $this->loadFixture('vendor/orders.events.json');
        $this->assertListEnvelope($fixture);
        foreach ($fixture->data as $event) {
            $this->assertSchemaPass($event, 'event.row.schema.json');
        }
    }

    public function test_vendor_orders_retry_events_valid(): void
    {
        $fixture = $this->loadFixture('vendor/orders.retry.events.json');
        $this->assertListEnvelope($fixture);
        // Empty retry list is valid
        $this->assertCount(0, $fixture->data, 'retry events fixture should be empty');
    }

    public function test_vendor_orders_summary_ut_grouped_valid(): void
    {
        $fixture = $this->loadFixture('vendor/orders.summary.ut.json');
        $this->assertListEnvelope($fixture);
        foreach ($fixture->data as $row) {
            $this->assertSchemaPass($row, 'group.row.schema.json');
            $this->assertSummaryInvariants($row->summary);
        }
    }

    // ──────────────────────────────────────────────────────────
    // Positive: all seven status codes must individually be valid
    // ──────────────────────────────────────────────────────────

    public function test_all_seven_status_codes_valid(): void
    {
        $rows = json_decode(file_get_contents(
            $this->fixturesDir.'/valid/order.row.all_status_codes.json'
        ));
        $validCodes = ['01', '02', '03', '04', '05', '06', '07'];
        foreach ($rows as $i => $row) {
            $this->assertSchemaPass($row, 'order.row.schema.json', "row[$i]");
            $this->assertContains($row->process_status_code, $validCodes);
        }
        // code06 must NOT be delivered — find it
        $returnRow = null;
        $deliveredRow = null;
        foreach ($rows as $row) {
            if ($row->process_status_code === '06') {
                $returnRow = $row;
            }
            if ($row->process_status_code === '07') {
                $deliveredRow = $row;
            }
        }
        $this->assertNotNull($returnRow, 'Must have code06 fixture');
        $this->assertSame('06', $returnRow->process_status_code, 'code06 is Return');
        $this->assertNotNull($deliveredRow, 'Must have code07 fixture');
        $this->assertSame('07', $deliveredRow->process_status_code, 'code07 is Delivered');
    }

    // ──────────────────────────────────────────────────────────
    // Positive: nullable fields all null
    // ──────────────────────────────────────────────────────────

    public function test_inventory_row_all_nullable_valid(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/valid/inventory.row.all_nullable.json'
        ));
        $this->assertSchemaPass($row, 'inventory.row.schema.json');
        $this->assertNull($row->required_quantity, 'required_quantity may be null → unknown status');
    }

    public function test_order_row_unicode_valid(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/valid/order.row.unicode.json'
        ));
        $this->assertSchemaPass($row, 'order.row.schema.json');
    }

    public function test_empty_list_envelope_valid(): void
    {
        $fixture = json_decode(file_get_contents(
            $this->fixturesDir.'/valid/envelope.list.empty.json'
        ));
        $this->assertListEnvelope($fixture);
        $this->assertCount(0, $fixture->data);
        $this->assertFalse($fixture->meta->has_more);
        $this->assertSame(0, $fixture->meta->total_filtered);
    }

    // ──────────────────────────────────────────────────────────
    // Negative: invalid fixtures must FAIL schema validation
    // ──────────────────────────────────────────────────────────

    public function test_bad_status_code_integer_fails(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/invalid/order.row.bad_status_int.json'
        ));
        $this->assertSchemaFail($row, 'order.row.schema.json', 'process_status_code as integer should fail');
    }

    public function test_bad_status_code08_fails(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/invalid/order.row.bad_status_08.json'
        ));
        $this->assertSchemaFail($row, 'order.row.schema.json', 'process_status_code 08 should fail');
    }

    public function test_inventory_quantity_string_fails(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/invalid/inventory.row.bad_quantity_string.json'
        ));
        $this->assertSchemaFail($row, 'inventory.row.schema.json', 'stock_quantity as string should fail');
    }

    public function test_inventory_bad_date_fails(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/invalid/inventory.row.bad_date.json'
        ));
        $this->assertSchemaFail($row, 'inventory.row.schema.json', 'bad date-time format should fail');
    }

    public function test_inventory_bad_item_type_fails(): void
    {
        $row = json_decode(file_get_contents(
            $this->fixturesDir.'/invalid/inventory.row.bad_item_type.json'
        ));
        $this->assertSchemaFail($row, 'inventory.row.schema.json', 'item_type=kit should fail');
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function loadFixture(string $path): object
    {
        $full = dirname(__DIR__, 3).'/docs/fixtures/'.$path;
        $this->assertFileExists($full, "Fixture missing: $path");

        return json_decode(file_get_contents($full));
    }

    private function assertListEnvelope(object $fixture): void
    {
        $this->assertIsArray($fixture->data ?? null, 'data must be array');
        $meta = $fixture->meta;
        $this->assertGreaterThanOrEqual(0, $meta->total_filtered);
        $this->assertGreaterThanOrEqual(0, $meta->offset);
        $this->assertGreaterThan(0, $meta->limit);
        $this->assertCount(
            min(count($fixture->data), $meta->limit),
            $fixture->data,
            'data.length <= meta.limit'
        );
        $expectedHasMore = ($meta->offset + count($fixture->data)) < $meta->total_filtered;
        $this->assertSame($expectedHasMore, $meta->has_more, 'has_more derivation');
    }

    private function assertSchemaPass(object $data, string $schemaFile, string $context = ''): void
    {
        $schemaObj = json_decode(file_get_contents($this->schemasDir.'/'.$schemaFile));
        $result = $this->validator->validate($data, $schemaObj);
        if (! $result->isValid()) {
            $error = $result->error();
            $detail = $error ? json_encode((new ErrorFormatter)->format($error), JSON_PRETTY_PRINT) : '(no error detail)';
            $this->fail("Schema validation FAILED for $schemaFile $context: $detail");
        }
        $this->assertTrue(true); // mark assertion
    }

    private function assertSchemaFail(object $data, string $schemaFile, string $message = ''): void
    {
        $schemaObj = json_decode(file_get_contents($this->schemasDir.'/'.$schemaFile));
        $result = $this->validator->validate($data, $schemaObj);
        $this->assertFalse($result->isValid(), "Expected schema to FAIL but it passed. $message");
    }

    private function assertSummaryInvariants(object $d): void
    {
        // status_counts must sum to total_orders
        $bucketSum = $d->status_counts->on_process + $d->status_counts->on_delivery
            + $d->status_counts->retry + $d->status_counts->returned + $d->status_counts->delivered;
        $this->assertSame($d->total_orders, $bucketSum, 'status_counts must sum to total_orders');

        // status_code_counts must sum to total_orders
        $codeSum = $d->status_code_counts->{'01'} + $d->status_code_counts->{'02'}
            + $d->status_code_counts->{'03'} + $d->status_code_counts->{'04'}
            + $d->status_code_counts->{'05'} + $d->status_code_counts->{'06'}
            + $d->status_code_counts->{'07'};
        $this->assertSame($d->total_orders, $codeSum, 'status_code_counts must sum to total_orders');

        // Bucket-code mapping: code06=returned, code07=delivered, 03+04+05=retry
        $this->assertSame($d->status_counts->returned, $d->status_code_counts->{'06'}, 'returned == code06');
        $this->assertSame($d->status_counts->delivered, $d->status_code_counts->{'07'}, 'delivered == code07');
        $retryFromCodes = $d->status_code_counts->{'03'} + $d->status_code_counts->{'04'} + $d->status_code_counts->{'05'};
        $this->assertSame($d->status_counts->retry, $retryFromCodes, 'retry == 03+04+05');

        // SLA counts must sum to total_orders
        $slaSum = $d->sla_counts->on_sla + $d->sla_counts->over_sla
            + $d->sla_counts->not_applicable + $d->sla_counts->unknown;
        $this->assertSame($d->total_orders, $slaSum, 'sla_counts must sum to total_orders');

        // sample_count <= delivered
        $this->assertLessThanOrEqual(
            $d->status_code_counts->{'07'},
            $d->completed_sla_sample_count,
            'sla_sample_count <= delivered'
        );
        if ($d->completed_sla_sample_count === 0) {
            $this->assertSame(0, $d->completed_sla_seconds_sum, 'count=0 => sum=0');
        }
    }
}
