<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\Receivables\PostBillAction;
use App\Actions\Finance\Receivables\SaveBillAction;
use App\Actions\Finance\Receivables\SubmitBillAction;
use App\Actions\Finance\Receivables\VoidBillAction;
use App\Enums\Finance\BillStatus;
use App\Enums\Finance\PaymentPartnerType;
use App\Exceptions\Finance\FinanceException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\Receivables\ReasonRequest;
use App\Http\Requests\Finance\Receivables\StoreBillRequest;
use App\Http\Resources\Finance\Receivables\BillResource;
use App\Models\Finance\Bill;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BillController extends ApiController
{
    private const SHOW_RELATIONS = ['lines.account:id,code,name', 'lines.product:id,sku,name', 'allocations.payment:id,number,payment_date,method,status', 'branch:id,name', 'approver:id,name'];

    public function __construct(private readonly PartnerDirectory $partners) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Bill::class);

        $query = Bill::query()->with('branch:id,name')->withCount('lines');
        $filters = (array) $request->input('filter', []);

        if (($filters['status'] ?? null) === BillStatus::Overdue->value || $request->boolean('overdue')) {
            $query->overdue();
            unset($filters['status']);
            $request->merge(['filter' => $filters]);
        }

        $this->applySearch($query, $request, ['number', 'supplier_reference']);
        $this->applyFilters($query, $request, ['status', 'supplier_id', 'branch_id', 'purchase_order_id', 'currency']);
        $this->applyDateRange($query, $request, 'bill_date');
        $this->applySort($query, $request, ['bill_date', 'due_date', 'number', 'total', 'balance_due', 'status', 'created_at'], '-bill_date');

        $allowed = app(TenantContext::class)->allowedBranchIds();
        if ($allowed !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $allowed));
        }

        $page = $query->paginate($this->perPage($request));
        $this->partners->attach($page->getCollection(), PaymentPartnerType::Supplier, 'supplier_id');

        return $this->paginated($page, BillResource::class);
    }

    public function store(StoreBillRequest $request, SaveBillAction $save): JsonResponse
    {
        Gate::authorize('create', Bill::class);

        $bill = $save->handle($request->validated(), null, $request->user()?->id);

        return $this->resource($bill)->response()->setStatusCode(201);
    }

    public function show(Bill $bill): BillResource
    {
        Gate::authorize('view', $bill);

        return $this->resource($bill);
    }

    public function update(StoreBillRequest $request, Bill $bill, SaveBillAction $save): BillResource
    {
        Gate::authorize('update', $bill);

        return $this->resource($save->handle($request->validated(), $bill, $request->user()?->id));
    }

    public function destroy(Bill $bill): JsonResponse
    {
        Gate::authorize('delete', $bill);

        if (! $bill->isEditable()) {
            throw FinanceException::conflict("Bill {$bill->number} has been posted or voided and cannot be deleted; void it instead.");
        }

        $bill->delete();

        return $this->noContent();
    }

    public function submit(Request $request, Bill $bill, SubmitBillAction $submit): BillResource
    {
        Gate::authorize('submit', $bill);

        return $this->resource($submit->handle($bill, $request->user()?->id));
    }

    public function approve(Request $request, Bill $bill, PostBillAction $post): BillResource
    {
        Gate::authorize('approve', $bill);

        return $this->resource($post->handle($bill, $request->user()?->id));
    }

    public function void(ReasonRequest $request, Bill $bill, VoidBillAction $void): BillResource
    {
        Gate::authorize('void', $bill);

        return $this->resource($void->handle($bill, $request->validated('reason'), $request->user()?->id));
    }

    private function resource(Bill $bill): BillResource
    {
        $bill->load(self::SHOW_RELATIONS);
        $this->partners->attach([$bill], PaymentPartnerType::Supplier, 'supplier_id');

        return new BillResource($bill);
    }
}
