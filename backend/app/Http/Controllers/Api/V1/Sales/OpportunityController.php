<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Enums\Sales\OpportunityStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreOpportunityRequest;
use App\Http\Resources\Sales\OpportunityResource;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use App\Services\Sales\SalesReports;
use App\Support\Money\Money;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpportunityController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Opportunity::class);

        $query = Opportunity::query()->with(['stage', 'customer', 'owner']);
        $this->applySearch($query, $request, ['title']);
        $this->applyFilters($query, $request, ['status', 'stage_id', 'owner_id', 'customer_id', 'lead_id']);
        $this->applyDateRange($query, $request, 'expected_close_date');
        $this->applySort($query, $request, ['title', 'value', 'probability', 'expected_close_date', 'status', 'created_at']);

        return $this->paginated($query->paginate($this->perPage($request)), OpportunityResource::class);
    }

    /** Kanban payload: every stage (ordered) with its opportunities and totals. */
    public function board(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Opportunity::class);

        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(OpportunityStatus::class)],
            'owner_id' => ['sometimes', 'integer'],
        ]);

        $stages = PipelineStage::query()->orderBy('position')->orderBy('id')->get();

        $opportunities = Opportunity::query()
            ->with(['customer', 'owner', 'stage'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['owner_id']), fn ($q) => $q->where('owner_id', $filters['owner_id']))
            ->orderByDesc('value')
            ->orderBy('id')
            ->get()
            ->groupBy('stage_id');

        return response()->json([
            'data' => $stages->map(function (PipelineStage $stage) use ($opportunities, $request) {
                $items = $opportunities->get($stage->id, collect());

                return [
                    'stage' => ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color, 'probability' => $stage->probability, 'is_won' => $stage->is_won, 'is_lost' => $stage->is_lost],
                    'count' => $items->count(),
                    'total_value' => Money::sum($items->map(fn (Opportunity $o) => (string) $o->value)->all()),
                    'items' => OpportunityResource::collection($items)->toArray($request),
                ];
            })->values()->all(),
        ]);
    }

    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        Gate::authorize('create', Opportunity::class);

        $opportunity = DB::transaction(function () use ($request): Opportunity {
            $data = $request->validated();
            $stage = PipelineStage::query()->findOrFail($data['stage_id']);

            $opportunity = new Opportunity($data + [
                'currency' => app(TenantContext::class)->company()?->currency ?? 'USD',
                'owner_id' => $request->user()->id,
            ]);
            $opportunity->applyStage($stage, $data['lost_reason'] ?? null);

            if (isset($data['probability'])) {
                $opportunity->probability = (int) $data['probability'];
            }

            $opportunity->save();

            return $opportunity;
        });

        return (new OpportunityResource($opportunity->load(['stage', 'customer', 'owner'])))->response()->setStatusCode(201);
    }

    public function show(Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('view', $opportunity);

        return new OpportunityResource($opportunity->load(['stage', 'customer', 'owner']));
    }

    public function update(StoreOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('update', $opportunity);

        DB::transaction(function () use ($request, $opportunity): void {
            $data = $request->validated();
            $opportunity->fill($data);

            if (isset($data['stage_id']) && (int) $data['stage_id'] !== (int) $opportunity->getOriginal('stage_id')) {
                $opportunity->applyStage(PipelineStage::query()->findOrFail($data['stage_id']), $data['lost_reason'] ?? null);

                if (isset($data['probability'])) {
                    $opportunity->probability = (int) $data['probability'];
                }
            }

            $opportunity->save();
        });

        return new OpportunityResource($opportunity->refresh()->load(['stage', 'customer', 'owner']));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        Gate::authorize('delete', $opportunity);

        $opportunity->delete();

        return $this->noContent();
    }

    /** Move to another stage (won/lost stages close the opportunity). */
    public function moveStage(Request $request, Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('update', $opportunity);

        $data = $request->validate([
            'stage_id' => ['required', 'integer', new TenantExists(PipelineStage::class)],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $opportunity->applyStage(PipelineStage::query()->findOrFail($data['stage_id']), $data['lost_reason'] ?? null);
        $opportunity->save();

        return new OpportunityResource($opportunity->refresh()->load(['stage', 'customer', 'owner']));
    }

    public function pipelineSummary(SalesReports $reports): JsonResponse
    {
        Gate::authorize('sales.pipeline.view');

        return response()->json(['data' => $reports->pipelineSummary()]);
    }
}
