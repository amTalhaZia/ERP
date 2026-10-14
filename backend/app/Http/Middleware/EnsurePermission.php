<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `permission:invoices.approve` or `permission:a|b` (any).
 * Policies remain the primary check; this is the coarse gate at the router.
 */
class EnsurePermission
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $slugs = explode('|', $permissions);

        if ($user === null || ! $user->hasAnyPermission($slugs)) {
            $this->audit->log('auth.permission_denied', context: [
                'required' => $slugs,
                'route' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'message' => "You don't have permission to perform this action.",
                'code' => 'auth.forbidden',
            ], 403);
        }

        return $next($request);
    }
}
