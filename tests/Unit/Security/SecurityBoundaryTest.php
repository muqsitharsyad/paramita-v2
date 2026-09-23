<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\Gateway\SafeHttpClient;
use App\Services\Security\IntegrationKeyEncrypter;
use PHPUnit\Framework\TestCase;

/**
 * F1: Security & SSRF Protection Tests (PRD §17 SEC-01..05)
 */
class SecurityBoundaryTest extends TestCase
{
    private SafeHttpClient $client;

    private IntegrationKeyEncrypter $encrypter;

    private string|false $previousIntegrationKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousIntegrationKey = getenv('PARAMITA_INTEGRATION_KEY');
        putenv('PARAMITA_INTEGRATION_KEY=base64:'.base64_encode(str_repeat('t', 32)));
        $this->client = new SafeHttpClient;
        $this->encrypter = new IntegrationKeyEncrypter;
    }

    protected function tearDown(): void
    {
        if ($this->previousIntegrationKey === false) {
            putenv('PARAMITA_INTEGRATION_KEY');
        } else {
            putenv('PARAMITA_INTEGRATION_KEY='.$this->previousIntegrationKey);
        }

        parent::tearDown();
    }

    public function test_ssrf_blocks_private_and_loopback_ips(): void
    {
        $this->assertTrue($this->client->isPrivateOrReservedIp('127.0.0.1'));
        $this->assertTrue($this->client->isPrivateOrReservedIp('10.0.0.1'));
        $this->assertTrue($this->client->isPrivateOrReservedIp('192.168.1.1'));
        $this->assertTrue($this->client->isPrivateOrReservedIp('172.16.0.1'));
        $this->assertTrue($this->client->isPrivateOrReservedIp('169.254.169.254')); // AWS metadata
        $this->assertTrue($this->client->isPrivateOrReservedIp('::1'));

        // Public IP should NOT be blocked
        $this->assertFalse($this->client->isPrivateOrReservedIp('8.8.8.8'));
        $this->assertFalse($this->client->isPrivateOrReservedIp('1.1.1.1'));
    }

    public function test_rejects_http_in_production(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only HTTPS scheme is allowed');
        $this->client->validateUrl('http://api.vendor.com/data', allowHttpLocal: false);
    }

    public function test_rejects_non_standard_ports(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Port 8080 is not permitted');
        $this->client->validateUrl('https://api.vendor.com:8080/data', allowHttpLocal: false);
    }

    public function test_aes256_gcm_roundtrip(): void
    {
        $secret = 'super-secret-vendor-api-token-xyz-12345';
        $encrypted = $this->encrypter->encrypt($secret);

        $this->assertNotSame($secret, $encrypted);
        $decrypted = $this->encrypter->decrypt($encrypted);

        $this->assertSame($secret, $decrypted);
    }
}
