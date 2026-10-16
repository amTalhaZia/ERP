<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveCompanyContext;
use App\Support\Tenancy\TenantMismatchException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;
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
        // Sanctum SPA auth: the frontend origin is "stateful" and authenticates
        // with the session cookie; CSRF is enforced via the XSRF-TOKEN cookie.
        $middleware->statefulApi();

        // Behind a hosting proxy (Render, Vercel rewrites, a load balancer) set TRUSTED_PROXIES=*
        // (or the proxy IPs) so client IPs, rate limits and https detection are correct.
        // Unset locally, so X-Forwarded-* headers from a direct client are never trusted.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->alias([
            'tenant' => ResolveCompanyContext::class,
            'permission' => EnsurePermission::class,
            'platform' => EnsurePlatformAdmin::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // The tenant must be resolved BEFORE route-model binding so `{branch}`
        // etc. are looked up inside the company scope (other tenants' ids → 404).
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveCompanyContext::class);
        $middleware->prependToPriorityList(ResolveCompanyContext::class, EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([TenantMismatchException::class]);

        // Consistent JSON envelope: { message, code, errors? }. Never leak
        // stack traces or SQL to API clients.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => response()->json([
                    'message' => 'Please correct the highlighted fields.',
                    'code' => 'validation.failed',
                    'errors' => $e->errors(),
                ], 422),

                $e instanceof AuthenticationException => response()->json([
                    'message' => 'Unauthenticated.',
                    'code' => 'auth.unauthenticated',
                ], 401),

                $e instanceof TokenMismatchException => response()->json([
                    'message' => 'Your session has expired. Please refresh and try again.',
                    'code' => 'auth.csrf',
                ], 419),

                $e instanceof AuthorizationException => response()->json([
                    'message' => $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                        ? $e->getMessage()
                        : "You don't have permission to perform this action.",
                    'code' => 'auth.forbidden',
                ], 403),

                // Cross-tenant ids are "not found" (tenant scope) — never 403,
                // so a record's existence in another company is not disclosed.
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => response()->json([
                    'message' => 'The requested record was not found.',
                    'code' => 'not_found',
                ], 404),

                $e instanceof ThrottleRequestsException => response()->json([
                    'message' => 'Too many attempts. Please wait a moment and try again.',
                    'code' => 'throttled',
                ], 429, $e->getHeaders()),

                $e instanceof TenantMismatchException => response()->json([
                    'message' => 'The referenced record belongs to another company.',
                    'code' => 'tenant.mismatch',
                ], 422),

                // Laravel converts AuthorizationException → AccessDeniedHttpException,
                // TokenMismatchException → HttpException(419) etc. before rendering,
                // so map well-known statuses to stable codes here.
                $e instanceof HttpExceptionInterface => response()->json([
                    'message' => match ($e->getStatusCode()) {
                        401 => 'Unauthenticated.',
                        403 => $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                            ? $e->getMessage()
                            : "You don't have permission to perform this action.",
                        404 => 'The requested record was not found.',
                        419 => 'Your session has expired. Please refresh and try again.',
                        429 => 'Too many attempts. Please wait a moment and try again.',
                        default => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                    },
                    'code' => match ($e->getStatusCode()) {
                        401 => 'auth.unauthenticated',
                        403 => 'auth.forbidden',
                        404 => 'not_found',
                        409 => 'conflict',
                        419 => 'auth.csrf',
                        429 => 'throttled',
                        default => 'http.'.$e->getStatusCode(),
                    },
                ], $e->getStatusCode(), $e->getHeaders()),

                default => response()->json(array_filter([
                    'message' => 'The server encountered an error. Please try again.',
                    'code' => 'server.error',
                    'debug' => config('app.debug') ? [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile().':'.$e->getLine(),
                    ] : null,
                ]), 500),
            };
        });
    })->create();
