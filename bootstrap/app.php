<?php

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\OptionalAuthenticate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'guest.token' => OptionalAuthenticate::class,
        ]);

        // This app has no `login` route — it is an API with OTP auth. Laravel
        // otherwise defaults guests to `route('login')`, which throws while
        // building the redirect and turns a plain 401 into a 500.
        $middleware->redirectGuestsTo(fn () => null);

        // Trust the reverse proxy in front of us (Cloudflare Tunnel, or any
        // load balancer in production) so signed URLs and absolute links use
        // the scheme the client actually connected with (https) instead of
        // the plain http the proxy forwards to this origin.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        /*
         * Every error the app can hit must carry a `message` it can drop
         * straight into a toast (§1.4) — never a blank body or a stack trace.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null; // Laravel already emits { message, errors }.
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Please sign in to continue.'], 401);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'We could not find what you were looking for.',
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'message' => $e->getMessage() ?: match ($status) {
                        403 => 'You do not have access to this.',
                        405 => 'That action is not supported.',
                        429 => 'Too many attempts, try again in a few minutes.',
                        default => 'Something went wrong. Please try again.',
                    },
                ], $status);
            }

            if (config('app.debug')) {
                return null;
            }

            // Never leak an internal error message to a user-facing toast.
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        });
    })->create();
