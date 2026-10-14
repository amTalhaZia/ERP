<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\Company\CompanyResource;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The current company's profile & settings. There is deliberately no
 * index/show-by-id here: a tenant user can only ever see their active company.
 * Cross-company administration lives under /platform.
 */
class CompanyController extends ApiController
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function show(): CompanyResource
    {
        $company = $this->tenant->company()->loadCount(['branches', 'memberships']);
        Gate::authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request): CompanyResource
    {
        $company = $this->tenant->company();
        Gate::authorize('update', $company);

        $company->fill($request->validated())->save();

        return new CompanyResource($company->loadCount(['branches', 'memberships']));
    }

    public function uploadLogo(Request $request): CompanyResource
    {
        $company = $this->tenant->company();
        Gate::authorize('update', $company);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:'.config('erp.uploads.image_max_kb')],
        ]);

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $path = $request->file('logo')->store("companies/{$company->id}/branding", 'public');
        $company->forceFill(['logo_path' => $path])->save();

        $this->audit->log('companies.logo_updated', $company);

        return new CompanyResource($company);
    }

    public function deleteLogo(): JsonResponse
    {
        $company = $this->tenant->company();
        Gate::authorize('update', $company);

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->forceFill(['logo_path' => null])->save();
        }

        return $this->noContent();
    }
}
