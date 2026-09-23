<?php

declare(strict_types=1);

namespace Tests\Feature\Repair;

use App\Services\Contracts\VendorPayloadContract;
use Database\Seeders\DefaultContractsSeeder;
use Database\Seeders\MasterAndRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The vendor-facing contract report is a plain ALERT: one short sentence per distinct problem,
 * naming the column, what is required and which rows are affected.
 *
 *   Column stock_quantity must be INTEGER or NUMBER, got STRING (data[0])
 *   Column unit_weight_kg is required (must be NUMBER) (data[0..3])
 */
class VendorContractReportTest extends TestCase
{
    use DatabaseTransactions;

    private const OP = 'inventory.lookup';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterAndRoleSeeder::class);
        $this->seed(DefaultContractsSeeder::class);
    }

    private function row(int $i, string $keyName = 'catalog_key'): array
    {
        return [
            $keyName => 'PKT-'.$i,
            'item_type' => 'package',
            'item_code' => 'MKDU410'.$i,
            'edition' => '2025.1',
            'title' => 'Paket '.$i,
            'size_label' => 'Paket',
            'ut_code' => 'UN31.UT15',
            'program_code' => '61201',
            'stock_quantity' => 10 + $i,
            'required_quantity' => 10,
            'unit_weight_kg' => 0.8,
            'total_weight_kg' => 9.6,
            'total_height_cm' => 20,
            'total_area_m2' => 1.2,
            'updated_at' => '2026-09-14T00:00:00+00:00',
        ];
    }

    private function meta(int $total = 3): array
    {
        return [
            'generated_at' => '2026-09-14T00:00:00+00:00',
            'data_as_of' => '2026-09-13T23:59:30+00:00',
            'scope' => ['ut_code' => null, 'program_codes' => []],
            'limit' => 25, 'offset' => 0, 'total_filtered' => $total, 'has_more' => false,
        ];
    }

    /** @param list<string> $rows */
    private function activateContract(array $rows): void
    {
        DB::table('json_templates')->where('name', self::OP)->update(['template_data' => json_encode([
            'data' => $rows,
            'meta' => $this->meta(count($rows)),
        ])]);
    }

    public function test_a_missing_field_on_every_row_is_one_alert_without_row_indexes(): void
    {
        // Admin renamed catalog_key -> catalog_keys.
        $this->activateContract(array_map(fn (int $i) => $this->row($i, 'catalog_keys'), range(1, 8)));

        // Vendor still sends the old name on all 8 rows.
        $payload = json_decode(json_encode([
            'data' => array_map(fn (int $i) => $this->row($i), range(1, 8)),
            'meta' => $this->meta(8),
        ]));

        $problems = app(VendorPayloadContract::class)->validate(self::OP, $payload);

        $this->assertCount(1, $problems, implode("\n", $problems));
        $line = $problems[0];
        // Structure/type only: the same mismatch on 8 rows is ONE finding, and how many rows carried
        // it is not part of the message.
        $this->assertStringContainsString('Column catalog_keys is required', $line);
        $this->assertStringNotContainsString('data[', $line);
        $this->assertStringNotContainsString('$[*]', $line);
        $this->assertStringNotContainsString('MISSING', $line);
    }

    public function test_the_same_problem_in_several_rows_stays_one_line(): void
    {
        $this->activateContract(array_map(fn (int $i) => $this->row($i, 'catalog_keys'), range(1, 3)));

        $problems = app(VendorPayloadContract::class)->validate(self::OP, json_decode(json_encode([
            'data' => array_map(fn (int $i) => $this->row($i), range(1, 3)),
            'meta' => $this->meta(),
        ])));

        $this->assertCount(1, $problems);
        $this->assertStringNotContainsString('data[', $problems[0]);
    }

    public function test_type_error_states_column_expected_and_actual(): void
    {
        $this->activateContract(array_map(fn (int $i) => $this->row($i), range(1, 3)));

        $payload = ['data' => array_map(fn (int $i) => $this->row($i), range(1, 3)), 'meta' => $this->meta()];
        $payload['data'][1]['stock_quantity'] = 'banyak';   // row index 1

        $problems = app(VendorPayloadContract::class)->validate(self::OP, json_decode(json_encode($payload)));

        $this->assertNotSame([], $problems);
        $line = implode("\n", $problems);
        $this->assertStringContainsString('Column stock_quantity must be INTEGER or NUMBER, got STRING', $line);
        $this->assertStringNotContainsString('data[', $line);
    }

    public function test_missing_field_alert_states_the_expected_type(): void
    {
        $this->activateContract(array_map(fn (int $i) => $this->row($i), range(1, 3)));

        $payload = ['data' => array_map(fn (int $i) => $this->row($i), range(1, 3)), 'meta' => $this->meta()];
        unset($payload['data'][0]['stock_quantity']);

        $problems = app(VendorPayloadContract::class)->validate(self::OP, json_decode(json_encode($payload)));

        $this->assertNotSame([], $problems);
        $this->assertStringContainsString('Column stock_quantity is required', $problems[0]);
        $this->assertStringContainsString('must be INTEGER', $problems[0]);
    }

    public function test_many_problems_are_capped_with_a_short_tail(): void
    {
        $this->activateContract(array_map(fn (int $i) => $this->row($i), range(1, 3)));

        $payload = ['data' => array_map(fn (int $i) => $this->row($i), range(1, 3)), 'meta' => $this->meta()];
        foreach ($payload['data'] as $i => $row) {
            unset($payload['data'][$i]['stock_quantity'], $payload['data'][$i]['required_quantity'],
                $payload['data'][$i]['unit_weight_kg'], $payload['data'][$i]['total_weight_kg'],
                $payload['data'][$i]['total_height_cm'], $payload['data'][$i]['total_area_m2'],
                $payload['data'][$i]['updated_at'], $payload['data'][$i]['program_code']);
        }

        $problems = app(VendorPayloadContract::class)->validate(self::OP, json_decode(json_encode($payload)));

        // 6 alerts shown, then a single "and N more" tail — never an unbounded list.
        $this->assertLessThanOrEqual(7, count($problems), implode("\n", $problems));
        $this->assertStringContainsString('masalah lain', end($problems));
    }

    public function test_compliant_payload_reports_nothing(): void
    {
        $this->activateContract(array_map(fn (int $i) => $this->row($i), range(1, 3)));

        $problems = app(VendorPayloadContract::class)->validate(self::OP, json_decode(json_encode([
            'data' => array_map(fn (int $i) => $this->row($i), range(1, 3)),
            'meta' => $this->meta(),
        ])));

        $this->assertSame([], $problems);
    }

    public function test_root_and_meta_must_keep_the_object_envelope_shape(): void
    {
        $this->activateContract([$this->row(1)]);
        $contract = app(VendorPayloadContract::class);

        $this->assertNotSame([], $contract->validate(self::OP, []));
        $this->assertNotSame([], $contract->validate(self::OP, [
            'data' => [$this->row(1)],
            'meta' => [],
        ]));
    }

    public function test_null_template_value_accepts_scalars_but_rejects_containers(): void
    {
        $row = $this->row(1);
        $row['optional_note'] = null;
        $this->activateContract([$row]);

        $payload = ['data' => [$row], 'meta' => $this->meta(1)];
        $payload['data'][0]['optional_note'] = 'ready';
        $this->assertSame([], app(VendorPayloadContract::class)->validate(self::OP, $payload));

        $payload['data'][0]['optional_note'] = ['unexpected'];
        $this->assertStringContainsString(
            'Column optional_note must be',
            implode("\n", app(VendorPayloadContract::class)->validate(self::OP, $payload))
        );
    }

    public function test_impossible_calendar_timestamp_is_rejected(): void
    {
        $this->activateContract([$this->row(1)]);
        $payload = [
            'data' => [$this->row(1)],
            'meta' => $this->meta(1),
        ];
        $payload['meta']['generated_at'] = '2026-02-30T10:00:00Z';

        $problems = app(VendorPayloadContract::class)->validate(self::OP, $payload);

        $this->assertStringContainsString('meta.generated_at must be an RFC3339 timestamp', implode("\n", $problems));
    }

    public function test_alert_wording_has_no_internal_jargon(): void
    {
        $this->activateContract(array_map(fn (int $i) => $this->row($i), range(1, 3)));

        $payload = ['data' => array_map(fn (int $i) => $this->row($i), range(1, 3)), 'meta' => $this->meta()];
        $payload['data'][0]['item_type'] = 'gadget';

        $problems = app(VendorPayloadContract::class)->validate(self::OP, json_decode(json_encode($payload)));
        $line = implode(' ', $problems);

        foreach (['TYPE_MISMATCH', '$[*]', 'json-pointer', 'harus=', 'dapat='] as $jargon) {
            $this->assertStringNotContainsString($jargon, $line);
        }
    }
}
