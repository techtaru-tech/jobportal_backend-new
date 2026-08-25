<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * An admin-panel operator.
 *
 * A separate authenticatable from [User] on purpose — see the `admins`
 * migration for why an `admin` case on `UserRole` would be a
 * privilege-escalation hole rather than a shortcut. Consequences worth knowing:
 *
 *  - Admins authenticate with email + password, not phone + OTP.
 *  - Their Sanctum tokens are minted with the `admin` ability, and
 *    [EnsureIsAdmin] additionally checks the token's owner really is an
 *    `Admin`, so an app user's `['*']` token can never reach an admin route.
 *  - Nothing in the app's own code path knows this table exists.
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use HasApiTokens;

    /** The ability every admin token carries; checked by [EnsureIsAdmin]. */
    public const ABILITY = 'admin';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class)->latest();
    }

    /**
     * Whether this operator may change things, as opposed to only read them.
     *
     * `viewer` exists so support staff can be given the panel without being
     * able to verify an employer or move somebody's application — both of
     * which are user-visible and effectively irreversible.
     */
    public function canWrite(): bool
    {
        return $this->role !== 'viewer';
    }
}
