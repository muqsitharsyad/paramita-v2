<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_a_scoped_tutor_account(): void
    {
        $admin = $this->admin();
        $regionId = (int) DB::table('ut_regions')->where('is_active', true)->value('id');
        $programId = (int) DB::table('programs')->where('is_active', true)->value('id');
        $email = 'managed-'.Str::lower(Str::random(10)).'@example.test';
        $password = Str::random(20);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Managed Tutor',
            'email' => $email,
            'role' => 'tutor',
            'status' => 'active',
            'ut_id' => $regionId,
            'program_ids' => [$programId],
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertSame('tutor', $user->role);
        $this->assertSame($regionId, $user->ut_id);
        $this->assertTrue($user->hasRole('tutor'));
        $this->assertDatabaseHas('tutor_program', ['user_id' => $user->id, 'program_id' => $programId]);
    }

    public function test_non_admin_cannot_open_user_management(): void
    {
        $user = User::factory()->create(['role' => 'tutor', 'status' => 'active', 'vendor_id' => null]);
        Role::findOrCreate('tutor', 'web');
        $user->syncRoles(['tutor']);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_cannot_remove_their_own_admin_access(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'tutor',
            'status' => 'active',
            'ut_id' => DB::table('ut_regions')->where('is_active', true)->value('id'),
            'program_ids' => [DB::table('programs')->where('is_active', true)->value('id')],
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_vendor_account_cannot_be_edited_from_internal_user_management(): void
    {
        $admin = $this->admin();
        $vendorUser = User::query()->whereNotNull('vendor_id')->first();
        if (! $vendorUser) {
            $this->markTestSkipped('No vendor account is available in the shared test database.');
        }

        $this->actingAs($admin)->get(route('admin.users.edit', $vendorUser))->assertNotFound();
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'vendor_id' => null,
            'ut_id' => null,
            'permission_revision' => 1,
        ]);
        $admin->syncRoles(['admin']);

        return $admin;
    }
}
