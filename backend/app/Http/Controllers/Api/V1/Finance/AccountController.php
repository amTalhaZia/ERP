<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\SeedCompanyFinanceDefaultsAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\ReportRequest;
use App\Http\Requests\Finance\StoreAccountRequest;
use App\Http\Resources\Finance\AccountResource;
use App\Models\Finance\Account;
use App\Services\Finance\ReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AccountController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Account::class);

        $query = Account::query()->with('parent:id,code,name')->withCount('lines');
        $this->applySearch($query, $request, ['code', 'name']);
        $this->applyFilters($query, $request, ['type', 'subtype', 'is_active', 'is_bank', 'is_cash', 'is_system', 'parent_id']);
        $this->applySort($query, $request, ['code', 'name', 'type', 'created_at'], 'code');

        return $this->paginated($query->paginate($this->perPage($request)), AccountResource::class);
    }

    /** Full hierarchy for the chart-of-accounts view and account pickers. */
    public function tree(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->get(['id', 'parent_id', 'code', 'name', 'type', 'subtype', 'is_bank', 'is_cash', 'is_system', 'is_active']);

        $byParent = $accounts->groupBy('parent_id');
        $ids = $accounts->pluck('id')->all();

        $build = function (?int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, collect())->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type->value,
                'subtype' => $a->subtype,
                'is_bank' => $a->is_bank,
                'is_cash' => $a->is_cash,
                'is_system' => $a->is_system,
                'is_active' => $a->is_active,
                'children' => $build($a->id),
            ])->values()->all();
        };

        // Roots: no parent, or a parent that is filtered out (inactive).
        $roots = $build(null);
        foreach ($byParent->keys()->filter(fn ($k) => $k !== null && $k !== '' && ! in_array((int) $k, $ids, true)) as $orphanParent) {
            $roots = array_merge($roots, $build((int) $orphanParent));
        }

        return response()->json(['data' => $roots]);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        Gate::authorize('create', Account::class);

        $data = $request->validated();
        $data['currency'] ??= app(TenantContext::class)->company()?->currency ?? 'USD';
        $data['is_system'] = false;

        $account = Account::query()->create($data);

        return (new AccountResource($account->load('parent:id,code,name')))->response()->setStatusCode(201);
    }

    public function show(Account $account): AccountResource
    {
        Gate::authorize('view', $account);

        return new AccountResource($account->load('parent:id,code,name')->loadCount(['lines', 'children']));
    }

    public function update(StoreAccountRequest $request, Account $account): AccountResource|JsonResponse
    {
        Gate::authorize('update', $account);

        $data = $request->validated();
        $hasLines = $account->hasJournalLines();

        if ($hasLines && isset($data['type']) && $data['type'] !== $account->type->value) {
            return $this->message('The account type cannot be changed once journal lines exist.', ['code' => 'conflict'], 409);
        }

        if ($hasLines && array_key_exists('is_active', $data) && ! $data['is_active'] && $account->is_active) {
            return $this->message('An account with journal lines cannot be deactivated.', ['code' => 'conflict'], 409);
        }

        if ($account->is_system) {
            unset($data['code'], $data['type']);
        }

        $account->fill($data)->save();

        return new AccountResource($account->refresh()->load('parent:id,code,name')->loadCount(['lines', 'children']));
    }

    public function destroy(Account $account): JsonResponse
    {
        Gate::authorize('delete', $account);

        if ($account->hasJournalLines()) {
            return $this->message('An account with journal lines cannot be deleted; deactivate it instead.', ['code' => 'conflict'], 409);
        }

        if ($account->children()->exists()) {
            return $this->message('Move or delete child accounts first.', ['code' => 'conflict'], 409);
        }

        $account->delete();

        return $this->noContent();
    }

    /** General ledger for one account: opening balance, lines with running balance. */
    public function ledger(ReportRequest $request, Account $account, ReportService $reports): JsonResponse
    {
        Gate::authorize('view', $account);

        $tenant = app(TenantContext::class)->company();

        $ledger = $reports->ledger(
            $tenant,
            $account,
            $request->validated('from'),
            $request->validated('to'),
            $request->branchId(),
            max(1, $request->integer('page', 1)),
            $this->perPage($request),
        );

        $paginator = $ledger['lines'];

        return response()->json([
            'data' => [
                'account' => $ledger['account'],
                'from' => $ledger['from'],
                'to' => $ledger['to'],
                'opening_balance' => $ledger['opening_balance'],
                'closing_balance' => $ledger['closing_balance'],
                'lines' => $paginator->items(),
            ],
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /** Seeds the standard chart, current financial year and tax rates (idempotent). */
    public function seedDefaults(SeedCompanyFinanceDefaultsAction $seed): JsonResponse
    {
        Gate::authorize('create', Account::class);

        $created = $seed->handle(app(TenantContext::class)->company());

        return response()->json(['data' => $created]);
    }
}
