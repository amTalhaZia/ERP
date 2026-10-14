<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\Receivables\PostInvoiceAction;
use App\Actions\Finance\Receivables\SaveInvoiceAction;
use App\Actions\Finance\Receivables\SubmitInvoiceAction;
use App\Actions\Finance\Receivables\VoidInvoiceAction;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentPartnerType;
use App\Exceptions\Finance\FinanceException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\Receivables\ReasonRequest;
use App\Http\Requests\Finance\Receivables\StoreInvoiceRequest;
use App\Http\Resources\Finance\Receivables\InvoiceResource;
use App\Models\Finance\Invoice;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends ApiController
{
    private const SHOW_RELATIONS = ['lines.account:id,code,name', 'lines.product:id,sku,name', 'allocations.payment:id,number,payment_date,method,status', 'branch:id,name', 'creator:id,name', 'approver:id,name'];

    public function __construct(private readonly PartnerDirectory $partners) {}

    /**
     * Filters: filter[status] (overdue = derived), filter[customer_id],
     * filter[branch_id], overdue=true, from/to on invoice_date, search on number.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        $query = Invoice::query()->with('branch:id,name')->withCount('lines');
        $filters = (array) $request->input('filter', []);

        if (($filters['status'] ?? null) === InvoiceStatus::Overdue->value || $request->boolean('overdue')) {
            $query->overdue();
            unset($filters['status']);
            $request->merge(['filter' => $filters]);
        }

        $this->applySearch($query, $request, ['number']);
        $this->applyFilters($query, $request, ['status', 'customer_id', 'branch_id', 'sales_order_id', 'currency']);
        $this->applyDateRange($query, $request, 'invoice_date');
        $this->applySort($query, $request, ['invoice_date', 'due_date', 'number', 'total', 'balance_due', 'status', 'created_at'], '-invoice_date');

        $allowed = app(TenantContext::class)->allowedBranchIds();
        if ($allowed !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $allowed));
        }

        $page = $query->paginate($this->perPage($request));
        $this->partners->attach($page->getCollection(), PaymentPartnerType::Customer, 'customer_id');

        return $this->paginated($page, InvoiceResource::class);
    }

    public function store(StoreInvoiceRequest $request, SaveInvoiceAction $save): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $save->handle($request->validated(), null, $request->user()?->id);

        return $this->resource($invoice)->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return $this->resource($invoice);
    }

    public function update(StoreInvoiceRequest $request, Invoice $invoice, SaveInvoiceAction $save): InvoiceResource
    {
        Gate::authorize('update', $invoice);

        return $this->resource($save->handle($request->validated(), $invoice, $request->user()?->id));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        Gate::authorize('delete', $invoice);

        if (! $invoice->isEditable()) {
            throw FinanceException::conflict("Invoice {$invoice->number} has been posted or voided and cannot be deleted; void it instead.");
        }

        $invoice->delete();

        return $this->noContent();
    }

    public function submit(Request $request, Invoice $invoice, SubmitInvoiceAction $submit): InvoiceResource
    {
        Gate::authorize('submit', $invoice);

        return $this->resource($submit->handle($invoice, $request->user()?->id));
    }

    public function approve(Request $request, Invoice $invoice, PostInvoiceAction $post): InvoiceResource
    {
        Gate::authorize('approve', $invoice);

        return $this->resource($post->handle($invoice, $request->user()?->id));
    }

    public function void(ReasonRequest $request, Invoice $invoice, VoidInvoiceAction $void): InvoiceResource
    {
        Gate::authorize('void', $invoice);

        return $this->resource($void->handle($invoice, $request->validated('reason'), $request->user()?->id));
    }

    public function pdf(Invoice $invoice, AuditLogger $audit): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['lines', 'branch:id,name']);
        $company = app(TenantContext::class)->company();
        $logo = null;

        if ($company?->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $mime = Storage::disk('public')->mimeType($company->logo_path) ?: 'image/png';
            $logo = 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($company->logo_path));
        }

        $audit->log('invoices.exported', $invoice, null, null, ['format' => 'pdf']);

        return Pdf::loadView('pdf.finance.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'logo' => $logo,
            'customer' => $this->partners->details('customers', (int) $invoice->customer_id),
        ])->setPaper('a4')->download("{$invoice->number}.pdf");
    }

    private function resource(Invoice $invoice): InvoiceResource
    {
        $invoice->load(self::SHOW_RELATIONS);
        $this->partners->attach([$invoice], PaymentPartnerType::Customer, 'customer_id');

        return new InvoiceResource($invoice);
    }
}
