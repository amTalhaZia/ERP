<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Auth\SessionResource;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends ApiController
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /** Current user + memberships + permissions for the active company. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => (new SessionResource($request->user()))->resolve($request)]);
    }

    /**
     * Switch the active company. The membership is verified server-side and
     * the switch is audited; the SPA then sends X-Company-Id on every request.
     */
    public function switchCompany(Request $request): JsonResponse
    {
        $validated = $request->validate(['company_id' => ['required', 'integer']]);

        /** @var User $user */
        $user = $request->user();

        $membership = CompanyUser::query()
            ->with('company')
            ->where('user_id', $user->id)
            ->where('company_id', $validated['company_id'])
            ->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->first();

        if ($membership === null) {
            $this->audit->log('auth.switch_company_denied', context: ['company_id' => $validated['company_id']]);

            throw ValidationException::withMessages(['company_id' => ['You do not have access to that company.']]);
        }

        CompanyUser::query()->where('user_id', $user->id)->update(['is_default' => false]);
        $membership->forceFill(['is_default' => true])->save();

        $this->tenant->set($membership->company, $membership);
        $user->forgetPermissionCache();

        $this->audit->log('auth.switch_company', $membership->company, companyId: $membership->company_id);

        return response()->json(['data' => (new SessionResource($user))->resolve($request)]);
    }
}
