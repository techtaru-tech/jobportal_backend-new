<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks every API request as wanting JSON.
 *
 * Several framework paths branch on `$request->expectsJson()` — most visibly
 * the guest redirect on a failed `auth:sanctum` check. A client that omits the
 * `Accept` header would otherwise be sent an HTML redirect instead of the 401
 * envelope in §1.4, so the header is normalised on the way in rather than every
 * caller being trusted to set it.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
