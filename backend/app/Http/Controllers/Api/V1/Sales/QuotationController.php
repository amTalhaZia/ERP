<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Actions\Sales\CreateSalesOrderFromQuotationAction;
use App\Actions\Sales\UpsertQuotationAction;
use App\Enums\Sales\QuotationStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Http\Resources\Sales\QuotationResource;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuotationController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Quotation::class);

        Quotation::expireStale();

        $query = Quotation::query()->with(['customer', 'salesRep'])->withCount('lines');
        $this->applySearch($query, $request, ['number', 'notes']);
        $this->applyFilters($query, $request, ['status', 'customer_id', 'sales_rep_id', 'currency']);
        $this->applyDateRange($query, $request, 'date');
        $this->applySort($query, $request, ['number', 'date', 'valid_until', 'status', 'total', 'created_at']);

        return $this->paginated($query->paginate($this->perPage($request)), QuotationResource::class);
    }

    public function store(StoreQuotationRequest $request, UpsertQuotationAction $action): JsonResponse
    {
        Gate::authorize('create', Quotation::class);

        $quotation = $action->handle($request->validated(), null, $request->user());

        return (new QuotationResource($this->present($quotation)))->response()->setStatusCode(201);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        Gate::authorize('view', $quotation);

        Quotation::expireStale();

        return new QuotationResource($this->present($quotation->refresh()));
    }

    public function update(StoreQuotationRequest $request, Quotation $quotation, UpsertQuotationAction $action): QuotationResource
    {
        Gate::authorize('update', $quotation);
        $this->ensureDraft($quotation, 'edited');

        $quotation = $action->handle($request->validated(), $quotation, $request->user());

        return new QuotationResource($this->present($quotation->refresh()));
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        Gate::authorize('delete', $quotation);
        $this->ensureDraft($quotation, 'deleted');

        $quotation->delete();

        return $this->noContent();
    }

    public function send(Quotation $quotation): QuotationResource
    {
        Gate::authorize('update', $quotation);
        $this->ensureDraft($quotation, 'sent');

        if (! $quotation->lines()->exists()) {
            throw new ConflictHttpException('Add at least one line before sending the quotation.');
        }

        $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();
        $this->audit->log('quotations.sent', $quotation);

        return new QuotationResource($this->present($quotation));
    }

    public function accept(Quotation $quotation): QuotationResource
    {
        Gate::authorize('update', $quotation);
        $this->ensureStatus($quotation, [QuotationStatus::Sent], 'accepted');

        $quotation->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now()])->save();
        $this->audit->log('quotations.accepted', $quotation);

        return new QuotationResource($this->present($quotation));
    }

    public function reject(Request $request, Quotation $quotation): QuotationResource
    {
        Gate::authorize('update', $quotation);
        $this->ensureStatus($quotation, [QuotationStatus::Sent], 'rejected');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $quotation->forceFill([
            'status' => QuotationStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $data['reason'] ?? null,
        ])->save();
        $this->audit->log('quotations.rejected', $quotation, context: ['reason' => $data['reason'] ?? null]);

        return new QuotationResource($this->present($quotation));
    }

    public function convert(Request $request, Quotation $quotation, CreateSalesOrderFromQuotationAction $action): JsonResponse
    {
        Gate::authorize('update', $quotation);
        Gate::authorize('create', SalesOrder::class);
        $this->ensureStatus($quotation, [QuotationStatus::Sent, QuotationStatus::Accepted], 'converted');

        $order = $action->handle($quotation, $request->user());

        return (new SalesOrderResource($order->load(['customer', 'salesRep', 'lines.product:id,sku,name', 'warehouse:id,name'])))->response()->setStatusCode(201);
    }

    private function present(Quotation $quotation): Quotation
    {
        return $quotation->loadMissing(['customer', 'salesRep', 'lines.product:id,sku,name']);
    }

    private function ensureDraft(Quotation $quotation, string $verb): void
    {
        $this->ensureStatus($quotation, [QuotationStatus::Draft], $verb);
    }

    /**
     * @param  array<int, QuotationStatus>  $allowed
     */
    private function ensureStatus(Quotation $quotation, array $allowed, string $verb): void
    {
        if (! in_array($quotation->status, $allowed, true)) {
            throw new ConflictHttpException(sprintf(
                'A %s quotation cannot be %s.', $quotation->status->value, $verb,
            ));
        }
    }
}
