<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\DTO\ScopeContext;
use App\Http\Controllers\Controller;
use App\Services\Contracts\VendorContractGuide;
use App\Services\Gateway\AuthResolver;
use App\Services\Gateway\SafeHttpClient;
use App\Services\Gateway\VendorGateway;
use App\Services\Onboarding\ApprovalService;
use App\Services\Security\IntegrationKeyEncrypter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class VendorPortalController extends Controller
{
    private const AUTH_TYPES = ['none', 'api_key_header', 'bearer', 'basic', 'api_key_query', 'oauth2_client_credentials', 'token_login'];

    public function __construct(
        private readonly VendorGateway $gateway,
        private readonly ApprovalService $approvalService,
        private readonly VendorContractGuide $contractGuide,
        private readonly SafeHttpClient $safeHttpClient,
        private readonly AuthResolver $authResolver,
    ) {}

    public function dashboard(Request $request): View
    {
        $vendorId = (int) $request->user()->vendor_id;
        $section = (string) ($request->route('section') ?? 'overview');
        $selectedOperation = (string) $request->query('operation', 'inventory.list');
        $vendor = DB::table('vendors')->where('id', $vendorId)->firstOrFail();
        $this->ensureEndpointBindings($vendorId);

        $connections = DB::table('connections as c')
            ->leftJoin('connection_revisions as cr', function ($join): void {
                $join->on('cr.connection_id', '=', 'c.id')
                    ->whereRaw('cr.revision = (select max(revision) from connection_revisions where connection_id = c.id)');
            })
            ->where('c.vendor_id', $vendorId)
            ->select('c.id', 'c.label', 'cr.id as revision_id', 'cr.base_url', 'cr.auth_type', 'cr.auth_config_ciphertext', 'cr.revision')
            ->orderBy('c.id')
            ->get()
            ->map(function (object $connection): object {
                $connection->auth_label = $this->authLabel((string) ($connection->auth_type ?? 'none'));
                $connection->credential_configured = $connection->auth_type === 'none' || ! empty($connection->auth_config_ciphertext);
                $publicConfig = $this->publicAuthConfig((string) ($connection->auth_type ?? 'none'), $connection->auth_config_ciphertext ?? null);
                $connection->auth_details = $this->safeAuthDetails((string) ($connection->auth_type ?? 'none'), $connection->auth_config_ciphertext ?? null);
                $connection->auth_config_public = $publicConfig;
                unset($connection->auth_config_ciphertext);

                return $connection;
            });

        $bindings = DB::table('endpoint_bindings as eb')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->leftJoin('binding_revisions as active', 'active.id', '=', 'eb.active_revision_id')
            ->leftJoin('connection_revisions as acr', 'acr.id', '=', 'active.connection_revision_id')
            ->leftJoin('binding_revisions as draft', 'draft.id', '=', 'eb.draft_revision_id')
            ->leftJoin('connection_revisions as dcr', 'dcr.id', '=', 'draft.connection_revision_id')
            ->where('eb.vendor_id', $vendorId)
            ->select(
                'eb.id', 'eb.is_enabled', 'eb.active_revision_id', 'eb.draft_revision_id',
                'c.operation_key', 'c.module', 'c.label',
                'active.path as active_path', 'active.status as active_status', 'acr.base_url as active_base_url',
                'acr.auth_type as active_auth_type', 'dcr.auth_type as draft_auth_type',
                'active.connection_revision_id as active_connection_revision_id', 'draft.connection_revision_id as draft_connection_revision_id',
                'draft.path as draft_path', 'draft.status as draft_status', 'dcr.base_url as draft_base_url'
            )
            ->orderBy('c.module')
            ->orderBy('c.operation_key')
            ->get()
            ->map(function (object $binding): object {
                $targetRevisionId = $binding->draft_revision_id ?: $binding->active_revision_id;
                $binding->latest_run = $targetRevisionId
                    ? DB::table('endpoint_test_runs')->where('binding_revision_id', $targetRevisionId)->orderByDesc('id')->first()
                    : null;
                $binding->guide = $this->contractGuide->forOperation((string) $binding->operation_key);
                if ($tpl = DB::table('json_templates')->where('name', $binding->operation_key)->where('is_active', true)->first()) {
                    $binding->guide['doc_version'] = (string) $tpl->version;
                }
                $binding->effective_path = $binding->draft_path ?: $binding->active_path;
                $binding->effective_base_url = $binding->draft_base_url ?: $binding->active_base_url;
                $effectiveAuth = (string) ($binding->draft_auth_type ?: $binding->active_auth_type ?: 'none');
                $binding->effective_auth_type = $effectiveAuth;
                $binding->effective_auth_label = $this->authLabel($effectiveAuth);
                $binding->uses_auto_token = in_array($effectiveAuth, ['token_login', 'oauth2_client_credentials'], true);
                $binding->sample_url = $binding->effective_base_url && $binding->effective_path
                    ? rtrim((string) $binding->effective_base_url, '/').'/'.ltrim((string) $binding->effective_path, '/')
                    : null;

                return $binding;
            });

        return view('vendor.dashboard', [
            'vendor' => $vendor,
            'connections' => $connections,
            'bindings' => $bindings,
            'credentialStorageReady' => $this->credentialStorageReady(),
            'section' => $section,
            'selectedOperation' => $selectedOperation,
            'transport' => VendorContractGuide::transport(),
            // Reference codes Paramita owns and the vendor must use (section: reference).
            'reference' => $this->referenceData(),
        ]);
    }

    /**
     * Reference data the vendor must speak: the codes Paramita sends/expects.
     * Read from the tables the admin maintains, so the vendor page can never show a stale list.
     *
     * @return array<string, Collection<int, object>>
     */
    private function referenceData(): array
    {
        return [
            'ut' => DB::table('ut_regions')->where('is_active', true)->orderBy('code', 'desc')->get(['code', 'name', 'type']),
            'program' => DB::table('programs')->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'catalog' => DB::table('catalog_items')->where('is_active', true)->orderBy('catalog_key')->get(['catalog_key', 'item_type', 'item_code', 'edition', 'title']),
            'status' => DB::table('process_statuses')->where('is_active', true)->orderBy('sort_order')->get(['code', 'label', 'bucket']),
            'retry_reason' => DB::table('retry_reasons')->where('is_active', true)->orderBy('sort_order')->get(['code', 'label', 'description']),
        ];
    }

    public function storeConnection(Request $request): RedirectResponse
    {
        if (DB::table('connections')->where('vendor_id', (int) $request->user()->vendor_id)->exists()) {
            return back()->withInput()->with('error', 'Koneksi API sudah ada. Gunakan tombol Perbarui.');
        }
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_type' => ['required', 'in:'.implode(',', self::AUTH_TYPES)],
            'credential_header_name' => ['nullable', 'string', 'max:255'],
            'credential_param_name' => ['nullable', 'string', 'max:255'],
            'credential_username' => ['nullable', 'string', 'max:255'],
            'credential_secret' => ['nullable', 'string', 'max:4096'],
            'oauth_token_url' => ['nullable', 'url', 'max:2048'],
            'oauth_client_id' => ['nullable', 'string', 'max:255'],
            'oauth_scope' => ['nullable', 'string', 'max:1000'],
            'login_url' => ['nullable', 'url', 'max:2048'],
            'login_username_field' => ['nullable', 'string', 'max:100'],
            'login_password_field' => ['nullable', 'string', 'max:100'],
            'login_token_field' => ['nullable', 'string', 'max:255'],
            'login_expires_in_field' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->safeHttpClient->validateUrl($validated['base_url']);
            if (in_array($validated['auth_type'], ['oauth2_client_credentials', 'token_login'], true)) {
                $tokenEndpoint = $validated['auth_type'] === 'oauth2_client_credentials'
                    ? (string) ($validated['oauth_token_url'] ?? '')
                    : (string) ($validated['login_url'] ?? '');
                $this->safeHttpClient->validateUrl($tokenEndpoint);
            }
        } catch (Throwable $exception) {
            return back()->withInput()->with('error', 'Base URL ditolak: '.$exception->getMessage());
        }

        $authConfig = $this->authConfigFor($validated);
        if ($authConfig !== [] && ! $this->credentialStorageReady()) {
            return back()->withInput()->with('error', 'Credential belum dapat disimpan karena PARAMITA_INTEGRATION_KEY belum dipasang oleh operator server. Base URL dapat disimpan setelah key tersebut tersedia.');
        }

        $vendorId = (int) $request->user()->vendor_id;
        $ciphertext = $authConfig === []
            ? null
            : app(IntegrationKeyEncrypter::class)->encrypt(json_encode($authConfig, JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($request, $validated, $vendorId, $ciphertext): void {
            $connectionId = DB::table('connections')->insertGetId([
                'vendor_id' => $vendorId,
                'label' => $validated['label'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('connection_revisions')->insert([
                'connection_id' => $connectionId,
                'revision' => 1,
                'base_url' => rtrim($validated['base_url'], '/'),
                'auth_type' => $validated['auth_type'],
                'auth_config_ciphertext' => $ciphertext,
                'secret_version' => 1,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Koneksi API tersimpan.');
    }

    public function testConnectionLogin(Request $request, int $connection): RedirectResponse
    {
        $vendorId = (int) $request->user()->vendor_id;
        $rev = DB::table('connection_revisions as cr')
            ->join('connections as c', 'c.id', '=', 'cr.connection_id')
            ->where('cr.connection_id', $connection)->where('c.vendor_id', $vendorId)
            ->orderByDesc('cr.revision')->first();
        abort_unless($rev, 404);

        if ($rev->auth_type === 'none') {
            return back()->with('success', 'Koneksi ini tidak memakai login.');
        }
        if (! in_array($rev->auth_type, ['token_login', 'oauth2_client_credentials'], true)) {
            try {
                $auth = $this->authResolver->resolve((string) $rev->auth_type, $rev->auth_config_ciphertext, (int) $rev->id);
                $ok = $auth['headers'] !== [] || $auth['query'] !== [];

                return back()->with($ok ? 'success' : 'error', $ok
                    ? 'Kredensial siap digunakan.'
                    : 'Kredensial belum lengkap.');
            } catch (Throwable $exception) {
                return back()->with('error', 'Kredensial gagal disiapkan: '.$this->sanitizeLoginError($exception));
            }
        }

        $this->authResolver->forgetAccessToken((int) $rev->id);
        $started = now();
        try {
            $auth = $this->authResolver->resolve((string) $rev->auth_type, $rev->auth_config_ciphertext, (int) $rev->id);
            $ok = isset($auth['headers']['Authorization']);

            return back()->with($ok ? 'success' : 'error', $ok
                ? 'Login berhasil.'
                : 'Token tidak ditemukan pada response.');
        } catch (Throwable $exception) {
            return back()->with('error', 'Login gagal: '.$this->sanitizeLoginError($exception));
        }
    }

    private function sanitizeLoginError(Throwable $exception): string
    {
        $msg = $exception->getMessage();
        $msg = preg_replace('/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/', '$1[redacted]', $msg) ?? $msg;

        return str($msg)->limit(180)->value();
    }

    public function updateConnection(Request $request, int $connection): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_type' => ['required', 'in:'.implode(',', self::AUTH_TYPES)],
            'credential_header_name' => ['nullable', 'string', 'max:255'],
            'credential_param_name' => ['nullable', 'string', 'max:255'],
            'credential_username' => ['nullable', 'string', 'max:255'],
            'credential_secret' => ['nullable', 'string', 'max:4096'],
            'oauth_token_url' => ['nullable', 'url', 'max:2048'],
            'oauth_client_id' => ['nullable', 'string', 'max:255'],
            'oauth_scope' => ['nullable', 'string', 'max:1000'],
            'login_url' => ['nullable', 'url', 'max:2048'],
            'login_username_field' => ['nullable', 'string', 'max:100'],
            'login_password_field' => ['nullable', 'string', 'max:100'],
            'login_token_field' => ['nullable', 'string', 'max:255'],
            'login_expires_in_field' => ['nullable', 'string', 'max:255'],
        ]);

        $vendorId = (int) $request->user()->vendor_id;
        $existing = DB::table('connection_revisions as cr')
            ->join('connections as c', 'c.id', '=', 'cr.connection_id')
            ->where('cr.connection_id', $connection)->where('c.vendor_id', $vendorId)
            ->orderByDesc('cr.revision')->first();
        abort_unless($existing, 404);

        $secretKeyByType = ['bearer' => 'token', 'basic' => 'password', 'oauth2_client_credentials' => 'client_secret', 'api_key_header' => 'key_value', 'api_key_query' => 'key_value', 'token_login' => 'password'];
        if (empty($validated['credential_secret']) && $existing->auth_type !== 'none') {
            $previous = $this->decryptAuthConfig($existing->auth_config_ciphertext ?? null);
            $previousKey = $secretKeyByType[$existing->auth_type] ?? 'key_value';
            $targetKey = $secretKeyByType[$validated['auth_type']] ?? 'key_value';
            if ($previousKey === $targetKey && ! empty($previous[$previousKey])) {
                $validated['credential_secret'] = (string) $previous[$previousKey];
            }
        }

        $authConfig = $this->authConfigFor($validated);
        if ($authConfig !== [] && ! $this->credentialStorageReady()) {
            return back()->withInput()->with('error', 'Credential belum dapat disimpan karena PARAMITA_INTEGRATION_KEY belum dipasang operator.');
        }

        $ciphertext = $authConfig === [] ? null : app(IntegrationKeyEncrypter::class)->encrypt(json_encode($authConfig, JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($request, $validated, $existing, $ciphertext): void {
            DB::table('connections')->where('id', $existing->connection_id)->update([
                'label' => $validated['label'],
                'updated_at' => now(),
            ]);
            DB::table('connection_revisions')->insert([
                'connection_id' => $existing->connection_id,
                'revision' => (int) $existing->revision + 1,
                'base_url' => rtrim($validated['base_url'], '/'),
                'auth_type' => $validated['auth_type'],
                'auth_config_ciphertext' => $ciphertext,
                'secret_version' => (int) $existing->secret_version + ($ciphertext !== null ? 1 : 0),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Koneksi API diperbarui.');
    }

    private function decryptAuthConfig(?string $ciphertext): array
    {
        if ($ciphertext === null || $ciphertext === '') {
            return [];
        }
        try {
            return json_decode(app(IntegrationKeyEncrypter::class)->decrypt($ciphertext), true) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function publicAuthConfig(string $authType, ?string $ciphertext): array
    {
        if ($authType === 'none') {
            return [];
        }
        $config = $this->decryptAuthConfig($ciphertext);
        unset($config['key_value'], $config['password'], $config['client_secret'], $config['token']);

        return array_map(strval(...), $config);
    }

    private function safeAuthDetails(string $authType, ?string $ciphertext): array
    {
        if ($authType === 'none') {
            return ['Metode' => 'Tanpa autentikasi'];
        }
        $config = $this->decryptAuthConfig($ciphertext);
        $labels = [
            'login_url' => 'Login URL',
            'token_url' => 'Token URL',
            'header_name' => 'Header',
            'param_name' => 'Parameter query',
            'username_field' => 'Field username',
            'password_field' => 'Field password',
            'token_field' => 'Path token',
            'expires_in_field' => 'Path masa token',
            'client_id' => 'Client ID',
            'scope' => 'Scope',
        ];
        $details = [];
        foreach ($labels as $key => $label) {
            if (! empty($config[$key])) {
                $details[$label] = (string) $config[$key];
            }
        }
        if (! empty($config['username'])) {
            $details['Username akun service'] = (string) $config['username'];
        }
        foreach (['key_value', 'password', 'client_secret', 'token'] as $secretKey) {
            if (! empty($config[$secretKey])) {
                $details['Kredensial rahasia'] = 'tersimpan terenkripsi';
                break;
            }
        }

        return $details ?: ['Kredensial' => 'belum diisi'];
    }

    public function updateBindingDraft(Request $request, int $binding): RedirectResponse
    {
        $validated = $request->validate([
            'connection_revision_id' => ['required', 'integer'],
            'path' => ['required', 'string', 'max:2048', 'regex:/^[^?#\\s]+(?:\\{source_id\\})?[^?#\\s]*$/'],
        ], ['path.regex' => 'Path hanya boleh berisi path endpoint. Jangan masukkan URL penuh, query, spasi, atau fragment.']);
        $vendorId = (int) $request->user()->vendor_id;

        $bindingRow = DB::table('endpoint_bindings as eb')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->join('contract_versions as cv', function ($join): void {
                $join->on('cv.contract_id', '=', 'c.id')->where('cv.status', '=', 'published');
            })
            ->where('eb.id', $binding)->where('eb.vendor_id', $vendorId)
            ->select('eb.*', 'cv.id as contract_version_id')->first();
        abort_unless($bindingRow, 404);

        $connRevision = DB::table('connection_revisions as cr')
            ->join('connections as c', 'c.id', '=', 'cr.connection_id')
            ->where('cr.id', $validated['connection_revision_id'])->where('c.vendor_id', $vendorId)
            ->select('cr.*')->first();
        abort_unless($connRevision, 404);

        $nextRevision = (int) DB::table('binding_revisions')->where('binding_id', $binding)->max('revision') + 1;
        $draftId = DB::table('binding_revisions')->insertGetId([
            'binding_id' => $binding,
            'revision' => $nextRevision,
            'connection_revision_id' => $connRevision->id,
            'contract_version_id' => $bindingRow->contract_version_id,
            'path' => ltrim($validated['path'], '/'),
            'static_query_json' => null,
            'dispatch_mode' => 'separate_path',
            'scope_revision' => 1,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('endpoint_bindings')->where('id', $binding)->update([
            'draft_revision_id' => $draftId,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Draft endpoint tersimpan.');
    }

    public function submitBindingDraft(Request $request, int $binding): RedirectResponse
    {
        $vendorId = (int) $request->user()->vendor_id;
        $bindingRow = DB::table('endpoint_bindings')->where('id', $binding)->where('vendor_id', $vendorId)->first();
        abort_unless($bindingRow && $bindingRow->draft_revision_id, 404);

        $latestPassedRun = DB::table('endpoint_test_runs')
            ->where('binding_revision_id', $bindingRow->draft_revision_id)
            ->where('status', 'passed')->orderByDesc('id')->first();
        if (! $latestPassedRun) {
            return back()->with('error', 'Test endpoint harus lulus sebelum diajukan.');
        }

        try {
            $this->approvalService->submit((int) $bindingRow->draft_revision_id, (int) $latestPassedRun->id);

            return back()->with('success', 'Draft dikirim ke admin.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function runEndpointTest(Request $request, int $binding): RedirectResponse
    {
        $result = $this->executeBindingTest($request, $binding);

        return back()->with($result['status'] === 'passed' ? 'success' : 'error', $result['message']);
    }

    public function runAllEndpointTests(Request $request): RedirectResponse
    {
        $bindingIds = DB::table('endpoint_bindings')
            ->where('vendor_id', $request->user()->vendor_id)
            ->where(fn ($query) => $query->whereNotNull('draft_revision_id')->orWhereNotNull('active_revision_id'))
            ->pluck('id');
        $passed = 0;
        $failed = 0;
        foreach ($bindingIds as $bindingId) {
            $result = $this->executeBindingTest($request, (int) $bindingId);
            $result['status'] === 'passed' ? $passed++ : $failed++;
        }

        return back()->with($failed === 0 ? 'success' : 'error', "Test selesai: {$passed} lulus, {$failed} perlu diperbaiki.");
    }

    private function executeBindingTest(Request $request, int $bindingId): array
    {
        $user = $request->user();
        $vendor = DB::table('vendors')->where('id', $user->vendor_id)->first();
        $binding = DB::table('endpoint_bindings as eb')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->where('eb.id', $bindingId)->where('eb.vendor_id', $user->vendor_id)
            ->select('eb.id', 'eb.active_revision_id', 'eb.draft_revision_id', 'c.operation_key')->first();
        $targetRevisionId = $binding?->draft_revision_id ?: $binding?->active_revision_id;
        if (! $vendor || ! $binding || ! $targetRevisionId) {
            abort(404);
        }
        $targetRevision = DB::table('binding_revisions')->where('id', $targetRevisionId)->first();
        abort_unless($targetRevision, 404);

        $started = now();
        $status = 'failed';
        $checks = [];
        $payloadStats = null;
        $error = null;
        try {
            $scope = new ScopeContext((int) $user->id, 'admin', (int) $user->permission_revision, null, [], true, (int) $user->vendor_id);
            [$query, $sourceId] = $this->testQueryForOperation((string) $binding->operation_key, (string) $vendor->code, $scope);
            $payload = $this->gateway->callRevision((string) $vendor->code, (string) $binding->operation_key, (int) $targetRevisionId, $scope, $query, $sourceId);
            $status = 'passed';
            $checks = [
                ['name' => 'Koneksi', 'status' => 'pass', 'detail' => 'Endpoint merespons HTTP 200 dan JSON valid.'],
                ['name' => 'Format response', 'status' => 'pass', 'detail' => 'Envelope dan seluruh record yang diuji sesuai kontrak endpoint.'],
                ['name' => 'Format JSON', 'status' => 'pass', 'detail' => 'Response sesuai format JSON tunggal yang ditetapkan admin.'],
                ['name' => 'Pagination dan pencarian', 'status' => 'pass', 'detail' => $this->paginationSearchDetail((string) $binding->operation_key, $payload)],
            ];
            $payloadStats = $this->payloadStats($payload);
        } catch (Throwable $exception) {
            $error = $this->friendlyTestError($exception->getMessage());
            $checks[] = ['name' => 'Test endpoint', 'status' => 'fail', 'detail' => $error];
        }

        $report = [
            'status' => $status,
            'operation_key' => $binding->operation_key,
            'binding_revision_id' => (int) $targetRevisionId,
            'revision_status' => $targetRevision->status,
            'checks' => $checks,
            'payload_stats' => $payloadStats,
            'error' => $error,
            'started_at' => $started->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ];

        DB::table('endpoint_test_runs')->insert([
            'binding_revision_id' => $targetRevisionId,
            'connection_revision_id' => $targetRevision->connection_revision_id,
            'contract_version_id' => $targetRevision->contract_version_id,
            'suite_version' => 'vendor-portal-2.0.0',
            'status' => $status,
            'report_json' => json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'started_at' => $started,
            'finished_at' => now(),
            'expires_at' => now()->addHours(24),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['status' => $status, 'message' => $status === 'passed' ? "{$binding->operation_key}: test berhasil." : "{$binding->operation_key}\n{$error}"];
    }

    private function authConfigFor(array $data): array
    {
        return match ($data['auth_type']) {
            'none' => [],
            'api_key_header' => $this->requiredSecretConfig($data, ['header_name' => $data['credential_header_name'] ?: 'X-API-Key']),
            'bearer' => $this->requiredSecretConfig($data, []),
            'api_key_query' => $this->requiredSecretConfig($data, ['param_name' => $data['credential_param_name'] ?: 'api_key']),
            'basic' => $this->basicConfig($data),
            'oauth2_client_credentials' => $this->oauthClientCredentialsConfig($data),
            'token_login' => $this->tokenLoginConfig($data),
        };
    }

    private function requiredSecretConfig(array $data, array $prefix): array
    {
        if (empty($data['credential_secret'])) {
            abort(422, 'Credential wajib diisi untuk metode autentikasi yang dipilih.');
        }

        return $data['auth_type'] === 'bearer'
            ? ['token' => $data['credential_secret']]
            : array_merge($prefix, ['key_value' => $data['credential_secret']]);
    }

    private function basicConfig(array $data): array
    {
        if (empty($data['credential_username']) || empty($data['credential_secret'])) {
            abort(422, 'Username dan password wajib diisi untuk Basic Auth.');
        }

        return ['username' => $data['credential_username'], 'password' => $data['credential_secret']];
    }

    private function oauthClientCredentialsConfig(array $data): array
    {
        if (empty($data['oauth_token_url']) || empty($data['oauth_client_id']) || empty($data['credential_secret'])) {
            abort(422, 'Token URL, client ID, dan client secret wajib diisi untuk OAuth 2 Client Credentials.');
        }

        return [
            'token_url' => $data['oauth_token_url'],
            'client_id' => $data['oauth_client_id'],
            'client_secret' => $data['credential_secret'],
            'scope' => $data['oauth_scope'] ?: null,
        ];
    }

    private function tokenLoginConfig(array $data): array
    {
        if (empty($data['login_url']) || empty($data['credential_username']) || empty($data['credential_secret'])) {
            abort(422, 'Login URL, username, dan password wajib diisi untuk Login Endpoint.');
        }

        return [
            'login_url' => $data['login_url'],
            'username' => $data['credential_username'],
            'password' => $data['credential_secret'],
            'username_field' => $data['login_username_field'] ?: 'username',
            'password_field' => $data['login_password_field'] ?: 'password',
            'token_field' => $data['login_token_field'] ?: 'access_token',
            'expires_in_field' => $data['login_expires_in_field'] ?: 'expires_in',
        ];
    }

    private function credentialStorageReady(): bool
    {
        try {
            app(IntegrationKeyEncrypter::class);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function authLabel(string $authType): string
    {
        return match ($authType) {
            'none' => 'Tanpa autentikasi',
            'api_key_header' => 'API key pada header',
            'bearer' => 'Bearer token',
            'basic' => 'Basic Auth',
            'api_key_query' => 'API key pada query',
            'oauth2_client_credentials' => 'OAuth 2 Client Credentials (token otomatis)',
            'token_login' => 'Login endpoint (token otomatis)',
            default => $authType,
        };
    }

    private function ensureEndpointBindings(int $vendorId): void
    {
        foreach (DB::table('contracts')->pluck('id') as $contractId) {
            DB::table('endpoint_bindings')->updateOrInsert(['vendor_id' => $vendorId, 'contract_id' => $contractId], ['is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function testQueryForOperation(string $operationKey, string $vendorCode, ScopeContext $scope): array
    {
        return match ($operationKey) {
            'inventory.list', 'inventory.lookup' => [['limit' => 5, 'offset' => 0, 'search' => 'MKDU', 'item_type' => 'package'], null],
            'inventory.summary' => [['item_type' => 'package'], null],
            'orders.list' => [['limit' => 5, 'offset' => 0, 'period_code' => '20252'], null],
            'orders.summary' => [['group_by' => 'program', 'program_codes' => '61201'], null],
            'orders.analytics' => [[
                'ordered_from' => now('Asia/Jakarta')->startOfDay()->toRfc3339String(),
                'ordered_to' => now('Asia/Jakarta')->addDay()->startOfDay()->toRfc3339String(),
                'occurred_from' => now('Asia/Jakarta')->startOfDay()->toRfc3339String(),
                'occurred_to' => now('Asia/Jakarta')->addDay()->startOfDay()->toRfc3339String(),
            ], null],
            'orders.detail', 'orders.events' => [[], $this->firstOrderId($vendorCode, $scope)],
            default => [[], null],
        };
    }

    private function firstOrderId(string $vendorCode, ScopeContext $scope): string
    {
        $list = $this->gateway->call($vendorCode, 'orders.list', $scope, ['limit' => 1, 'offset' => 0]);
        $id = $list['data'][0]['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('orders.list tidak mengembalikan id DO untuk menguji detail atau history.');
        }

        return $id;
    }

    private function paginationSearchDetail(string $operationKey, array $payload): string
    {
        if (in_array($operationKey, ['inventory.list', 'inventory.lookup', 'orders.list'], true)) {
            $meta = $payload['meta'] ?? [];

            return 'Diterima: limit='.($meta['limit'] ?? 'tidak ada').', offset='.($meta['offset'] ?? 'tidak ada').', total_filtered='.($meta['total_filtered'] ?? 'tidak ada').', has_more='.json_encode($meta['has_more'] ?? null).'.';
        }

        return 'Endpoint ini bukan tabel berpaginasi. Kontrak khusus endpoint sudah sesuai.';
    }

    private function payloadStats(array $payload): array
    {
        return ['data_count' => isset($payload['data']) && is_array($payload['data']) ? count($payload['data']) : 1, 'has_meta' => array_key_exists('meta', $payload)];
    }

    private function friendlyTestError(string $message): string
    {
        // Describe WHAT is wrong and WHERE (row / field), like the legacy Paramita test report.
        if (preg_match('/schema validation failed for ([\w.]+\.json)(.*?): (\{.*\}|\[.*\])$/s', $message, $m)) {
            $where = trim($m[2]) !== '' ? str_replace('pada', 'di', trim($m[2])) : 'di seluruh body';
            $decoded = json_decode($m[3], true);
            $lines = [];
            if (is_array($decoded)) {
                foreach ($decoded as $path => $messages) {
                    $location = $this->describeSchemaPath((string) $path);
                    foreach ((array) $messages as $rawMessage) {
                        $scope = trim($where);
                        $lines[] = ($location === 'root response' ? $scope : $scope.' '.$location).': '.$this->describeSchemaMessage((string) $rawMessage);
                    }
                }
            }
            if ($lines === []) {
                $lines[] = 'Response tidak sesuai kontrak '.$m[1].': '.str($m[3])->limit(200)->value();
            }

            return "Response TIDAK sesuai format kontrak.\n".implode("\n", array_slice($lines, 0, 8))
               .(count($lines) > 8 ? "\n... masih ada ".(count($lines) - 8).' masalah lain.' : '');
        }
        // New alert-style contract report: already short, human-readable lines — show as-is.
        if (str_starts_with($message, 'Column ') || str_contains($message, '\nColumn ')) {
            return $message;
        }
        if (str_contains($message, 'baris ke-')) {
            return $message.'.';
        }
        if (str_contains($message, 'HTTP ')) {
            return $message.'. Periksa Base URL, path endpoint, dan credential.';
        }

        return str($message)->limit(300)->value();
    }

    private function describeSchemaPath(string $path): string
    {
        $path = str_replace('\\/', '/', $path);
        if ($path === '' || $path === '/') {
            return 'root response';
        }
        if (preg_match('#^/data/(\d+)(?:/(.+))?$#', $path, $m)) {
            return 'data baris ke-'.((int) $m[1] + 1).(($m[2] ?? '') !== '' ? ', field `'.str_replace('/', '`.`', $m[2]).'`' : '');
        }
        if (preg_match('#^/(.+)$#', $path, $m)) {
            return 'field `'.str_replace('/', '`.`', $m[1]).'`';
        }

        return 'path '.$path;
    }

    private function describeSchemaMessage(string $message): string
    {
        $replacements = [
            '/^The data \(([^)]+)\) must match the type:\s*(.+)$/' => 'harus bertipe $2, tapi diisi bertipe $1',
            '/^The data must match the type:\s*(.+)$/' => 'harus bertipe $1',
            '/^The data \(([^)]+)\) must match one of the types:\s*(.+)$/' => 'harus salah satu tipe $2, tapi diisi bertipe $1',
            '/^The data must match one of the types:\s*(.+)$/' => 'harus salah satu tipe $1',
            '/^The data must be at least (\S+)$/' => 'nilainya minimal $1',
            '/^The data must be at most (\S+)$/' => 'nilainya maksimal $1',
            '/^The data must match the format:\s*(.+)$/' => 'harus berformat $1',
            '/^The data must be a valid date/' => 'harus tanggal/waktu RFC 3339 yang valid',
            '/^The data must be one of:\s*(.+)$/' => 'harus salah satu dari $1',
            '/The required properties \(([^)]+)\) are missing/' => 'field wajib `$1` tidak ada di response',
            '/The property \(\'([^\']+)\'\) is expected to be \(\'([^\']+)\'\), however/' => 'field `$1` harus bertipe $2, padahal',
            '/The data \(.*?\) is not according to validation/' => 'value tidak sesuai aturan',
            '/Is not a string/' => 'bukan string',
            '/The matched number is not an integer/' => 'bukan integer',
        ];
        foreach ($replacements as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        return str($message)->ucfirst()->value();
    }
}
