<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Access\ScopeResolver;
use App\Services\Gateway\VendorGateway;
use App\Services\Monitoring\ListPaginator;
use App\Services\Monitoring\MatrixService;
use App\Services\Monitoring\MonitoringFilters;
use App\Services\Monitoring\MonitoringRepository;
use App\Services\Monitoring\SummaryAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MonitoringDataController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly VendorGateway $gateway,
        private readonly ListPaginator $paginator,
        private readonly SummaryAggregator $aggregator,
        private readonly MatrixService $matrixService,
        private readonly MonitoringRepository $repository
    ) {}

    public function stockSummary(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());
        $filters = (new MonitoringFilters)->normalize($request->all(), $scope, 'inventory.summary');
        $result = $this->aggregator->aggregate('inventory.summary', $filters, $scope);
        $result['meta']['request_id'] = 'REQ-SUMMARY-'.uniqid();
        $result['meta']['scope'] = [
            'role' => $scope->role,
            'ut_code' => $scope->assignedUtCode,
            'is_all_regions' => $scope->isAllRegions,
        ];

        return response()->json($result);
    }

    public function stockItems(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());
        $filters = MonitoringFilters::stock($request->all(), $scope);
        $result = $this->paginator->paginate('inventory.list', $filters, $scope);

        return response()->json($result);
    }

    public function stockMatrix(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());
        $filters = (new MonitoringFilters)->normalize($request->all(), $scope, 'inventory.lookup');
        $result = $this->matrixService->matrix($filters, $scope);

        return response()->json($result);
    }

    public function ordersList(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());

        if ($request->has('process_status_bucket') && $request->has('process_status_code')) {
            return response()->json([
                'type' => 'https://paramita-final.test/errors/invalid-parameters',
                'title' => 'Invalid Parameters',
                'status' => 422,
                'detail' => 'process_status_bucket and process_status_code are mutually exclusive',
                'instance' => $request->path(),
                'request_id' => 'REQ-422',
            ], 422, ['Content-Type' => 'application/problem+json']);
        }

        $filters = MonitoringFilters::orders($request->all(), $scope);
        $result = $this->paginator->paginate('orders.list', $filters, $scope);
        $result['meta'] = [
            'pagination' => $result['pagination'],
            'sources' => $result['sources'],
            'scope' => [
                'role' => $scope->role,
                'ut_code' => $scope->assignedUtCode,
            ],
        ];

        if (! $scope->canViewPii()) {
            foreach ($result['data'] as &$row) {
                $row['student_name'] = 'Mahasiswa (Disamarkan)';
                $row['province'] = null;
                $row['city'] = null;
                $row['district'] = null;
                $row['village'] = null;
            }
        }

        return response()->json($result);
    }

    public function ordersSummary(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());
        $filters = (new MonitoringFilters)->normalize($request->all(), $scope, 'orders.summary');
        $result = $this->aggregator->aggregate('orders.summary', $filters, $scope);

        return response()->json($result);
    }

    public function ordersAnalytics(Request $request): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());
        $filters = (new MonitoringFilters)->normalize($request->all(), $scope, 'orders.analytics');
        $result = $this->aggregator->aggregate('orders.analytics', $filters, $scope);

        return response()->json($result);
    }

    public function orderDetail(Request $request, string $vendor, string $source_id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());

        // Tutor is not allowed full detail/POD (PRD §3.1)
        if (! $scope->canViewPii()) {
            return response()->json([
                'type' => 'https://paramita-final.test/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Tutor is not authorized to view individual order details',
                'instance' => $request->path(),
                'request_id' => 'REQ-403',
            ], 403, ['Content-Type' => 'application/problem+json']);
        }

        $result = $this->gateway->execute(
            vendorCode: $vendor,
            operationKey: 'orders.detail',
            scope: $scope,
            filters: [],
            sourceId: $source_id
        );
        $result['data'] = $this->repository->labels($result['data'] ?? []);

        // Defense-in-depth: a regional head must never open a DO from another UT, even if the
        // vendor ignores the ut_code filter on orders.detail. The detail row carries ut_code.
        $rowUtCode = is_array($result['data']) && isset($result['data']['ut_code']) && is_string($result['data']['ut_code'])
            ? $result['data']['ut_code']
            : null;
        if (! $scope->rowBelongsToScope($rowUtCode)) {
            return response()->json([
                'type' => 'https://paramita-final.test/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'SCOPE_FORBIDDEN',
                'instance' => $request->path(),
                'request_id' => 'REQ-403',
            ], 403, ['Content-Type' => 'application/problem+json']);
        }

        $result['meta']['scope']['role'] = $scope->role;
        $result['meta']['scope']['ut_code'] = $scope->assignedUtCode;

        return response()->json($result);
    }

    public function orderEvents(Request $request, string $vendor, string $source_id): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());

        // Validate the parent DO before exposing its event history. Vendors receive ut_code on
        // both calls, but this check keeps the route fail-closed if an upstream ignores it.
        $detail = $this->gateway->execute(
            vendorCode: $vendor,
            operationKey: 'orders.detail',
            scope: $scope,
            filters: [],
            sourceId: $source_id
        );
        $detailUtCode = is_string($detail['data']['ut_code'] ?? null) ? $detail['data']['ut_code'] : null;
        if (! $scope->rowBelongsToScope($detailUtCode)) {
            return response()->json([
                'type' => 'https://paramita-final.test/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'SCOPE_FORBIDDEN',
                'instance' => $request->path(),
                'request_id' => 'REQ-403',
            ], 403, ['Content-Type' => 'application/problem+json']);
        }

        $filters = (new MonitoringFilters)->normalize(
            array_merge($request->all(), ['kind' => $request->query('kind', 'history')]),
            $scope,
            'orders.events'
        );
        $result = $this->gateway->execute(
            vendorCode: $vendor,
            operationKey: 'orders.events',
            scope: $scope,
            filters: $filters,
            sourceId: $source_id
        );
        $result['data'] = array_map(
            fn (array $event): array => $this->repository->labels($event),
            $result['data'] ?? []
        );
        $result['meta']['scope']['role'] = $scope->role;
        $result['meta']['scope']['ut_code'] = $scope->assignedUtCode;

        return response()->json($result);
    }

    public function orderProof(Request $request, string $vendor, string $source_id): Response|JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());
        if (! $scope->canViewPii()) {
            return response()->json([
                'type' => 'https://paramita-final.test/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Tutor is not authorized to view proof of delivery',
                'instance' => $request->path(),
                'request_id' => 'REQ-403',
            ], 403, ['Content-Type' => 'application/problem+json']);
        }

        $proof = $this->gateway->orderProof($vendor, $scope, $source_id);

        return response($proof['body'], 200, [
            'Content-Type' => $proof['content_type'],
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function options(Request $request, string $type): JsonResponse
    {
        $scope = $this->scopeResolver->resolve($request->user());

        $data = match ($type) {
            'ut' => $scope->isAllRegions
                ? DB::table('ut_regions')->where('is_active', true)->select('code', 'name', 'type')->get()
                : DB::table('ut_regions')->where('code', $scope->assignedUtCode)->select('code', 'name', 'type')->get(),
            'programs' => DB::table('programs')->where('is_active', true)->select('code', 'name')->get(),
            'period' => DB::table('periods')->where('is_active', true)->orderBy('sort_order')->select('code', 'name')->get(),
            'vendors' => DB::table('vendors')->where('status', 'approved')->select('code', 'legal_name as name')->get(),
            'catalog' => DB::table('catalog_items')->where('is_active', true)->select('catalog_key as code', 'title as name')->get(),
            'retry-reasons' => DB::table('retry_reasons')->where('is_active', true)->orderBy('sort_order')->select('code', 'label as name')->get(),
            default => [],
        };

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'limit' => count($data),
                    'offset' => 0,
                    'total_filtered' => count($data),
                    'has_more' => false,
                    'next_offset' => null,
                ],
                'scope' => [
                    'role' => $scope->role,
                    'ut_code' => $scope->assignedUtCode,
                ],
            ],
        ]);
    }
}
