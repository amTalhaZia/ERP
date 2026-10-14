<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Actions\Payroll\ApprovePayrollRunAction;
use App\Actions\Payroll\CalculatePayrollRunAction;
use App\Actions\Payroll\CancelPayrollRunAction;
use App\Actions\Payroll\CreatePayrollRunAction;
use App\Actions\Payroll\FinalizePayrollRunAction;
use App\Actions\Payroll\MarkPayrollPaidAction;
use App\Enums\Payroll\PayrollRunStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payroll\MarkPayrollPaidRequest;
use App\Http\Requests\Payroll\StorePayrollRunRequest;
use App\Http\Resources\Payroll\PayrollRunResource;
use App\Http\Resources\Payroll\PayslipResource;
use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayrollErrors;
use App\Services\Payroll\PayslipDocuments;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends ApiController
{
    private const SHOW_RELATIONS = ['branch:id,name', 'department:id,name', 'creator:id,name', 'approver:id,name', 'finalizer:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $query = PayrollRun::query()->with(['branch:id,name', 'department:id,name']);
        $this->applySearch($query, $request, ['number', 'name']);
        $this->applyFilters($query, $request, ['status', 'branch_id', 'department_id']);
        $this->applyDateRange($query, $request, 'period_end');
        $this->applySort($query, $request, ['period_end', 'payment_date', 'number', 'status', 'created_at'], '-period_end');

        $allowed = app(TenantContext::class)->allowedBranchIds();
        if ($allowed !== null) {
            $query->whereIn('branch_id', $allowed);
        }

        return $this->paginated($query->paginate($this->perPage($request)), PayrollRunResource::class);
    }

    public function store(StorePayrollRunRequest $request, CreatePayrollRunAction $create): JsonResponse
    {
        Gate::authorize('create', PayrollRun::class);

        $result = $create->handle($request->validated(), $request->user()?->id);

        return $this->resource($result['run'])
            ->additional(['meta' => ['skipped' => $result['skipped']]])
            ->response()->setStatusCode(201);
    }

    public function show(PayrollRun $payrollRun): PayrollRunResource
    {
        Gate::authorize('view', $payrollRun);

        return $this->resource($payrollRun);
    }

    /** Only Draft runs may be deleted; later states are cancelled instead. */
    public function destroy(PayrollRun $payrollRun, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('delete', $payrollRun);

        if ($payrollRun->status !== PayrollRunStatus::Draft) {
            throw PayrollErrors::invalidState($payrollRun, 'deleted');
        }

        $audit->log('payroll.run_deleted', $payrollRun, ['status' => $payrollRun->status->value, 'number' => $payrollRun->number], null);
        $payrollRun->delete();

        return $this->noContent();
    }

    public function calculate(Request $request, PayrollRun $payrollRun, CalculatePayrollRunAction $calculate): PayrollRunResource
    {
        Gate::authorize('process', $payrollRun);

        $result = $calculate->handle($payrollRun, $request->user()?->id);

        return $this->resource($result['run'])->additional(['meta' => ['skipped' => $result['skipped']]]);
    }

    public function approve(Request $request, PayrollRun $payrollRun, ApprovePayrollRunAction $approve): PayrollRunResource
    {
        Gate::authorize('process', $payrollRun);

        return $this->resource($approve->handle($payrollRun, (int) $request->user()->id));
    }

    public function finalize(Request $request, PayrollRun $payrollRun, FinalizePayrollRunAction $finalize): PayrollRunResource
    {
        Gate::authorize('process', $payrollRun);

        return $this->resource($finalize->handle($payrollRun, (int) $request->user()->id));
    }

    public function pay(MarkPayrollPaidRequest $request, PayrollRun $payrollRun, MarkPayrollPaidAction $pay): PayrollRunResource
    {
        Gate::authorize('process', $payrollRun);

        return $this->resource($pay->handle($payrollRun, $request->validated(), (int) $request->user()->id));
    }

    public function cancel(Request $request, PayrollRun $payrollRun, CancelPayrollRunAction $cancel): PayrollRunResource
    {
        Gate::authorize('process', $payrollRun);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        return $this->resource($cancel->handle($payrollRun, (int) $request->user()->id, $reason));
    }

    public function payslips(Request $request, PayrollRun $payrollRun): AnonymousResourceCollection
    {
        Gate::authorize('view', $payrollRun);

        $query = $payrollRun->payslips()->getQuery();
        $this->applySearch($query, $request, ['employee_code', 'employee_name', 'number']);
        $this->applyFilters($query, $request, ['department_id']);
        $this->applySort($query, $request, ['employee_code', 'employee_name', 'net', 'gross'], 'employee_code');

        return $this->paginated($query->paginate($this->perPage($request)), PayslipResource::class);
    }

    public function export(Request $request, PayrollRun $payrollRun, PayslipDocuments $documents): StreamedResponse
    {
        Gate::authorize('export', $payrollRun);

        $format = $request->validate(['format' => ['sometimes', 'in:csv,xlsx']])['format'] ?? 'csv';

        return $documents->export($payrollRun, $format);
    }

    private function resource(PayrollRun $run): PayrollRunResource
    {
        return new PayrollRunResource($run->load(self::SHOW_RELATIONS));
    }
}
