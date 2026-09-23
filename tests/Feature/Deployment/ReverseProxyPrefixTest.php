<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

class ReverseProxyPrefixTest extends TestCase
{
    public function test_generated_login_urls_retain_a_trusted_reverse_proxy_prefix(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Host' => 'prodev.ut.ac.id',
                'X-Forwarded-Host' => 'prodev.ut.ac.id',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Prefix' => '/paramita-final',
            ])
            ->get('/login');

        $response
            ->assertOk()
            ->assertSee('action="https://prodev.ut.ac.id/paramita-final/login"', false)
            ->assertSee('href="https://prodev.ut.ac.id/paramita-final/register"', false);
    }
}
