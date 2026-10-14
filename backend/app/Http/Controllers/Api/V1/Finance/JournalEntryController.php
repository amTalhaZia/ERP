<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\CreateJournalEntryAction;
use App\Actions\Finance\PostJournalEntryAction;
use App\Actions\Finance\ReverseJournalEntryAction;
use App\Actions\Finance\UpdateJournalEntryAction;
use App\Enums\Finance\JournalSourceType;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\ReverseJournalEntryRequest;
use App\Http\Requests\Finance\StoreJournalEntryRequest;
use App\Http\Resources\Finance\JournalEntryResource;
use App\Models\Finance\JournalEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class JournalEntryController extends ApiController
{
    private const SHOW_RELATIONS = ['lines.account:id,code,name', 'period:id,name,status', 'branch:id,name', 'creator:id,name', 'poster:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $query = JournalEntry::query()->with(['period:id,name,status', 'branch:id,name'])->withCount('lines');
        $this->applySearch($query, $request, ['number', 'reference', 'memo']);
        $this->applyFilters($query, $request, ['status', 'source_type', 'source_id', 'branch_id', 'financial_period_id']);
        $this->applyDateRange($query, $request, 'entry_date');
        $this->applySort($query, $request, ['entry_date', 'number', 'status', 'total_debit', 'created_at'], '-entry_date');

        if ($accountId = $request->integer('account_id')) {
            $query->whereHas('lines', fn (Builder $q) => $q->where('account_id', $accountId));
        }

        $allowedBranches = app(TenantContext::class)->allowedBranchIds();
        if ($allowedBranches !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $allowedBranches));
        }

        return $this->paginated($query->paginate($this->perPage($request)), JournalEntryResource::class);
    }

    public function store(StoreJournalEntryRequest $request, CreateJournalEntryAction $create): JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $data = $request->validated();
        $data['source_type'] = JournalSourceType::Manual->value;

        $entry = $create->handle($data, (int) app(TenantContext::class)->id(), $request->user()?->id);

        return (new JournalEntryResource($entry->load(self::SHOW_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        Gate::authorize('view', $journalEntry);

        return new JournalEntryResource($journalEntry->load(self::SHOW_RELATIONS));
    }

    public function update(StoreJournalEntryRequest $request, JournalEntry $journalEntry, UpdateJournalEntryAction $update): JournalEntryResource
    {
        Gate::authorize('update', $journalEntry);

        $entry = $update->handle($journalEntry, $request->validated());

        return new JournalEntryResource($entry->load(self::SHOW_RELATIONS));
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        Gate::authorize('delete', $journalEntry);

        if (! $journalEntry->isDraft()) {
            return $this->message('Only draft journal entries can be deleted.', ['code' => 'conflict'], 409);
        }

        $journalEntry->delete();

        return $this->noContent();
    }

    public function post(Request $request, JournalEntry $journalEntry, PostJournalEntryAction $post): JournalEntryResource
    {
        Gate::authorize('post', $journalEntry);

        $entry = $post->handle($journalEntry, $request->user()?->id);

        return new JournalEntryResource($entry->load(self::SHOW_RELATIONS));
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $journalEntry, ReverseJournalEntryAction $reverse): JsonResponse
    {
        Gate::authorize('reverse', $journalEntry);

        $reversal = $reverse->handle(
            $journalEntry,
            $request->validated('entry_date') ?? now()->toDateString(),
            $request->validated('memo'),
            $request->user()?->id,
        );

        return (new JournalEntryResource($reversal->load(self::SHOW_RELATIONS)))->response()->setStatusCode(201);
    }
}
