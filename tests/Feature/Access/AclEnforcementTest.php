<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\User;
use App\Services\Access\ScopeResolver;
use Database\Seeders\MasterAndRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F1: Integration ACL Tests with Database (PRD §17 ACL-01, ACL-02, ACL-06)
 */
class AclEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    private ScopeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ScopeResolver;
        $this->seed(MasterAndRoleSeeder::class);
    }

    public function test_acl06_thirty_nine_regions_and_one_luar_negeri_total_forty(): void
    {
        $regions = DB::table('ut_regions')->where('is_active', true)->get();
        $this->assertCount(40, $regions, 'Exactly 40 active service units');

        $daerah = $regions->where('type', 'daerah');
        $luarNegeri = $regions->where('type', 'luar_negeri');

        $this->assertCount(39, $daerah, 'Exactly 39 daerah');
        $this->assertCount(1, $luarNegeri, 'Exactly 1 luar_negeri');

        // Pusat must not be in ut_regions
        $pusat = DB::table('ut_regions')->where('name', 'like', '%pusat%')->first();
        $this->assertNull($pusat, 'UT Pusat is an organization, not a service unit in ut_regions');
    }

    public function test_acl01_tutor_without_assignment_blocked(): void
    {
        $user = User::factory()->create([
            'role' => 'tutor',
            'status' => 'active',
            'ut_id' => null,
        ]);

        $ctx = $this->resolver->resolve($user);

        $this->assertNull($ctx->assignedUtCode);
        $this->assertEmpty($ctx->allowedProgramCodes);
        $this->assertFalse($ctx->allowsUt('UN31.UT2'));
        $this->assertFalse($ctx->allowsUt(null));
        $this->assertFalse($ctx->canViewPii());
        $this->assertFalse($ctx->canViewPod());
    }

    public function test_acl02_kepala_daerah_cannot_access_other_daerah(): void
    {
        $ut1 = DB::table('ut_regions')->where('code', 'UN31.UT2')->first();
        $ut2 = DB::table('ut_regions')->where('code', 'UN31.UT3')->first();

        $user = User::factory()->create([
            'role' => 'kepala_ut_daerah',
            'status' => 'active',
            'ut_id' => $ut1->id,
        ]);

        $ctx = $this->resolver->resolve($user);

        $this->assertTrue($ctx->allowsUt('UN31.UT2'));
        $this->assertFalse($ctx->allowsUt('UN31.UT3'), 'Must reject other UT region');
        $this->assertFalse($ctx->allowsUt(null), 'Must reject national scope');
    }

    public function test_luar_negeri_unit_head_isolated(): void
    {
        $utLn = DB::table('ut_regions')->where('type', 'luar_negeri')->first();

        $user = User::factory()->create([
            'role' => 'kepala_ut_daerah',
            'status' => 'active',
            'ut_id' => $utLn->id,
        ]);

        $ctx = $this->resolver->resolve($user);

        $this->assertSame($utLn->code, $ctx->assignedUtCode);
        $this->assertTrue($ctx->allowsUt($utLn->code));
        $this->assertFalse($ctx->allowsUt('UN31.UT2'), 'Luar Negeri head cannot access domestic UT');
    }
}
