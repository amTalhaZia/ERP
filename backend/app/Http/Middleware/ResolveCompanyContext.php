<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the trusted tenant for the request.
 *
 * Order of resolution: X-Company-Id header → `company_id` query (downloads
 * opened in a new tab) → the user's default membership. Whatever the source,
 * the user must hold an active membership in an active company, otherwise the
 * request is refused. Platform administrators without a membership get no
 * tenant and can only reach /platform routes.
 */
class ResolveCompanyContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $requested = $request->header(config('erp.company_header')) ?? $request->query('company_id');
        $requestedId = is_numeric($requested) ? (int) $requested : null;

        $membership = $this->resolveMembership($user, $requestedId);

        if ($membership === null) {
            if ($mode === 'optional') {
                return $next($request);
            }

            return response()->json([
                'message' => $requestedId
                    ? 'You do not have access to the selected company.'
                    : 'Select a company to continue.',
                'code' => 'tenant.forbidden',
            ], 403);
        }

        $this->tenant->set($membership->company, $membership);
        Context::add('company_id', $membership->company_id);

        return $next($request);
    }

    private function resolveMembership(User $user, ?int $requestedId): ?CompanyUser
    {
        $query = CompanyUser::query()
            ->with('company')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('is_active', true));

        if ($requestedId !== null) {
            return $query->where('company_id', $requestedId)->first();
        }

        return $query->orderByDesc('is_default')->orderBy('id')->first();
    }
}
