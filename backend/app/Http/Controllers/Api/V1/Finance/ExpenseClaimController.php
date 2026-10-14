<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\Receivables\DecideExpenseClaimAction;
use App\Actions\Finance\Receivables\SaveExpenseClaimAction;
use App\Actions\Finance\Receivables\SubmitExpenseClaimAction;
use App\Enums\Finance\ExpenseClaimStatus;
use App\Exceptions\Finance\FinanceException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\Receivables\ReasonRequest;
use App\Http\Requests\Finance\Receivables\StoreExpenseClaimRequest;
use App\Http\Requests\Finance\Receivables\UploadExpenseReceiptRequest;
use App\Http\Resources\Finance\Receivables\ExpenseClaimResource;
use App\Models\Finance\ExpenseClaim;
use App\Models\Finance\ExpenseClaimLine;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseClaimController extends ApiController
{
    private const SHOW_RELATIONS = ['lines', 'user:id,name', 'approver:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ExpenseClaim::class);

        $query = ExpenseClaim::query()->with('user:id,name')->withCount('lines');

        if (! Gate::allows('viewAll', ExpenseClaim::class)) {
            $query->where('user_id', $request->user()->id);
        }

        $this->applySearch($query, $request, ['number', 'notes']);
        $this->applyFilters($query, $request, ['status', 'user_id', 'employee_id', 'branch_id']);
        $this->applyDateRange($query, $request, 'claim_date');
        $this->applySort($query, $request, ['claim_date', 'number', 'total', 'status', 'created_at'], '-claim_date');

        return $this->paginated($query->paginate($this->perPage($request)), ExpenseClaimResource::class);
    }

    public function store(StoreExpenseClaimRequest $request, SaveExpenseClaimAction $save): JsonResponse
    {
        Gate::authorize('create', ExpenseClaim::class);

        $claim = $save->handle($request->validated(), null, (int) $request->user()->id);

        return (new ExpenseClaimResource($claim->load(self::SHOW_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(ExpenseClaim $expenseClaim): ExpenseClaimResource
    {
        Gate::authorize('view', $expenseClaim);

        return new ExpenseClaimResource($expenseClaim->load(self::SHOW_RELATIONS));
    }

    public function update(StoreExpenseClaimRequest $request, ExpenseClaim $expenseClaim, SaveExpenseClaimAction $save): ExpenseClaimResource
    {
        Gate::authorize('update', $expenseClaim);

        $claim = $save->handle($request->validated(), $expenseClaim, (int) $request->user()->id);

        return new ExpenseClaimResource($claim->load(self::SHOW_RELATIONS));
    }

    public function destroy(ExpenseClaim $expenseClaim): JsonResponse
    {
        Gate::authorize('delete', $expenseClaim);

        if (! in_array($expenseClaim->status, [ExpenseClaimStatus::Draft, ExpenseClaimStatus::Rejected], true)) {
            throw FinanceException::conflict("Expense claim {$expenseClaim->number} is {$expenseClaim->status->value} and cannot be deleted.");
        }

        $paths = $expenseClaim->lines()->whereNotNull('receipt_path')->pluck('receipt_path')->all();
        $expenseClaim->delete();
        Storage::disk('local')->delete($paths);

        return $this->noContent();
    }

    public function submit(ExpenseClaim $expenseClaim, SubmitExpenseClaimAction $submit): ExpenseClaimResource
    {
        Gate::authorize('submit', $expenseClaim);

        return new ExpenseClaimResource($submit->handle($expenseClaim)->load(self::SHOW_RELATIONS));
    }

    public function approve(Request $request, ExpenseClaim $expenseClaim, DecideExpenseClaimAction $decide): ExpenseClaimResource
    {
        Gate::authorize('approve', $expenseClaim);

        return new ExpenseClaimResource($decide->approve($expenseClaim, (int) $request->user()->id)->load(self::SHOW_RELATIONS));
    }

    public function reject(ReasonRequest $request, ExpenseClaim $expenseClaim, DecideExpenseClaimAction $decide): ExpenseClaimResource
    {
        Gate::authorize('reject', $expenseClaim);

        $claim = $decide->reject($expenseClaim, (int) $request->user()->id, $request->validated('reason'));

        return new ExpenseClaimResource($claim->load(self::SHOW_RELATIONS));
    }

    public function uploadReceipt(UploadExpenseReceiptRequest $request, ExpenseClaim $expenseClaim, ExpenseClaimLine $line, AuditLogger $audit): ExpenseClaimResource
    {
        Gate::authorize('update', $expenseClaim);

        if (! in_array($expenseClaim->status, [ExpenseClaimStatus::Draft, ExpenseClaimStatus::Rejected], true)) {
            throw FinanceException::conflict("Receipts can only be attached while expense claim {$expenseClaim->number} is a draft.");
        }

        $file = $request->file('receipt');
        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs("expense-receipts/{$expenseClaim->company_id}/{$expenseClaim->id}", $name, 'local');
        $previous = $line->receipt_path;

        $line->update(['receipt_path' => $path, 'receipt_original_name' => Str::limit($file->getClientOriginalName(), 250, '')]);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        $audit->log('expense_claims.receipt_uploaded', $expenseClaim, null, ['line_id' => $line->id, 'file' => $line->receipt_original_name]);

        return new ExpenseClaimResource($expenseClaim->load(self::SHOW_RELATIONS));
    }

    public function downloadReceipt(ExpenseClaim $expenseClaim, ExpenseClaimLine $line): StreamedResponse
    {
        Gate::authorize('view', $expenseClaim);

        abort_if($line->receipt_path === null || ! Storage::disk('local')->exists($line->receipt_path), 404);

        return Storage::disk('local')->download($line->receipt_path, $line->receipt_original_name ?? basename($line->receipt_path));
    }
}
