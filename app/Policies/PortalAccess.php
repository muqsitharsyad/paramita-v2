<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PortalAccess
{
    public static function admin(?User $user): bool
    {
        return $user !== null && $user->status === 'active' && $user->email_verified_at !== null && $user->role === 'admin' && $user->vendor_id === null;
    }

    public static function vendor(?User $user): bool
    {
        return $user !== null && $user->status === 'active' && $user->email_verified_at !== null && $user->role === 'vendor' && $user->vendor_id !== null
            && DB::table('vendors')->where('id', $user->vendor_id)->where('status', 'approved')->exists();
    }
}
