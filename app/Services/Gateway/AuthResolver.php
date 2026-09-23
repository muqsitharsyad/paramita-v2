<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Services\Security\IntegrationKeyEncrypter;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

final class AuthResolver
{
    public function __construct(
        private readonly SafeHttpClient $httpClient,
        private readonly IntegrationKeyEncrypter $encrypter,
    ) {}

    /** Resolve per-request vendor auth. Secrets and issued tokens never leave server memory/cache. */
    public function resolve(string $authType, ?string $ciphertext, ?int $connectionRevisionId = null): array
    {
        if ($authType === 'none') {
            return ['headers' => [], 'query' => []];
        }
        if (empty($ciphertext)) {
            throw new Exception("Credential belum dikonfigurasi untuk autentikasi {$authType}");
        }

        $config = json_decode($this->encrypter->decrypt($ciphertext), true);
        if (! is_array($config)) {
            throw new Exception('Konfigurasi autentikasi terenkripsi tidak valid');
        }

        return match ($authType) {
            'api_key_header' => ['headers' => [$this->required($config, 'header_name', 'X-API-Key') => $this->required($config, 'key_value')], 'query' => []],
            'bearer' => ['headers' => ['Authorization' => 'Bearer '.$this->required($config, 'token')], 'query' => []],
            'basic' => ['headers' => ['Authorization' => 'Basic '.base64_encode($this->required($config, 'username').':'.$this->required($config, 'password'))], 'query' => []],
            'api_key_query' => ['headers' => [], 'query' => [$this->required($config, 'param_name', 'api_key') => $this->required($config, 'key_value')]],
            'oauth2_client_credentials' => ['headers' => ['Authorization' => 'Bearer '.$this->oauthAccessToken($config, $connectionRevisionId)], 'query' => []],
            'token_login' => ['headers' => ['Authorization' => 'Bearer '.$this->loginAccessToken($config, $connectionRevisionId)], 'query' => []],
            default => throw new Exception("Jenis autentikasi tidak didukung: {$authType}"),
        };
    }

    /** Clear cached access token after expiry/revocation. The next request obtains a new one. */
    public function forgetAccessToken(?int $connectionRevisionId): void
    {
        if ($connectionRevisionId !== null) {
            Cache::forget($this->accessTokenCacheKey($connectionRevisionId));
        }
    }

    // Kept for compatibility with existing callers while all issued-token modes use the same cache lifecycle.
    public function forgetOAuthToken(?int $connectionRevisionId): void
    {
        $this->forgetAccessToken($connectionRevisionId);
    }

    private function oauthAccessToken(array $config, ?int $connectionRevisionId): string
    {
        return $this->cachedAccessToken($connectionRevisionId, function () use ($config): array {
            $clientId = $this->required($config, 'client_id');
            $clientSecret = $this->required($config, 'client_secret');
            $form = ['grant_type' => 'client_credentials'];
            if (! empty($config['scope'])) {
                $form['scope'] = (string) $config['scope'];
            }

            $response = $this->httpClient->postForm(
                url: $this->required($config, 'token_url'),
                headers: [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
                ],
                form: $form,
                timeout: 8,
            );

            return $this->tokenFromResponse($response, 'OAuth token endpoint', $config);
        });
    }

    private function loginAccessToken(array $config, ?int $connectionRevisionId): string
    {
        return $this->cachedAccessToken($connectionRevisionId, function () use ($config): array {
            $usernameField = $this->required($config, 'username_field', 'username');
            $passwordField = $this->required($config, 'password_field', 'password');
            $response = $this->httpClient->postJson(
                url: $this->required($config, 'login_url'),
                headers: ['Accept' => 'application/json'],
                payload: [
                    $usernameField => $this->required($config, 'username'),
                    $passwordField => $this->required($config, 'password'),
                ],
                timeout: 8,
            );

            return $this->tokenFromResponse($response, 'Login endpoint vendor', $config);
        });
    }

    /** @param callable(): array{token:string,expires_in:int} $issuer */
    private function cachedAccessToken(?int $connectionRevisionId, callable $issuer): string
    {
        if ($connectionRevisionId === null) {
            throw new Exception('Autentikasi berbasis token membutuhkan connection revision untuk cache token');
        }
        $cacheKey = $this->accessTokenCacheKey($connectionRevisionId);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $this->encrypter->decrypt($cached);
        }

        try {
            ['token' => $token, 'expires_in' => $expiresIn] = $issuer();
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new Exception('Gagal memperoleh access token vendor: '.$exception->getMessage());
        }

        // Refresh one minute early, bounded so vendor-issued values cannot exhaust cache storage.
        $cacheSeconds = max(30, min(3600, $expiresIn - 60));
        Cache::put($cacheKey, $this->encrypter->encrypt($token), now()->addSeconds($cacheSeconds));

        return $token;
    }

    /** @return array{token:string,expires_in:int} */
    private function tokenFromResponse(Response $response, string $endpointName, array $config): array
    {
        if ($response->status() !== 200) {
            throw new Exception("{$endpointName} mengembalikan HTTP {$response->status()}");
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new Exception("{$endpointName} tidak mengembalikan JSON object");
        }
        $token = $this->valueAtPath($payload, (string) ($config['token_field'] ?? 'access_token'));
        if (! is_string($token) || trim($token) === '') {
            throw new Exception("{$endpointName} tidak mengembalikan token pada field yang dikonfigurasi");
        }
        $expires = $this->valueAtPath($payload, (string) ($config['expires_in_field'] ?? 'expires_in'));

        return ['token' => $token, 'expires_in' => is_numeric($expires) ? (int) $expires : 300];
    }

    private function valueAtPath(array $payload, string $path): mixed
    {
        $value = $payload;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function required(array $config, string $key, ?string $default = null): string
    {
        $value = $config[$key] ?? $default;
        if (! is_string($value) || trim($value) === '') {
            throw new Exception("Konfigurasi autentikasi tidak memiliki {$key}");
        }

        return $value;
    }

    private function accessTokenCacheKey(int $connectionRevisionId): string
    {
        return 'paramita:vendor-access-token:connection-revision:'.$connectionRevisionId;
    }
}
