<?php

namespace App\Http\Controllers\Api\V1\Approvals;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Approvals\ApprovalInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only approvals inbox (approvals.view). Acting on an item happens on
 * the owning module's approve/reject endpoint, which re-checks its policy.
 */
class ApprovalController extends ApiController
{
    public function __construct(private readonly ApprovalInbox $inbox) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', 'array'],
            'filter.type' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $types = null;
        if (! empty($data['filter']['type'])) {
            $types = array_values(array_filter(explode(',', $data['filter']['type'])));
            validator(['types' => $types], ['types.*' => [Rule::in(array_keys(ApprovalInbox::TYPES))]])->validate();
        }

        $items = $this->inbox->pending($request->user(), $types);
        $perPage = $this->perPage($request);
        $page = max(1, $request->integer('page', 1));
        $total = $items->count();

        return response()->json([
            'data' => $items->forPage($page, $perPage)->values(),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                ],
            ],
        ]);
    }

    public function counts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->inbox->counts($request->user())]);
    }
}
