<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\ScopeContext;
use App\Services\Gateway\VendorGateway;

class SummaryAggregator
{
    public function __construct(
        private readonly MonitoringRepository $repository,
        private readonly VendorGateway $gateway
    ) {}

    public function aggregate(string $operationKey, array $filters, ScopeContext $scope): array
    {
        $all = ($filters['vendor_scope'] ?? 'all') === 'all';
        $sources = $this->repository->sources($operationKey, $filters, $all);

        $contributions = [];
        $included = 0;
        $missing = 0;
        $fresh = 0;
        $stale = 0;
        $sourceStatuses = [];

        foreach ($sources as $src) {
            try {
                $upstreamRes = $this->gateway->execute(
                    vendorCode: $src['code'],
                    operationKey: $operationKey,
                    scope: $scope,
                    filters: $filters
                );

                $summaryData = $upstreamRes['data'] ?? [];
                if ($operationKey === 'orders.analytics') {
                    foreach (['retries', 'sla_details', 'retry_details'] as $section) {
                        $summaryData[$section] = array_map(
                            fn (array $row): array => $this->repository->labels($row),
                            $summaryData[$section] ?? []
                        );
                    }
                    // Per-row UT scoping (defense-in-depth). Only the per-DO detail lists carry
                    // ut_code; the aggregate sections (sla, retries, distribution) are already
                    // scoped upstream because we pass ut_code to the vendor. For a regional head
                    // these detail lists must only contain rows from their assigned UT.
                    foreach (['sla_details', 'retry_details'] as $section) {
                        $summaryData[$section] = array_values(array_filter(
                            $summaryData[$section] ?? [],
                            fn (array $row): bool => $scope->rowBelongsToScope(
                                isset($row['ut_code']) && is_string($row['ut_code']) ? $row['ut_code'] : null
                            )
                        ));
                    }
                    if (! $scope->isAllRegions) {
                        $summaryData = $this->rebuildScopedAnalytics($summaryData);
                    }
                }
                if ($operationKey === 'orders.summary' && is_array($summaryData) && array_is_list($summaryData)) {
                    $summaryData = $this->repository->labelGroups($summaryData, (string) ($filters['group_by'] ?? 'none'));
                }
                $freshness = $this->repository->freshness((array) ($upstreamRes['meta'] ?? []));
                $freshness['state'] === 'fresh' ? $fresh++ : $stale++;
                $contributions[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    'summary' => $summaryData,
                ];
                $included++;
                $sourceStatuses[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    ...$freshness,
                    'problems' => [],
                ];
            } catch (\Throwable $exception) {
                $missing++;
                $message = trim($exception->getMessage());
                $isContractFailure = preg_match('/^(Column |Response |Meta |data |meta ).*(must|required|expected|invalid)/imu', $message) === 1;
                $contractProblems = $isContractFailure
                    ? array_slice(array_values(array_filter(preg_split('/\R/u', $message) ?: [])), 0, 6)
                    : [];
                $sourceStatuses[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    'state' => $contractProblems === [] ? 'unavailable' : 'invalid_format',
                    'problems' => $contractProblems,
                ];
            }
        }

        $totals = null;
        if (! empty($contributions) && $operationKey !== 'orders.analytics') {
            $totals = $contributions[0]['summary'];
            for ($i = 1; $i < count($contributions); $i++) {
                $totals = $this->combine($totals, $contributions[$i]['summary']);
            }
        }

        $isComplete = ($missing === 0 && ! empty($sources));
        $state = ! $isComplete ? 'partial' : ($stale > 0 ? 'stale' : 'fresh');

        return [
            'data' => [
                'totals_all_vendors' => ($all && $isComplete) ? $totals : null,
                'totals_selected_vendors' => (! $all && $isComplete) ? $totals : null,
                'totals_available' => $totals,
                'by_vendor' => $contributions,
            ],
            'meta' => [
                'state' => $state,
                'vendor_scope' => $filters['vendor_scope'] ?? 'all',
                'coverage' => [
                    'expected_sources' => count($sources),
                    'included_sources' => $included,
                    'fresh_sources' => $fresh,
                    'stale_sources' => $stale,
                    'missing_sources' => $missing,
                    'excluded_sources' => 0,
                ],
                'sources' => $sourceStatuses,
            ],
        ];
    }

    /**
     * Rebuild regional analytics from the already scope-filtered detail rows. This keeps
     * chart totals consistent with the visible DO list even when a vendor accidentally
     * returns national aggregates for a regional request.
     */
    private function rebuildScopedAnalytics(array $summaryData): array
    {
        $slaBuckets = [];
        foreach ($summaryData['sla_details'] ?? [] as $row) {
            $carrier = (string) ($row['carrier_name'] ?? '');
            $targetDays = (int) ($row['sla_target_days'] ?? 0);
            $status = (string) ($row['sla_status'] ?? '');
            if ($carrier === '' || ! in_array($status, ['faster', 'on_sla', 'over_sla'], true)) {
                continue;
            }
            $key = $carrier.'|'.$targetDays;
            $slaBuckets[$key] ??= [
                'carrier_name' => $carrier,
                'faster_count' => 0,
                'on_sla_count' => 0,
                'over_sla_count' => 0,
                'total_orders' => 0,
                'sla_target_days' => $targetDays,
            ];
            $slaBuckets[$key][$status.'_count']++;
            $slaBuckets[$key]['total_orders']++;
        }
        $summaryData['sla'] = array_values($slaBuckets);

        $retryBuckets = [];
        foreach ($summaryData['retry_details'] ?? [] as $row) {
            $reason = (string) ($row['reason_code'] ?? '');
            $carrier = (string) ($row['carrier_name'] ?? '');
            if ($reason === '' || $carrier === '') {
                continue;
            }
            $key = $reason.'|'.$carrier;
            $retryBuckets[$key] ??= [
                'reason_code' => $reason,
                'carrier_name' => $carrier,
                'retry_count' => 0,
            ];
            $retryBuckets[$key]['retry_count']++;
        }
        $summaryData['retries'] = array_values($retryBuckets);

        return $summaryData;
    }

    private function combine(array $a, array $b): array
    {
        if ($this->isGroupedSummaryList($a) || $this->isGroupedSummaryList($b)) {
            return $this->combineGroupedSummaryLists($a, $b);
        }

        $res = [];
        foreach ($a as $k => $v) {
            if ($k === 'group_code') {
                $res[$k] = $v;
            } elseif (is_array($v)) {
                $res[$k] = $this->combine($v, $b[$k] ?? []);
            } elseif (is_numeric($v)) {
                $res[$k] = $v + ($b[$k] ?? 0);
            } else {
                $res[$k] = $v;
            }
        }
        foreach ($b as $k => $v) {
            if (! array_key_exists($k, $res)) {
                $res[$k] = $v;
            }
        }

        return $res;
    }

    private function isGroupedSummaryList(array $value): bool
    {
        return isset($value[0]) && is_array($value[0]) && array_key_exists('group_code', $value[0]);
    }

    private function combineGroupedSummaryLists(array $a, array $b): array
    {
        $groups = [];
        foreach (array_merge($a, $b) as $row) {
            if (! is_array($row) || ! array_key_exists('group_code', $row)) {
                continue;
            }
            $key = (string) $row['group_code'];
            $groups[$key] = isset($groups[$key]) ? $this->combine($groups[$key], $row) : $row;
            $groups[$key]['group_code'] = $row['group_code'];
        }
        ksort($groups, SORT_STRING);

        return array_values($groups);
    }
}
