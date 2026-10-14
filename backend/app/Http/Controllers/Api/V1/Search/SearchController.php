<?php

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Search\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET /search?q=&types= — any authenticated tenant user; result types are
 * filtered by the user's module permissions inside GlobalSearch.
 */
class SearchController extends ApiController
{
    public function __invoke(Request $request, GlobalSearch $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'string', 'max:300'],
        ]);

        $types = null;
        if (! empty($data['types'])) {
            $types = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['types'])))));
            validator(['types' => $types], ['types.*' => [Rule::in(array_keys(GlobalSearch::TYPES))]])->validate();
        }

        $term = trim($data['q']);
        $user = $request->user();

        return response()->json([
            'data' => $search->search($user, $term, $types),
            'meta' => [
                'query' => $term,
                'types' => $types === null ? $search->typesFor($user) : array_values(array_intersect($search->typesFor($user), $types)),
            ],
        ]);
    }
}
