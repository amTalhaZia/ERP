<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Models\Finance\BankAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BankAccountController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', BankAccount::class);

        $query = BankAccount::query()->with('account:id,code,name');
        $this->applySearch($query, $request, ['name', 'bank_name', 'account_number_last4']);
        $this->applyFilters($query, $request, ['is_active', 'currency', 'account_id']);
        $this->applySort($query, $request, ['name', 'bank_name', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), BankAccountResource::class);
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        Gate::authorize('create', BankAccount::class);

        $data = $request->validated();
        $data['currency'] ??= app(TenantContext::class)->company()?->currency ?? 'USD';

        $bankAccount = BankAccount::query()->create($data);

        return (new BankAccountResource($bankAccount->load('account:id,code,name')))->response()->setStatusCode(201);
    }

    public function show(BankAccount $bankAccount): BankAccountResource
    {
        Gate::authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount->load('account:id,code,name'));
    }

    public function update(StoreBankAccountRequest $request, BankAccount $bankAccount): BankAccountResource
    {
        Gate::authorize('update', $bankAccount);

        $bankAccount->fill($request->validated())->save();

        return new BankAccountResource($bankAccount->refresh()->load('account:id,code,name'));
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        Gate::authorize('delete', $bankAccount);

        $bankAccount->delete();

        return $this->noContent();
    }
}
