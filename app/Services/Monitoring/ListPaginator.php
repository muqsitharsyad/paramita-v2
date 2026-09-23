<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\ScopeContext;
use App\Services\Gateway\VendorGateway;

class ListPaginator
{
    private const UPSTREAM_PAGE_SIZE = 100;

    private const MAX_UPSTREAM_PAGES = 1000;

    public function __construct(
        private readonly MonitoringRepository $repository,
        private readonly VendorGateway $gateway
    ) {}

    public function paginate(string $operationKey, array $filters, ScopeContext $scope): array
    {
        $sources = $this->repository->sources($operationKey, $filters, false);

        if (empty($sources)) {
            return [
                'data' => [],
                'pagination' => MonitoringPayload::pagination($filters['limit'], $filters['offset'], 0, 0),
                'sources' => [],
            ];
        }

        $allData = [];
        $totalCount = 0;
        $sourceStatuses = [];
        $freshCount = 0;
        $staleCount = 0;

        foreach ($sources as $src) {
            try {
                $upstreamOffset = 0;
                $previousPageFingerprint = null;

                for ($page = 0; $page < self::MAX_UPSTREAM_PAGES; $page++) {
                    $upstreamRes = $this->gateway->execute(
                        vendorCode: $src['code'],
                        operationKey: $operationKey,
                        scope: $scope,
                        filters: array_merge($filters, [
                            'limit' => self::UPSTREAM_PAGE_SIZE,
                            'offset' => $upstreamOffset,
                        ])
                    );

                    $rows = $upstreamRes['data'] ?? [];
                    $fingerprint = hash('sha256', serialize($rows));
                    if ($rows !== [] && $fingerprint === $previousPageFingerprint) {
                        throw new MonitoringException(502, 'UPSTREAM_PAGINATION_STALLED');
                    }
                    $previousPageFingerprint = $fingerprint;

                    foreach ($rows as $row) {
                        $row['vendor_code'] = $src['code'];
                        $row['vendor_name'] = $src['name'];
                        $row = $this->repository->labels($row);
                        if ($this->matchesFilters($row, $filters)) {
                            $allData[] = $row;
                        }
                    }

                    $rowCount = count($rows);
                    if ($rowCount === 0 || ! ($upstreamRes['meta']['has_more'] ?? false)) {
                        break;
                    }

                    $upstreamOffset += $rowCount;
                    if ($page === self::MAX_UPSTREAM_PAGES - 1) {
                        throw new MonitoringException(502, 'UPSTREAM_PAGINATION_LIMIT');
                    }
                }

                $totalCount = count($allData);
                $freshness = $this->repository->freshness((array) ($upstreamRes['meta'] ?? []));
                $freshness['state'] === 'fresh' ? $freshCount++ : $staleCount++;

                $sourceStatuses[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    ...$freshness,
                    'fetched_at' => now()->toIso8601String(),
                    'error_code' => null,
                ];
            } catch (\Throwable $e) {
                $sourceStatuses[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    'state' => 'unavailable',
                    'fetched_at' => null,
                    'error_code' => 'UPSTREAM_ERROR',
                ];
            }
        }

        $limit = $filters['limit'];
        $offset = $filters['offset'];
        $sliced = array_slice($allData, $offset, $limit);

        return [
            'data' => $sliced,
            'pagination' => MonitoringPayload::pagination($limit, $offset, $totalCount ?: count($allData), count($sliced)),
            'sources' => $sourceStatuses,
            'meta' => [
                'state' => count($sourceStatuses) !== $freshCount + $staleCount ? 'partial' : ($staleCount > 0 ? 'stale' : 'fresh'),
                'coverage' => [
                    'expected_sources' => count($sources),
                    'included_sources' => $freshCount + $staleCount,
                    'fresh_sources' => $freshCount,
                    'stale_sources' => $staleCount,
                    'missing_sources' => count($sourceStatuses) - $freshCount - $staleCount,
                ],
            ],
        ];
    }

    private function matchesSearch(array $row, ?string $search): bool
    {
        if ($search === null || trim($search) === '') {
            return true;
        }

        $needle = mb_strtolower(trim($search));
        $fields = [
            'id', 'order_number', 'student_name', 'province', 'city', 'district', 'village',
            'ut_code', 'ut_name', 'program_code', 'program_name', 'package_code', 'package_title',
            'catalog_key', 'item_code', 'title', 'vendor_code', 'vendor_name', 'tracking_number',
        ];

        foreach ($fields as $field) {
            if (isset($row[$field]) && mb_stripos((string) $row[$field], $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        if (! $this->matchesSearch($row, $filters['search'] ?? null)) {
            return false;
        }

        foreach (['ut_code', 'period_code', 'item_type'] as $field) {
            if (($filters[$field] ?? '') !== '' && (string) ($row[$field] ?? '') !== (string) $filters[$field]) {
                return false;
            }
        }

        if (($filters['program_codes'] ?? '') !== '') {
            $programs = explode(',', (string) $filters['program_codes']);
            if (! in_array((string) ($row['program_code'] ?? ''), $programs, true)) {
                return false;
            }
        }

        if (($filters['stock_status'] ?? '') !== '' && (string) ($row['stock_status'] ?? '') !== (string) $filters['stock_status']) {
            return false;
        }

        return $this->matchesDateRange($row['updated_at'] ?? null, $filters['updated_from'] ?? null, $filters['updated_to'] ?? null)
            && $this->matchesDateRange($row['ordered_at'] ?? null, $filters['ordered_from'] ?? null, $filters['ordered_to'] ?? null);
    }

    private function matchesDateRange(mixed $value, ?string $from, ?string $to): bool
    {
        if ((! $from && ! $to) || ! $value) {
            return true;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return false;
        }

        return (! $from || $timestamp >= strtotime($from)) && (! $to || $timestamp < strtotime($to));
    }
}
