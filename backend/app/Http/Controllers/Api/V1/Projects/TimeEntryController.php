<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\StoreTimeEntryRequest;
use App\Http\Resources\Projects\TimeEntryResource;
use App\Http\Resources\Projects\UserSummary;
use App\Models\Projects\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TimeEntryController extends ApiController
{
    /**
     * Filters: filter[project_id|user_id|task_id|is_billable], from/to (date).
     * Without projects.view/manage only the caller's own entries are listed.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TimeEntry::class);

        $query = TimeEntry::query()
            ->whereHas('project')
            ->with(['project:id,number,name', 'task:id,title', 'user:'.UserSummary::COLUMNS]);

        if (! Gate::allows('viewAll', TimeEntry::class)) {
            $query->where('time_entries.user_id', $request->user()->id);
        }

        $this->applySearch($query, $request, ['description']);
        $this->applyFilters($query, $request, ['project_id', 'user_id', 'task_id', 'is_billable']);
        $this->applyDateRange($query, $request, 'date');
        $this->applySort($query, $request, ['date', 'hours', 'created_at'], '-date');

        return $this->paginated($query->paginate($this->perPage($request)), TimeEntryResource::class);
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        Gate::authorize('create', TimeEntry::class);

        $data = $request->validated();
        $data['user_id'] ??= $request->user()->id;

        $entry = TimeEntry::query()->create($data);

        return (new TimeEntryResource($this->loadDetail($entry)))->response()->setStatusCode(201);
    }

    public function show(TimeEntry $timeEntry): TimeEntryResource
    {
        Gate::authorize('view', $timeEntry);

        return new TimeEntryResource($this->loadDetail($timeEntry));
    }

    public function update(StoreTimeEntryRequest $request, TimeEntry $timeEntry): TimeEntryResource
    {
        Gate::authorize('update', $timeEntry);

        $data = $request->validated();

        // Moving an entry to another project drops a task link that no longer fits.
        if (isset($data['project_id']) && (int) $data['project_id'] !== $timeEntry->project_id && ! array_key_exists('task_id', $data)) {
            $data['task_id'] = null;
        }

        $timeEntry->fill($data)->save();

        return new TimeEntryResource($this->loadDetail($timeEntry->refresh()));
    }

    public function destroy(TimeEntry $timeEntry): JsonResponse
    {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return $this->noContent();
    }

    private function loadDetail(TimeEntry $entry): TimeEntry
    {
        return $entry->load(['project:id,number,name', 'task:id,title', 'user:'.UserSummary::COLUMNS]);
    }
}
