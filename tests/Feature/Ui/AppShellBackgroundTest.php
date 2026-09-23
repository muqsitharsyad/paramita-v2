<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Models\User;
use Database\Seeders\MasterAndRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AppShellBackgroundTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authenticated_shell_uses_the_white_application_background(): void
    {
        $this->seed(MasterAndRoleSeeder::class);

        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'vendor_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<body class="app-body"', false);

        $appCss = file_get_contents(resource_path('css/app.css'));
        $shellCss = file_get_contents(resource_path('css/shell.css'));

        $this->assertStringContainsString('background-color: #fff;', $appCss);
        $this->assertStringContainsString('.page-dashboard, #wrapper', $shellCss);
        $this->assertStringContainsString('background: #fff;', $shellCss);
    }
}
