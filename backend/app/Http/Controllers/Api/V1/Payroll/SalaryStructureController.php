<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Actions\Payroll\SaveSalaryStructureAction;
use App\Exceptions\Finance\FinanceException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payroll\StoreSalaryStructureRequest;
use App\Http\Resources\Payroll\SalaryStructureResource;
use App\Models\Payroll\SalaryStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SalaryStructureController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SalaryStructure::class);

        $query = SalaryStructure::query()->withCount('components');
        $this->applySearch($query, $request, ['name']);
        $this->applyFilters($query, $request, ['is_active', 'currency']);
        $this->applySort($query, $request, ['name', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), SalaryStructureResource::class);
    }

    public function store(StoreSalaryStructureRequest $request, SaveSalaryStructureAction $save): JsonResponse
    {
        Gate::authorize('create', SalaryStructure::class);

        return (new SalaryStructureResource($save->handle($request->payload())))->response()->setStatusCode(201);
    }

    public function show(SalaryStructure $salaryStructure): SalaryStructureResource
    {
        Gate::authorize('view', $salaryStructure);

        return new SalaryStructureResource($salaryStructure->load('components.component'));
    }

    public function update(StoreSalaryStructureRequest $request, SalaryStructure $salaryStructure, SaveSalaryStructureAction $save): SalaryStructureResource
    {
        Gate::authorize('update', $salaryStructure);

        return new SalaryStructureResource($save->handle($request->payload(), $salaryStructure));
    }

    public function destroy(SalaryStructure $salaryStructure): JsonResponse
    {
        Gate::authorize('delete', $salaryStructure);

        if ($salaryStructure->salaries()->exists()) {
            throw FinanceException::conflict(
                "Salary structure {$salaryStructure->name} is assigned to employee salaries; deactivate it instead.",
                'payroll.structure_in_use',
            );
        }

        $salaryStructure->delete();

        return $this->noContent();
    }
}
