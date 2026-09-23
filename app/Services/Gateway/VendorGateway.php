<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\DTO\ScopeContext;
use App\Services\Contracts\VendorPayloadContract;
use App\Services\Monitoring\MonitoringException;
use App\Services\Monitoring\MonitoringFilters;
use Exception;
use Illuminate\Support\Facades\DB;

class VendorGateway
{
    private const SAFE_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly SafeHttpClient $httpClient,
        private readonly AuthResolver $authResolver,
        private readonly VendorPayloadContract $payloadContract
    ) {}

    public function execute(
        string $vendorCode,
        string $operationKey,
        ScopeContext $scope,
        array $filters = [],
        ?string $sourceId = null
    ): array {
        return $this->call($vendorCode, $operationKey, $scope, $filters, $sourceId);
    }

    /**
     * Call an approved active vendor binding.
     */
    public function call(
        string $vendorCode,
        string $operationKey,
        ScopeContext $scope,
        array $query = [],
        ?string $sourceId = null,
        ?string $schemaFileName = null
    ): array {
        $vendor = $this->approvedVendor($vendorCode);
        $contract = $this->contract($operationKey);

        $binding = DB::table('endpoint_bindings')
            ->where('vendor_id', $vendor->id)
            ->where('contract_id', $contract->id)
            ->where('is_enabled', true)
            ->first();

        if (! $binding || ! $binding->active_revision_id) {
            throw new Exception("No approved active revision for vendor $vendorCode on $operationKey");
        }

        $rev = DB::table('binding_revisions')->where('id', $binding->active_revision_id)->first();
        if (! $rev || $rev->status !== 'approved') {
            throw new Exception("No approved binding revision for vendor $vendorCode on $operationKey");
        }

        return $this->callResolvedRevision($vendorCode, $operationKey, $rev, $scope, $query, $sourceId, $schemaFileName);
    }

    /**
     * Call a specific vendor binding revision. Used by vendor self-test for draft edits before approval.
     */
    public function callRevision(
        string $vendorCode,
        string $operationKey,
        int $bindingRevisionId,
        ScopeContext $scope,
        array $query = [],
        ?string $sourceId = null,
        ?string $schemaFileName = null
    ): array {
        $vendor = $this->approvedVendor($vendorCode);
        $contract = $this->contract($operationKey);

        $rev = DB::table('binding_revisions as br')
            ->join('endpoint_bindings as eb', 'eb.id', '=', 'br.binding_id')
            ->where('br.id', $bindingRevisionId)
            ->where('eb.vendor_id', $vendor->id)
            ->where('eb.contract_id', $contract->id)
            ->select('br.*')
            ->first();

        if (! $rev) {
            throw new Exception("Binding revision $bindingRevisionId not found for $vendorCode $operationKey");
        }

        return $this->callResolvedRevision($vendorCode, $operationKey, $rev, $scope, $query, $sourceId, $schemaFileName);
    }

    /** @return array{body:string, content_type:string} */
    public function orderProof(string $vendorCode, ScopeContext $scope, string $sourceId): array
    {
        $detail = $this->call($vendorCode, 'orders.detail', $scope, sourceId: $sourceId);
        $detailUtCode = is_string($detail['data']['ut_code'] ?? null) ? $detail['data']['ut_code'] : null;
        if (! $scope->rowBelongsToScope($detailUtCode)) {
            throw new MonitoringException(403, 'SCOPE_FORBIDDEN');
        }
        $proofUrl = $detail['data']['proof_of_delivery_url'] ?? null;
        if (! is_string($proofUrl) || trim($proofUrl) === '') {
            throw new Exception('Proof of delivery is not available');
        }

        $vendor = $this->approvedVendor($vendorCode);
        $contract = $this->contract('orders.detail');
        $revision = DB::table('endpoint_bindings as eb')
            ->join('binding_revisions as br', 'br.id', '=', 'eb.active_revision_id')
            ->where('eb.vendor_id', $vendor->id)
            ->where('eb.contract_id', $contract->id)
            ->where('eb.is_enabled', true)
            ->where('br.status', 'approved')
            ->select('br.*')
            ->first();
        if ($revision === null) {
            throw new Exception("No approved binding revision for vendor $vendorCode on orders.detail");
        }

        $connection = DB::table('connection_revisions')->where('id', $revision->connection_revision_id)->first();
        if ($connection === null) {
            throw new Exception("Connection revision missing for vendor $vendorCode on orders.detail");
        }

        $url = $this->resolveProofUrl((string) $connection->base_url, $proofUrl);
        $sameOrigin = $this->origin($url) === $this->origin((string) $connection->base_url);
        $auth = $sameOrigin
            ? $this->authResolver->resolve($connection->auth_type, $connection->auth_config_ciphertext, (int) $connection->id)
            : ['headers' => [], 'query' => []];

        $response = $this->httpClient->get(
            $url,
            array_merge($auth['headers'], ['Accept' => implode(', ', self::SAFE_IMAGE_TYPES)]),
            $auth['query'],
            timeout: 8
        );
        if ($response->status() !== 200) {
            throw new Exception('Vendor proof returned HTTP '.$response->status());
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! in_array($contentType, self::SAFE_IMAGE_TYPES, true)) {
            throw new Exception('Vendor proof must be JPEG, PNG, WebP, or GIF');
        }

        return ['body' => $response->body(), 'content_type' => $contentType];
    }

    private function callResolvedRevision(
        string $vendorCode,
        string $operationKey,
        object $rev,
        ScopeContext $scope,
        array $query,
        ?string $sourceId,
        ?string $schemaFileName
    ): array {
        $connRev = DB::table('connection_revisions')->where('id', $rev->connection_revision_id)->first();
        if (! $connRev) {
            throw new Exception("Connection revision missing for vendor $vendorCode on $operationKey");
        }

        if (! $scope->isAllRegions) {
            $query['ut_code'] = $scope->assignedUtCode;
        }

        $staticQuery = json_decode((string) ($rev->static_query_json ?? ''), true);
        if (! is_array($staticQuery)) {
            $staticQuery = [];
        }

        $path = (string) $rev->path;
        if ($sourceId !== null) {
            $path = str_replace('{source_id}', rawurlencode($sourceId), $path);
        }
        if (str_contains($path, '{source_id}')) {
            throw new Exception("Binding path for $operationKey requires source id");
        }

        $url = rtrim($connRev->base_url, '/').'/'.ltrim($path, '/');

        $auth = $this->authResolver->resolve($connRev->auth_type, $connRev->auth_config_ciphertext, (int) $connRev->id);
        $headers = array_merge($auth['headers'], ['Accept' => 'application/json']);
        $mergedQuery = array_merge($staticQuery, MonitoringFilters::upstream($query), $auth['query']);

        $res = $this->httpClient->get($url, $headers, $mergedQuery, timeout: 8);
        if ($res->status() === 401 && in_array($connRev->auth_type, ['oauth2_client_credentials', 'token_login'], true)) {
            // Token may have been revoked early. Discard this revision's cached token and retry exactly once.
            $this->authResolver->forgetAccessToken((int) $connRev->id);
            $auth = $this->authResolver->resolve($connRev->auth_type, $connRev->auth_config_ciphertext, (int) $connRev->id);
            $headers = array_merge($auth['headers'], ['Accept' => 'application/json']);
            $mergedQuery = array_merge($staticQuery, MonitoringFilters::upstream($query), $auth['query']);
            $res = $this->httpClient->get($url, $headers, $mergedQuery, timeout: 8);
        }
        if ($res->status() !== 200) {
            throw new Exception('Vendor returned HTTP '.$res->status());
        }

        $body = $res->body();
        $decoded = json_decode($body);
        if (! $decoded) {
            throw new Exception('Vendor returned malformed JSON');
        }

        // One source, one validation: the raw vendor response is checked directly against the
        // JSON format saved by the admin. No mapping, rule table, or second schema is involved.
        $contractProblems = $this->payloadContract->validate($operationKey, $decoded);
        if ($contractProblems !== []) {
            throw new Exception(implode("\n", $contractProblems));
        }

        return json_decode($body, true);
    }

    private function approvedVendor(string $vendorCode): object
    {
        $vendor = DB::table('vendors')->where('code', $vendorCode)->where('status', 'approved')->first();
        if (! $vendor) {
            throw new Exception("Vendor $vendorCode not found or not approved");
        }

        return $vendor;
    }

    private function contract(string $operationKey): object
    {
        $contract = DB::table('contracts')->where('operation_key', $operationKey)->first();
        if (! $contract) {
            throw new Exception("Contract operation $operationKey not found");
        }

        return $contract;
    }

    private function resolveProofUrl(string $baseUrl, string $proofUrl): string
    {
        if (parse_url($proofUrl, PHP_URL_SCHEME) !== null) {
            return $proofUrl;
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            throw new Exception('Vendor base URL is invalid');
        }
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return $base['scheme'].'://'.$base['host'].$port.'/'.ltrim($proofUrl, '/');
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new Exception('Proof of delivery URL is invalid');
        }
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).':'.$port;
    }
}
