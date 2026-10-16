<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\ProvisionCompanyAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Platform\StorePlatformCompanyRequest;
use App\Http\Requests\Platform\UpdatePlatformCompanyRequest;
use App\Http\Resources\Platform\PlatformCompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Cross-company administration. Runs without a tenant context: the
 * `platform` middleware (404 for everyone else) and `permission:platform.*`
 * gate the routes; every action re-checks the permission.
 */
class PlatformCompanyController extends ApiController
{
    private const PERMISSION = 'platform.companies.manage';

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePlatform($request);

        $query = Company::query()->withCount(['branches', 'memberships']);

        $this->applySearch($query, $request, ['name', 'legal_name', 'code', 'slug', 'email']);
        $this->applyFilters($query, $request, ['is_active', 'is_demo', 'country', 'currency']);
        $this->applySort($query, $request, ['name', 'code', 'created_at', 'is_active'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), PlatformCompanyResource::class);
    }

    public function store(StorePlatformCompanyRequest $request, ProvisionCompanyAction $provision): JsonResponse
    {
        $this->authorizePlatform($request);

        /** @var User $actor */
        $actor = $request->user();

        $company = $provision->handle($request->companyAttributes(), $request->owner(), $actor);

        return (new PlatformCompanyResource($company->refresh()->loadCount(['branches', 'memberships'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, Company $company): PlatformCompanyResource
    {
        $this->authorizePlatform($request);

        $company->loadCount(['branches', 'memberships']);

        // Headcount only (no HR details) — platform staff never see business data.
        $company->setAttribute('employees_count', Schema::hasTable('employees')
            ? DB::table('employees')->where('company_id', $company->id)->whereNull('deleted_at')->count()
            : null);

        $company->setAttribute('owners', $this->membersQuery($company)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->whereColumn('role_user.user_id', 'users.id')
                ->where('role_user.company_id', $company->id)
                ->where('roles.slug', 'company.owner'))
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->all());

        return new PlatformCompanyResource($company);
    }

    /**
     * Login accounts of a company with their roles — account-level data only
     * (name, email, roles, status). No HR, payroll or financial records.
     */
    public function members(Request $request, Company $company): JsonResponse
    {
        $this->authorizePlatform($request);

        $members = $this->membersQuery($company)
            ->orderBy('users.name')
            ->get([
                'users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.is_active as user_is_active',
                'users.last_login_at', 'users.two_factor_confirmed_at',
                'company_user.is_active as membership_active', 'company_user.branch_ids', 'company_user.joined_at',
            ]);

        $roles = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.company_id', $company->id)
            ->whereIn('role_user.user_id', $members->pluck('id'))
            ->get(['role_user.user_id', 'roles.slug', 'roles.name'])
            ->groupBy('user_id');

        return response()->json(['data' => $members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'avatar_url' => $m->avatar_path ? Storage::disk('public')->url($m->avatar_path) : null,
            'roles' => ($roles->get($m->id) ?? collect())->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name])->values(),
            'is_active' => (bool) $m->membership_active && (bool) $m->user_is_active,
            'branch_restricted' => $m->branch_ids !== null,
            'two_factor_enabled' => $m->two_factor_confirmed_at !== null,
            'last_login_at' => $m->last_login_at ? Carbon::parse($m->last_login_at)->toJSON() : null,
            'joined_at' => $m->joined_at ? Carbon::parse($m->joined_at)->toJSON() : null,
        ])->values()]);
    }

    private function membersQuery(Company $company): \Illuminate\Database\Query\Builder
    {
        return DB::table('users')
            ->join('company_user', 'company_user.user_id', '=', 'users.id')
            ->where('company_user.company_id', $company->id);
    }

    public function update(UpdatePlatformCompanyRequest $request, Company $company): PlatformCompanyResource
    {
        $this->authorizePlatform($request);

        $before = $company->getAttributes();
        $company->fill($request->validated())->save();

        [$old, $new] = AuditLogger::diff(
            $company->auditableAttributes($before),
            $company->auditableAttributes($company->getAttributes()),
        );

        $this->audit->log('platform.company_updated', $company, $old, $new, companyId: $company->id);

        return new PlatformCompanyResource($company->loadCount(['branches', 'memberships']));
    }

    public function activate(Request $request, Company $company): PlatformCompanyResource
    {
        $this->authorizePlatform($request);

        if (! $company->is_active) {
            $company->forceFill(['is_active' => true])->save();
            $this->audit->log('platform.company_activated', $company, ['is_active' => false], ['is_active' => true], companyId: $company->id);
        }

        return new PlatformCompanyResource($company->loadCount(['branches', 'memberships']));
    }

    public function deactivate(Request $request, Company $company): PlatformCompanyResource
    {
        $this->authorizePlatform($request);

        if ($company->is_active) {
            $company->forceFill(['is_active' => false])->save();
            $this->audit->log('platform.company_deactivated', $company, ['is_active' => true], ['is_active' => false], companyId: $company->id);
        }

        return new PlatformCompanyResource($company->loadCount(['branches', 'memberships']));
    }

    /** Soft-delete. Only inactive companies may be deleted (409 otherwise). */
    public function destroy(Request $request, Company $company): JsonResponse
    {
        $this->authorizePlatform($request);

        if ($company->is_active) {
            return $this->message('Deactivate the company before deleting it.', ['code' => 'conflict'], 409);
        }

        $company->delete();

        $this->audit->log('platform.company_deleted', $company, ['name' => $company->name, 'code' => $company->code], null, companyId: $company->id);

        return $this->noContent();
    }

    private function authorizePlatform(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        Gate::allowIf($user->hasPermission(self::PERMISSION));
    }
}
