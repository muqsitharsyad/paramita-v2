<?php

declare(strict_types=1);

namespace Tests\Unit\Gateway;

use App\Services\Gateway\AuthResolver;
use App\Services\Gateway\SafeHttpClient;
use App\Services\Security\IntegrationKeyEncrypter;
use PHPUnit\Framework\TestCase;

/**
 * F2: Auth Modes Tests (PRD §4.3 & §17 SEC-05)
 */
class AuthResolverTest extends TestCase
{
    private AuthResolver $resolver;

    private IntegrationKeyEncrypter $encrypter;

    private string|false $previousIntegrationKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousIntegrationKey = getenv('PARAMITA_INTEGRATION_KEY');
        putenv('PARAMITA_INTEGRATION_KEY=base64:'.base64_encode(str_repeat('t', 32)));
        $this->encrypter = new IntegrationKeyEncrypter;
        $this->resolver = new AuthResolver(new SafeHttpClient, $this->encrypter);
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

    public function test_api_key_header_mode(): void
    {
        $payload = json_encode(['header_name' => 'X-API-Key', 'key_value' => 'secret-123']);
        $cipher = $this->encrypter->encrypt($payload);

        $resolved = $this->resolver->resolve('api_key_header', $cipher);
        $this->assertArrayHasKey('X-API-Key', $resolved['headers']);
        $this->assertSame('secret-123', $resolved['headers']['X-API-Key']);
        $this->assertEmpty($resolved['query']);
    }

    public function test_bearer_token_mode(): void
    {
        $payload = json_encode(['token' => 'jwt-vendor-token']);
        $cipher = $this->encrypter->encrypt($payload);

        $resolved = $this->resolver->resolve('bearer', $cipher);
        $this->assertSame('Bearer jwt-vendor-token', $resolved['headers']['Authorization']);
    }

    public function test_basic_auth_mode(): void
    {
        $payload = json_encode(['username' => 'vendor_user', 'password' => 'pass123']);
        $cipher = $this->encrypter->encrypt($payload);

        $resolved = $this->resolver->resolve('basic', $cipher);
        $expected = 'Basic '.base64_encode('vendor_user:pass123');
        $this->assertSame($expected, $resolved['headers']['Authorization']);
    }

    public function test_none_auth_mode_returns_empty(): void
    {
        $resolved = $this->resolver->resolve('none', null);
        $this->assertEmpty($resolved['headers']);
        $this->assertEmpty($resolved['query']);
    }
}
