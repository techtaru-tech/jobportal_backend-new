<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One account, one job: a job seeker's account applies for jobs, an
 * employer's account posts them. The same mobile number may hold one of each,
 * but they are separate accounts with separate sign-ins.
 *
 * Answers a call from the wrong kind of account with 403 and
 * `code: wrong_account_role` plus the role it needs, so the app and website
 * can send the person to sign in (or register) on the other side rather than
 * show a dead error.
 */
class EnsureAccountRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        $needed = UserRole::from($role);

        if ($user instanceof User && $user->role !== $needed) {
            return new JsonResponse([
                'message' => $needed === UserRole::Recruiter
                    ? 'This is a job seeker account. Sign in as an employer to post jobs.'
                    : 'This is an employer account. Sign in as a job seeker to apply for jobs.',
                'code' => 'wrong_account_role',
                'required_role' => $needed->value,
            ], 403);
        }

        return $next($request);
    }
}
