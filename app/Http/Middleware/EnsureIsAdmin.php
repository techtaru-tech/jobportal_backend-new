<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gates `/api/v1/admin/*`. Runs after `auth:sanctum`, which has already
 * resolved the bearer token to whatever model owns it.
 *
 * Three separate checks, because each closes a different hole:
 *
 *  1. **The token's owner is an [Admin].** Sanctum resolves a token to its
 *     `tokenable`, and every app user's token is minted with abilities
 *     `['*']` — which satisfies any ability check you care to write. So the
 *     ability alone is not enough; the identity is what matters. Without this
 *     line, any signed-in candidate could call every admin route.
 *  2. **The token carries the `admin` ability.** Defence in depth for the day
 *     something else starts minting tokens for admins (a narrower
 *     integration token, say) — such a token should not reach these routes
 *     just because its owner happens to be an operator.
 *  3. **The account is still active.** Deactivating an operator has to take
 *     effect without hunting down the tokens they already hold.
 *
 * Writes are additionally gated on [Admin::canWrite] so a `viewer` cannot
 * change user-visible state.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next, ?string $requirement = null)
    {
        $admin = $request->user();

        // Deliberately 404, not 403: to anything that is not an admin, these
        // routes should not appear to exist at all.
        if (! $admin instanceof Admin) {
            throw new HttpException(404, 'We could not find what you were looking for.');
        }

        if (! $request->user()?->currentAccessToken()?->can(Admin::ABILITY)) {
            throw new AccessDeniedHttpException('This token cannot be used for admin access.');
        }

        if (! $admin->is_active) {
            throw new AccessDeniedHttpException('This admin account has been deactivated.');
        }

        if ($requirement === 'write' && ! $admin->canWrite()) {
            throw new AccessDeniedHttpException('Your admin account has read-only access.');
        }

        return $next($request);
    }
}
