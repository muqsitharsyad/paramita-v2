<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Services\Gateway\AuthResolver;
use App\Services\Security\IntegrationKeyEncrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LoginTokenAuthTest extends TestCase
{
    public function test_login_endpoint_token_is_cached_and_can_be_refreshed(): void
    {
        config()->set('app.paramita_integration_key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $connectionRevisionId = 987654;
        $encrypter = app(IntegrationKeyEncrypter::class);
        $ciphertext = $encrypter->encrypt(json_encode([
            'login_url' => 'https://example.com/vendor/login',
            'username' => 'vendor-service',
            'password' => 'not-exposed',
            'username_field' => 'email',
            'password_field' => 'password',
            'token_field' => 'data.token',
            'expires_in_field' => 'data.expires',
        ], JSON_THROW_ON_ERROR));

        Http::fake([
            'https://example.com/vendor/login' => Http::sequence()
                ->push(['data' => ['token' => 'first-issued-token', 'expires' => 3600]], 200)
                ->push(['data' => ['token' => 'refreshed-token', 'expires' => 3600]], 200),
        ]);

        $resolver = app(AuthResolver::class);
        $first = $resolver->resolve('token_login', $ciphertext, $connectionRevisionId);
        $second = $resolver->resolve('token_login', $ciphertext, $connectionRevisionId);

        $this->assertSame('Bearer first-issued-token', $first['headers']['Authorization']);
        $this->assertSame('Bearer first-issued-token', $second['headers']['Authorization']);
        Http::assertSentCount(1);

        $resolver->forgetAccessToken($connectionRevisionId);
        $this->assertNull(Cache::get('paramita:vendor-access-token:connection-revision:'.$connectionRevisionId));
        $refreshed = $resolver->resolve('token_login', $ciphertext, $connectionRevisionId);
        $this->assertSame('Bearer refreshed-token', $refreshed['headers']['Authorization']);
        Cache::forget('paramita:vendor-access-token:connection-revision:'.$connectionRevisionId);
    }
}
