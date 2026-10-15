<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Enums\HR\ContractStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreEmploymentContractRequest;
use App\Http\Resources\HR\EmploymentContractResource;
use App\Models\HR\EmploymentContract;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmploymentContractController extends ApiController
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', EmploymentContract::class);

        $query = EmploymentContract::query()->visibleBranches()->with('employee:id,first_name,last_name,employee_code');
        $this->applyFilters($query, $request, ['employee_id', 'status', 'contract_type']);
        $this->applyDateRange($query, $request, 'start_date');
        $this->applySort($query, $request, ['start_date', 'end_date', 'salary', 'status', 'created_at'], '-start_date');

        return $this->paginated($query->paginate($this->perPage($request)), EmploymentContractResource::class);
    }

    public function store(StoreEmploymentContractRequest $request): JsonResponse
    {
        Gate::authorize('create', EmploymentContract::class);

        $data = $request->safe()->except('document');
        $data['status'] ??= ContractStatus::Active->value;

        $contract = DB::transaction(function () use ($request, $data): EmploymentContract {
            if ($data['status'] === ContractStatus::Active->value) {
                $this->assertNoOtherActive((int) $data['employee_id']);
            }

            if ($request->hasFile('document')) {
                $data['document_path'] = $request->file('document')
                    ->store("companies/{$this->tenant->id()}/employees/{$data['employee_id']}/contracts", 'local');
            }

            return EmploymentContract::query()->create($data);
        });

        return (new EmploymentContractResource($contract->load('employee:id,first_name,last_name,employee_code')))->response()->setStatusCode(201);
    }

    public function show(EmploymentContract $employmentContract): EmploymentContractResource
    {
        Gate::authorize('view', $employmentContract);

        return new EmploymentContractResource($employmentContract->load('employee:id,first_name,last_name,employee_code'));
    }

    public function update(StoreEmploymentContractRequest $request, EmploymentContract $employmentContract): EmploymentContractResource
    {
        Gate::authorize('update', $employmentContract);

        $data = $request->safe()->except('document');

        DB::transaction(function () use ($request, $data, $employmentContract): void {
            if (($data['status'] ?? $employmentContract->status->value) === ContractStatus::Active->value
                && $employmentContract->status !== ContractStatus::Active) {
                $this->assertNoOtherActive($employmentContract->employee_id, $employmentContract->id);
            }

            if ($request->hasFile('document')) {
                if ($employmentContract->document_path) {
                    Storage::disk('local')->delete($employmentContract->document_path);
                }
                $data['document_path'] = $request->file('document')
                    ->store("companies/{$this->tenant->id()}/employees/{$employmentContract->employee_id}/contracts", 'local');
            }

            $employmentContract->fill($data)->save();
        });

        return new EmploymentContractResource($employmentContract->refresh()->load('employee:id,first_name,last_name,employee_code'));
    }

    public function destroy(EmploymentContract $employmentContract): JsonResponse
    {
        Gate::authorize('delete', $employmentContract);

        if ($employmentContract->document_path) {
            Storage::disk('local')->delete($employmentContract->document_path);
        }

        $employmentContract->delete();

        return $this->noContent();
    }

    private function assertNoOtherActive(int $employeeId, ?int $ignoreId = null): void
    {
        $exists = EmploymentContract::query()
            ->where('employee_id', $employeeId)
            ->where('status', ContractStatus::Active->value)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['status' => ['This employee already has an active contract. Expire or terminate it first.']]);
        }
    }

    /** GET /employment-contracts/{id}/document — employees.view + employees.documents.view; private disk, audited. */
    public function document(EmploymentContract $employmentContract, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('downloadDocument', $employmentContract);

        if ($employmentContract->document_path === null || ! Storage::disk('local')->exists($employmentContract->document_path)) {
            abort(404);
        }

        $audit->log('employment_contracts.document_downloaded', $employmentContract, context: ['employee_id' => $employmentContract->employee_id]);

        $extension = pathinfo($employmentContract->document_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $employmentContract->document_path,
            "employment-contract-{$employmentContract->id}".($extension ? ".{$extension}" : ''),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}
