<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Sanctum user when a token is present, but lets the request
 * through when it is not.
 *
 * The browse endpoints are public (§4) yet return richer data — saved and
 * applied flags — for a signed-in candidate, so they need "authenticate if you
 * can" rather than "authenticate or 401".
 */
class OptionalAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && ($user = Auth::guard('sanctum')->user())) {
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        }

        return $next($request);
    }
}
