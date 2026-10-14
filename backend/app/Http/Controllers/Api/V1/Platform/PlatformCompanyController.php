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
use Illuminate\Support\Facades\Gate;

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

        return new PlatformCompanyResource($company->loadCount(['branches', 'memberships']));
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
