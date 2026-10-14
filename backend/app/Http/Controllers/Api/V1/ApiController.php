<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared helpers for the `{ data, meta }` envelope, list parameters and
 * allow-listed sorting. Controllers stay thin: validate → authorize → action → resource.
 */
abstract class ApiController extends Controller
{
    protected function perPage(Request $request): int
    {
        $requested = $request->integer('per_page', config('erp.pagination.default'));

        return max(1, min($requested, config('erp.pagination.max')));
    }

    /**
     * Apply `sort=-created_at` style sorting against an allow-list, always
     * adding the primary key as a stable tie-breaker.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $allowed
     * @return Builder<Model>
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default = '-id'): Builder
    {
        $sort = (string) $request->string('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = ltrim($default, '-');
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        $table = $query->getModel()->getTable();
        $query->orderBy("{$table}.{$column}", $direction);

        if ($column !== $query->getModel()->getKeyName()) {
            $query->orderBy("{$table}.".$query->getModel()->getKeyName(), $direction);
        }

        return $query;
    }

    /**
     * Apply `filter[status]=x` params against an allow-list of columns.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $allowed
     * @return Builder<Model>
     */
    protected function applyFilters(Builder $query, Request $request, array $allowed): Builder
    {
        $filters = (array) $request->input('filter', []);
        $table = $query->getModel()->getTable();

        foreach ($filters as $column => $value) {
            if (! in_array($column, $allowed, true) || $value === null || $value === '') {
                continue;
            }

            if (is_array($value) || str_contains((string) $value, ',')) {
                $query->whereIn("{$table}.{$column}", is_array($value) ? $value : explode(',', $value));
            } elseif (in_array($value, ['true', 'false'], true)) {
                $query->where("{$table}.{$column}", $value === 'true');
            } else {
                $query->where("{$table}.{$column}", $value);
            }
        }

        return $query;
    }

    /**
     * Case-insensitive search across allow-listed columns.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $columns
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query, Request $request, array $columns): Builder
    {
        $term = trim((string) $request->string('search'));

        if ($term === '' || $columns === []) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($columns, $like, $table) {
            foreach ($columns as $column) {
                $qualified = str_contains($column, '.') ? $column : "{$table}.{$column}";
                $q->orWhere($qualified, 'like', $like);
            }
        });
    }

    /**
     * Date range on a column: `from=2026-01-01&to=2026-01-31` (inclusive, UTC).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyDateRange(Builder $query, Request $request, string $column): Builder
    {
        $table = $query->getModel()->getTable();

        if ($from = $request->date('from')) {
            $query->where("{$table}.{$column}", '>=', $from->startOfDay());
        }
        if ($to = $request->date('to')) {
            $query->where("{$table}.{$column}", '<=', $to->endOfDay());
        }

        return $query;
    }

    /**
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginated(LengthAwarePaginator $paginator, string $resource): AnonymousResourceCollection
    {
        return $resource::collection($paginator)->additional([
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

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function message(string $message, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message] + $extra, $status);
    }
}
