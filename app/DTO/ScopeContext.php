<?php

declare(strict_types=1);

namespace App\DTO;

class ScopeContext
{
    public function __construct(
        public readonly int $actorId,
        public readonly string $role,
        public readonly int $permissionRevision,
        public readonly ?string $assignedUtCode,
        public readonly array $allowedProgramCodes,
        public readonly bool $isAllRegions,
        public readonly ?int $vendorId = null
    ) {}

    public function allowsUt(?string $requestedUtCode): bool
    {
        if ($this->role === 'admin' || $this->role === 'kepala_ut_pusat') {
            return true;
        }

        if ($this->role === 'kepala_ut_daerah' || $this->role === 'tutor') {
            if ($this->assignedUtCode === null) {
                return false;
            }
            if ($requestedUtCode === null) {
                return false; // must be scoped to their UT
            }

            return $this->assignedUtCode === $requestedUtCode;
        }

        return false;
    }

    public function allowsProgram(?string $requestedProgramCode): bool
    {
        if ($this->role === 'admin' || $this->role === 'kepala_ut_pusat' || $this->role === 'kepala_ut_daerah') {
            return true;
        }

        if ($this->role === 'tutor') {
            if ($requestedProgramCode === null) {
                return false;
            }

            return in_array($requestedProgramCode, $this->allowedProgramCodes, true);
        }

        return false;
    }

    public function canViewPii(): bool
    {
        // PRD §3.1: Tutor masked by default, no detailed location / POD
        return in_array($this->role, ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah'], true);
    }

    public function canViewPod(): bool
    {
        return $this->role !== 'tutor' && $this->role !== 'vendor';
    }

    /**
     * Defense-in-depth guard for per-row UT scoping. A regional head (or tutor) may only
     * see rows whose ut_code equals their assigned UT. All-regions roles (admin,
     * kepala_ut_pusat) may see any row. A row missing ut_code is rejected for scoped roles
     * because we cannot prove it belongs to the assigned UT.
     */
    public function rowBelongsToScope(?string $rowUtCode): bool
    {
        if ($this->role === 'admin' || $this->role === 'kepala_ut_pusat') {
            return true;
        }

        if ($this->role === 'kepala_ut_daerah' || $this->role === 'tutor') {
            if ($this->assignedUtCode === null || $rowUtCode === null) {
                return false;
            }

            return $this->assignedUtCode === $rowUtCode;
        }

        return false;
    }
}
