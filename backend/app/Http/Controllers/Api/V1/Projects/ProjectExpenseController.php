<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\StoreProjectExpenseRequest;
use App\Http\Resources\Projects\ProjectExpenseResource;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectExpense;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Project cost ledger. Expenses are NOT posted to the general ledger (out of
 * scope); they feed the project budget-vs-actual summary only.
 */
class ProjectExpenseController extends ApiController
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('viewFinancials', $project);

        $query = $project->expenses()->getQuery();
        $this->applySearch($query, $request, ['category', 'description']);
        $this->applyFilters($query, $request, ['category', 'currency']);
        $this->applyDateRange($query, $request, 'date');
        $this->applySort($query, $request, ['date', 'amount', 'category', 'created_at'], '-date');

        return $this->paginated($query->paginate($this->perPage($request)), ProjectExpenseResource::class);
    }

    public function store(StoreProjectExpenseRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validated();
        $data['amount'] = Money::normalize((string) $data['amount']);
        $data['currency'] ??= $project->currency;
        $data['created_by'] = $request->user()->id;

        $expense = $project->expenses()->create($data);

        return (new ProjectExpenseResource($expense->refresh()))->response()->setStatusCode(201);
    }

    public function show(Project $project, ProjectExpense $expense): ProjectExpenseResource
    {
        Gate::authorize('view', $expense);

        return new ProjectExpenseResource($expense);
    }

    public function update(StoreProjectExpenseRequest $request, Project $project, ProjectExpense $expense): ProjectExpenseResource
    {
        Gate::authorize('update', $expense);

        $data = $request->validated();

        if (isset($data['amount'])) {
            $data['amount'] = Money::normalize((string) $data['amount']);
        }

        $expense->fill($data)->save();

        return new ProjectExpenseResource($expense->refresh());
    }

    public function destroy(Project $project, ProjectExpense $expense): JsonResponse
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        return $this->noContent();
    }
}
