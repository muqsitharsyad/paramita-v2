<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\MasterAndRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

final class MicrosoftSsoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterAndRoleSeeder::class);
        config()->set('services.microsoft.enabled', true);
        config()->set('services.microsoft.client_id', 'test-client-id');
        config()->set('services.microsoft.client_secret', 'test-client-secret');
        config()->set('services.microsoft.redirect', 'http://localhost/auth/microsoft/callback');
        config()->set('services.microsoft.tenant', 'test-tenant-id');
    }

    public function test_login_page_shows_microsoft_sso_when_configured(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('auth.microsoft.redirect'))
            ->assertSee('Microsoft');
    }

    public function test_microsoft_redirect_uses_the_configured_socialite_provider(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://login.microsoftonline.com/test'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($provider);

        $this->get(route('auth.microsoft.redirect'))
            ->assertRedirect('https://login.microsoftonline.com/test');
    }

    public function test_existing_active_internal_user_can_sign_in_and_is_bound_to_microsoft_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@ut.ac.id',
            'role' => 'admin',
            'status' => 'active',
            'vendor_id' => null,
            'email_verified_at' => now(),
            'microsoft_id' => null,
        ]);

        $this->mockMicrosoftUser('microsoft-user-123', 'staff@ut.ac.id', 'Staff UT');

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('microsoft-user-123', $user->refresh()->microsoft_id);
        $this->assertNotNull($user->last_sso_login_at);
    }

    public function test_sso_does_not_auto_provision_unknown_users(): void
    {
        $this->mockMicrosoftUser('microsoft-user-404', 'unknown@ut.ac.id', 'Unknown User');

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unknown@ut.ac.id']);
    }

    public function test_vendor_account_cannot_use_internal_microsoft_sso(): void
    {
        User::factory()->create([
            'email' => 'vendor@ut.ac.id',
            'role' => 'vendor',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->mockMicrosoftUser('microsoft-vendor-1', 'vendor@ut.ac.id', 'Vendor User');

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
    }

    public function test_bound_account_rejects_a_different_microsoft_identity(): void
    {
        User::factory()->create([
            'email' => 'staff@ut.ac.id',
            'role' => 'admin',
            'status' => 'active',
            'vendor_id' => null,
            'email_verified_at' => now(),
            'microsoft_id' => 'original-microsoft-id',
        ]);

        $this->mockMicrosoftUser('different-microsoft-id', 'staff@ut.ac.id', 'Staff UT');

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
    }

    public function test_login_page_keeps_the_microsoft_option_visible_before_credentials_are_configured(): void
    {
        config()->set('services.microsoft.enabled', false);
        config()->set('services.microsoft.client_id', null);
        config()->set('services.microsoft.client_secret', null);
        config()->set('services.microsoft.redirect', null);
        config()->set('services.microsoft.tenant', null);

        $this->get('/login')
            ->assertOk()
            ->assertSee(route('auth.microsoft.redirect'))
            ->assertSee('Microsoft');
    }

    public function test_unconfigured_sso_redirect_returns_to_login_with_a_clear_error(): void
    {
        config()->set('services.microsoft.enabled', false);
        config()->set('services.microsoft.client_id', null);
        config()->set('services.microsoft.client_secret', null);
        config()->set('services.microsoft.redirect', null);
        config()->set('services.microsoft.tenant', null);

        $this->get(route('auth.microsoft.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');
    }

    private function mockMicrosoftUser(string $id, string $email, string $name): void
    {
        $microsoftUser = (new SocialiteUser)->setRaw([
            'id' => $id,
            'mail' => $email,
            'userPrincipalName' => $email,
            'displayName' => $name,
        ])->map([
            'id' => $id,
            'email' => $email,
            'name' => $name,
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($microsoftUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($provider);
    }
}
