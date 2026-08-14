<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards recruiter-only and candidate-only routes — a candidate hitting a
 * recruiter route gets a 403 with a readable message (§1.4).
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $expected = UserRole::from($role);
        $user = $request->user();

        if (! $user || $user->role !== $expected) {
            return response()->json([
                'message' => "This action is only available to {$expected->value} accounts.",
            ], 403);
        }

        return $next($request);
    }
}
