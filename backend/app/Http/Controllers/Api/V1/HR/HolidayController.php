<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Actions\HR\AnnounceHolidayAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreHolidayRequest;
use App\Http\Resources\HR\HolidayResource;
use App\Models\HR\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class HolidayController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Holiday::class);

        $query = Holiday::query()->with('branch:id,name');
        $this->applySearch($query, $request, ['name']);
        $this->applyFilters($query, $request, ['branch_id', 'is_recurring']);
        $this->applyDateRange($query, $request, 'date');
        $this->applySort($query, $request, ['date', 'name', 'created_at'], 'date');

        return $this->paginated($query->paginate($this->perPage($request)), HolidayResource::class);
    }

    public function store(StoreHolidayRequest $request, AnnounceHolidayAction $announce): JsonResponse
    {
        Gate::authorize('create', Holiday::class);

        $holiday = Holiday::query()->create($request->safe()->except('notify'));

        // Upcoming holidays are announced to the members who work there, unless the HR user opts out.
        $notified = $request->boolean('notify', true) ? $announce->handle($holiday) : 0;

        return (new HolidayResource($holiday->load('branch:id,name')))
            ->additional(['meta' => ['notified' => $notified]])
            ->response()->setStatusCode(201);
    }

    public function show(Holiday $holiday): HolidayResource
    {
        Gate::authorize('view', $holiday);

        return new HolidayResource($holiday->load('branch:id,name'));
    }

    public function update(StoreHolidayRequest $request, Holiday $holiday): HolidayResource
    {
        Gate::authorize('update', $holiday);

        $holiday->fill($request->safe()->except('notify'))->save();

        return new HolidayResource($holiday->refresh()->load('branch:id,name'));
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        Gate::authorize('delete', $holiday);

        $holiday->delete();

        return $this->noContent();
    }
}
