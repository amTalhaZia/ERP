<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Actions\Sales\ConvertLeadAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\Rules\CompanyMember;
use App\Http\Requests\Sales\StoreLeadRequest;
use App\Http\Resources\Sales\CustomerResource;
use App\Http\Resources\Sales\LeadResource;
use App\Http\Resources\Sales\OpportunityResource;
use App\Models\Sales\Lead;
use App\Models\Sales\PipelineStage;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LeadController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lead::class);

        $query = Lead::query()->with('assignee');
        $this->applySearch($query, $request, ['name', 'company_name', 'email', 'phone']);
        $this->applyFilters($query, $request, ['status', 'source', 'assigned_to']);
        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['name', 'company_name', 'status', 'estimated_value', 'created_at']);

        return $this->paginated($query->paginate($this->perPage($request)), LeadResource::class);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        Gate::authorize('create', Lead::class);

        $lead = Lead::query()->create($request->validated());

        return (new LeadResource($lead->load('assignee')))->response()->setStatusCode(201);
    }

    public function show(Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);

        return new LeadResource($lead->load(['assignee', 'convertedCustomer']));
    }

    public function update(StoreLeadRequest $request, Lead $lead): LeadResource
    {
        Gate::authorize('update', $lead);

        $lead->fill($request->validated())->save();

        return new LeadResource($lead->refresh()->load(['assignee', 'convertedCustomer']));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return $this->noContent();
    }

    public function convert(Request $request, Lead $lead, ConvertLeadAction $action): JsonResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'create_opportunity' => ['sometimes', 'boolean'],
            'stage_id' => ['nullable', 'integer', new TenantExists(PipelineStage::class)],
        ]);

        $result = $action->handle(
            $lead,
            (bool) ($data['create_opportunity'] ?? false),
            isset($data['stage_id']) ? (int) $data['stage_id'] : null,
            $request->user(),
        );

        return response()->json([
            'data' => [
                'lead' => new LeadResource($lead->refresh()->load(['assignee', 'convertedCustomer'])),
                'customer' => new CustomerResource($result['customer']),
                'customer_created' => $result['customer_created'],
                'opportunity' => $result['opportunity'] ? new OpportunityResource($result['opportunity']->load('stage')) : null,
            ],
        ]);
    }

    public function assign(Request $request, Lead $lead, AuditLogger $audit): LeadResource
    {
        Gate::authorize('update', $lead);

        $data = $request->validate(['user_id' => ['required', 'integer', new CompanyMember]]);

        $previous = $lead->assigned_to;
        $lead->forceFill(['assigned_to' => (int) $data['user_id']])->save();

        $audit->log('leads.assigned', $lead, ['assigned_to' => $previous], ['assigned_to' => $lead->assigned_to]);

        return new LeadResource($lead->refresh()->load('assignee'));
    }
}
