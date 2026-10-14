<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Payroll\PayslipResource;
use App\Models\HR\Employee;
use App\Models\Payroll\Payslip;
use App\Services\Payroll\PayslipDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee self-service: the caller's own released (Finalized / Paid)
 * payslips, resolved through employees.user_id. Anything else is a 404.
 */
class MyPayslipController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewMine', Payslip::class);

        $query = $this->mine($request)->with('run');
        $this->applySort($query, $request, ['created_at', 'id'], '-id');

        return $this->paginated($query->paginate($this->perPage($request)), PayslipResource::class);
    }

    public function show(Request $request, int $payslip): PayslipResource
    {
        Gate::authorize('viewMine', Payslip::class);

        return new PayslipResource($this->mine($request)->with(['lines', 'run'])->findOrFail($payslip));
    }

    public function pdf(Request $request, int $payslip, PayslipDocuments $documents): Response
    {
        Gate::authorize('viewMine', Payslip::class);

        return $documents->pdf($this->mine($request)->findOrFail($payslip));
    }

    /**
     * @return Builder<Payslip>
     */
    private function mine(Request $request): Builder
    {
        $employeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');

        return Payslip::query()
            ->where('employee_id', $employeeId ?? 0)
            ->whereIn('status', PayrollRunStatus::releasedValues());
    }
}
