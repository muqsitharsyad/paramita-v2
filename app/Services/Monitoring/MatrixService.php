<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\ScopeContext;
use App\Services\Gateway\VendorGateway;

class MatrixService
{
    public function __construct(
        private readonly MonitoringRepository $repository,
        private readonly VendorGateway $gateway
    ) {}

    public function matrix(array $filters, ScopeContext $scope): array
    {
        $catalogPage = $this->repository->page('catalog', $filters, $scope);
        $catalogItems = $catalogPage['data'];
        $sources = $this->repository->sources('inventory.lookup', $filters, false);

        $matrixRows = [];
        foreach ($catalogItems as $item) {
            $matrixRows[$item['code']] = [
                'catalog_key' => $item['code'],
                'item_code' => $item['item_code'] ?? $item['code'],
                'edition' => $item['edition'] ?? '',
                'title' => $item['name'],
                'stocks' => [],
            ];
        }

        $sourceStatuses = [];
        $freshCount = 0;
        $staleCount = 0;
        $catalogKeys = array_keys($matrixRows);
        foreach ($sources as $src) {
            try {
                $lookupFilters = array_merge($filters, [
                    'catalog_keys' => implode(',', $catalogKeys),
                    'limit' => count($catalogKeys),
                    'offset' => 0,
                ]);
                $response = $this->gateway->execute(
                    vendorCode: $src['code'],
                    operationKey: 'inventory.lookup',
                    scope: $scope,
                    filters: $lookupFilters
                );
                $rows = $response['data'] ?? [];
                foreach ($rows as $row) {
                    $catalogKey = $row['catalog_key'] ?? null;
                    if (! $catalogKey || ! isset($matrixRows[$catalogKey])) {
                        continue;
                    }
                    $matrixRows[$catalogKey]['stocks'][$src['code']] = [
                        'vendor_code' => $src['code'],
                        'vendor_name' => $src['name'],
                        'stock_quantity' => $row['stock_quantity'] ?? null,
                        'availability' => $row['availability'] ?? 'available',
                        'updated_at' => $row['updated_at'] ?? null,
                    ];
                }
                $freshness = $this->repository->freshness((array) ($response['meta'] ?? []));
                $freshness['state'] === 'fresh' ? $freshCount++ : $staleCount++;
                $sourceStatuses[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    ...$freshness,
                ];
            } catch (\Throwable) {
                $sourceStatuses[] = [
                    'vendor_code' => $src['code'],
                    'vendor_name' => $src['name'],
                    'state' => 'unavailable',
                ];
            }
        }

        return [
            'data' => array_values($matrixRows),
            'pagination' => $catalogPage['pagination'],
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
}
