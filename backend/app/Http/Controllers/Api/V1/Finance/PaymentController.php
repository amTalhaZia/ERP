<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\Receivables\RecordPaymentAction;
use App\Actions\Finance\Receivables\VoidPaymentAction;
use App\Enums\Finance\PaymentPartnerType;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\Receivables\ReasonRequest;
use App\Http\Requests\Finance\Receivables\StorePaymentRequest;
use App\Http\Resources\Finance\Receivables\PaymentResource;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Receipts and payments. No update/delete: a recorded payment is posted; the
 * only correction is void (reversal + allocation rollback).
 */
class PaymentController extends ApiController
{
    private const SHOW_RELATIONS = ['allocations', 'bankAccount:id,name,account_number_last4'];

    public function __construct(private readonly PartnerDirectory $partners) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Payment::class);

        $query = Payment::query()->with('bankAccount:id,name,account_number_last4');
        $this->applySearch($query, $request, ['number', 'reference']);
        $this->applyFilters($query, $request, ['type', 'partner_type', 'partner_id', 'method', 'status', 'bank_account_id', 'branch_id']);
        $this->applyDateRange($query, $request, 'payment_date');
        $this->applyAllocationFilter($query, $request);
        $this->applySort($query, $request, ['payment_date', 'number', 'amount', 'created_at'], '-payment_date');

        $allowed = app(TenantContext::class)->allowedBranchIds();
        if ($allowed !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $allowed));
        }

        $page = $query->paginate($this->perPage($request));
        $this->attachPartners($page->getCollection()->all());

        return $this->paginated($page, PaymentResource::class);
    }

    public function store(StorePaymentRequest $request, RecordPaymentAction $record): JsonResponse
    {
        Gate::authorize('create', Payment::class);

        $payment = $record->handle($request->validated(), $request->user()?->id);

        return $this->resource($payment)->response()->setStatusCode(201);
    }

    public function show(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return $this->resource($payment);
    }

    public function void(ReasonRequest $request, Payment $payment, VoidPaymentAction $void): PaymentResource
    {
        Gate::authorize('void', $payment);

        return $this->resource($void->handle($payment, $request->validated('reason'), $request->user()?->id));
    }

    private function resource(Payment $payment): PaymentResource
    {
        $payment->load(self::SHOW_RELATIONS);
        $this->attachPartners([$payment]);
        $this->attachDocumentNumbers($payment);

        return new PaymentResource($payment);
    }

    /**
     * @param  array<int, Payment>  $payments
     */
    private function attachPartners(array $payments): void
    {
        foreach (PaymentPartnerType::cases() as $type) {
            $this->partners->attach(array_filter($payments, fn (Payment $p) => $p->partner_type === $type), $type, 'partner_id');
        }
    }

    /**
     * `filter[allocatable_type]=invoice&filter[allocatable_id]=12` → payments
     * that settle that document (type alone narrows to payments of that kind).
     *
     * @param  Builder<Payment>  $query
     */
    private function applyAllocationFilter(Builder $query, Request $request): void
    {
        $filters = (array) $request->input('filter', []);
        $type = $filters['allocatable_type'] ?? null;
        $id = $filters['allocatable_id'] ?? null;

        if (($type === null || $type === '') && ($id === null || $id === '')) {
            return;
        }

        $query->whereHas('allocations', function (Builder $allocations) use ($type, $id): void {
            if (is_string($type) && $type !== '') {
                $allocations->where('allocatable_type', array_key_exists($type, PaymentAllocation::TYPES) ? $type : '__none__');
            }

            if (is_scalar($id) && $id !== '') {
                $allocations->where('allocatable_id', is_numeric($id) ? (int) $id : 0);
            }
        });
    }

    /** Adds each allocation's document number (one query per document type). */
    private function attachDocumentNumbers(Payment $payment): void
    {
        foreach ($payment->allocations->groupBy('allocatable_type') as $type => $allocations) {
            $model = PaymentAllocation::TYPES[$type] ?? null;
            $numbers = $model === null ? collect() : $model::query()
                ->whereKey($allocations->pluck('allocatable_id')->unique()->all())
                ->pluck('number', 'id');

            foreach ($allocations as $allocation) {
                $allocation->setAttribute('document_number', $numbers->get($allocation->allocatable_id));
            }
        }
    }
}
