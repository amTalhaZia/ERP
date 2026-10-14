<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /members — lightweight member picker (assignees, approvers, sales reps).
 *
 * Open to every active member of the current company (no users.view needed):
 * it exposes only id / name / email / avatar of active members of the SAME
 * company, at most 50 rows. `permission=invoices.approve` narrows the list to
 * members holding that permission in this company (through a company role).
 * The full user-management listing stays behind users.view in Access.
 */
class MemberController extends ApiController
{
    private const LIMIT = 50;

    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'permission' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/'],
        ]);

        $companyId = (int) $tenant->id();
        $query = User::query()
            ->where('users.is_active', true)
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->where('company_user.company_id', $companyId)
                ->where('company_user.is_active', true));

        if (($term = trim((string) ($validated['search'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn (Builder $q) => $q->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like));
        }

        if (! empty($validated['permission'])) {
            $slug = $validated['permission'];
            $query->whereHas('roles', fn (Builder $roles) => $roles
                ->where('role_user.company_id', $companyId)
                ->where('roles.company_id', $companyId)
                ->whereHas('permissions', fn (Builder $permissions) => $permissions->where('permissions.slug', $slug)));
        }

        $members = $query->orderBy('users.name')->orderBy('users.id')
            ->limit(self::LIMIT)
            ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatarUrl(),
            ]);

        return response()->json(['data' => $members->values()->all()]);
    }
}
