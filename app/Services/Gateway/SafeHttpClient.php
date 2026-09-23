<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SafeHttpClient
{
    private const ALLOWED_PORTS = [443, 80];

    private const LOCAL_DEVELOPMENT_PORTS = [80, 8011];

    public const MAX_RESPONSE_BYTES = 2097152; // 2MB (PRD §14.4)

    /**
     * Validate a target URL against SSRF vulnerabilities before request.
     * PRD §14.1: public HTTPS allowlist, resolve IPv4/IPv6, block private/loopback/linklocal.
     */
    public function validateUrl(string $url, bool $allowHttpLocal = false): string
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['host'])) {
            throw new Exception('Invalid URL');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'], '[]'));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1', 'paramita-final.test'], true);
        $laravelApp = function_exists('app') ? app() : null;
        $isLocalEnvironment = is_object($laravelApp)
            && method_exists($laravelApp, 'environment')
            && $laravelApp->environment(['local', 'testing']);
        $allowLocalDevelopment = $allowHttpLocal || $isLocalEnvironment;
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new Exception('URL credentials and fragments are forbidden');
        }
        if ($isLocal && ! $allowLocalDevelopment) {
            throw new Exception('Local endpoints are forbidden');
        }

        if ($scheme !== 'https') {
            if (! ($allowLocalDevelopment && $isLocal && $scheme === 'http')) {
                throw new Exception('Only HTTPS scheme is allowed for vendor endpoints');
            }
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($scheme === 'https' && $port !== 443) {
            throw new Exception("Port $port is not permitted");
        }
        if ($scheme === 'http' && ! ($allowLocalDevelopment && $isLocal && in_array($port, self::LOCAL_DEVELOPMENT_PORTS, true))) {
            throw new Exception("Port $port is not permitted");
        }

        $host = $parts['host'];

        // If not in local test mode, resolve DNS and check IP
        if (! $isLocal) {
            $ips = dns_get_record($host, DNS_A + DNS_AAAA);
            if (empty($ips)) {
                $ip = gethostbyname($host);
                if ($ip === $host) {
                    throw new Exception("Unable to resolve DNS for host: $host");
                }
                $ips = [['ip' => $ip]];
            }

            foreach ($ips as $record) {
                $targetIp = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($targetIp && $this->isPrivateOrReservedIp($targetIp)) {
                    throw new Exception("SSRF Protection: Host resolves to private/reserved IP: $targetIp");
                }
            }
        }

        return $url;
    }

    public function isPrivateOrReservedIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    public function postJson(string $url, array $headers = [], array $payload = [], int $timeout = 5): Response
    {
        $this->validateUrl($url, app()->environment('testing'));

        return Http::withOptions($this->tlsOptions())->acceptJson()
            ->withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(2)
            ->withoutRedirecting()
            ->post($url, $payload);
    }

    public function postForm(string $url, array $headers = [], array $form = [], int $timeout = 5): Response
    {
        $this->validateUrl($url, app()->environment('testing'));

        return Http::withOptions($this->tlsOptions())->asForm()
            ->withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(2)
            ->withoutRedirecting()
            ->post($url, $form);
    }

    /**
     * Bounded HTTP GET with SSRF check, size limit, no redirects.
     */
    public function get(string $url, array $headers = [], array $query = [], int $timeout = 5): Response
    {
        $this->validateUrl($url, app()->environment('testing'));

        $response = Http::withOptions($this->tlsOptions())->withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(2)
            ->withoutRedirecting()
            ->get($url, $query);

        $contentLength = (int) ($response->header('Content-Length') ?: 0);
        if ($contentLength > self::MAX_RESPONSE_BYTES || strlen($response->body()) > self::MAX_RESPONSE_BYTES) {
            throw new Exception('Vendor response exceeds the 2MB limit');
        }

        return $response;
    }

    private function tlsOptions(): array
    {
        $caBundle = getenv('PARAMITA_CA_BUNDLE') ?: null;
        if ($caBundle !== null && $caBundle !== '' && is_file($caBundle)) {
            return ['verify' => $caBundle];
        }

        return ['verify' => true];
    }
}
