<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Dashboard\DashboardService;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * GET /dashboard (dashboard.view). The payload is cached for 60 seconds per
 * company + user + permission set + branch scope + params, so it can never be
 * served across tenants or to a user with different rights.
 */
class DashboardController extends ApiController
{
    private const MAX_RANGE_DAYS = 1096;

    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
        ]);

        $company = $this->tenant->company();
        $user = $request->user();
        $today = Carbon::now($company->timezone ?: 'UTC');

        $from = $data['from'] ?? $today->copy()->startOfMonth()->toDateString();
        $to = $data['to'] ?? $today->toDateString();

        if ($from > $to) {
            throw ValidationException::withMessages(['to' => ['The to date must be on or after the from date.']]);
        }
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['from' => ['The date range may not exceed three years.']]);
        }

        $branchIds = isset($data['branch_id']) ? [(int) $data['branch_id']] : $this->tenant->allowedBranchIds();

        $permissions = $user->permissionsFor($company->id);
        sort($permissions);

        $key = sprintf(
            'dashboard:%d:%d:%s',
            $company->id,
            $user->id,
            md5((string) json_encode([$permissions, $branchIds, $from, $to])),
        );

        $payload = Cache::remember($key, 60, fn () => $this->dashboard->build($company, $user, $from, $to, $branchIds));

        return response()->json(['data' => $payload]);
    }
}
