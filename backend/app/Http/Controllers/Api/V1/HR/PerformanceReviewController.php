<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Enums\HR\ReviewStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StorePerformanceReviewRequest;
use App\Http\Resources\HR\PerformanceReviewResource;
use App\Models\HR\PerformanceReview;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PerformanceReviewController extends ApiController
{
    /** @var array<int, string> */
    private const RELATIONS = ['employee:id,first_name,last_name,employee_code', 'reviewer:id,name'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** Without performance.view the list is limited to reviews the user is subject of or reviewer for. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PerformanceReview::class);

        $query = PerformanceReview::query()->visibleBranches()->with(self::RELATIONS);

        if (! Gate::allows('viewAll', PerformanceReview::class)) {
            $userId = $request->user()->id;
            $query->where(fn (Builder $q) => $q
                ->where('reviewer_id', $userId)
                ->orWhereHas('employee', fn (Builder $e) => $e->where('user_id', $userId)));
        }

        $this->applySearch($query, $request, ['cycle']);
        $this->applyFilters($query, $request, ['status', 'employee_id', 'reviewer_id', 'cycle']);
        $this->applyDateRange($query, $request, 'period_end');
        $this->applySort($query, $request, ['cycle', 'period_end', 'status', 'overall_rating', 'created_at'], '-period_end');

        return $this->paginated($query->paginate($this->perPage($request)), PerformanceReviewResource::class);
    }

    public function store(StorePerformanceReviewRequest $request): JsonResponse
    {
        Gate::authorize('create', PerformanceReview::class);

        $review = PerformanceReview::query()->create($request->validated() + ['status' => ReviewStatus::Draft]);

        return (new PerformanceReviewResource($review->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(PerformanceReview $performanceReview): PerformanceReviewResource
    {
        Gate::authorize('view', $performanceReview);

        return new PerformanceReviewResource($performanceReview->load(self::RELATIONS));
    }

    public function update(StorePerformanceReviewRequest $request, PerformanceReview $performanceReview): PerformanceReviewResource
    {
        Gate::authorize('update', $performanceReview);

        if ($performanceReview->status !== ReviewStatus::Draft) {
            abort(409, 'Only draft reviews can be edited.');
        }

        $performanceReview->fill($request->validated())->save();

        return new PerformanceReviewResource($performanceReview->refresh()->load(self::RELATIONS));
    }

    public function destroy(PerformanceReview $performanceReview): JsonResponse
    {
        Gate::authorize('delete', $performanceReview);

        if ($performanceReview->status === ReviewStatus::Acknowledged) {
            return $this->message('Acknowledged reviews cannot be deleted.', ['code' => 'conflict'], 409);
        }

        $performanceReview->delete();

        return $this->noContent();
    }

    public function submit(PerformanceReview $performanceReview): PerformanceReviewResource
    {
        Gate::authorize('submit', $performanceReview);

        if ($performanceReview->status !== ReviewStatus::Draft) {
            abort(409, 'This review has already been submitted.');
        }

        $performanceReview->fill(['status' => ReviewStatus::Submitted, 'submitted_at' => now()])->save();
        $this->audit->log('performance.reviews.submitted', $performanceReview, ['status' => 'draft'], ['status' => 'submitted']);

        return new PerformanceReviewResource($performanceReview->load(self::RELATIONS));
    }

    /** The reviewed employee confirms they have read the review. */
    public function acknowledge(PerformanceReview $performanceReview): PerformanceReviewResource
    {
        Gate::authorize('acknowledge', $performanceReview);

        if ($performanceReview->status !== ReviewStatus::Submitted) {
            abort(409, 'Only submitted reviews can be acknowledged.');
        }

        $performanceReview->fill(['status' => ReviewStatus::Acknowledged, 'acknowledged_at' => now()])->save();
        $this->audit->log('performance.reviews.acknowledged', $performanceReview, ['status' => 'submitted'], ['status' => 'acknowledged']);

        return new PerformanceReviewResource($performanceReview->load(self::RELATIONS));
    }
}
