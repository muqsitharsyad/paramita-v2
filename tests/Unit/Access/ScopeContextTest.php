<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\DTO\ScopeContext;
use PHPUnit\Framework\TestCase;

/**
 * F1: ACL & Scope Enforcement Tests (PRD §17 ACL-01..06)
 */
class ScopeContextTest extends TestCase
{
    public function test_admin_allows_all_ut_and_program(): void
    {
        $ctx = new ScopeContext(
            actorId: 1,
            role: 'admin',
            permissionRevision: 1,
            assignedUtCode: null,
            allowedProgramCodes: [],
            isAllRegions: true
        );

        $this->assertTrue($ctx->allowsUt('UN31.UT1'));
        $this->assertTrue($ctx->allowsUt('UN31.UT40')); // luar negeri
        $this->assertTrue($ctx->allowsUt(null));
        $this->assertTrue($ctx->allowsProgram('PRODI-DEMO-01'));
        $this->assertTrue($ctx->canViewPii());
        $this->assertTrue($ctx->canViewPod());
        $this->assertTrue($ctx->rowBelongsToScope('UN31.UT1'));
        $this->assertTrue($ctx->rowBelongsToScope(null));
    }

    public function test_kepala_ut_pusat_allows_all_ut(): void
    {
        $ctx = new ScopeContext(
            actorId: 2,
            role: 'kepala_ut_pusat',
            permissionRevision: 1,
            assignedUtCode: null,
            allowedProgramCodes: [],
            isAllRegions: true
        );

        $this->assertTrue($ctx->allowsUt('UN31.UT5'));
        $this->assertTrue($ctx->allowsUt('UN31.UT40')); // Luar Negeri
        $this->assertTrue($ctx->canViewPii());
        $this->assertTrue($ctx->canViewPod());
    }

    public function test_kepala_ut_daerah_restricted_to_assigned_ut(): void
    {
        $ctx = new ScopeContext(
            actorId: 3,
            role: 'kepala_ut_daerah',
            permissionRevision: 1,
            assignedUtCode: 'UN31.UT5',
            allowedProgramCodes: [],
            isAllRegions: false
        );

        $this->assertTrue($ctx->allowsUt('UN31.UT5'));
        $this->assertFalse($ctx->allowsUt('UN31.UT1'), 'Cannot access other UT');
        $this->assertFalse($ctx->allowsUt(null), 'Cannot access national summary');
        $this->assertTrue($ctx->canViewPii());
        $this->assertTrue($ctx->canViewPod());
        $this->assertTrue($ctx->rowBelongsToScope('UN31.UT5'));
        $this->assertFalse($ctx->rowBelongsToScope('UN31.UT1'));
        $this->assertFalse($ctx->rowBelongsToScope(null));
    }

    public function test_tutor_restricted_and_no_pii_no_pod(): void
    {
        $ctx = new ScopeContext(
            actorId: 4,
            role: 'tutor',
            permissionRevision: 1,
            assignedUtCode: 'UN31.UT5',
            allowedProgramCodes: ['PRODI-DEMO-01', 'PRODI-DEMO-02'],
            isAllRegions: false
        );

        $this->assertTrue($ctx->allowsUt('UN31.UT5'));
        $this->assertFalse($ctx->allowsUt('UN31.UT1'));
        $this->assertTrue($ctx->allowsProgram('PRODI-DEMO-01'));
        $this->assertFalse($ctx->allowsProgram('PRODI-DEMO-99'));

        // PRD §3.1: Tutor masked by default, detail nama/alamat/POD tidak tersedia
        $this->assertFalse($ctx->canViewPii(), 'Tutor cannot view PII');
        $this->assertFalse($ctx->canViewPod(), 'Tutor cannot view POD');
    }

    public function test_tutor_without_assignment_blocked(): void
    {
        $ctx = new ScopeContext(
            actorId: 5,
            role: 'tutor',
            permissionRevision: 1,
            assignedUtCode: null,
            allowedProgramCodes: [],
            isAllRegions: false
        );

        $this->assertFalse($ctx->allowsUt('UN31.UT1'));
        $this->assertFalse($ctx->allowsUt(null));
    }

    public function test_luar_negeri_unit_isolation(): void
    {
        $ctx = new ScopeContext(
            actorId: 6,
            role: 'kepala_ut_daerah',
            permissionRevision: 1,
            assignedUtCode: 'UN31.UT40', // unit luar negeri
            allowedProgramCodes: [],
            isAllRegions: false
        );

        $this->assertTrue($ctx->allowsUt('UN31.UT40'));
        $this->assertFalse($ctx->allowsUt('UN31.UT1'));
    }
}
