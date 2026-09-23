<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\DTO\ScopeContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ScopeResolver
{
    public function resolve(User $user): ScopeContext
    {
        // Re-read user status and permissions authoritative from DB
        $freshUser = DB::table('users')->where('id', $user->id)->first();
        if (! $freshUser || $freshUser->status !== 'active') {
            throw new AuthorizationException('User inactive or not found');
        }

        if (! in_array($freshUser->role, ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor'], true)) {
            throw new AuthorizationException('Monitoring access denied');
        }

        $assignedUtCode = null;
        if ($freshUser->ut_id) {
            $ut = DB::table('ut_regions')->where('id', $freshUser->ut_id)->first();
            $assignedUtCode = $ut?->code;
        }

        $allowedProgramCodes = [];
        if ($freshUser->role === 'tutor') {
            $allowedProgramCodes = DB::table('tutor_program')
                ->join('programs', 'tutor_program.program_id', '=', 'programs.id')
                ->where('tutor_program.user_id', $freshUser->id)
                ->pluck('programs.code')
                ->toArray();
        }

        $isAllRegions = in_array($freshUser->role, ['admin', 'kepala_ut_pusat'], true);

        return new ScopeContext(
            actorId: (int) $freshUser->id,
            role: $freshUser->role,
            permissionRevision: (int) $freshUser->permission_revision,
            assignedUtCode: $assignedUtCode,
            allowedProgramCodes: $allowedProgramCodes,
            isAllRegions: $isAllRegions,
            vendorId: $freshUser->vendor_id ? (int) $freshUser->vendor_id : null
        );
    }
}
