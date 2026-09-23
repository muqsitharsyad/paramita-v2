<?php

declare(strict_types=1);

namespace Tests\Unit\Aggregates;

use App\DTO\ScopeContext;
use App\Services\Gateway\VendorGateway;
use App\Services\Monitoring\MonitoringRepository;
use App\Services\Monitoring\SummaryAggregator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class RegionalAnalyticsScopeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_regional_analytics_filters_cross_ut_rows_and_rebuilds_chart_totals(): void
    {
        $repository = Mockery::mock(MonitoringRepository::class);
        $gateway = Mockery::mock(VendorGateway::class);
        $aggregator = new SummaryAggregator($repository, $gateway);

        $scope = new ScopeContext(
            actorId: 16,
            role: 'kepala_ut_daerah',
            permissionRevision: 1,
            assignedUtCode: 'UN31.UT15',
            allowedProgramCodes: [],
            isAllRegions: false
        );
        $filters = [
            'vendor_scope' => 'all',
            'vendor_codes' => [],
            'ut_code' => 'UN31.UT15',
        ];

        $repository->shouldReceive('sources')
            ->once()
            ->with('orders.analytics', $filters, true)
            ->andReturn([['code' => 'VENDOR-A', 'name' => 'Vendor A']]);
        $repository->shouldReceive('labels')
            ->andReturnUsing(static fn (array $row): array => $row);
        $repository->shouldReceive('freshness')
            ->once()
            ->andReturn(['state' => 'fresh', 'generated_at' => null, 'data_as_of' => null, 'age_seconds' => 0]);

        $gateway->shouldReceive('execute')
            ->once()
            ->andReturn([
                'data' => [
                    // Deliberately wrong national aggregates from an upstream vendor.
                    'sla' => [[
                        'carrier_name' => 'JNE',
                        'faster_count' => 1,
                        'on_sla_count' => 2,
                        'over_sla_count' => 1,
                        'total_orders' => 4,
                        'sla_target_days' => 3,
                    ]],
                    'sla_details' => [
                        $this->slaRow('DO-BDG-1', 'UN31.UT15', 'faster'),
                        $this->slaRow('DO-JKT-1', 'UN31.UT12', 'over_sla'),
                        $this->slaRow('DO-NO-UT', null, 'on_sla'),
                        $this->slaRow('DO-BDG-2', 'UN31.UT15', 'on_sla'),
                    ],
                    'retries' => [[
                        'reason_code' => 'ADDRESS_NOT_FOUND',
                        'carrier_name' => 'JNE',
                        'retry_count' => 2,
                    ]],
                    'retry_details' => [
                        $this->retryRow('DO-BDG-1', 'UN31.UT15'),
                        $this->retryRow('DO-JKT-1', 'UN31.UT12'),
                    ],
                    'distribution' => [],
                ],
                'meta' => [],
            ]);

        $result = $aggregator->aggregate('orders.analytics', $filters, $scope);
        $summary = $result['data']['by_vendor'][0]['summary'];

        $this->assertSame(['DO-BDG-1', 'DO-BDG-2'], array_column($summary['sla_details'], 'order_number'));
        $this->assertSame(['UN31.UT15', 'UN31.UT15'], array_column($summary['sla_details'], 'ut_code'));
        $this->assertCount(1, $summary['retry_details']);
        $this->assertSame('UN31.UT15', $summary['retry_details'][0]['ut_code']);

        $this->assertSame([[
            'carrier_name' => 'JNE',
            'faster_count' => 1,
            'on_sla_count' => 1,
            'over_sla_count' => 0,
            'total_orders' => 2,
            'sla_target_days' => 3,
        ]], $summary['sla']);
        $this->assertSame([[
            'reason_code' => 'ADDRESS_NOT_FOUND',
            'carrier_name' => 'JNE',
            'retry_count' => 1,
        ]], $summary['retries']);
    }

    private function slaRow(string $orderNumber, ?string $utCode, string $status): array
    {
        return [
            'id' => strtolower($orderNumber),
            'order_number' => $orderNumber,
            'ut_code' => $utCode,
            'carrier_name' => 'JNE',
            'tracking_number' => 'TRK-'.$orderNumber,
            'sla_status' => $status,
            'ordered_at' => '2026-09-20T08:00:00+07:00',
            'handed_to_carrier_at' => '2026-09-20T14:00:00+07:00',
            'completed_at' => '2026-09-22T14:00:00+07:00',
            'process_status_code' => '07',
            'sla_target_days' => 3,
            'sla_elapsed_days' => 2.0,
        ];
    }

    private function retryRow(string $orderNumber, string $utCode): array
    {
        return [
            'id' => strtolower($orderNumber),
            'order_number' => $orderNumber,
            'ut_code' => $utCode,
            'program_code' => '61201',
            'carrier_name' => 'JNE',
            'reason_code' => 'ADDRESS_NOT_FOUND',
            'retry_attempt' => 1,
            'occurred_at' => '2026-09-21T08:00:00+07:00',
            'process_status_code' => '03',
        ];
    }
}
