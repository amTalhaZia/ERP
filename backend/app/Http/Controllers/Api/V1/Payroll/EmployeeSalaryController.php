<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Actions\Payroll\RecordEmployeeSalaryAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payroll\StoreEmployeeSalaryRequest;
use App\Http\Resources\Payroll\EmployeeSalaryResource;
use App\Models\HR\Employee;
use App\Models\Payroll\EmployeeSalary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Salary history of an employee (newest first). `{employee}` is resolved in
 * the tenant scope, so another company's employee is a 404.
 */
class EmployeeSalaryController extends ApiController
{
    public function index(Request $request, Employee $employee): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', EmployeeSalary::class);

        $query = EmployeeSalary::query()
            ->with(['structure:id,name', 'components.component', 'creator:id,name'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request)), EmployeeSalaryResource::class);
    }

    public function store(StoreEmployeeSalaryRequest $request, Employee $employee, RecordEmployeeSalaryAction $record): JsonResponse
    {
        Gate::authorize('create', EmployeeSalary::class);

        $salary = $record->handle($employee, $request->payload(), $request->user()?->id);

        return (new EmployeeSalaryResource($salary))->response()->setStatusCode(201);
    }
}
